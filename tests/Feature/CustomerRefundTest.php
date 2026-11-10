<?php

namespace Tests\Feature;

use App\Domain\Finance\CustomerRefunds;
use App\Domain\Sales\FinalBookings;
use App\Models\BookingChangeRequest;
use App\Models\CustomerRefund;
use App\Models\User;
use App\Support\BusinessClock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\FinalBookingFixtures;
use Tests\TestCase;

class CustomerRefundTest extends TestCase
{
    use FinalBookingFixtures,RefreshDatabase;

    public function test_accountant_prepares_partial_refund_and_admin_approves_exact_outflow(): void
    {
        $f = $this->fixture();
        $booking = app(FinalBookings::class)->confirm($f['agent'], $f['draft']->id, $this->confirmation($f['receipt']->id));
        BookingChangeRequest::create(['booking_id' => $booking->id, 'kind' => 'cancellation', 'reason' => 'Signed cancellation request received and verified.', 'state' => 'approved', 'requested_by' => $f['agent']->id, 'command_key' => (string) Str::uuid(), 'decided_by' => $f['admin']->id, 'decision_note' => 'Cancellation approved for controlled refund.', 'decided_at' => now()]);
        $accountant = User::factory()->create()->assignRole('accountant');
        $payload = ['booking_id' => $booking->id, 'financial_account_id' => $f['account']->id, 'amount' => '60.01', 'deduction_amount' => '10.00', 'deduction_reason' => 'Approved documentation and processing deduction.', 'business_date' => app(BusinessClock::class)->today(), 'verification_note' => 'Cancellation and refundable credit evidence verified.', 'command_key' => (string) Str::uuid(), 'confirmed' => true];
        $this->actingAs($accountant)->post('/finance/refunds', $payload)->assertSessionHasNoErrors();
        $this->post('/finance/refunds', $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('customer_refunds', 1);
        $refund = CustomerRefund::firstOrFail();
        $this->assertSame('30.00', app(CustomerRefunds::class)->available($booking->id));
        $this->post('/finance/refunds/'.$refund->id.'/decision', ['decision' => 'approve', 'decision_note' => 'Refund evidence and deduction approved.', 'decision_key' => (string) Str::uuid(), 'confirmed' => true])->assertForbidden();
        $this->get('/finance/refunds/'.$refund->id.'/print')->assertNotFound();
        $this->actingAs($f['admin'])->post('/finance/refunds/'.$refund->id.'/decision', ['decision' => 'approve', 'decision_note' => 'Refund evidence and deduction approved.', 'decision_key' => (string) Str::uuid(), 'confirmed' => true])->assertSessionHasNoErrors();
        $this->assertSame('approved', $refund->fresh()->state);
        $this->assertSame('30.00', app(CustomerRefunds::class)->available($booking->id));
        $this->get('/finance/movements')->assertOk()->assertSee('60.01');
        $this->actingAs($f['admin'])->get('/finance/refunds/'.$refund->id.'/print')->assertOk()
            ->assertSee('Customer cancellation refund')->assertSee($refund->number)
            ->assertSee('PKR 60.01')->assertSee('PKR 10.00')->assertSee('PKR 70.01')
            ->assertSee($accountant->name)->assertSee($f['admin']->name)
            ->assertSee('Only the cash refund is a cashbook outflow.')
            ->assertDontSee($refund->command_key)->assertDontSee($refund->payload_hash);
    }
}
