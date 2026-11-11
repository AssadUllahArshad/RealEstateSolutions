<?php

namespace App\Domain\Finance;

use App\Models\BankReconciliation;
use App\Models\FinancialAccount;
use App\Models\FinancialAccountOpeningBalance;
use App\Models\Society;
use App\Models\User;
use App\Rules\PositiveDecimal;
use App\Rules\SignedDecimal;
use App\Support\BusinessClock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class BankReconciliations
{
    private const SOURCE_TYPES = ['account_movement', 'office_expense', 'owner_payout', 'customer_refund', 'commission_payment'];

    public function canPrepare(User $actor): bool
    {
        return $actor->can('finance.reconciliation.prepare') && $actor->hasRole('accountant');
    }

    public function canFinalize(User $actor): bool
    {
        return $actor->can('finance.reconciliation.approve') && $actor->hasAnyRole(['admin', 'super_admin']);
    }

    /** @param array<string,mixed> $filters
     * @return array<string,mixed>
     */
    public function context(User $actor, array $filters): array
    {
        Gate::forUser($actor)->authorize('finance.accounts.view');
        abort_unless(app(ReceiptDrafts::class)->wideAccess($actor), 403);
        $today = app(BusinessClock::class)->today();
        $data = Validator::make($filters, [
            'account' => ['nullable', 'integer', 'exists:financial_accounts,id'],
            'from' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'to' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today', 'after_or_equal:from'],
        ])->validate();
        if (filled($data['from'] ?? null) !== filled($data['to'] ?? null)) {
            throw ValidationException::withMessages(['from' => 'Choose both statement dates.']);
        }
        $selectedAccount = isset($data['account'])
            ? FinancialAccount::query()->findOrFail((int) $data['account'])
            : null;
        if ($selectedAccount && $selectedAccount->type !== 'bank') {
            throw ValidationException::withMessages(['account' => 'Only bank accounts can be reconciled.']);
        }
        $opening = $selectedAccount
            ? FinancialAccountOpeningBalance::query()->where('financial_account_id', $selectedAccount->id)->first()
            : null;
        $bookOpening = null;
        $bookClosing = null;
        $entries = [];
        if ($selectedAccount && $opening && isset($data['from'], $data['to']) && $data['from'] >= $opening->effective_on) {
            $ledger = app(CashbookLedger::class);
            $bookOpening = $ledger->balanceAtStart($selectedAccount->id, $data['from']);
            $bookClosing = $ledger->balanceAtEnd($selectedAccount->id, $data['to']);
            $entries = $ledger->entries($selectedAccount->id, $data['from'], $data['to']);
        }
        $records = BankReconciliation::query()
            ->with(['account:id,code,name', 'preparer:id,name', 'decider:id,name', 'lines'])
            ->latest('id')->limit(30)->get()
            ->map(fn (BankReconciliation $record): array => [
                ...$record->toArray(),
                'decision_key' => $record->state === 'prepared' ? (string) Str::uuid() : null,
            ]);

        return [
            'accounts' => FinancialAccount::query()->where('type', 'bank')->orderBy('code')->get(['id', 'code', 'name']),
            'selectedAccount' => $selectedAccount?->only(['id', 'code', 'name']),
            'filters' => ['account' => isset($data['account']) ? (string) $data['account'] : '', 'from' => $data['from'] ?? '', 'to' => $data['to'] ?? ''],
            'openingEffectiveOn' => $opening?->effective_on,
            'bookOpeningBalance' => $bookOpening,
            'bookClosingBalance' => $bookClosing,
            'bookEntries' => $entries,
            'records' => $records,
            'businessDate' => $today,
            'commandKey' => (string) Str::uuid(),
            'canPrepare' => $this->canPrepare($actor),
            'canFinalize' => $this->canFinalize($actor),
        ];
    }

    /** @param array<string,mixed> $input */
    public function prepare(User $actor, array $input): BankReconciliation
    {
        $data = Validator::make($input, [
            'financial_account_id' => ['required', 'integer', 'min:1'],
            'period_start' => ['required', 'date_format:Y-m-d'],
            'period_end' => ['required', 'date_format:Y-m-d', 'after_or_equal:period_start'],
            'statement_opening_balance' => ['required', new SignedDecimal],
            'statement_closing_balance' => ['required', new SignedDecimal],
            'note' => ['required', 'string', 'min:10', 'max:2000'],
            'lines' => ['present', 'array'],
            'lines.*' => ['array:statement_date,amount,direction,description,reference,source_type,source_id'],
            'lines.*.statement_date' => ['required', 'date_format:Y-m-d'],
            'lines.*.amount' => ['required', new PositiveDecimal],
            'lines.*.direction' => ['required', Rule::in(['inflow', 'outflow'])],
            'lines.*.description' => ['required', 'string', 'max:200'],
            'lines.*.reference' => ['nullable', 'string', 'max:160'],
            'lines.*.source_type' => ['required', Rule::in(self::SOURCE_TYPES)],
            'lines.*.source_id' => ['required', 'integer', 'min:1'],
            'command_key' => ['required', 'uuid'], 'confirmed' => ['required', 'accepted'],
            'actor_id' => ['prohibited'], 'state' => ['prohibited'], 'decided_by' => ['prohibited'],
        ])->validate();
        $today = app(BusinessClock::class)->today();
        if ($data['period_end'] > $today) {
            throw ValidationException::withMessages(['period_end' => 'A statement period cannot end in the future.']);
        }
        $lines = array_map(fn ($line) => [
            'statement_date' => $line['statement_date'],
            'amount' => bcadd($line['amount'], '0', 2),
            'direction' => $line['direction'],
            'description' => trim($line['description']),
            'reference' => trim($line['reference'] ?? '') ?: null,
            'source_type' => $line['source_type'],
            'source_id' => (int) $line['source_id'],
        ], $data['lines']);
        foreach ($lines as $line) {
            if ($line['description'] === '') {
                throw ValidationException::withMessages(['lines' => 'Every statement line needs a description.']);
            }
        }
        usort($lines, fn ($left, $right) => strcmp($left['source_type'], $right['source_type']) ?: $left['source_id'] <=> $right['source_id']);
        $values = [
            'financial_account_id' => (int) $data['financial_account_id'],
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
            'statement_opening_balance' => bcadd($data['statement_opening_balance'], '0', 2),
            'statement_closing_balance' => bcadd($data['statement_closing_balance'], '0', 2),
            'note' => trim($data['note']),
            'lines' => $lines,
        ];
        if (mb_strlen($values['note']) < 10) {
            throw ValidationException::withMessages(['note' => 'Record at least 10 characters describing the reviewed statement.']);
        }
        $hashes = $this->hashes(json_encode($values, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($actor, $data, $values, $hashes): BankReconciliation {
            $fresh = User::query()->lockForUpdate()->findOrFail($actor->id);
            Gate::forUser($fresh)->authorize('finance.reconciliation.prepare');
            abort_unless($this->canPrepare($fresh), 403);
            Society::query()->whereKey(1)->lockForUpdate()->firstOrFail();
            $account = FinancialAccount::query()->whereKey($values['financial_account_id'])->lockForUpdate()->firstOrFail();
            $prior = BankReconciliation::query()->where('prepared_by', $fresh->id)
                ->where('command_key', strtolower($data['command_key']))->lockForUpdate()->first();
            if ($prior) {
                if (! in_array($prior->payload_hash, $hashes, true)) {
                    throw new ConflictHttpException('This reconciliation key was already used with different details.');
                }

                return $prior;
            }
            if ($account->type !== 'bank') {
                throw ValidationException::withMessages(['financial_account_id' => 'Only bank accounts can be reconciled.']);
            }
            $opening = FinancialAccountOpeningBalance::query()->where('financial_account_id', $account->id)->lockForUpdate()->first();
            if (! $opening) {
                throw ValidationException::withMessages(['financial_account_id' => 'Record a verified opening balance before reconciling this bank account.']);
            }
            if ($values['period_start'] < $opening->effective_on) {
                throw ValidationException::withMessages(['period_start' => 'The reconciliation cannot begin before the account opening balance takes effect.']);
            }
            $this->assertNoOverlap($account->id, $values['period_start'], $values['period_end']);
            $this->validateExact($account->id, $values, null);

            $record = BankReconciliation::create([
                'financial_account_id' => $account->id,
                'period_start' => $values['period_start'],
                'period_end' => $values['period_end'],
                'statement_opening_balance' => $values['statement_opening_balance'],
                'statement_closing_balance' => $values['statement_closing_balance'],
                'state' => 'prepared',
                'note' => $values['note'],
                'prepared_by' => $fresh->id,
                'command_key' => strtolower($data['command_key']),
                'payload_hash' => $hashes[0],
            ]);
            $record->lines()->createMany($values['lines']);

            return $record;
        }, attempts: 3);
    }

    /** @param array<string,mixed> $input */
    public function decide(User $actor, BankReconciliation $reconciliation, array $input): BankReconciliation
    {
        $data = Validator::make($input, [
            'decision' => ['required', Rule::in(['finalize', 'reject'])],
            'decision_note' => ['required', 'string', 'min:5', 'max:2000'],
            'decision_key' => ['required', 'uuid'], 'confirmed' => ['required', 'accepted'],
            'financial_account_id' => ['prohibited'], 'prepared_by' => ['prohibited'],
        ])->validate();
        $decisionNote = trim($data['decision_note']);

        return DB::transaction(function () use ($actor, $reconciliation, $data, $decisionNote): BankReconciliation {
            $fresh = User::query()->lockForUpdate()->findOrFail($actor->id);
            Gate::forUser($fresh)->authorize('finance.reconciliation.approve');
            abort_unless($this->canFinalize($fresh), 403);
            Society::query()->whereKey(1)->lockForUpdate()->firstOrFail();
            $record = BankReconciliation::query()->whereKey($reconciliation->id)->lockForUpdate()->firstOrFail();
            if ($record->decision_key === strtolower($data['decision_key'])) {
                if ($record->state !== ($data['decision'] === 'finalize' ? 'finalized' : 'rejected') || $record->decision_note !== $decisionNote) {
                    throw new ConflictHttpException('This reconciliation decision key was replayed with different details.');
                }

                return $record;
            }
            if ($record->state !== 'prepared') {
                throw new ConflictHttpException('This reconciliation has already been decided.');
            }
            if ($record->prepared_by === $fresh->id) {
                throw ValidationException::withMessages(['decision' => 'A different admin must review the accountant’s reconciliation.']);
            }
            if ($data['decision'] === 'finalize') {
                $this->assertNoOverlap($record->financial_account_id, $record->period_start, $record->period_end, $record->id);
                $opening = FinancialAccountOpeningBalance::query()
                    ->where('financial_account_id', $record->financial_account_id)->lockForUpdate()->firstOrFail();
                if ($record->period_start < $opening->effective_on) {
                    throw ValidationException::withMessages(['decision' => 'The opening balance no longer supports this statement period.']);
                }
                $values = [
                    'period_start' => $record->period_start,
                    'period_end' => $record->period_end,
                    'statement_opening_balance' => (string) $record->statement_opening_balance,
                    'statement_closing_balance' => (string) $record->statement_closing_balance,
                    'lines' => $record->lines()->orderBy('source_type')->orderBy('source_id')->get()->map(fn ($line) => [
                        'statement_date' => $line->statement_date,
                        'amount' => (string) $line->amount,
                        'direction' => $line->direction,
                        'description' => $line->description,
                        'reference' => $line->reference,
                        'source_type' => $line->source_type,
                        'source_id' => (int) $line->source_id,
                    ])->all(),
                ];
                $this->validateExact($record->financial_account_id, $values, $record->id);
            }
            $record->update([
                'state' => $data['decision'] === 'finalize' ? 'finalized' : 'rejected',
                'decided_by' => $fresh->id,
                'decision_note' => $decisionNote,
                'decided_at' => now(),
                'decision_key' => strtolower($data['decision_key']),
            ]);

            return $record;
        }, attempts: 3);
    }

    public function assertPostingAllowed(int $accountId, string $businessDate): void
    {
        if (BankReconciliation::query()->where('financial_account_id', $accountId)->where('state', 'finalized')
            ->whereDate('period_start', '<=', $businessDate)->whereDate('period_end', '>=', $businessDate)->exists()) {
            throw ValidationException::withMessages(['business_date' => 'This date is inside a finalized bank reconciliation period. Use an open business date.']);
        }
    }

    /** @param array<string,mixed> $values */
    private function validateExact(int $accountId, array $values, ?int $exceptReconciliation): void
    {
        $ledger = app(CashbookLedger::class);
        $opening = $ledger->balanceAtStart($accountId, $values['period_start']);
        $closing = $ledger->balanceAtEnd($accountId, $values['period_end']);
        if ($opening === null || bccomp($opening, $values['statement_opening_balance'], 2) !== 0) {
            throw ValidationException::withMessages(['statement_opening_balance' => 'The statement opening balance must equal the account book balance at the start of this date.']);
        }
        if ($closing === null || bccomp($closing, $values['statement_closing_balance'], 2) !== 0) {
            throw ValidationException::withMessages(['statement_closing_balance' => 'The statement closing balance must equal the account book balance at the end of the period.']);
        }
        $entries = $ledger->entries($accountId, $values['period_start'], $values['period_end']);
        $expected = [];
        foreach ($entries as $entry) {
            $expected[$this->sourceKey($entry['source_type'], $entry['source_id'])] = $entry;
        }
        $seen = [];
        $statementMovement = '0.00';
        foreach ($values['lines'] as $line) {
            if ($line['statement_date'] < $values['period_start'] || $line['statement_date'] > $values['period_end']) {
                throw ValidationException::withMessages(['lines' => 'Every statement line date must fall within the selected period.']);
            }
            $key = $this->sourceKey($line['source_type'], (int) $line['source_id']);
            $entry = $expected[$key] ?? null;
            if (! $entry || isset($seen[$key]) || $entry['direction'] !== $line['direction'] || bccomp($entry['amount'], $line['amount'], 2) !== 0) {
                throw ValidationException::withMessages(['lines' => 'Each statement line must match a unique posted cashbook entry for this bank account, direction and exact amount.']);
            }
            $seen[$key] = true;
            $statementMovement = $line['direction'] === 'inflow'
                ? bcadd($statementMovement, $line['amount'], 2)
                : bcsub($statementMovement, $line['amount'], 2);
        }
        if (count($seen) !== count($expected)) {
            throw ValidationException::withMessages(['lines' => 'Every posted cashbook entry in the period must have exactly one matching statement line. Unmatched items cannot be finalized.']);
        }
        $calculatedClosing = bcadd($values['statement_opening_balance'], $statementMovement, 2);
        if (bccomp($calculatedClosing, $values['statement_closing_balance'], 2) !== 0) {
            throw ValidationException::withMessages(['statement_closing_balance' => 'Statement lines do not reconcile to the entered closing balance.']);
        }
        $this->assertSourcesAvailable($accountId, $values['lines'], $exceptReconciliation);
    }

    private function assertNoOverlap(int $accountId, string $start, string $end, ?int $except = null): void
    {
        $overlap = BankReconciliation::query()->where('financial_account_id', $accountId)
            ->whereIn('state', ['prepared', 'finalized'])
            ->whereDate('period_start', '<=', $end)->whereDate('period_end', '>=', $start)
            ->when($except, fn ($query) => $query->where('id', '<>', $except))->exists();
        if ($overlap) {
            throw new ConflictHttpException('This statement period overlaps another prepared or finalized reconciliation.');
        }
    }

    /** @param list<array<string,mixed>> $lines */
    private function assertSourcesAvailable(int $accountId, array $lines, ?int $exceptReconciliation): void
    {
        foreach ($lines as $line) {
            $used = DB::table('bank_reconciliation_lines as lines')
                ->join('bank_reconciliations as reconciliations', 'reconciliations.id', '=', 'lines.bank_reconciliation_id')
                ->where('reconciliations.financial_account_id', $accountId)
                ->whereIn('reconciliations.state', ['prepared', 'finalized'])
                ->where('lines.source_type', $line['source_type'])
                ->where('lines.source_id', $line['source_id'])
                ->when($exceptReconciliation, fn ($query) => $query->where('reconciliations.id', '<>', $exceptReconciliation))
                ->exists();
            if ($used) {
                throw new ConflictHttpException('A cashbook entry is already reserved by another prepared or finalized reconciliation.');
            }
        }
    }

    private function sourceKey(string $type, int $id): string
    {
        return $type.':'.$id;
    }

    /** @return list<string> */
    private function hashes(string $payload): array
    {
        return array_values(array_map(fn (string $key): string => hash_hmac('sha256', $payload, $key), [config('app.key'), ...config('app.previous_keys', [])]));
    }
}
