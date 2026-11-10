<?php

namespace Tests\Feature;

use App\Domain\CRM\Customers;
use App\Domain\Inventory\Catalogue;
use App\Domain\Sales\BookingDrafts;
use App\Domain\Sales\DraftTerms;
use App\Domain\Sales\InstallmentCalculator;
use App\Models\BookingDraft;
use App\Models\BusinessPolicy;
use App\Models\Customer;
use App\Models\DraftTerm;
use App\Models\DraftTermEvent;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

class DraftTermsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $agent;

    private Customer $customer;

    private BookingDraft $draft;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->create()->assignRole('super_admin');
        $this->agent = User::factory()->create()->assignRole('sales_agent');
        $this->customer = app(Customers::class)->save($this->agent, ['name' => 'DEMO Terms customer', 'creation_key' => (string) Str::uuid()]);
        $catalogue = app(Catalogue::class);
        $catalogue->society($this->admin, ['name' => 'DEMO Terms Society']);
        $block = $catalogue->block($this->admin, ['code' => 'T', 'name' => 'DEMO Terms']);
        $size = $catalogue->size($this->admin, ['label' => 'DEMO Terms', 'area_marla' => '5']);
        $plot = $catalogue->plot($this->admin, ['block_id' => $block->id, 'plot_size_id' => $size->id, 'number' => 'T1']);
        $this->draft = app(BookingDrafts::class)->save($this->agent, ['customer_id' => $this->customer->id, 'plot_id' => $plot->id, 'quoted_price' => '1000.00', 'creation_key' => (string) Str::uuid()]);
        foreach (['booking' => $this->booking(), 'installments' => $this->policy()] as $key => $values) {
            BusinessPolicy::create(['key' => $key, 'values' => $values, 'version' => 1, 'updated_by' => $this->admin->id]);
        }
    }

    /** @return array<string,mixed> */
    private function booking(): array
    {
        return ['down_payment_type' => 'percentage', 'down_payment_value' => '10.0000', 'required_documents' => ['signed_booking_form'], 'held_plot_access' => 'holder_or_admin'];
    }

    /** @return array<string,mixed> */
    private function policy(): array
    {
        return ['frequencies' => ['monthly', 'quarterly', 'half_yearly', 'yearly', 'custom'], 'first_due_date' => 'entered_per_booking', 'month_end' => 'clamp', 'rounding' => 'half_up', 'residual' => 'last'];
    }

    /** @return array<string,mixed> */
    private function payload(): array
    {
        return ['version' => 0, 'source_version' => 1, 'policy_versions' => ['booking' => 1, 'installments' => 1], 'discount' => '0.00', 'discount_reason' => '', 'down_payment' => '100.00', 'frequency' => 'monthly', 'count' => 7, 'first_due_date' => '2028-01-31', 'custom' => []];
    }

    public function test_exact_schedule_snapshots_replay_and_stale_versions(): void
    {
        $url = '/sales/'.$this->draft->id.'/terms';
        $payload = $this->payload();
        $this->actingAs($this->agent)->patch($url, $payload)->assertSessionHasNoErrors();
        $term = DraftTerm::firstOrFail();
        $this->assertSame('1000.00', $term->snapshot['net']);
        $this->assertSame('100.00', $term->snapshot['minimum_down_payment']);
        $this->assertSame('128.57', $term->snapshot['schedule'][0]['amount']);
        $this->assertSame('128.58', $term->snapshot['schedule'][6]['amount']);
        $this->assertSame('2028-02-29', $term->snapshot['schedule'][1]['due_date']);
        $this->assertSame('2028-03-31', $term->snapshot['schedule'][2]['due_date']);
        $this->patch($url, $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('draft_term_events', 1);
        $this->patch($url, [...$payload, 'count' => 8])->assertSessionHasErrors('conflict');
        $this->assertDatabaseCount('plot_holds', 0);
        $this->get($url)->assertInertia(fn (Assert $page) => $page->component('sales/terms')->where('stale', false)->where('canDiscount', false)->has('term.snapshot.schedule', 7));
        $this->draft->update(['version' => 2, 'quoted_price' => '1100.00']);
        $this->get($url)->assertInertia(fn (Assert $page) => $page->where('stale', true));
        $this->patch($url, [...$payload, 'version' => 1])->assertSessionHasErrors('conflict');
    }

    public function test_only_authorized_admin_controls_discount_and_rebase(): void
    {
        $url = '/sales/'.$this->draft->id.'/terms';
        $payload = [...$this->payload(), 'discount' => '10.00', 'discount_reason' => 'DEMO negotiated reduction'];
        $this->actingAs($this->agent)->patch($url, $payload)->assertForbidden();
        $this->agent->givePermissionTo('sales.discounts');
        $this->patch($url, $payload)->assertForbidden();
        $ordinary = User::factory()->create()->assignRole('admin');
        $this->actingAs($ordinary)->patch($url, $payload)->assertSessionHasNoErrors();
        $term = DraftTerm::firstOrFail();
        $this->assertSame('990.00', $term->snapshot['net']);
        $this->assertSame($ordinary->id, $term->snapshot['discount_authorized_by']);
        $this->actingAs($this->agent)->patch($url, [...$payload, 'version' => 1, 'count' => 8])->assertSessionHasNoErrors();
        $this->patch($url, [...$payload, 'version' => 2, 'discount' => '0'])->assertForbidden();
        $this->draft->update(['version' => 2, 'quoted_price' => '1200.00']);
        $this->patch($url, [...$payload, 'version' => 2, 'source_version' => 2])->assertForbidden();
    }

    public function test_rounding_modes_remainders_and_calendar_anchors_are_explicit(): void
    {
        $calculator = app(InstallmentCalculator::class);
        $this->assertSame('1.01', $calculator->round('1.005', 'half_up'));
        $this->assertSame('1.00', $calculator->round('1.005', 'half_even'));
        $this->assertSame('1.02', $calculator->round('1.015', 'half_even'));
        $this->assertSame('1.00', $calculator->round('1.009', 'down'));
        $input = [...$this->payload(), 'count' => 3, 'down_payment' => '100.00'];
        $roll = $calculator->calculate('1000.01', $input, $this->booking(), [...$this->policy(), 'month_end' => 'roll_forward', 'residual' => 'first']);
        $this->assertSame('2028-03-02', $roll['schedule'][1]['due_date']);
        $this->assertSame('2028-03-31', $roll['schedule'][2]['due_date']);
        $this->assertSame('300.01', $roll['schedule'][0]['amount']);
        foreach (['quarterly' => '2028-04-30', 'half_yearly' => '2028-07-31', 'yearly' => '2029-01-31'] as $frequency => $date) {
            $result = $calculator->calculate('1000.00', [...$input, 'frequency' => $frequency], $this->booking(), $this->policy());
            $this->assertSame($date, $result['schedule'][1]['due_date']);
        }
        $large = $calculator->calculate('9999999999999999.99', [...$input, 'down_payment' => '999999999999999.99'], ['down_payment_type' => 'fixed', 'down_payment_value' => '1.00'], $this->policy());
        $sum = '0.00';
        foreach ($large['schedule'] as $row) {
            $sum = bcadd($sum, $row['amount'], 2);
        }
        $this->assertSame($large['balance'], $sum);
    }

    public function test_custom_full_payment_validation_and_setup_gates(): void
    {
        $url = '/sales/'.$this->draft->id.'/terms';
        $payload = $this->payload();
        $this->actingAs($this->agent)->patch($url, [...$payload, 'down_payment' => '99.99'])->assertSessionHasErrors('down_payment');
        $this->patch($url, [...$payload, 'first_due_date' => '2028-02-30'])->assertSessionHasErrors('first_due_date');
        $this->patch($url, [...$payload, 'down_payment' => 100.00, 'gross' => '1.00'])->assertSessionHasErrors(['down_payment', 'gross']);
        $custom = [...$payload, 'frequency' => 'custom', 'custom' => [['due_date' => '2028-02-01', 'amount' => '400.00'], ['due_date' => '2028-03-01', 'amount' => '500.00']]];
        $this->patch($url, $custom)->assertSessionHasNoErrors();
        $this->patch($url, [...$custom, 'version' => 1, 'custom' => [['due_date' => '2028-03-01', 'amount' => '400'], ['due_date' => '2028-02-01', 'amount' => '500']]])->assertSessionHasErrors('custom');
        $this->patch($url, [...$payload, 'version' => 1, 'frequency' => 'full', 'down_payment' => '1000.00', 'custom' => [['due_date' => '', 'amount' => '']], 'first_due_date' => 'not-a-date'])->assertSessionHasNoErrors();
        $this->assertSame([], DraftTerm::firstOrFail()->snapshot['schedule']);
        BusinessPolicy::whereKey('installments')->update(['version' => 2]);
        $this->patch($url, [...$payload, 'version' => 2])->assertSessionHasErrors('conflict');
        BusinessPolicy::whereKey('installments')->update(['values' => [...$this->policy(), 'rounding' => null]]);
        $this->patch($url, [...$payload, 'version' => 2, 'policy_versions' => ['booking' => 1, 'installments' => 2]])->assertSessionHasErrors('plan');
    }

    public function test_scope_and_audit_failure_do_not_write_terms(): void
    {
        $url = '/sales/'.$this->draft->id.'/terms';
        $other = User::factory()->create()->assignRole('sales_agent');
        $this->actingAs($other)->get($url)->assertNotFound();
        $this->patch($url, $this->payload())->assertNotFound();
        DraftTermEvent::creating(fn () => throw new RuntimeException('DEMO terms audit unavailable'));
        try {
            app(DraftTerms::class)->save($this->agent, $this->draft->id, $this->payload());
            $this->fail('Expected audit failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('DEMO terms audit unavailable', $exception->getMessage());
        } finally {
            DraftTermEvent::flushEventListeners();
        }
        $this->assertDatabaseCount('draft_terms', 0);
        $this->assertDatabaseCount('draft_term_events', 0);
    }
}
