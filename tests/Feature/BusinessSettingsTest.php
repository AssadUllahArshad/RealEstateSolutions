<?php

namespace Tests\Feature;

use App\Domain\Configuration\BusinessPolicies;
use App\Models\BusinessPolicy;
use App\Models\BusinessPolicyEvent;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

class BusinessSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $main;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->main = User::factory()->create()->assignRole('super_admin');
    }

    public function test_only_main_admin_can_read_or_write_even_with_a_delegated_permission(): void
    {
        $this->get('/business-settings')->assertRedirect('/login');
        foreach (['admin', 'accountant', 'sales_agent'] as $role) {
            $user = User::factory()->create()->assignRole($role)->givePermissionTo('business-settings.manage');
            $this->actingAs($user)->get('/business-settings')->assertForbidden();
            $this->patch('/business-settings/owners', ['version' => 0, 'values' => []])->assertForbidden();
        }
        $this->actingAs($this->main)->get('/business-settings/unknown')->assertNotFound();
    }

    public function test_new_settings_have_no_monetary_defaults_and_sections_are_bounded(): void
    {
        $this->actingAs($this->main)->get('/business-settings')->assertInertia(fn (Assert $page) => $page->component('business-settings/index')->has('sections', 7)->where('version', 0)->where('values.down_payment_value', null)->where('values.required_documents', [])->has('history.data', 0));
        $this->get('/business-settings/owners')->assertInertia(fn (Assert $page) => $page->where('values.default_percentage', null));
        $this->assertDatabaseCount('business_policies', 0);
    }

    public function test_exact_percentages_version_history_and_no_op_survive_mysql_json_key_order(): void
    {
        $data = ['version' => 0, 'values' => ['default_percentage' => '12.3456', 'basis' => 'cleared_collections', 'trigger' => 'collection_cleared', 'cancellation' => 'admin_review']];
        $this->actingAs($this->main)->patch('/business-settings/owners', $data)->assertSessionHasNoErrors()->assertRedirect('/business-settings/owners');
        $record = BusinessPolicy::findOrFail('owners');
        $this->assertSame('12.3456', $record->values['default_percentage']);
        $this->patch('/business-settings/owners', [...$data, 'version' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('business_policy_events', 1);
        $this->patch('/business-settings/owners', [...$data, 'version' => 1, 'values' => [...$data['values'], 'default_percentage' => '25']])->assertSessionHasNoErrors();
        $this->patch('/business-settings/owners', [...$data, 'version' => 1])->assertSessionHasErrors('conflict');
        $this->assertSame('25.0000', $record->fresh()->values['default_percentage']);
        $this->assertDatabaseCount('business_policy_events', 2);
        $event = BusinessPolicyEvent::latest('id')->firstOrFail();
        $this->assertSame('12.3456', $event->before['default_percentage']);
        $this->assertSame('25.0000', $event->after['default_percentage']);
    }

    public function test_partial_setup_stays_explicit_and_zero_is_not_treated_as_missing(): void
    {
        $service = app(BusinessPolicies::class);
        $record = $service->save($this->main, 'owners', ['version' => 0, 'values' => ['default_percentage' => '0']]);
        $this->assertSame(['basis', 'trigger', 'cancellation'], $service->missing('owners', $record->values));
        $record = $service->save($this->main, 'charges', ['version' => 0, 'values' => ['late_fee_mode' => 'manual']]);
        $this->assertSame('manual', $record->values['late_fee_mode']);
        foreach (['late_fee_value', 'grace_days', 'basis', 'frequency', 'cap_mode', 'cap_value'] as $field) {
            $this->assertNull($record->values[$field]);
        }
        $this->assertSame([], $service->missing('charges', $record->values));
    }

    public function test_invalid_rates_overflow_unknown_fields_and_contradictory_rules_are_rejected(): void
    {
        $this->actingAs($this->main);
        foreach (['-1', '100.0001', '1e2', '1.12345', 25] as $rate) {
            $this->patch('/business-settings/owners', ['version' => 0, 'values' => ['default_percentage' => $rate]])->assertSessionHasErrors('values.default_percentage');
        }
        $this->patch('/business-settings/owners', ['version' => 0, 'values' => ['default_percentage' => '10', 'basis' => 'net_contract', 'trigger' => 'collection_cleared']])->assertSessionHasErrors('values.trigger');
        $this->patch('/business-settings/commissions', ['version' => 0, 'values' => ['type' => 'fixed', 'value' => '10000000000000000.00']])->assertSessionHasErrors('values.value');
        $this->patch('/business-settings/charges', ['version' => 0, 'values' => ['late_fee_mode' => 'disabled', 'late_fee_value' => '100']])->assertSessionHasErrors();
        foreach (['fixed', 'percentage'] as $mode) {
            $this->patch('/business-settings/charges', ['version' => 0, 'values' => ['late_fee_mode' => $mode]])->assertSessionHasErrors('values.late_fee_mode');
        }
        $this->patch('/business-settings/owners', ['version' => 0, 'values' => ['approved_by' => $this->main->id]])->assertSessionHasErrors('values');
        $this->assertDatabaseCount('business_policies', 0);
    }

    public function test_booking_requires_positive_configured_threshold_and_document_choices_are_whitelisted(): void
    {
        $this->actingAs($this->main)->patch('/business-settings/booking', ['version' => 0, 'values' => ['down_payment_type' => 'percentage', 'down_payment_value' => '0', 'required_documents' => ['cnic_copy']]])->assertSessionHasErrors('values.down_payment_value');
        $this->patch('/business-settings/booking', ['version' => 0, 'values' => ['down_payment_type' => 'percentage', 'down_payment_value' => '20.125', 'required_documents' => ['cnic_copy', 'signed_booking_form'], 'held_plot_access' => 'holder_or_admin']])->assertSessionHasNoErrors();
        $record = BusinessPolicy::findOrFail('booking');
        $this->assertSame('20.1250', $record->values['down_payment_value']);
        $this->assertSame([], app(BusinessPolicies::class)->missing('booking', $record->values));
        $this->patch('/business-settings/booking', ['version' => 1, 'values' => ['required_documents' => ['executable']]])->assertSessionHasErrors('values.required_documents.0');
    }

    public function test_audit_failure_rolls_back_and_unchosen_collection_rules_stay_unconfigured(): void
    {
        BusinessPolicyEvent::creating(fn () => throw new RuntimeException('DEMO policy audit outage'));
        try {
            app(BusinessPolicies::class)->save($this->main, 'collections', ['version' => 0, 'values' => ['methods' => ['cash']]]);
            $this->fail('Expected rollback');
        } catch (RuntimeException $exception) {
            $this->assertSame('DEMO policy audit outage', $exception->getMessage());
            $this->assertDatabaseCount('business_policies', 0);
        } finally {
            BusinessPolicyEvent::flushEventListeners();
        }
    }
}
