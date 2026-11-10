<?php

namespace Tests\Feature;

use App\Domain\Finance\AgentCommissions;
use App\Domain\Sales\FinalBookings;
use App\Models\BusinessPolicy;
use App\Models\CommissionEvent;
use App\Models\CommissionPayment;
use App\Models\LegacyCommissionAssignment;
use App\Models\User;
use App\Support\BusinessClock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\FinalBookingFixtures;
use Tests\TestCase;

class AgentCommissionTest extends TestCase
{
    use FinalBookingFixtures,RefreshDatabase;

    public function test_collection_commission_is_snapshotted_and_payment_requires_admin(): void
    {
        $f = $this->fixture();
        BusinessPolicy::create(['key' => 'commissions', 'values' => ['type' => 'percentage', 'value' => '5.0000', 'basis' => 'cleared_collections', 'trigger' => 'collection_cleared', 'cancellation' => 'reverse_and_recover'], 'version' => 1, 'updated_by' => $f['admin']->id]);
        $booking = app(FinalBookings::class)->confirm($f['agent'], $f['draft']->id, $this->confirmation($f['receipt']->id));
        $this->assertSame('5.00', app(AgentCommissions::class)->payable($f['agent']->id));
        $this->assertSame('5.0000', $booking->commission_percentage);
        $accountant = User::factory()->create()->assignRole('accountant');
        $payload = ['agent_id' => $f['agent']->id, 'financial_account_id' => $f['account']->id, 'amount' => '3.00', 'business_date' => app(BusinessClock::class)->today(), 'verification_note' => 'Earned collection commission verified against booking.', 'command_key' => (string) Str::uuid(), 'confirmed' => true];
        $this->actingAs($accountant)->post('/finance/commissions', $payload)->assertSessionHasNoErrors();
        $this->post('/finance/commissions', $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('commission_payments', 1);
        $p = CommissionPayment::firstOrFail();
        $this->post('/finance/commissions/'.$p->id.'/decision', ['decision' => 'approve', 'decision_note' => 'Collection and rate snapshot verified.', 'decision_key' => (string) Str::uuid(), 'confirmed' => true])->assertForbidden();
        $this->actingAs($f['admin'])->post('/finance/commissions/'.$p->id.'/decision', ['decision' => 'approve', 'decision_note' => 'Collection and rate snapshot verified.', 'decision_key' => (string) Str::uuid(), 'confirmed' => true])->assertSessionHasNoErrors();
        $this->assertSame('2.00', app(AgentCommissions::class)->payable($f['agent']->id));
    }

    public function test_reconciliation_csv_uses_date_filters_and_safe_agent_names(): void
    {
        $f = $this->fixture();
        BusinessPolicy::create(['key' => 'commissions', 'values' => ['type' => 'percentage', 'value' => '5.0000', 'basis' => 'cleared_collections', 'trigger' => 'collection_cleared', 'cancellation' => 'reverse_and_recover'], 'version' => 1, 'updated_by' => $f['admin']->id]);
        app(FinalBookings::class)->confirm($f['agent'], $f['draft']->id, $this->confirmation($f['receipt']->id));
        $f['agent']->update(['name' => '=HYPERLINK("unsafe")']);

        $accountant = User::factory()->create()->assignRole('accountant');
        $service = app(AgentCommissions::class);
        $payment = $service->prepare($accountant, ['agent_id' => $f['agent']->id, 'financial_account_id' => $f['account']->id, 'amount' => '3.00', 'business_date' => app(BusinessClock::class)->today(), 'verification_note' => 'Verified commission export against booking allocations.', 'command_key' => (string) Str::uuid(), 'confirmed' => true]);
        $service->decide($f['admin'], $payment, ['decision' => 'approve', 'decision_note' => 'Verified commission payment for reconciliation.', 'decision_key' => (string) Str::uuid(), 'confirmed' => true]);

        $response = $this->actingAs($f['admin'])->get('/finance/commissions/export?agent_id='.$f['agent']->id);
        $response->assertOk()->assertDownload('estateflow-agent-commissions.csv')->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();
        $rows = array_map(static fn ($line) => str_getcsv($line, ',', '"', ''), explode("\n", trim($csv)));
        $this->assertSame("'=HYPERLINK(\"unsafe\")", $rows[1][1]);
        $this->assertStringContainsString('booking_allocation#', $csv);
        $this->assertStringContainsString($payment->number, $csv);

        $filtered = $this->get('/finance/commissions/export?agent_id='.$f['agent']->id.'&from=2099-01-01&to=2099-12-31')->assertOk()->streamedContent();
        $this->assertStringNotContainsString('booking_allocation#', $filtered);
        $this->assertStringNotContainsString($payment->number, $filtered);
        $this->actingAs(User::factory()->create())->get('/finance/commissions/export')->assertForbidden();
    }

    public function test_admin_assigns_exact_legacy_rate_and_start_date_without_mutating_booking(): void
    {
        $f = $this->fixture();
        $booking = app(FinalBookings::class)->confirm($f['agent'], $f['draft']->id, $this->confirmation($f['receipt']->id));
        $this->assertNull($booking->commission_agent_id);
        $payload = ['agent_id' => $f['agent']->id, 'percentage' => '7.5000', 'effective_from' => app(BusinessClock::class)->today(), 'reason' => 'Approved legacy commission schedule and responsible agent verified.', 'confirmed' => true];
        $this->actingAs($f['agent'])->post('/finance/commissions/legacy/'.$booking->id, $payload)->assertForbidden();
        $this->actingAs($f['admin'])->post('/finance/commissions/legacy/'.$booking->id, $payload)->assertSessionHasNoErrors();
        $this->assertSame('7.50', app(AgentCommissions::class)->payable($f['agent']->id));
        $this->assertNull($booking->fresh()->commission_agent_id);
        $this->post('/finance/commissions/legacy/'.$booking->id, $payload)->assertSessionHasErrors('booking_id');
        $this->get('/reports/agents/'.$f['agent']->id.'/commission-statement/print')->assertOk()->assertSee('7.50');
    }

    public function test_only_main_admin_can_correct_legacy_commission_with_paid_amount_offset(): void
    {
        $f = $this->fixture();
        $booking = app(FinalBookings::class)->confirm($f['agent'], $f['draft']->id, $this->confirmation($f['receipt']->id));
        $service = app(AgentCommissions::class);
        $service->assignLegacy($f['admin'], $booking->id, ['agent_id' => $f['agent']->id, 'percentage' => '7.5000', 'effective_from' => app(BusinessClock::class)->today(), 'reason' => 'Approved original legacy commission assignment.', 'confirmed' => true]);
        $accountant = User::factory()->create()->assignRole('accountant');
        $payment = $service->prepare($accountant, ['agent_id' => $f['agent']->id, 'financial_account_id' => $f['account']->id, 'amount' => '3.00', 'business_date' => app(BusinessClock::class)->today(), 'verification_note' => 'Verified partial commission payment before correction.', 'command_key' => (string) Str::uuid(), 'confirmed' => true]);
        $service->decide($f['admin'], $payment, ['decision' => 'approve', 'decision_note' => 'Verified commission payment.', 'decision_key' => (string) Str::uuid(), 'confirmed' => true]);
        $replacement = User::factory()->create()->assignRole('sales_agent');
        $regularAdmin = User::factory()->create()->assignRole('admin');
        $payload = ['agent_id' => $replacement->id, 'percentage' => '5.0000', 'effective_from' => app(BusinessClock::class)->today(), 'reason' => 'Corrected responsible agent and rate from signed approval.', 'confirmed' => true];
        $this->actingAs($regularAdmin)->post('/finance/commissions/legacy/'.$booking->id.'/correct', $payload)->assertForbidden();
        $this->actingAs($f['admin'])->post('/finance/commissions/legacy/'.$booking->id.'/correct', $payload)->assertSessionHasNoErrors();
        $this->assertSame('-3.00', $service->payable($f['agent']->id));
        $this->assertSame('5.00', $service->payable($replacement->id));
        $this->assertSame(1, LegacyCommissionAssignment::where('booking_id', $booking->id)->where('state', 'superseded')->count());
        $this->assertSame(1, LegacyCommissionAssignment::where('booking_id', $booking->id)->where('state', 'active')->where('agent_id', $replacement->id)->count());
        $this->assertSame(1, CommissionEvent::where('booking_id', $booking->id)->where('source_kind', 'booking_allocation')->count());
        $this->assertSame(2, CommissionEvent::where('booking_id', $booking->id)->whereIn('source_kind', ['legacy_correction_debit', 'legacy_correction_credit'])->count());
        $this->get('/reports/agents/'.$f['agent']->id.'/commission-statement/print')->assertOk()->assertSee('Recoverable from agent')->assertSee('-PKR 3.00')->assertSee('-PKR 7.50');
    }
}
