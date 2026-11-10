<?php

namespace Tests\Feature;

use App\Domain\Finance\AgentCommissions;
use App\Domain\Finance\ChargePayments;
use App\Domain\Finance\ManualCharges;
use App\Domain\Finance\CustomerRefunds;
use App\Domain\Finance\InstallmentLedger;
use App\Domain\Finance\ReceiptCredit;
use App\Domain\Finance\ReceiptDrafts;
use App\Domain\Finance\ReceiptPosting;
use App\Domain\Sales\FinalBookings;
use App\Models\BusinessPolicy;
use App\Models\CustomerActivity;
use App\Models\OwnerEntitlementEvent;
use App\Models\PlotOwner;
use App\Models\User;
use App\Support\BusinessClock;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\Support\FinalBookingFixtures;
use Tests\TestCase;

class InstallmentLedgerTest extends TestCase
{
    use FinalBookingFixtures, RefreshDatabase;

    /** @return array<string,mixed> */
    private function setupBooking(): array
    {
        $f = $this->fixture();
        $f['booking'] = app(FinalBookings::class)->confirm($f['agent'], $f['draft']->id, $this->confirmation($f['receipt']->id));

        return $f;
    }

    /** @return array<string,mixed> */
    private function command(int $receipt): array
    {
        return ['command_key' => (string) Str::uuid(), 'version' => 0, 'policy_version' => 1, 'receipt_id' => $receipt, 'amount' => '50.01', 'mode' => 'oldest_first', 'rows' => [], 'charge_rows' => [], 'confirmed' => true];
    }

    /** @return array<string,mixed> */
    private function chargePayload(int $booking): array
    {
        return [
            'booking_id' => $booking,
            'amount' => '125.50',
            'business_date' => app(BusinessClock::class)->today(),
            'reason' => 'DEMO approved late documentation charge',
            'note' => 'DEMO evidence checked by admin.',
            'evidence_reference' => 'DEMO-CHARGE-42',
            'confirmed' => true,
        ];
    }

    public function test_exact_partial_allocation_replay_shared_credit_and_scoped_ledger(): void
    {
        $f = $this->setupBooking();
        $input = $this->command($f['receipt']->id);
        $ledger = app(InstallmentLedger::class);
        $batch = $ledger->allocate($f['admin'], $f['booking']->id, $input);
        $this->assertSame($batch, $ledger->allocate($f['admin'], $f['booking']->id, $input));
        $this->assertSame('150.02', app(ReceiptCredit::class)->used($f['receipt']->id));
        $this->assertSame('50.01', DB::table('installment_allocations')->value('amount'));
        $state = $ledger->context($f['agent'], $f['booking']->id);
        $this->assertSame('849.99', $state['outstanding']);
        $this->assertSame('249.99', $state['schedule'][0]['remaining']);
        $this->assertSame('150.02', $state['paidPrincipal']);
        $this->assertSame([], $state['credits']);
        $this->assertFalse($state['canAllocate']);
        $url = '/bookings/'.$f['booking']->id.'/ledger';
        $this->actingAs($f['agent'])->get($url)->assertInertia(fn (Assert $page) => $page->component('finance/ledger')->where('outstanding', '849.99')->missing('booking.private_snapshot'));
        $outsider = User::factory()->create()->assignRole('sales_agent');
        $this->actingAs($outsider)->get($url)->assertNotFound();
        $this->actingAs($f['admin'])->post('/bookings/'.$f['booking']->id.'/allocations', [...$input, 'amount' => '1'])->assertSessionHasErrors('conflict');
        $this->post('/bookings/'.$f['booking']->id.'/allocations', [...$input, 'command_key' => (string) Str::uuid()])->assertSessionHasErrors('conflict');
        $this->assertDatabaseCount('installment_batches', 1);
        $this->assertDatabaseCount('account_movements', 1);
        $this->get('/finance/posted/'.$f['receipt']->id.'/print')->assertOk()->assertSee('PKR 150.02')->assertSee('PKR 0.00');
    }

