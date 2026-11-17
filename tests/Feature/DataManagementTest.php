<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\PaymentReceipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\FinalBookingFixtures;
use Tests\TestCase;

class DataManagementTest extends TestCase
{
    use FinalBookingFixtures, RefreshDatabase;

    public function test_only_main_admin_can_open_and_delete_operational_records(): void
    {
        $fixture = $this->fixture();

        $this->actingAs($fixture['agent'])->get('/data-management')->assertForbidden();
        $this->actingAs($fixture['agent'])->delete('/data-management/customers/'.$fixture['customer']->id, [
            'resource' => 'customers', 'confirmation' => 'DELETE', 'reason' => 'Duplicate test record',
        ])->assertForbidden();

        $this->actingAs($fixture['admin'])->get('/data-management')->assertOk()
            ->assertInertia(fn ($page) => $page->component('administration/data-management')->where('selected', 'customers'));
    }

    public function test_main_admin_hard_delete_removes_the_aggregate_and_records_an_audit_event(): void
    {
        $fixture = $this->fixture();

        $this->actingAs($fixture['admin'])->delete('/data-management/customers/'.$fixture['customer']->id, [
            'resource' => 'customers',
            'confirmation' => 'DELETE',
            'reason' => 'Duplicate customer entered during testing',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertFalse(Customer::whereKey($fixture['customer']->id)->exists());
        $this->assertFalse(PaymentReceipt::whereKey($fixture['receipt']->id)->exists());
        $this->assertDatabaseHas('record_deletion_events', [
            'actor_id' => $fixture['admin']->id,
            'resource_table' => 'customers',
            'resource_id' => $fixture['customer']->id,
            'reason' => 'Duplicate customer entered during testing',
        ]);
        $this->assertGreaterThan(1, DB::table('record_deletion_events')->value('deleted_rows'));
    }

    public function test_delete_requires_an_explicit_confirmation_and_reason(): void
    {
        $fixture = $this->fixture();

        $this->actingAs($fixture['admin'])->delete('/data-management/customers/'.$fixture['customer']->id, [
            'resource' => 'customers', 'confirmation' => 'NO', 'reason' => 'short',
        ])->assertSessionHasErrors(['confirmation', 'reason']);

        $this->assertTrue(Customer::whereKey($fixture['customer']->id)->exists());
    }
}
