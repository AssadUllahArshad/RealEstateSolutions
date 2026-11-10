<?php

namespace App\Domain\Sales;

use App\Models\BookingChangeRequest;
use App\Models\BookingEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class BookingChanges
{
    /** @param array<string,mixed> $input */
    public function request(User $actor, int $bookingId, array $input): BookingChangeRequest
    {
        Gate::forUser($actor)->authorize('sales.changes.request');
        $data = Validator::make($input, [
            'kind' => ['required', Rule::in(['correction', 'cancellation'])],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
            'proposed_change' => ['nullable', 'string', 'max:4000', Rule::requiredIf(fn () => ($input['kind'] ?? null) === 'correction')],
            'command_key' => ['required', 'uuid'],
        ])->validate();

        return DB::transaction(function () use ($actor, $bookingId, $data) {
            $fresh = User::query()->lockForUpdate()->findOrFail($actor->id);
            Gate::forUser($fresh)->authorize('sales.changes.request');
            $booking = app(FinalBookings::class)->query($fresh)->lockForUpdate()->findOrFail($bookingId);
            $prior = BookingChangeRequest::where('requested_by', $fresh->id)->where('command_key', strtolower($data['command_key']))->lockForUpdate()->first();
            if ($prior) {
                return $prior;
            }
            if (BookingChangeRequest::where('booking_id', $booking->id)->where('state', 'pending')->lockForUpdate()->exists()) {
                throw new ConflictHttpException('This booking already has a pending change request. Review it before submitting another.');
            }

            return BookingChangeRequest::create([
                'booking_id' => $booking->id, 'kind' => $data['kind'], 'reason' => $data['reason'],
                'proposed_change' => $data['proposed_change'] ?? null, 'state' => 'pending',
                'requested_by' => $fresh->id, 'command_key' => strtolower($data['command_key']),
            ]);
        }, attempts: 3);
    }

    /** @param array<string,mixed> $input */
    public function decide(User $actor, int $requestId, array $input): BookingChangeRequest
    {
        Gate::forUser($actor)->authorize('sales.changes.approve');
        $data = Validator::make($input, [
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'decision_note' => ['required', 'string', 'min:5', 'max:2000'],
            'confirmed' => ['required', 'accepted'],
        ])->validate();

        return DB::transaction(function () use ($actor, $requestId, $data) {
            $fresh = User::query()->lockForUpdate()->findOrFail($actor->id);
            Gate::forUser($fresh)->authorize('sales.changes.approve');
            abort_unless($fresh->hasAnyRole(['admin', 'super_admin']), 403);
            $change = BookingChangeRequest::whereKey($requestId)->lockForUpdate()->firstOrFail();
            app(FinalBookings::class)->query($fresh)->findOrFail($change->booking_id);
            if ($change->state !== 'pending') {
                throw ValidationException::withMessages(['decision' => 'This request has already been decided.']);
            }
            $change->forceFill(['state' => $data['decision'], 'decided_by' => $fresh->id, 'decision_note' => $data['decision_note'], 'decided_at' => now()])->save();
            BookingEvent::create(['booking_id' => $change->booking_id, 'actor_id' => $fresh->id, 'details' => [
                'event' => 'change_request.'.$data['decision'], 'request_id' => $change->id, 'kind' => $change->kind,
                'reason' => $change->reason, 'proposed_change' => $change->proposed_change, 'decision_note' => $data['decision_note'],
                'financial_effect' => 'none',
            ]]);

            return $change;
        }, attempts: 3);
    }
}
