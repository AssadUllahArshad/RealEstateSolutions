<?php

namespace Tests\Feature;

use App\Domain\CRM\Customers;
use App\Models\Customer;
use App\Models\CustomerActivity;
use App\Models\FollowUp;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

class CustomerWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $agent;

    private User $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->create()->assignRole('super_admin');
        $this->agent = User::factory()->create()->assignRole('sales_agent');
        $this->other = User::factory()->create()->assignRole('sales_agent');
    }

    private function customer(User $actor, string $name = 'DEMO Customer'): Customer
    {
        return app(Customers::class)->save($actor, ['name' => $name, 'phone' => '0300-0000000', 'creation_key' => (string) Str::uuid()]);
    }

    public function test_agents_create_own_contacts_and_replay_without_duplicate_records_or_audit(): void
    {
        $data = ['name' => 'DEMO Own Customer', 'email' => 'customer@example.test', 'creation_key' => (string) Str::uuid()];
        $this->actingAs($this->agent)->post('/customers', $data)->assertSessionHasNoErrors()->assertRedirect();
        $record = Customer::firstOrFail();
        $this->assertSame($this->agent->id, $record->assigned_to);
        $this->post('/customers', $data)->assertRedirect('/customers/'.$record->id);
        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseCount('customer_activities', 1);
        $this->post('/customers', [...$data, 'name' => 'Changed payload'])->assertSessionHasErrors('conflict');
        $this->assertDatabaseCount('customers', 1);
        $this->get('/customers/'.$record->id)->assertInertia(fn (Assert $page) => $page->component('customers/show')->where('customer.name', $data['name'])->missing('customer.creation_hash')->missing('customer.creation_key')->has('agents', 0));
    }

    public function test_foreign_customers_are_hidden_from_list_profile_search_dashboard_and_edits(): void
    {
        $own = $this->customer($this->agent, 'DEMO Own');
        $foreign = $this->customer($this->other, 'DEMO Secret');
        $this->actingAs($this->agent)->get('/customers')->assertInertia(fn (Assert $page) => $page->has('customers.data', 1)->where('customers.data.0.id', $own->id));
        $this->get('/customers/'.$foreign->id)->assertNotFound();
        $this->patch('/customers/'.$foreign->id, ['name' => 'Tampered'])->assertNotFound();
        $this->get('/search?q=DEMO')->assertInertia(fn (Assert $page) => $page->has('customers', 1)->where('customers.0.id', $own->id));
        $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('stats.customers', 1));
        $this->get('/customers?q=Secret')->assertInertia(fn (Assert $page) => $page->has('customers.data', 0));
    }

    public function test_admin_assignment_changes_access_immediately_and_preserves_activity(): void
    {
        $record = $this->customer($this->agent);
        $this->actingAs($this->admin)->patch('/customers/'.$record->id, ['name' => $record->name, 'assigned_to' => $this->other->id])->assertSessionHasNoErrors();
        $this->actingAs($this->agent)->get('/customers/'.$record->id)->assertNotFound();
        $this->actingAs($this->other)->get('/customers/'.$record->id)->assertOk();
        $event = CustomerActivity::latest('id')->firstOrFail();
        $this->assertSame($this->agent->id, $event->metadata['assigned_from']);
        $this->assertSame($this->other->id, $event->metadata['assigned_to']);
        $this->assertArrayNotHasKey('phone', $event->metadata);
    }

    public function test_agents_cannot_choose_assignee_or_actor_and_invalid_identity_or_contacts_fail(): void
    {
        $this->actingAs($this->agent)->post('/customers', ['name' => '', 'email' => 'bad', 'assigned_to' => $this->other->id, 'created_by' => $this->admin->id, 'cnic' => 'private', 'creation_key' => 'bad'])->assertSessionHasErrors(['name', 'email', 'assigned_to', 'created_by', 'cnic', 'creation_key']);
        $this->assertDatabaseCount('customers', 0);
        $accountant = User::factory()->create()->assignRole('accountant');
        $this->actingAs($accountant)->get('/customers')->assertOk();
        $this->get('/follow-ups')->assertOk();
        $ungranted = User::factory()->create();
        $this->actingAs($ungranted)->get('/customers')->assertForbidden();
    }

    public function test_manual_followups_are_scoped_complete_once_and_do_not_send_messages(): void
    {
        $own = $this->customer($this->agent);
        $foreign = $this->customer($this->other);
        $this->actingAs($this->agent)->post('/customers/'.$own->id.'/follow-ups', ['note' => 'DEMO call about a site visit', 'due_date' => '2026-11-03'])->assertSessionHasNoErrors();
        $follow = FollowUp::firstOrFail();
        $this->post('/customers/'.$foreign->id.'/follow-ups', ['note' => 'Denied', 'due_date' => '2026-11-04'])->assertNotFound();
        $this->actingAs($this->other)->post('/follow-ups/'.$follow->id.'/complete')->assertNotFound();
        $this->actingAs($this->agent)->get('/follow-ups')->assertInertia(fn (Assert $page) => $page->has('followUps.data', 1)->where('followUps.data.0.customer.id', $own->id));
        $this->post('/follow-ups/'.$follow->id.'/complete')->assertSessionHasNoErrors();
        $this->post('/follow-ups/'.$follow->id.'/complete')->assertSessionHasNoErrors();
        $this->assertSame(1, CustomerActivity::where('event', 'follow_up.completed')->count());
        $this->assertSame($this->agent->id, $follow->fresh()->completed_by);
        $this->get('/follow-ups')->assertInertia(fn (Assert $page) => $page->has('followUps.data', 0));
        $this->get('/follow-ups?state=completed')->assertInertia(fn (Assert $page) => $page->has('followUps.data', 1));
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_karachi_due_date_filters_and_dashboard_counts_use_scoped_real_data(): void
    {
        CarbonImmutable::setTestNow('2026-11-02 20:00:00 UTC');
        try {
            $own = $this->customer($this->agent);
            $foreign = $this->customer($this->other);
            foreach ([[$own, '2026-11-02'], [$own, '2026-11-03'], [$own, '2026-11-04'], [$foreign, '2026-11-02']] as [$customer,$due]) {
                FollowUp::create(['customer_id' => $customer->id, 'created_by' => $customer->assigned_to, 'note' => 'DEMO task', 'due_date' => $due]);
            }
            $this->actingAs($this->agent)->get('/follow-ups?state=today')->assertInertia(fn (Assert $page) => $page->where('today', '2026-11-03')->has('followUps.data', 1));
            $this->get('/follow-ups?state=overdue')->assertInertia(fn (Assert $page) => $page->has('followUps.data', 1));
            $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('stats.followUps', 3)->where('stats.overdue', 1)->has('tasks', 3)->missing('collections'));
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_audit_outage_rolls_back_customer_creation(): void
    {
        CustomerActivity::creating(fn () => throw new RuntimeException('Simulated history outage'));
        try {
            $this->customer($this->agent);
            $this->fail('History failure must abort creation.');
        } catch (RuntimeException $e) {
            $this->assertSame('Simulated history outage', $e->getMessage());
            $this->assertDatabaseCount('customers', 0);
        } finally {
            CustomerActivity::flushEventListeners();
            CustomerActivity::clearBootedModels();
        }
    }

    public function test_customer_search_handles_literal_wildcards_and_zero_without_leaking_foreign_records(): void
    {
        $this->customer($this->agent, 'DEMO 0%');
        $this->customer($this->agent, 'DEMO Other')->update(['phone' => '111111111']);
        $this->customer($this->other, 'DEMO 0% Secret');
        $this->actingAs($this->agent)->get('/customers?q=0')->assertInertia(fn (Assert $page) => $page->has('customers.data', 1));
        $this->get('/customers?q=%25')->assertInertia(fn (Assert $page) => $page->has('customers.data', 1));
        $this->get('/search?q=%25')->assertInertia(fn (Assert $page) => $page->has('customers', 1));
    }

    public function test_followup_validation_and_reassignment_prevent_stale_owner_completion(): void
    {
        $record = $this->customer($this->agent);
        $this->actingAs($this->agent)->post('/customers/'.$record->id.'/follow-ups', ['note' => '', 'due_date' => '2026-02-30'])->assertSessionHasErrors(['note', 'due_date']);
        $this->post('/customers/'.$record->id.'/follow-ups', ['note' => 'DEMO task', 'due_date' => '2026-11-05'])->assertSessionHasNoErrors();
        $follow = FollowUp::firstOrFail();
        app(Customers::class)->save($this->admin, ['name' => $record->name, 'assigned_to' => $this->other->id], $record->id);
        $this->post('/follow-ups/'.$follow->id.'/complete')->assertNotFound();
        $this->get('/follow-ups')->assertInertia(fn (Assert $page) => $page->has('followUps.data', 0));
        $this->actingAs($this->other)->get('/follow-ups')->assertInertia(fn (Assert $page) => $page->has('followUps.data', 1));
    }
}