    public function test_receipt_applies_to_principal_before_splitting_surplus_to_charges_and_reversal_restores_charge_credit(): void
    {
        $f = $this->fixture('1100.00');
        $f['booking'] = app(FinalBookings::class)->confirm($f['agent'], $f['draft']->id, $this->confirmation($f['receipt']->id));
        BusinessPolicy::create(['key' => 'charges', 'version' => 1, 'values' => ['late_fee_mode' => 'manual'], 'updated_by' => $f['admin']->id]);
        $charge = app(ManualCharges::class)->post($f['admin'], [
            ...$this->chargePayload($f['booking']->id),
            'command_key' => (string) Str::uuid(),
        ]);
        $ledger = app(InstallmentLedger::class);
        $first = $ledger->allocate($f['admin'], $f['booking']->id, [
            ...$this->command($f['receipt']->id),
            'amount' => '999.99',
            'charge_rows' => [['charge_id' => $charge->id, 'amount' => '99.99']],
        ]);
        $this->assertSame('900.00', DB::table('installment_batches')->where('id', $first)->value('amount'));
        $this->assertSame('99.99', DB::table('booking_charge_payment_batches')->value('amount'));
        $this->assertSame('0.00', $ledger->state($f['booking'])['outstanding']);
        $this->assertSame('25.51', $ledger->context($f['agent'], $f['booking']->id)['outstandingCharges']);
        $this->assertSame('1100.00', app(ReceiptCredit::class)->used($f['receipt']->id));
        $this->assertSame('1000.01', app(CustomerRefunds::class)->collected($f['booking']->id));
        $this->actingAs($f['admin'])->get('/finance/charges?state=active')->assertInertia(fn (Assert $page) => $page
            ->where('activeTotal', '25.51')
            ->where('charges.data.0.paidAmount', '99.99')
            ->where('charges.data.0.remainingAmount', '25.51'));

        $date = app(BusinessClock::class)->today();
        $draft = app(ReceiptDrafts::class)->save($f['agent'], [
            'customer_id' => $f['customer']->id, 'financial_account_id' => $f['account']->id,
            'amount' => '60.00', 'reported_on' => $date, 'method' => 'cash', 'policy_version' => 1,
            'creation_key' => (string) Str::uuid(),
        ]);
        $receipt = app(ReceiptPosting::class)->accept($f['admin'], $draft->id, [
            'command_key' => (string) Str::uuid(), 'expected_version' => 1, 'posting_version' => 1,
            'collection_version' => 1, 'business_date' => $date,
            'note' => 'DEMO recognized receipt to settle the remaining manual charge.',
            'confirmed' => true, 'stage' => 'confirmed',
        ]);
        $chargeBatch = $ledger->allocate($f['admin'], $f['booking']->id, [
            ...$this->command($receipt->id), 'version' => 1, 'amount' => '25.51',
            'charge_rows' => [['charge_id' => $charge->id, 'amount' => '25.51']],
        ]);
        $this->assertSame(2, $ledger->state($f['booking'])['version']);
        $this->assertSame('0.00', $ledger->context($f['agent'], $f['booking']->id)['outstandingCharges']);
        $this->assertSame('25.51', DB::table('booking_charge_payment_batches')->where('id', $chargeBatch)->value('amount'));
        $this->assertSame('25.51', app(ReceiptCredit::class)->used($receipt->id));
        $this->get('/finance/charges?state=settled')->assertInertia(fn (Assert $page) => $page
            ->where('charges.data.0.status', 'settled')
            ->where('charges.data.0.remainingAmount', '0.00'));

        app(ReceiptPosting::class)->advance($f['admin'], $receipt->id, 'reverse', [
            'command_key' => (string) Str::uuid(), 'expected_version' => 1, 'posting_version' => 1,
            'business_date' => $date, 'note' => 'DEMO reverse the receipt and restore its charge credit.',
            'confirmed' => true,
        ]);
        $this->assertSame('reversed', $receipt->fresh()->state);
        $this->assertSame('0.00', app(ReceiptCredit::class)->used($receipt->id));
        $this->assertSame('25.51', app(InstallmentLedger::class)->context($f['agent'], $f['booking']->id)['outstandingCharges']);
        $this->assertDatabaseCount('booking_charge_payment_reversals', 1);
        $this->get('/finance/charges?state=active')->assertInertia(fn (Assert $page) => $page
            ->where('activeTotal', '25.51')
            ->where('charges.data.0.remainingAmount', '25.51'));
        $this->actingAs($f['admin'])->post('/finance/charges/'.$charge->id.'/waive', [
            'amount' => '25.52', 'business_date' => $date, 'reason' => 'DEMO waiver exceeds the remaining amount.',
            'command_key' => (string) Str::uuid(), 'confirmed' => true,
        ])->assertSessionHasErrors('amount');
        $this->post('/finance/charges/'.$charge->id.'/waive', [
            'amount' => '25.51', 'business_date' => $date, 'reason' => 'DEMO waive exact remaining charge amount.',
            'command_key' => (string) Str::uuid(), 'confirmed' => true,
        ])->assertSessionHasNoErrors();
        $this->assertSame('0.00', app(ChargePayments::class)->balance($charge));
        $this->assertSame('125.50', $charge->amount);
    }

