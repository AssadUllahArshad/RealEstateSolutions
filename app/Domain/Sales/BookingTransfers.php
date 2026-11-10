<?php

namespace App\Domain\Sales;

use App\Domain\Finance\InstallmentLedger;
use App\Models\Booking;
use App\Models\BookingEvent;
use App\Models\BookingTransfer;
use App\Models\BookingTransferParty;
use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Models\User;
use App\Support\BusinessClock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class BookingTransfers
{
    /** @return list<array{customer_id:int,name:string,share:string}> */
    public function activeParties(Booking $booking, bool $lock = false): array
    {
        $query = BookingTransfer::where('booking_id', $booking->id)->where('state', 'approved')->latest('id');
        $transfer = ($lock ? $query->lockForUpdate() : $query)->first();

        return $transfer ? $transfer->incoming_snapshot : $booking->snapshot['purchasers'];
    }

    /** @param array<string,mixed> $input */
    public function request(User $actor, int $bookingId, array $input): BookingTransfer
    {
        Gate::forUser($actor)->authorize('sales.changes.request');
        $data = Validator::make($input, [
            'reason' => ['required', 'string', 'min:10', 'max:2000'], 'command_key' => ['required', 'uuid'],
            'transfer_form_document_id' => ['required', 'integer', 'min:1'],
            'payment_evidence_document_id' => ['nullable', 'integer', 'min:1'],
            'purchasers' => ['required', 'array', 'min:1', 'max:10'],
            'purchasers.*' => ['required', 'array:customer_id,share'],
            'purchasers.*.customer_id' => ['required', 'integer', 'min:1', 'distinct'],
            'purchasers.*.share' => ['required', 'regex:/^(?:100(?:\.0{1,4})?|(?:[0-9]{1,2})(?:\.[0-9]{1,4})?)$/D'],
        ])->validate();
        $rows = array_map(fn ($row) => ['customer_id' => (int) $row['customer_id'], 'share' => bcadd($row['share'], '0', 4)], $data['purchasers']);
        usort($rows, fn ($a, $b) => $a['customer_id'] <=> $b['customer_id']);
        $total = array_reduce($rows, fn ($sum, $row) => bcadd($sum, $row['share'], 4), '0.0000');
        if (bccomp($total, '100.0000', 4) !== 0) {
            throw ValidationException::withMessages(['purchasers' => 'Incoming purchaser shares must total exactly 100%.']);
        }
        $payload = json_encode([$bookingId, $data['reason'], $data['transfer_form_document_id'], $data['payment_evidence_document_id'] ?? null, $rows], JSON_THROW_ON_ERROR);
        $hashes = array_map(fn ($key) => hash_hmac('sha256', $payload, $key), [config('app.key'), ...config('app.previous_keys', [])]);

        return DB::transaction(function () use ($actor, $bookingId, $data, $rows, $hashes) {
            $fresh = User::lockForUpdate()->findOrFail($actor->id);
            Gate::forUser($fresh)->authorize('sales.changes.request');
            $booking = app(FinalBookings::class)->query($fresh)->lockForUpdate()->findOrFail($bookingId);
            $prior = BookingTransfer::where('requested_by', $fresh->id)->where('command_key', strtolower($data['command_key']))->lockForUpdate()->first();
            if ($prior) {
                if (! in_array($prior->payload_hash, $hashes, true)) {
                    throw new ConflictHttpException('This transfer key was already used with different details.');
                }

                return $prior;
            }
            if (BookingTransfer::where('booking_id', $booking->id)->where('state', 'pending')->lockForUpdate()->exists()) {
                throw new ConflictHttpException('This booking already has a pending purchaser transfer.');
            }
            $outgoing = $this->activeParties($booking, true);
            $incoming = [];
            $private = [];
            $identities = [];
            foreach ($rows as $row) {
                $customer = app(\App\Domain\CRM\Customers::class)->query($fresh)->whereKey($row['customer_id'])->lockForUpdate()->firstOrFail();
                if (! filled($customer->cnic) || ! filled($customer->address) || in_array($customer->cnic, $identities, true)) {
                    throw ValidationException::withMessages(['purchasers' => 'Every incoming purchaser needs a complete, distinct CNIC and address.']);
                }
                $kinds = CustomerDocument::where('customer_id', $customer->id)->whereIn('kind', ['cnic_copy', 'customer_photo'])->lockForUpdate()->pluck('kind')->all();
                if (array_diff(['cnic_copy', 'customer_photo'], $kinds) !== []) {
                    throw ValidationException::withMessages(['purchasers' => $customer->name.' needs a CNIC copy and customer photo.']);
                }
                $identities[] = $customer->cnic;
                $incoming[] = ['customer_id' => $customer->id, 'name' => $customer->name, 'share' => $row['share']];
                $private[] = ['customer_id' => $customer->id, 'cnic' => $customer->cnic, 'address' => $customer->address];
            }
            if ($incoming === $outgoing) {
                throw ValidationException::withMessages(['purchasers' => 'Select a different legal ownership arrangement.']);
            }
            $outgoingIds = array_column($outgoing, 'customer_id');
            $form = CustomerDocument::whereKey($data['transfer_form_document_id'])->whereIn('customer_id', $outgoingIds)->where('kind', 'signed_transfer_form')->lockForUpdate()->firstOrFail();
            foreach ($outgoingIds as $customerId) {
                if (! CustomerDocument::where('customer_id', $customerId)->where('kind', 'cnic_copy')->lockForUpdate()->exists()) {
                    throw ValidationException::withMessages(['transfer_form_document_id' => 'Every outgoing purchaser must have a CNIC copy on record.']);
                }
            }
            $evidence = null;
            if (! empty($data['payment_evidence_document_id'])) {
                $evidence = CustomerDocument::whereKey($data['payment_evidence_document_id'])->whereIn('customer_id', $outgoingIds)->where('kind', 'payment_evidence')->lockForUpdate()->firstOrFail();
            }
            $outstanding = app(InstallmentLedger::class)->state($booking, true)['outstanding'];
            $transfer = BookingTransfer::create([
                'booking_id' => $booking->id, 'state' => 'pending', 'reason' => $data['reason'], 'outgoing_snapshot' => $outgoing,
                'incoming_snapshot' => $incoming, 'private_snapshot' => ['purchasers' => $private],
                'document_snapshot' => ['signed_transfer_form' => $form->id, 'outgoing_cnic_customer_ids' => $outgoingIds, 'incoming_cnic_customer_ids' => array_column($incoming, 'customer_id'), 'payment_evidence' => $evidence?->id],
                'outstanding_snapshot' => $outstanding, 'requested_by' => $fresh->id, 'command_key' => strtolower($data['command_key']), 'payload_hash' => $hashes[0],
            ]);
            foreach ($rows as $row) {
                BookingTransferParty::create(['booking_transfer_id' => $transfer->id, ...$row]);
            }
            BookingEvent::create(['booking_id' => $booking->id, 'actor_id' => $fresh->id, 'details' => ['event' => 'transfer.requested', 'transfer_id' => $transfer->id, 'outstanding' => $outstanding, 'incoming' => $incoming]]);

            return $transfer;
        }, attempts: 3);
    }

    /** @param array<string,mixed> $input */
    public function decide(User $actor, int $id, array $input): BookingTransfer
    {
        Gate::forUser($actor)->authorize('sales.changes.approve');
        $data = Validator::make($input, ['decision' => ['required', Rule::in(['approved', 'rejected'])], 'decision_note' => ['required', 'string', 'min:5', 'max:2000'], 'confirmed' => ['required', 'accepted']])->validate();

        return DB::transaction(function () use ($actor, $id, $data) {
            $fresh = User::lockForUpdate()->findOrFail($actor->id);
            Gate::forUser($fresh)->authorize('sales.changes.approve');
            abort_unless($fresh->hasAnyRole(['admin', 'super_admin']), 403);
            $transfer = BookingTransfer::whereKey($id)->lockForUpdate()->firstOrFail();
            $booking = app(FinalBookings::class)->query($fresh)->lockForUpdate()->findOrFail($transfer->booking_id);
            if ($transfer->state !== 'pending') {
                throw ValidationException::withMessages(['decision' => 'This transfer has already been decided.']);
            }
            $currentOwnership = json_encode($this->activeParties($booking, true), JSON_THROW_ON_ERROR);
            $requestedOwnership = json_encode($transfer->outgoing_snapshot, JSON_THROW_ON_ERROR);
            if ($data['decision'] === 'approved' && ! hash_equals($currentOwnership, $requestedOwnership)) {
                throw new ConflictHttpException('Active ownership changed after this request. Reject it and prepare a current transfer.');
            }
            $effective = $data['decision'] === 'approved' ? app(BusinessClock::class)->today() : null;
            $transfer->forceFill(['state' => $data['decision'], 'decided_by' => $fresh->id, 'decision_note' => $data['decision_note'], 'effective_on' => $effective, 'decided_at' => now()])->save();
            BookingEvent::create(['booking_id' => $booking->id, 'actor_id' => $fresh->id, 'details' => ['event' => 'transfer.'.$data['decision'], 'transfer_id' => $transfer->id, 'effective_on' => $effective, 'outgoing' => $transfer->outgoing_snapshot, 'incoming' => $transfer->incoming_snapshot, 'outstanding_snapshot' => $transfer->outstanding_snapshot, 'decision_note' => $data['decision_note']]]);

            return $transfer;
        }, attempts: 3);
    }
}
