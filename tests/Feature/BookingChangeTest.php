<?php

namespace Tests\Feature;

use App\Domain\Sales\FinalBookings;
use App\Models\Booking;
use App\Models\BookingChangeRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FinalBookingFixtures;
use Tests\TestCase;

class BookingChangeTest extends TestCase
{
    use FinalBookingFixtures, RefreshDatabase;

    public function test_agent_requests_correction_and_admin_approves_without_rewriting_contract_or_money(): void
    {
        $f = $this->fixture();
        $booking = app(FinalBookings::class)->confirm($f['agent'], $f['draft']->id, $this->confirmation($f['receipt']->id));
        $key = (string) Str::uuid();
        $payload = ['kind' => 'correction', 'reason' => 'The purchaser address needs a documented correction.', 'proposed_change' => 'Replace the historical correspondence address with the verified address on the attached evidence.', 'command_key' => $key];

        $this->actingAs($f['agent'])->post('/bookings/'.$booking->id.'/changes', $payload)->assertSessionHasNoErrors();
        $this->post('/bookings/'.$booking->id.'/changes', $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('booking_change_requests', 1);
        $change = BookingChangeRequest::firstOrFail();
        $this->post('/booking-changes/'.$change->id.'/decision', ['decision' => 'approved', 'decision_note' => 'Evidence reviewed and correction approved.', 'confirmed' => true])->assertForbidden();

        $this->actingAs($f['admin'])->post('/booking-changes/'.$change->id.'/decision', ['decision' => 'approved', 'decision_note' => 'Evidence reviewed and correction approved.', 'confirmed' => true])->assertSessionHasNoErrors();
        $this->assertSame('approved', $change->fresh()->state);
        $this->assertSame('1000.01', Booking::findOrFail($booking->id)->net);
        $this->assertDatabaseCount('booking_allocations', 1);
        $this->assertDatabaseCount('account_movements', 1);
        $this->assertDatabaseCount('booking_events', 2);
        $this->post('/booking-changes/'.$change->id.'/decision', ['decision' => 'rejected', 'decision_note' => 'Attempted second decision.', 'confirmed' => true])->assertSessionHasErrors('decision');
    }

    public function test_cancellation_requires_admin_and_pending_request_is_visible_on_booking(): void
    {
        $f = $this->fixture();
        $booking = app(FinalBookings::class)->confirm($f['agent'], $f['draft']->id, $this->confirmation($f['receipt']->id));
        $this->actingAs($f['agent'])->post('/bookings/'.$booking->id.'/changes', [
            'kind' => 'cancellation', 'reason' => 'Purchaser submitted a signed request to cancel the booking.', 'command_key' => (string) Str::uuid(),
        ])->assertSessionHasNoErrors();
        $this->get('/bookings/'.$booking->id)->assertInertia(fn (Assert $page) => $page
            ->component('sales/booked')->has('changes', 1)->where('changes.0.state', 'pending')
            ->where('canRequestChange', true)->where('canApproveChange', false));

        $admin = User::factory()->create()->assignRole('admin');
        $this->actingAs($admin)->get('/bookings/'.$booking->id)->assertInertia(fn (Assert $page) => $page
            ->where('canApproveChange', true));
        $this->actingAs($f['agent'])->post('/bookings/'.$booking->id.'/changes', [
            'kind' => 'correction', 'reason' => 'A second request must be blocked while review is pending.',
            'proposed_change' => 'Change a record field after evidence review.', 'command_key' => (string) Str::uuid(),
        ])->assertSessionHasErrors('change');
    }

    public function test_validation_and_scope_are_enforced(): void
    {
        $f = $this->fixture();
        $booking = app(FinalBookings::class)->confirm($f['agent'], $f['draft']->id, $this->confirmation($f['receipt']->id));
        $this->actingAs($f['agent'])->post('/bookings/'.$booking->id.'/changes', [
            'kind' => 'correction', 'reason' => 'too short', 'command_key' => (string) Str::uuid(),
        ])->assertSessionHasErrors(['reason', 'proposed_change']);
        $outsider = User::factory()->create()->assignRole('sales_agent');
        $this->actingAs($outsider)->post('/bookings/'.$booking->id.'/changes', [
            'kind' => 'cancellation', 'reason' => 'This actor cannot access the original booking customer.', 'command_key' => (string) Str::uuid(),
        ])->assertNotFound();
        $this->assertDatabaseCount('booking_change_requests', 0);
    }
}