    public function test_manual_rule_and_remaining_amounts_are_enforced(): void
    {
        $f = $this->setupBooking();
        $url = '/bookings/'.$f['booking']->id.'/allocations';
        $input = $this->command($f['receipt']->id);
        $this->actingAs($f['admin'])->post($url, [...$input, 'mode' => 'manual', 'rows' => [['sequence' => 2, 'amount' => '50.01']]])->assertSessionHasErrors('mode');
        $policy = BusinessPolicy::findOrFail('collections');
        $policy->update(['version' => 2, 'values' => [...$policy->values, 'allocation' => 'manual']]);
        $this->post($url, $input)->assertSessionHasErrors('conflict');
        $manual = [...$input, 'policy_version' => 2, 'mode' => 'manual', 'rows' => [['sequence' => 2, 'amount' => '49.00']]];
        $this->post($url, $manual)->assertSessionHasErrors('rows');
        $this->post($url, [...$manual, 'amount' => '50.02'])->assertSessionHasErrors('amount');
        $this->post($url, [...$manual, 'rows' => [['sequence' => 9, 'amount' => '50.01']]])->assertSessionHasErrors('rows');
        $this->post($url, [...$manual, 'rows' => [['sequence' => 2, 'amount' => '50.01']]])->assertSessionHasNoErrors();
        $state = app(InstallmentLedger::class)->context($f['agent'], $f['booking']->id);
        $this->assertSame('300.00', $state['schedule'][0]['remaining']);
        $this->assertSame('249.99', $state['schedule'][1]['remaining']);
        $this->assertDatabaseCount('installment_batches', 1);
    }

    public function test_roles_attestation_and_atomic_audit_failure(): void
    {
        $f = $this->setupBooking();
        $url = '/bookings/'.$f['booking']->id.'/allocations';
        $input = $this->command($f['receipt']->id);
        $f['agent']->givePermissionTo('finance.allocations.post');
        $this->actingAs($f['agent'])->post($url, $input)->assertForbidden();
        $this->actingAs($f['admin'])->post($url, [...$input, 'confirmed' => false])->assertSessionHasErrors('confirmed');
        CustomerActivity::creating(function ($event) {
            if ($event->event === 'installments.allocated') {
                throw new RuntimeException('DEMO forced allocation audit failure');
            }
        });
        try {
            app(InstallmentLedger::class)->allocate($f['admin'], $f['booking']->id, $input);
            $this->fail('Audit failure must roll back allocations.');
        } catch (RuntimeException $exception) {
            $this->assertSame('DEMO forced allocation audit failure', $exception->getMessage());
        } finally {
            CustomerActivity::flushEventListeners();
        }
        $this->assertDatabaseCount('installment_batches', 0);
        $this->assertDatabaseCount('installment_allocations', 0);
        $this->assertSame('100.01', app(ReceiptCredit::class)->used($f['receipt']->id));
    }

