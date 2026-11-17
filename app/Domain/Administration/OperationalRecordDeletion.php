<?php

namespace App\Domain\Administration;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class OperationalRecordDeletion
{
    /** @var array<string, array{label: string, title: string, search: list<string>}> */
    public const RESOURCES = [
        'societies' => ['label' => 'Societies', 'title' => 'name', 'search' => ['id', 'name']],
        'blocks' => ['label' => 'Blocks', 'title' => 'code', 'search' => ['id', 'code', 'name']],
        'plot_sizes' => ['label' => 'Plot sizes', 'title' => 'label', 'search' => ['id', 'label']],
        'plots' => ['label' => 'Plots', 'title' => 'number', 'search' => ['id', 'number']],
        'plot_owners' => ['label' => 'Plot owners', 'title' => 'name', 'search' => ['id', 'name']],
        'financial_accounts' => ['label' => 'Payment accounts', 'title' => 'name', 'search' => ['id', 'code', 'name', 'type']],
        'customers' => ['label' => 'Customers', 'title' => 'name', 'search' => ['name', 'phone', 'cnic', 'email']],
        'booking_drafts' => ['label' => 'Booking drafts', 'title' => 'id', 'search' => ['id', 'notes']],
        'bookings' => ['label' => 'Confirmed bookings', 'title' => 'id', 'search' => ['id']],
        'receipt_drafts' => ['label' => 'Receipt drafts', 'title' => 'id', 'search' => ['id', 'reference', 'notes']],
        'payment_receipts' => ['label' => 'Posted receipts', 'title' => 'number', 'search' => ['id', 'number', 'reference']],
        'follow_ups' => ['label' => 'Follow-ups', 'title' => 'note', 'search' => ['id', 'note']],
        'customer_documents' => ['label' => 'Customer documents', 'title' => 'kind', 'search' => ['id', 'kind', 'mime']],
        'plot_holds' => ['label' => 'Plot holds', 'title' => 'id', 'search' => ['id', 'purpose', 'release_reason']],
        'booking_file_submissions' => ['label' => 'File submissions', 'title' => 'id', 'search' => ['id', 'note']],
        'office_expenses' => ['label' => 'Office expenses', 'title' => 'payee', 'search' => ['id', 'payee', 'category', 'reference', 'note']],
        'salary_payments' => ['label' => 'Salary payments', 'title' => 'employee_name', 'search' => ['id', 'employee_name', 'reference', 'note']],
        'customer_refunds' => ['label' => 'Customer refunds', 'title' => 'number', 'search' => ['id', 'number', 'deduction_reason', 'reference']],
        'owner_payouts' => ['label' => 'Owner payouts', 'title' => 'id', 'search' => ['id', 'reference']],
        'commission_payments' => ['label' => 'Commission payments', 'title' => 'id', 'search' => ['id', 'reference']],
        'booking_charges' => ['label' => 'Booking charges', 'title' => 'number', 'search' => ['id', 'number', 'reason', 'note']],
        'bank_reconciliations' => ['label' => 'Bank reconciliations', 'title' => 'id', 'search' => ['id', 'note']],
        'import_batches' => ['label' => 'Import batches', 'title' => 'source_filename', 'search' => ['id', 'source_filename', 'template']],
    ];

    /** @return array{label: string, snapshot: array<string, mixed>, deleted: int} */
    public function delete(User $actor, string $table, int $id, string $reason): array
    {
        abort_unless($actor->hasRole('super_admin'), 403);
        $definition = self::RESOURCES[$table] ?? null;
        abort_unless($definition !== null, 404);

        $row = DB::table($table)->where('id', $id)->first();
        if (! $row) {
            throw ValidationException::withMessages(['record' => 'This record no longer exists.']);
        }

        $snapshot = (array) $row;
        $title = (string) ($snapshot[$definition['title']] ?? $id);

        $documentPaths = [];
        $result = DB::transaction(function () use ($actor, $table, $id, $reason, $snapshot, $definition, $title, &$documentPaths): array {
            $deleted = 0;
            $visited = [];
            $this->deleteTree($table, $id, $visited, $deleted, $documentPaths);

            DB::table('record_deletion_events')->insert([
                'actor_id' => $actor->id,
                'resource_table' => $table,
                'resource_id' => $id,
                'resource_label' => $definition['label'].' · '.$title,
                'reason' => $reason,
                'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
                'deleted_rows' => $deleted,
                'created_at' => now(),
            ]);

            return ['label' => $definition['label'].' · '.$title, 'snapshot' => $snapshot, 'deleted' => $deleted];
        }, 3);

        foreach ($documentPaths as $path) {
            Storage::disk('customer_documents')->delete($path);
        }

        return $result;
    }

    /**
     * @param  array<string, true>  $visited
     * @param  list<string>  $documentPaths
     */
    private function deleteTree(string $table, int $id, array &$visited, int &$deleted, array &$documentPaths): void
    {
        $key = $table.':'.$id;
        if (isset($visited[$key])) {
            return;
        }
        $visited[$key] = true;

        if ($table === 'customer_documents') {
            $path = DB::table($table)->where('id', $id)->value('path');
            if (is_string($path) && $path !== '') {
                $documentPaths[] = $path;
            }
        }

        $polymorphicDependents = match ($table) {
            'account_movements' => [['bank_reconciliation_lines', 'source_type', 'account_movement']],
            'office_expenses' => [['bank_reconciliation_lines', 'source_type', 'office_expense']],
            'owner_payouts' => [['bank_reconciliation_lines', 'source_type', 'owner_payout']],
            'customer_refunds' => [
                ['bank_reconciliation_lines', 'source_type', 'customer_refund'],
                ['owner_entitlement_events', 'source_kind', 'customer_refund'],
            ],
            'commission_payments' => [['bank_reconciliation_lines', 'source_type', 'commission_payment']],
            default => [],
        };
        foreach ($polymorphicDependents as [$childTable, $typeColumn, $type]) {
            $childIds = DB::table($childTable)->where($typeColumn, $type)->where('source_id', $id)->pluck('id');
            foreach ($childIds as $childId) {
                $this->deleteTree($childTable, (int) $childId, $visited, $deleted, $documentPaths);
            }
        }

        $foreignKeys = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('REFERENCED_TABLE_SCHEMA', DB::getDatabaseName())
            ->where('REFERENCED_TABLE_NAME', $table)
            ->whereNotNull('REFERENCED_COLUMN_NAME')
            ->get(['TABLE_NAME', 'COLUMN_NAME']);

        foreach ($foreignKeys as $foreignKey) {
            if ($foreignKey->TABLE_NAME === 'record_deletion_events') {
                continue;
            }
            $children = DB::table($foreignKey->TABLE_NAME)
                ->where($foreignKey->COLUMN_NAME, $id)
                ->pluck('id');
            foreach ($children as $childId) {
                $this->deleteTree($foreignKey->TABLE_NAME, (int) $childId, $visited, $deleted, $documentPaths);
            }
        }

        $deleted += DB::table($table)->where('id', $id)->delete();
    }
}
