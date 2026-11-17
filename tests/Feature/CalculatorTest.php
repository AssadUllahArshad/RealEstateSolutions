<?php

namespace Tests\Feature;

use App\Models\BusinessPolicy;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CalculatorTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->create()->assignRole('super_admin');
        $this->agent = User::factory()->create()->assignRole('sales_agent');
        foreach (['booking' => $this->booking(), 'installments' => $this->policy(), 'commissions' => $this->commissions()] as $key => $values) {
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
    private function commissions(): array
    {
        return ['type' => 'percentage', 'value' => '5.0000', 'basis' => 'net_contract', 'trigger' => 'booking_confirmed', 'cancellation' => 'admin_review'];
    }

    /** @return array<string,mixed> */
    private function payload(): array
    {
        return ['gross' => '1000.00', 'discount_type' => 'fixed', 'discount_value' => '0.00', 'down_payment' => '100.00', 'frequency' => 'monthly', 'count' => 7, 'first_due_date' => '2028-01-31', 'custom' => []];
    }

    public function test_authorized_agent_can_preview_exact_schedule_without_persisting(): void
    {
        $this->actingAs($this->agent)->get('/calculator')->assertOk()->assertInertia(fn (Assert $page) => $page->component('calculator')->where('result', null)->where('missingSetup', []));
        $this->post('/calculator', $this->payload())->assertSessionHasNoErrors()->assertInertia(fn (Assert $page) => $page
            ->component('calculator')
            ->where('result.net', '1000.00')
            ->where('result.minimum_down_payment', '100.00')
            ->where('result.planned_down_payment', '100.00')
            ->where('result.discount_applied_to_down_payment', '0.00')
            ->where('result.down_payment', '100.00')
            ->where('result.balance', '900.00')
            ->where('result.schedule.0.amount', '128.57')
            ->where('result.schedule.0.due_date', '2028-01-31')
            ->where('result.schedule.1.due_date', '2028-02-29')
            ->where('result.schedule.6.amount', '128.58')
            ->where('result.commission.amount', '50.00')
            ->where('result.policy_versions.booking', 1));
        $this->assertDatabaseCount('draft_terms', 0);
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_percentage_discount_and_fixed_commission_preview(): void
    {
        BusinessPolicy::whereKey('commissions')->update(['values' => ['type' => 'fixed', 'value' => '250.00', 'basis' => 'net_contract', 'trigger' => 'booking_confirmed', 'cancellation' => 'admin_review']]);
        $this->actingAs($this->agent)->post('/calculator', [...$this->payload(), 'discount_type' => 'percentage', 'discount_value' => '5'])->assertSessionHasNoErrors()->assertInertia(fn (Assert $page) => $page
            ->where('result.discount', '50.00')
            ->where('result.net', '1000.00')
            ->where('result.minimum_down_payment', '100.00')
            ->where('result.planned_down_payment', '100.00')
            ->where('result.discount_applied_to_down_payment', '50.00')
            ->where('result.down_payment', '50.00')
            ->where('result.balance', '900.00')
            ->where('result.commission.amount', '250.00'));
    }

    public function test_cleared_collections_commission_has_no_single_preview_amount(): void
    {
        BusinessPolicy::whereKey('commissions')->update(['values' => ['type' => 'percentage', 'value' => '5.0000', 'basis' => 'cleared_collections', 'trigger' => 'collection_cleared', 'cancellation' => 'reverse_and_recover']]);
        $this->actingAs($this->agent)->post('/calculator', $this->payload())->assertSessionHasNoErrors()->assertInertia(fn (Assert $page) => $page->where('result.commission.amount', null));
    }

    public function test_unauthorized_user_cannot_open_or_calculate(): void
    {
        $outsider = User::factory()->create();
        $this->actingAs($outsider)->get('/calculator')->assertForbidden();
        $this->post('/calculator', $this->payload())->assertForbidden();
    }

    public function test_incomplete_saved_setup_falls_back_to_fixed_operational_defaults(): void
    {
        BusinessPolicy::whereKey('installments')->update(['values' => [...$this->policy(), 'rounding' => null]]);
        $this->actingAs($this->agent)->post('/calculator', $this->payload())->assertSessionHasNoErrors()
            ->assertInertia(fn (Assert $page) => $page->where('result.schedule.0.amount', '128.57'));
        $this->get('/calculator')->assertInertia(fn (Assert $page) => $page->where('missingSetup', []));
    }

    public function test_business_rule_violations_return_errors_without_persisting(): void
    {
        $this->actingAs($this->agent)->post('/calculator', [...$this->payload(), 'discount_type' => 'fixed', 'discount_value' => '1000.00'])->assertSessionHasErrors('discount');
        $this->post('/calculator', [...$this->payload(), 'discount_type' => 'fixed', 'discount_value' => '100.00'])->assertSessionHasErrors('discount');
        $this->post('/calculator', [...$this->payload(), 'down_payment' => '99.99'])->assertSessionHasErrors('down_payment');
        $this->post('/calculator', [...$this->payload(), 'frequency' => 'full', 'down_payment' => '1000.00'])->assertSessionHasNoErrors();
        $this->post('/calculator', [...$this->payload(), 'frequency' => 'full', 'down_payment' => '999.99'])->assertSessionHasErrors('down_payment');
        $this->assertDatabaseCount('draft_terms', 0);
    }

    public function test_custom_schedule_must_total_balance_exactly(): void
    {
        $custom = [...$this->payload(), 'frequency' => 'custom', 'count' => null, 'first_due_date' => null, 'custom' => [['due_date' => '2028-02-01', 'amount' => '400.00'], ['due_date' => '2028-03-01', 'amount' => '500.00']]];
        $this->actingAs($this->agent)->post('/calculator', $custom)->assertSessionHasNoErrors()->assertInertia(fn (Assert $page) => $page->where('result.schedule.1.amount', '500.00'));
        $this->post('/calculator', [...$custom, 'custom' => [['due_date' => '2028-02-01', 'amount' => '400.00']]])->assertSessionHasErrors('custom');
    }
}