    public function test_hybrid_accountant_allocation_due_boundary_and_installment_only_reversal_guard(): void
    {
        $f = $this->setupBooking();
        $date = app(BusinessClock::class)->today();
        $prep = app(ReceiptDrafts::class)->save($f['admin'], ['customer_id' => $f['customer']->id, 'financial_account_id' => $f['account']->id, 'amount' => '100.00', 'reported_on' => $date, 'method' => 'cash', 'policy_version' => 1, 'creation_key' => (string) Str::uuid()]);
        $receipt = app(ReceiptPosting::class)->accept($f['admin'], $prep->id, ['command_key' => (string) Str::uuid(), 'expected_version' => 1, 'posting_version' => 1, 'collection_version' => 1, 'business_date' => $date, 'note' => 'DEMO additional recognized installment receipt', 'confirmed' => true, 'stage' => 'confirmed']);
        $policy = BusinessPolicy::findOrFail('collections');
        $policy->update(['version' => 2, 'values' => [...$policy->values, 'allocation' => 'hybrid']]);
        $accountant = User::factory()->create()->assignRole('accountant');
        $this->assertTrue($accountant->can('customers.view'));
        CarbonImmutable::setTestNow('2028-02-29T07:00:00Z');
        try {
            app(InstallmentLedger::class)->allocate($accountant, $f['booking']->id, [...$this->command($receipt->id), 'policy_version' => 2, 'mode' => 'manual', 'amount' => '25.00', 'rows' => [['sequence' => 2, 'amount' => '25.00']]]);
            $state = app(InstallmentLedger::class)->context($accountant, $f['booking']->id);
            $this->assertSame('300.00', $state['overdue']);
            $this->assertSame('Due today', $state['schedule'][1]['status']);
            $this->assertSame('275.00', $state['schedule'][1]['remaining']);
            $this->assertSame('25.00', app(ReceiptCredit::class)->used($receipt->id));
            $this->actingAs($f['admin'])->post('/finance/posted/'.$receipt->id.'/reverse', ['command_key' => (string) Str::uuid(), 'expected_version' => 1, 'posting_version' => 1, 'business_date' => '2028-02-29', 'note' => 'DEMO cannot reverse allocated installment-only funds', 'confirmed' => true])->assertSessionHasErrors('posting');
            $this->assertDatabaseCount('account_movements', 2);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_admin_approved_correction_restores_receipt_credit_without_rewriting_original_allocation(): void
    {
        $f = $this->setupBooking();
        app(AgentCommissions::class)->assignLegacy($f['admin'], $f['booking']->id, [
            'agent_id' => $f['agent']->id,
            'percentage' => '10',
            'effective_from' => app(BusinessClock::class)->today(),
            'reason' => 'DEMO commission assignment for correction regression coverage.',
            'confirmed' => true,
        ]);
        $ledger = app(InstallmentLedger::class);
        $batch = $ledger->allocate($f['admin'], $f['booking']->id, $this->command($f['receipt']->id));
        $allocation = DB::table('installment_allocations')->where('installment_batch_id', $batch)->first();
        $owner = PlotOwner::create([
            'name' => 'DEMO Correction Owner',
            'admin_user_id' => $f['admin']->id,
            'default_percentage' => '20.0000',
            'created_by' => $f['admin']->id,
        ]);
        $snapshot = $f['booking']->snapshot;
        $snapshot['plot']['owner'] = ['id' => $owner->id, 'name' => $owner->name];
        $snapshot['plot']['owner_percentage'] = '20.0000';
        DB::table('bookings')->where('id', $f['booking']->id)->update(['snapshot' => json_encode($snapshot)]);
        OwnerEntitlementEvent::create([
            'plot_owner_id' => $owner->id,
            'booking_id' => $f['booking']->id,
            'source_kind' => 'installment_batch',
            'source_id' => $batch,
            'eligible_amount' => '50.01',
            'percentage' => '20.0000',
            'entitlement_amount' => '10.00',
            'business_date' => app(BusinessClock::class)->today(),
            'policy_snapshot' => ['basis' => 'DEMO correction regression'],
        ]);
        $accountant = User::factory()->create()->assignRole('accountant');
        $request = [
            'allocation_id' => $allocation->id,
            'version' => 1,
            'amount' => '25.00',
            'reason' => 'DEMO correct the amount applied to the wrong installment.',
            'command_key' => (string) Str::uuid(),
            'confirmed' => true,
        ];
        $this->actingAs($accountant)->post('/bookings/'.$f['booking']->id.'/allocation-corrections', $request)->assertSessionHasNoErrors();
        $correction = DB::table('installment_allocation_corrections')->first();
        $this->assertSame('150.02', app(ReceiptCredit::class)->used($f['receipt']->id));
        $this->assertSame('125.02', app(CustomerRefunds::class)->available($f['booking']->id));

        $decision = [
            'decision' => 'approve',
            'decision_note' => 'DEMO verified the source receipt and correction evidence.',
            'decision_key' => (string) Str::uuid(),
            'confirmed' => true,
        ];
        $this->actingAs($f['admin'])->post('/allocation-corrections/'.$correction->id.'/decision', $decision)->assertSessionHasNoErrors();
        $this->actingAs($f['admin'])->post('/allocation-corrections/'.$correction->id.'/decision', $decision)->assertSessionHasNoErrors();
        $this->actingAs($f['admin'])->post('/allocation-corrections/'.$correction->id.'/decision', [
            ...$decision,
            'decision_note' => 'DEMO attempt to change the decision using the same key.',
        ])->assertSessionHasErrors('conflict');

        $this->assertSame('50.01', DB::table('installment_allocations')->where('id', $allocation->id)->value('amount'));
        $this->assertSame('125.02', app(ReceiptCredit::class)->used($f['receipt']->id));
        $this->assertSame('125.02', app(CustomerRefunds::class)->collected($f['booking']->id));
        $this->assertSame('125.02', app(CustomerRefunds::class)->available($f['booking']->id));
        $this->assertDatabaseHas('commission_events', [
            'source_kind' => 'allocation_correction',
            'source_id' => $correction->id,
            'eligible_amount' => '-25.00',
            'commission_amount' => '-2.50',
        ]);
        $this->assertDatabaseHas('owner_entitlement_events', [
            'source_kind' => 'allocation_correction',
            'source_id' => $correction->id,
            'eligible_amount' => '-25.00',
            'entitlement_amount' => '-5.00',
        ]);
        $state = $ledger->state($f['booking']);
        $this->assertSame(2, $state['version']);
        $this->assertSame('874.99', $state['outstanding']);
        $this->assertSame('274.99', $state['schedule'][0]['remaining']);
        $this->actingAs($f['admin'])->get('/reports/owners')->assertInertia(fn (Assert $page) => $page
            ->where('rows.0.owner_name', 'DEMO Correction Owner')
            ->where('rows.0.allocated_collections', '125.02')
            ->where('rows.0.outstanding_principal', '874.99')
            ->where('rows.0.owner_entitlement', '5.00')
            ->where('recent.0.owner_entitlement', '5.00'));
        $this->get('/bookings/'.$f['booking']->id.'/statement/print')
            ->assertOk()->assertSee('Admin-approved allocation reversals')->assertSee('PKR 25.00');

        $this->assertSame('25.00', bcsub($f['receipt']->amount, app(ReceiptCredit::class)->used($f['receipt']->id), 2));
        $next = $this->command($f['receipt']->id);
        $ledger->allocate($f['admin'], $f['booking']->id, [...$next, 'version' => 2, 'amount' => '25.00']);
        $this->assertSame(3, $ledger->state($f['booking'])['version']);
        $this->assertSame('849.99', $ledger->state($f['booking'])['outstanding']);
    }

    public function test_correction_cannot_be_requested_by_sales_agent_or_approved_by_requester(): void
    {
        $f = $this->setupBooking();
        $batch = app(InstallmentLedger::class)->allocate($f['admin'], $f['booking']->id, $this->command($f['receipt']->id));
        $allocation = DB::table('installment_allocations')->where('installment_batch_id', $batch)->first();
        $request = [
            'allocation_id' => $allocation->id, 'version' => 1, 'amount' => '10.00',
            'reason' => 'DEMO correction requires administrator review.',
            'command_key' => (string) Str::uuid(), 'confirmed' => true,
        ];
        $this->actingAs($f['agent'])->post('/bookings/'.$f['booking']->id.'/allocation-corrections', $request)->assertForbidden();
        $accountant = User::factory()->create()->assignRole('accountant');
        $this->actingAs($accountant)->post('/bookings/'.$f['booking']->id.'/allocation-corrections', $request)->assertSessionHasNoErrors();
        $correction = DB::table('installment_allocation_corrections')->first();
        $this->actingAs($accountant)->post('/allocation-corrections/'.$correction->id.'/decision', [
            'decision' => 'approve', 'decision_note' => 'DEMO requester cannot approve.',
            'decision_key' => (string) Str::uuid(), 'confirmed' => true,
        ])->assertForbidden();
        $this->assertDatabaseHas('installment_allocation_corrections', ['id' => $correction->id, 'state' => 'prepared']);
    }
}
