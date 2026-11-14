<?php

namespace App\Domain\Import;

use App\Models\Block;
use App\Models\Booking;
use App\Models\BookingDraft;
use App\Models\BookingEvent;
use App\Models\Customer;
use App\Models\DraftTerm;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\Plot;
use App\Models\PlotOwner;
use App\Models\PlotSize;
use App\Models\Society;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ImportService
{
    public function __construct() {}

    /** @return numeric-string */
    private function numeric(string $value): string
    {
        if (! is_numeric($value)) {
            throw ValidationException::withMessages(['value' => 'A numeric value is invalid.']);
        }

        return $value;
    }

    /** @param list<array<string,string>> $rows
     */
    public function dryRun(User $actor, string $template, array $rows, ?string $filename): ImportBatch
    {
        $definition = ImportTemplates::get($template);
        if ($definition === null) {
            throw ValidationException::withMessages(['template' => 'Choose a supported import template.']);
        }

        return DB::transaction(function () use ($actor, $template, $rows, $filename) {
            $batch = ImportBatch::create([
                'template' => $template,
                'template_version' => ImportTemplates::VERSION,
                'actor_id' => $actor->id,
                'state' => 'dry_run',
                'source_filename' => $filename,
                'row_count' => count($rows),
                'error_count' => 0,
                'command_key' => (string) Str::uuid(),
                'payload_hash' => hash('sha256', (string) json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            ]);

            $errorCount = 0;
            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2; // header is row 1
                $errors = $this->validateRow($template, $row, $actor);
                if ($errors !== []) {
                    $errorCount++;
                }
                ImportRow::create([
                    'import_batch_id' => $batch->id,
                    'row_number' => $rowNumber,
                    'payload' => $row,
                    'errors' => $errors === [] ? null : $errors,
                    'state' => $errors === [] ? 'valid' : 'invalid',
                ]);
            }

            $batch->update(['error_count' => $errorCount]);

            return $batch->fresh();
        }, attempts: 3);
    }

    public function commit(User $actor, int $batchId): ImportBatch
    {
        return DB::transaction(function () use ($actor, $batchId) {
            $batch = ImportBatch::query()->lockForUpdate()->findOrFail($batchId);
            abort_unless($batch->actor_id === $actor->id || $actor->hasAnyRole(['super_admin', 'admin']), 403);
            if ($batch->state !== 'dry_run') {
                throw ValidationException::withMessages(['batch' => 'This import batch has already been processed.']);
            }
            if ($batch->error_count > 0) {
                throw ValidationException::withMessages(['batch' => 'Fix the row errors and upload the file again before committing.']);
            }

            $rows = $batch->rows()->orderBy('row_number')->lockForUpdate()->get();
            $committed = 0;
            foreach ($rows as $row) {
                if ($row->state !== 'valid') {
                    continue;
                }
                $this->commitRow($batch->template, $row->payload, $actor);
                $row->update(['state' => 'committed']);
                $committed++;
            }

            $batch->update(['state' => 'committed', 'committed_at' => now()]);

            return $batch->fresh();
        }, attempts: 3);
    }

    /** @param array<string,string> $row
     * @return list<string>
     */
    private function validateRow(string $template, array $row, User $actor): array
    {
        return match ($template) {
            'customers' => $this->validateCustomer($row, $actor),
            'plot_owners' => $this->validatePlotOwner($row, $actor),
            'inventory' => $this->validateInventory($row, $actor),
            'bookings' => $this->validateBooking($row, $actor),
            default => ['Unsupported template.'],
        };
    }

    /** @param array<string,string> $row
     * @return list<string>
     */
    private function validateCustomer(array $row, User $actor): array
    {
        $errors = [];
        $name = trim($row['name'] ?? '');
        if ($name === '') {
            $errors[] = 'Name is required.';
        } elseif (mb_strlen($name) > 160) {
            $errors[] = 'Name must be 160 characters or fewer.';
        }
        $phone = trim($row['phone'] ?? '');
        if ($phone !== '' && mb_strlen($phone) > 40) {
            $errors[] = 'Phone must be 40 characters or fewer.';
        }
        $email = trim($row['email'] ?? '');
        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email is invalid.';
        }
        $cnic = trim($row['cnic'] ?? '');
        if ($cnic !== '' && ! preg_match('/^(?:[0-9]{13}|[0-9]{5}-[0-9]{7}-[0-9])$/', $cnic)) {
            $errors[] = 'CNIC must be 13 digits or the 5-7-1 format.';
        }
        $assignedEmail = trim($row['assigned_to_email'] ?? '');
        if ($assignedEmail !== '' && ! User::where('email', $assignedEmail)->exists()) {
            $errors[] = 'Assigned agent email does not match a staff account.';
        }
        if ($phone !== '' && Customer::where('phone', $phone)->exists()) {
            $errors[] = 'A customer with this phone already exists.';
        }

        return $errors;
    }

    /** @param array<string,string> $row
     * @return list<string>
     */
    private function validatePlotOwner(array $row, User $actor): array
    {
        $errors = [];
        $name = trim($row['name'] ?? '');
        if ($name === '') {
            $errors[] = 'Name is required.';
        } elseif (mb_strlen($name) > 120) {
            $errors[] = 'Name must be 120 characters or fewer.';
        } elseif (PlotOwner::where('name', $name)->exists()) {
            $errors[] = 'A plot owner with this name already exists.';
        }
        $adminEmail = trim($row['admin_email'] ?? '');
        if ($adminEmail === '') {
            $errors[] = 'Admin email is required.';
        } else {
            $admin = User::where('email', $adminEmail)->first();
            if (! $admin) {
                $errors[] = 'Admin email does not match a staff account.';
            } elseif (! $admin->hasAnyRole(['super_admin', 'admin'])) {
                $errors[] = 'Plot owner admin must be a super_admin or admin.';
            } elseif (PlotOwner::where('admin_user_id', $admin->id)->exists()) {
                $errors[] = 'This admin is already linked to a plot owner.';
            }
        }
        $percentage = trim($row['default_percentage'] ?? '');
        if ($percentage !== '' && ! preg_match('/^\d{1,3}(\.\d{1,4})?$/', $percentage)) {
            $errors[] = 'Default percentage must be a number with up to 4 decimals.';
        } elseif ($percentage !== '' && bccomp($this->numeric($percentage), '100', 4) > 0) {
            $errors[] = 'Default percentage cannot exceed 100.';
        }

        return $errors;
    }

    /** @param array<string,string> $row
     * @return list<string>
     */
    private function validateInventory(array $row, User $actor): array
    {
        $errors = [];
        $blockCode = trim($row['block_code'] ?? '');
        $block = $blockCode !== '' ? Block::where('code', $blockCode)->first() : null;
        if ($blockCode === '') {
            $errors[] = 'Block code is required.';
        } elseif (! $block) {
            $errors[] = 'Block code does not match an existing block.';
        }
        $sizeLabel = trim($row['size_label'] ?? '');
        $size = $sizeLabel !== '' ? PlotSize::where('label', $sizeLabel)->first() : null;
        if ($sizeLabel === '') {
            $errors[] = 'Size label is required.';
        } elseif (! $size) {
            $errors[] = 'Size label does not match an existing plot size.';
        }
        $number = trim($row['number'] ?? '');
        if ($number === '') {
            $errors[] = 'Plot number is required.';
        } elseif (mb_strlen($number) > 64) {
            $errors[] = 'Plot number must be 64 characters or fewer.';
        } elseif ($block && Plot::where('block_id', $block->id)->where('number', $number)->exists()) {
            $errors[] = 'A plot with this number already exists in the block.';
        }
        $price = trim($row['list_price'] ?? '');
        if ($price !== '' && ! preg_match('/^\d+(\.\d{1,2})?$/', $price)) {
            $errors[] = 'List price must be a positive amount with up to 2 decimals.';
        } elseif ($price !== '' && bccomp($this->numeric($price), '0', 2) <= 0) {
            $errors[] = 'List price must be greater than zero.';
        }
        $ownerName = trim($row['owner_name'] ?? '');
        $owner = $ownerName !== '' ? PlotOwner::where('name', $ownerName)->first() : null;
        if ($ownerName !== '' && ! $owner) {
            $errors[] = 'Owner name does not match an existing plot owner.';
        }
        $ownerPercentage = trim($row['owner_percentage'] ?? '');
        if ($ownerPercentage !== '' && ! preg_match('/^\d{1,3}(\.\d{1,4})?$/', $ownerPercentage)) {
            $errors[] = 'Owner percentage must be a number with up to 4 decimals.';
        } elseif ($ownerPercentage !== '' && bccomp($this->numeric($ownerPercentage), '100', 4) > 0) {
            $errors[] = 'Owner percentage cannot exceed 100.';
        }
        if ($owner && $ownerPercentage === '') {
            $errors[] = 'Owner percentage is required when an owner is assigned.';
        }

        return $errors;
    }

    /** @param array<string,string> $row
     * @return list<string>
     */
    private function validateBooking(array $row, User $actor): array
    {
        $errors = [];
        $customerPhone = trim($row['customer_phone'] ?? '');
        $customer = $customerPhone !== '' ? Customer::where('phone', $customerPhone)->first() : null;
        if ($customerPhone === '') {
            $errors[] = 'Customer phone is required.';
        } elseif (! $customer) {
            $errors[] = 'Customer phone does not match an existing customer.';
        }
        $blockCode = trim($row['block_code'] ?? '');
        $block = $blockCode !== '' ? Block::where('code', $blockCode)->first() : null;
        if ($blockCode === '') {
            $errors[] = 'Block code is required.';
        } elseif (! $block) {
            $errors[] = 'Block code does not match an existing block.';
        }
        $plotNumber = trim($row['plot_number'] ?? '');
        $plot = ($block && $plotNumber !== '') ? Plot::where('block_id', $block->id)->where('number', $plotNumber)->first() : null;
        if ($plotNumber === '') {
            $errors[] = 'Plot number is required.';
        } elseif ($block && ! $plot) {
            $errors[] = 'Plot number does not match an existing plot in the block.';
        } elseif ($plot && Booking::where('plot_id', $plot->id)->exists()) {
            $errors[] = 'This plot already has a confirmed booking.';
        }
        $confirmedOn = trim($row['confirmed_on'] ?? '');
        if ($confirmedOn === '') {
            $errors[] = 'Confirmed date is required.';
        } elseif (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $confirmedOn)) {
            $errors[] = 'Confirmed date must be YYYY-MM-DD.';
        }
        $net = trim($row['net'] ?? '');
        if ($net === '') {
            $errors[] = 'Net price is required.';
        } elseif (! preg_match('/^\d+(\.\d{1,2})?$/', $net)) {
            $errors[] = 'Net price must be a positive amount with up to 2 decimals.';
        } elseif (bccomp($this->numeric($net), '0', 2) <= 0) {
            $errors[] = 'Net price must be greater than zero.';
        }
        $downPayment = trim($row['down_payment'] ?? '');
        if ($downPayment === '') {
            $errors[] = 'Down payment is required.';
        } elseif (! preg_match('/^\d+(\.\d{1,2})?$/', $downPayment)) {
            $errors[] = 'Down payment must be a positive amount with up to 2 decimals.';
        } elseif (bccomp($this->numeric($downPayment), '0', 2) <= 0) {
            $errors[] = 'Down payment must be greater than zero.';
        } elseif (bccomp($this->numeric($downPayment), $this->numeric($net), 2) > 0) {
            $errors[] = 'Down payment cannot exceed the net price.';
        }
        $frequency = trim($row['frequency'] ?? '');
        if (! in_array($frequency, ['monthly', 'quarterly', 'half_yearly', 'yearly'], true)) {
            $errors[] = 'Frequency must be monthly, quarterly, half_yearly or yearly.';
        }
        $count = trim($row['count'] ?? '');
        if ($count === '') {
            $errors[] = 'Installment count is required.';
        } elseif (! ctype_digit($count) || (int) $count < 1 || (int) $count > 360) {
            $errors[] = 'Installment count must be between 1 and 360.';
        }
        $firstDueDate = trim($row['first_due_date'] ?? '');
        if ($firstDueDate === '') {
            $errors[] = 'First due date is required.';
        } elseif (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $firstDueDate)) {
            $errors[] = 'First due date must be YYYY-MM-DD.';
        }

        return $errors;
    }

    /** @param array<string,string> $row */
    private function commitRow(string $template, array $row, User $actor): void
    {
        match ($template) {
            'customers' => $this->commitCustomer($row, $actor),
            'plot_owners' => $this->commitPlotOwner($row, $actor),
            'inventory' => $this->commitInventory($row, $actor),
            'bookings' => $this->commitBooking($row, $actor),
            default => throw ValidationException::withMessages(['template' => 'Unsupported template.']),
        };
    }

    /** @param array<string,string> $row */
    private function commitCustomer(array $row, User $actor): void
    {
        $assignedEmail = trim($row['assigned_to_email'] ?? '');
        $assignedTo = $assignedEmail !== '' ? User::where('email', $assignedEmail)->value('id') : $actor->id;
        $cnic = trim($row['cnic'] ?? '');
        $cnic = $cnic !== '' ? str_replace('-', '', $cnic) : null;
        Customer::create([
            'name' => trim($row['name']),
            'phone' => trim($row['phone'] ?? '') ?: null,
            'email' => trim($row['email'] ?? '') ?: null,
            'assigned_to' => $assignedTo,
            'created_by' => $actor->id,
            'creation_key' => (string) Str::uuid(),
            'creation_hash' => hash('sha256', (string) json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'cnic' => $cnic,
            'address' => trim($row['address'] ?? '') ?: null,
        ]);
    }

    /** @param array<string,string> $row */
    private function commitPlotOwner(array $row, User $actor): void
    {
        $admin = User::where('email', trim($row['admin_email']))->firstOrFail();
        $percentage = trim($row['default_percentage'] ?? '');
        PlotOwner::create([
            'name' => trim($row['name']),
            'admin_user_id' => $admin->id,
            'default_percentage' => $percentage !== '' ? $percentage : null,
            'created_by' => $actor->id,
        ]);
    }

    /** @param array<string,string> $row */
    private function commitInventory(array $row, User $actor): void
    {
        $block = Block::where('code', trim($row['block_code']))->firstOrFail();
        $size = PlotSize::where('label', trim($row['size_label']))->firstOrFail();
        $ownerName = trim($row['owner_name'] ?? '');
        $owner = $ownerName !== '' ? PlotOwner::where('name', $ownerName)->first() : null;
        $ownerPercentage = trim($row['owner_percentage'] ?? '');
        $price = trim($row['list_price'] ?? '');
        $plot = new Plot([
            'block_id' => $block->id,
            'plot_size_id' => $size->id,
            'number' => trim($row['number']),
            'list_price' => $price !== '' ? $price : null,
            'plot_owner_id' => $owner?->id,
            'owner_percentage' => $ownerPercentage !== '' ? $ownerPercentage : null,
        ]);
        $plot->setAttribute('created_by', $actor->id);
        $plot->setAttribute('updated_by', $actor->id);
        $plot->save();
    }

    /** @param array<string,string> $row */
    private function commitBooking(array $row, User $actor): void
    {
        $customer = Customer::where('phone', trim($row['customer_phone']))->firstOrFail();
        $block = Block::where('code', trim($row['block_code']))->firstOrFail();
        $plot = Plot::where('block_id', $block->id)->where('number', trim($row['plot_number']))->firstOrFail();
        $society = Society::find(1);
        $net = bcadd($this->numeric(trim($row['net'])), '0', 2);
        $downPayment = bcadd($this->numeric(trim($row['down_payment'])), '0', 2);
        $frequency = trim($row['frequency']);
        $count = (int) trim($row['count']);
        $firstDueDate = trim($row['first_due_date']);
        $confirmedOn = trim($row['confirmed_on']);

        $draft = BookingDraft::create([
            'customer_id' => $customer->id,
            'plot_id' => $plot->id,
            'created_by' => $actor->id,
            'quoted_price' => $net,
            'catalogue_price_snapshot' => $plot->list_price,
            'notes' => 'Imported from legacy data.',
            'version' => 1,
            'creation_key' => (string) Str::uuid(),
            'creation_hash' => hash('sha256', (string) json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
        ]);

        DB::table('draft_purchasers')->insert([
            'booking_draft_id' => $draft->id,
            'customer_id' => $customer->id,
            'share' => '100.0000',
        ]);
        $draft->update(['purchasers_version' => 1]);

        $balance = bcsub($this->numeric($net), $this->numeric($downPayment), 2);
        $schedule = $this->buildSchedule($balance, $frequency, $count, $firstDueDate);
        $termsSnapshot = [
            'gross' => $net,
            'discount' => '0.00',
            'net' => $net,
            'minimum_down_payment' => $downPayment,
            'down_payment' => $downPayment,
            'balance' => $balance,
            'schedule' => $schedule,
            'source_version' => 1,
            'policy_versions' => ['booking' => 1, 'installments' => 1],
            'policy_snapshot' => ['booking' => [], 'installments' => []],
            'input' => ['discount' => '0.00', 'discount_reason' => '', 'down_payment' => $downPayment, 'frequency' => $frequency, 'count' => $count, 'first_due_date' => $firstDueDate, 'custom' => []],
        ];
        DraftTerm::create([
            'booking_draft_id' => $draft->id,
            'version' => 1,
            'snapshot' => $termsSnapshot,
            'updated_by' => $actor->id,
        ]);

        $plot->load('block', 'size', 'owner:id,name');
        $booking = Booking::create([
            'booking_draft_id' => $draft->id,
            'plot_id' => $plot->id,
            'customer_id' => $customer->id,
            'confirmed_by' => $actor->id,
            'confirmed_on' => $confirmedOn,
            'net' => $net,
            'down_payment' => $downPayment,
            'snapshot' => [
                'customer_name' => $customer->name,
                'society' => $society?->only(['name', 'office_name', 'phone', 'email', 'address']),
                'plot' => $plot->toArray(),
                'purchasers' => [['customer_id' => $customer->id, 'name' => $customer->name, 'share' => '100.0000']],
                'terms' => $termsSnapshot,
                'documents' => [],
                'source_version' => 1,
                'purchasers_version' => 1,
                'terms_version' => 1,
            ],
            'private_snapshot' => ['purchasers' => [['customer_id' => $customer->id, 'name' => $customer->name, 'share' => '100.0000']]],
            'command_key' => (string) Str::uuid(),
            'payload_hash' => hash('sha256', (string) json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
        ]);

        BookingEvent::create([
            'booking_id' => $booking->id,
            'actor_id' => $actor->id,
            'details' => ['event' => 'imported', 'source' => 'legacy_import', 'down_payment' => $downPayment],
        ]);
    }

    /** @return list<array{sequence:int,due_date:string,amount:string}> */
    private function buildSchedule(string $balance, string $frequency, int $count, string $firstDueDate): array
    {
        $interval = match ($frequency) {
            'monthly' => 1,
            'quarterly' => 3,
            'half_yearly' => 6,
            'yearly' => 12,
            default => 1,
        };
        $anchor = CarbonImmutable::createFromFormat('!Y-m-d', $firstDueDate, 'Asia/Karachi');
        $common = bcround(bcdiv($this->numeric($balance), (string) $count, 10), 2, \RoundingMode::HalfAwayFromZero);
        $remainder = bcsub($this->numeric($balance), bcmul($this->numeric($common), (string) $count, 2), 2);
        $schedule = [];
        for ($index = 0; $index < $count; $index++) {
            $month = $anchor->startOfMonth()->addMonths($index * $interval);
            $day = min($anchor->day, $month->daysInMonth);
            $date = $month->addDays($day - 1);
            $adjust = $index === $count - 1;
            $schedule[] = [
                'sequence' => $index + 1,
                'due_date' => $date->format('Y-m-d'),
                'amount' => $adjust ? bcadd($this->numeric($common), $this->numeric($remainder), 2) : $common,
            ];
        }

        return $schedule;
    }
}
