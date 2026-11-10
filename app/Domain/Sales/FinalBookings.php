<?php

namespace App\Domain\Sales;

use App\Domain\CRM\Customers;
use App\Domain\Finance\OwnerEntitlements;
use App\Domain\Finance\ReceiptCredit;
use App\Models\AccountMovement;
use App\Models\Booking;
use App\Models\BookingEvent;
use App\Models\BusinessPolicy;
use App\Models\CustomerDocument;
use App\Models\DraftPurchaser;
use App\Models\DraftTerm;
use App\Models\InventoryEvent;
use App\Models\PaymentReceipt;
use App\Models\Plot;
use App\Models\PlotHold;
use App\Models\Society;
use App\Models\User;
use App\Rules\PositiveDecimal;
use App\Support\BusinessClock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class FinalBookings
{
    /** @return Builder<Booking> */
    public function query(User $actor): Builder
    {
        Gate::forUser($actor)->authorize('sales.drafts');

        return Booking::whereIn('customer_id', app(Customers::class)->query($actor)->select('id'));
    }

    /** @return array<string,mixed> */
    public function review(User $actor, int $id): array
    {
        $draft = app(BookingDrafts::class)->query($actor)->with('customer:id,name', 'plot.block:id,code')->findOrFail($id);
        $receipts = PaymentReceipt::where('customer_id', $draft->customer_id)->where('state', 'recognized')->orderBy('id')->get();
        $credits = [];
        foreach ($receipts as $receipt) {
            $allocated = app(ReceiptCredit::class)->used($receipt->id);
            $available = bcsub($receipt->amount, (string) $allocated, 2);
            if (bccomp($available, '0', 2) > 0) {
                $credits[] = ['id' => $receipt->id, 'number' => $receipt->number, 'available' => $available, 'recognized_on' => $receipt->recognized_on];
            }
        }

        return ['draft' => $draft, 'terms' => app(DraftTerms::class)->context($actor, $id), 'purchasers' => app(DraftPurchasers::class)->listing($actor, $id),
            'preparation' => app(BookingPreparation::class)->forDraft($actor, $id), 'credits' => $credits,
            'canConfirm' => $actor->can('sales.confirm') && $actor->hasAnyRole(['admin', 'super_admin', 'sales_agent', 'accountant'])];
    }

    /** @param array<string,mixed> $input */
    public function confirm(User $actor, int $id, array $input): Booking
    {
        $data = Validator::make($input, [
            'command_key' => ['required', 'uuid'], 'source_version' => ['required', 'integer', 'min:1'],
            'purchasers_version' => ['required', 'integer', 'min:1'], 'terms_version' => ['required', 'integer', 'min:1'],
            'confirmed' => ['required', 'accepted'], 'allocations' => ['required', 'array', 'min:1', 'max:50'],
            'allocations.*' => ['required', 'array:receipt_id,amount'], 'allocations.*.receipt_id' => ['required', 'integer', 'distinct', 'min:1'],
            'allocations.*.amount' => ['required', new PositiveDecimal],
            'net' => ['prohibited'], 'status' => ['prohibited'], 'actor_id' => ['prohibited'], 'confirmed_on' => ['prohibited'],
        ])->validate();
        $rows = array_map(fn ($row) => ['receipt_id' => (int) $row['receipt_id'], 'amount' => bcadd($row['amount'], '0', 2)], $data['allocations']);
        usort($rows, fn ($a, $b) => $a['receipt_id'] <=> $b['receipt_id']);
        $payload = json_encode([$id, (int) $data['source_version'], (int) $data['purchasers_version'], (int) $data['terms_version'], $rows], JSON_THROW_ON_ERROR);
        $keys = [config('app.key'), ...config('app.previous_keys', [])];
        $hashes = array_map(fn ($key) => hash_hmac('sha256', $payload, $key), $keys);

        return DB::transaction(function () use ($actor, $id, $data, $rows, $hashes) {
            $fresh = User::query()->lockForUpdate()->findOrFail($actor->id);
            Gate::forUser($fresh)->authorize('sales.confirm');
            abort_unless($fresh->hasAnyRole(['admin', 'super_admin', 'sales_agent', 'accountant']), 403);
            $society = Society::whereKey(1)->lockForUpdate()->firstOrFail();
            $visible = app(BookingDrafts::class)->query($fresh)->findOrFail($id);
            $customer = app(Customers::class)->query($fresh)->whereKey($visible->customer_id)->lockForUpdate()->firstOrFail();
            $plot = Plot::whereKey($visible->plot_id)->lockForUpdate()->firstOrFail();
            $draft = app(BookingDrafts::class)->query($fresh)->lockForUpdate()->findOrFail($id);
            $prior = Booking::where('confirmed_by', $fresh->id)->where('command_key', strtolower($data['command_key']))->lockForUpdate()->first();
            if ($prior) {
                $this->query($fresh)->findOrFail($prior->id);
                if (! in_array($prior->payload_hash, $hashes, true)) {
                    throw new ConflictHttpException('This confirmation key was used with different details. Reload before trying again.');
                }

                return $prior;
            }
            if ($draft->customer_id !== $customer->id || $draft->plot_id !== $plot->id || $draft->version !== (int) $data['source_version'] || $draft->purchasers_version !== (int) $data['purchasers_version']) {
                throw new ConflictHttpException('The proposal or purchasers changed. Reload the confirmation review.');
            }
            if (Booking::where('plot_id', $plot->id)->orWhere('booking_draft_id', $id)->lockForUpdate()->first()) {
                throw new ConflictHttpException('This plot or proposal is already booked.');
            }
            $term = DraftTerm::where('booking_draft_id', $id)->lockForUpdate()->firstOrFail();
            $policies = BusinessPolicy::whereIn('key', ['booking', 'installments', 'commissions'])->orderBy('key')->lockForUpdate()->get()->keyBy('key');
            $versions = ['booking' => $policies->get('booking')->version ?? 0, 'installments' => $policies->get('installments')->version ?? 0];
            if ($term->version !== (int) $data['terms_version'] || $term->snapshot['source_version'] !== $draft->version || $term->snapshot['policy_versions'] !== $versions) {
                throw new ConflictHttpException('Pricing or business rules changed. Recalculate the terms and review again.');
            }
            $readiness = app(BookingPreparation::class)->forDraft($fresh, $id);
            foreach ($readiness['checks'] as $check) {
                if ($check['key'] !== 'payment' && $check['state'] !== 'recorded') {
                    throw ValidationException::withMessages(['confirmation' => $check['title'].': '.$check['detail']]);
                }
            }
            $purchasers = DraftPurchaser::where('booking_draft_id', $id)->orderBy('customer_id')->lockForUpdate()->get();
            $private = [];
            $public = [];
            $identities = [];
            $shares = '0.0000';
            foreach ($purchasers as $purchaser) {
                $person = app(Customers::class)->query($fresh)->whereKey($purchaser->customer_id)->lockForUpdate()->firstOrFail();
                if (! filled($person->cnic) || ! filled($person->address) || in_array($person->cnic, $identities, true)) {
                    throw ValidationException::withMessages(['confirmation' => 'Every purchaser needs a complete, distinct identity.']);
                }
                $identities[] = $person->cnic;
                $shares = bcadd($shares, $purchaser->share, 4);
                $public[] = ['customer_id' => $person->id, 'name' => $person->name, 'share' => $purchaser->share];
                $private[] = ['customer_id' => $person->id, 'cnic' => $person->cnic, 'address' => $person->address];
            }
            if ($public === [] || bccomp($shares, '100', 4) !== 0) {
                throw ValidationException::withMessages(['confirmation' => 'Legal purchaser shares must total exactly 100%.']);
            }
            $documents = CustomerDocument::where('customer_id', $customer->id)->orderBy('id')->lockForUpdate()->get(['id', 'kind']);
            if (array_diff($policies['booking']->values['required_documents'], $documents->pluck('kind')->all()) !== []) {
                throw ValidationException::withMessages(['confirmation' => 'Required booking documents are missing.']);
            }
            $hold = PlotHold::where('active_plot_id', $plot->id)->lockForUpdate()->first();
            $access = $policies['booking']->values['held_plot_access'];
            if ($hold && ($hold->kind === 'block' || ($access === 'holder_or_admin' && $hold->created_by !== $fresh->id && ! $fresh->hasAnyRole(['admin', 'super_admin'])))) {
                throw new ConflictHttpException('The plot hold prevents confirmation. Review current availability.');
            }
            $today = app(BusinessClock::class)->today();
            if ($plot->plot_owner_id !== null && $plot->owner_percentage === null) {
                throw ValidationException::withMessages(['confirmation' => 'Set the plot owner percentage before final booking.']);
            }
            $total = '0.00';
            foreach ($rows as $row) {
                $receipt = PaymentReceipt::whereKey($row['receipt_id'])->lockForUpdate()->firstOrFail();
                abort_unless($receipt->customer_id === $customer->id, 404);
                $credit = AccountMovement::where('payment_receipt_id', $receipt->id)->where('kind', 'receipt_credit')->lockForUpdate()->first();
                if ($receipt->state !== 'recognized' || ! $credit || $receipt->recognized_on > $today || bccomp($credit->amount, $receipt->amount, 2) !== 0) {
                    throw ValidationException::withMessages(['allocations' => 'Select recognized customer receipts with an intact credit. Pending or reversed receipts are ineligible.']);
                }
                $available = bcsub($receipt->amount, app(ReceiptCredit::class)->used($receipt->id, true), 2);
                if (bccomp($row['amount'], $available, 2) > 0) {
                    throw ValidationException::withMessages(['allocations' => 'The selected receipt has insufficient unallocated credit. Reload current amounts.']);
                }
                $total = bcadd($total, $row['amount'], 2);
            }
            if (bccomp($total, $term->snapshot['down_payment'], 2) !== 0) {
                throw ValidationException::withMessages(['allocations' => 'Selected amounts must equal the saved planned down payment exactly. Remaining credit stays unallocated.']);
            }
            $commission = $policies->get('commissions');
            $agent = User::whereKey($customer->assigned_to)->first();
            $commissionEnabled = $commission && $commission->values['type'] === 'percentage' && $commission->values['basis'] === 'cleared_collections' && $commission->values['trigger'] === 'collection_cleared' && $commission->values['cancellation'] === 'reverse_and_recover' && $agent?->hasRole('sales_agent');
            $booking = Booking::create(['booking_draft_id' => $id, 'plot_id' => $plot->id, 'customer_id' => $customer->id, 'confirmed_by' => $fresh->id,
                'commission_agent_id' => $commissionEnabled ? $agent->id : null, 'commission_percentage' => $commissionEnabled ? $commission->values['value'] : null,
                'commission_policy_snapshot' => $commissionEnabled ? ['version' => $commission->version, 'values' => $commission->values, 'agent' => $agent->only(['id', 'name'])] : null,
                'confirmed_on' => $today, 'net' => $term->snapshot['net'], 'down_payment' => $total,
                'snapshot' => ['customer_name' => $customer->name, 'society' => $society->only(['name', 'office_name', 'phone', 'email', 'address']), 'plot' => $plot->load('block', 'size', 'owner:id,name')->toArray(),
                    'purchasers' => $public, 'terms' => $term->snapshot, 'documents' => $documents->toArray(), 'source_version' => $draft->version, 'purchasers_version' => $draft->purchasers_version, 'terms_version' => $term->version],
                'private_snapshot' => ['purchasers' => $private], 'command_key' => strtolower($data['command_key']), 'payload_hash' => $hashes[0]]);
            foreach ($rows as $row) {
                $allocationId = DB::table('booking_allocations')->insertGetId(['booking_id' => $booking->id, 'payment_receipt_id' => $row['receipt_id'], 'amount' => $row['amount']]);
                if ($plot->plot_owner_id !== null) {
                    app(OwnerEntitlements::class)->record($booking, 'booking_allocation', $allocationId, $row['amount'], $today);
                }
                app(\App\Domain\Finance\AgentCommissions::class)->record($booking, 'booking_allocation', $allocationId, $row['amount'], $today);
            }
            if ($hold) {
                $hold->fill(['state' => 'released', 'released_by' => $fresh->id, 'released_at' => now(), 'release_reason' => 'Consumed by confirmed booking #'.$booking->id])->save();
            }
            InventoryEvent::create(['actor_id' => $fresh->id, 'subject_type' => 'booking', 'subject_id' => $booking->id, 'event' => 'booking.confirmed', 'before' => ['hold_id' => $hold?->id], 'after' => ['plot_id' => $plot->id, 'booking_id' => $booking->id]]);
            BookingEvent::create(['booking_id' => $booking->id, 'actor_id' => $fresh->id, 'details' => ['event' => 'confirmed', 'down_payment' => $total, 'receipt_ids' => array_column($rows, 'receipt_id'), 'attestation' => 'Purchaser identities, consent, required signed documents, terms and actual payment evidence verified.']]);

            return $booking;
        }, attempts: 3);
    }
}
