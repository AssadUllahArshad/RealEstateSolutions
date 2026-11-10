<?php

namespace Tests\Feature;

use App\Domain\Access\Actions\AssignUserRole;
use App\Models\AuditEvent;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_public_registration_is_unavailable(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', ['email' => 'outside@example.test'])->assertNotFound();
    }

    public function test_staff_directory_and_role_actions_require_authentication(): void
    {
        $subject = User::factory()->create();
        $this->get('/team')->assertRedirect('/login');
        $this->patch('/team/'.$subject->id.'/access', ['role' => 'super_admin'])->assertRedirect('/login');
    }

    public function test_agent_cannot_list_or_view_another_staff_record(): void
    {
        $agent = User::factory()->create()->assignRole('sales_agent');
        $other = User::factory()->create();
        $this->actingAs($agent)->get('/team')->assertForbidden();
        $this->get('/team/'.$other->id)->assertForbidden();
        $this->get('/team/'.$agent->id)->assertOk();
    }

    public function test_role_name_does_not_implicitly_grant_administration(): void
    {
        foreach (['admin', 'accountant', 'sales_agent'] as $role) {
            $user = User::factory()->create()->assignRole($role);
            $this->actingAs($user)->get('/team')->assertForbidden();
        }
    }

    public function test_admin_and_accountant_include_the_sales_agent_workflow(): void
    {
        foreach (['admin', 'accountant'] as $role) {
            $user = User::factory()->create()->assignRole($role);
            foreach (['inventory.view', 'inventory.reservation', 'customers.view', 'customers.manage', 'sales.drafts', 'sales.confirm', 'finance.receipts.view', 'finance.receipts.prepare'] as $ability) {
                $this->assertTrue($user->can($ability), $role.' is missing '.$ability);
            }
        }
        $this->assertTrue(User::factory()->create()->assignRole('accountant')->can('customers.view_all'));
    }

    public function test_administrator_can_view_directory_without_sensitive_fields(): void
    {
        $actor = User::factory()->create()->assignRole('super_admin');
        $this->actingAs($actor)->get('/team')->assertInertia(fn (Assert $page) => $page
            ->component('team/index')->has('users.data', 1)
            ->missing('users.data.0.password')->missing('users.data.0.two_factor_secret'));
    }

    public function test_role_change_requires_password_confirmation(): void
    {
        $actor = User::factory()->create()->assignRole('super_admin');
        $subject = User::factory()->create();
        $this->actingAs($actor)->patch('/team/'.$subject->id.'/access', ['role' => 'accountant'])
            ->assertRedirect(route('password.confirm'));
        $this->assertFalse($subject->fresh()->hasRole('accountant'));
    }

    public function test_authorized_change_is_audited_and_repeat_is_a_no_op(): void
    {
        $actor = User::factory()->create()->assignRole('super_admin');
        $subject = User::factory()->create()->assignRole('sales_agent');
        $this->actingAs($actor)->withSession(['auth.password_confirmed_at' => time()]);
        $this->patch('/team/'.$subject->id.'/access', ['role' => 'accountant'])->assertRedirect();
        $this->patch('/team/'.$subject->id.'/access', ['role' => 'accountant'])->assertRedirect();
        $this->assertTrue($subject->fresh()->hasRole('accountant'));
        $this->assertDatabaseCount('audit_events', 1);
        $event = AuditEvent::firstOrFail();
        $this->assertSame($actor->id, $event->actor_id);
        $this->assertSame(['sales_agent'], $event->metadata['before']);
        $this->assertSame(['accountant'], $event->metadata['after']);
    }

    public function test_agent_cannot_promote_self_or_another_user(): void
    {
        $actor = User::factory()->create()->assignRole('sales_agent');
        $other = User::factory()->create();
        $this->actingAs($actor)->withSession(['auth.password_confirmed_at' => time()]);
        foreach ([$actor, $other] as $subject) {
            $this->patch('/team/'.$subject->id.'/access', ['role' => 'super_admin'])->assertForbidden();
        }
        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_admin_cannot_edit_own_role_or_submit_arbitrary_permissions(): void
    {
        $actor = User::factory()->create()->assignRole('super_admin');
        $subject = User::factory()->create();
        $this->actingAs($actor)->withSession(['auth.password_confirmed_at' => time()]);
        $this->patch('/team/'.$actor->id.'/access', ['role' => 'sales_agent'])->assertForbidden();
        $this->patch('/team/'.$subject->id.'/access', ['role' => 'accountant', 'permissions' => ['finance.override'], 'actor_id' => $subject->id])
            ->assertSessionHasErrors(['permissions', 'actor_id']);
        $this->patch('/team/'.$subject->id.'/access', ['role' => 'invented'])->assertSessionHasErrors('role');
        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_revocation_is_effective_on_the_next_request(): void
    {
        $actor = User::factory()->create()->assignRole('super_admin');
        $second = User::factory()->create()->assignRole('super_admin');
        $this->actingAs($actor)->get('/team')->assertOk();
        app(AssignUserRole::class)->handle($second, $actor, 'sales_agent');
        $this->actingAs($actor->fresh())->get('/team')->assertForbidden();
    }

    public function test_audit_failure_rolls_back_role_change(): void
    {
        $actor = User::factory()->create()->assignRole('super_admin');
        $subject = User::factory()->create()->assignRole('sales_agent');
        AuditEvent::creating(fn () => throw new RuntimeException('Simulated audit failure'));
        try {
            app(AssignUserRole::class)->handle($actor, $subject, 'accountant');
            $this->fail('Expected the action to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated audit failure', $exception->getMessage());
        } finally {
            AuditEvent::flushEventListeners();
        }
        $this->assertTrue($subject->fresh()->hasRole('sales_agent'));
        $this->assertDatabaseCount('audit_events', 0);
    }
}
