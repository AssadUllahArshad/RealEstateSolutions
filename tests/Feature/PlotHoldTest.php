<?php

namespace Tests\Feature;

use App\Domain\Inventory\Catalogue;
use App\Domain\Inventory\Holds;
use App\Models\InventoryEvent;
use App\Models\Plot;
use App\Models\PlotHold;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class PlotHoldTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $agent;

    private Plot $plot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->create()->assignRole('super_admin');
        $this->agent = User::factory()->create()->assignRole('sales_agent');
        $catalogue = app(Catalogue::class);
        $catalogue->society($this->admin, ['name' => 'DEMO Hold Society']);
        $block = $catalogue->block($this->admin, ['code' => 'HOLD', 'name' => 'DEMO Hold Block']);
        $size = $catalogue->size($this->admin, ['label' => 'DEMO Custom', 'area_marla' => '5.75']);
        $this->plot = $catalogue->plot($this->admin, ['number' => 'HOLD-01', 'block_id' => $block->id, 'plot_size_id' => $size->id]);
    }

    private function createHold(string $kind = 'reservation', ?User $actor = null): PlotHold
    {
        return app(Holds::class)->execute($actor ?? $this->agent, $this->plot->id, $kind, (string) Str::uuid(), 'DEMO internal site visit');
    }

    public function test_agent_confirms_immediately_without_expiry_or_customer_and_can_view_history(): void
    {
        $this->actingAs($this->agent)->from('/inventory/plots/'.$this->plot->id.'/hold')->post('/inventory/plots/'.$this->plot->id.'/holds', [
            'kind' => 'reservation', 'key' => (string) Str::uuid(), 'reason' => 'Internal site visit',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('plot_holds', ['plot_id' => $this->plot->id, 'kind' => 'reservation', 'state' => 'active', 'created_by' => $this->agent->id, 'released_at' => null]);
        $this->get('/inventory/plots/'.$this->plot->id.'/hold')->assertOk()->assertInertia(fn (Assert $page) => $page->component('inventory/hold')->where('active.creator.name', $this->agent->name)->where('canAdminister', false)->has('history.data', 1)->missing('active.creator.email'));
        $this->get('/inventory')->assertInertia(fn (Assert $page) => $page->where('plots.data.0.active_hold.kind', 'reservation')->missing('plots.data.0.active_hold.purpose'));
    }

    public function test_only_authorized_admin_can_release_or_block_even_if_agent_gets_the_permission(): void
    {
        $hold = $this->createHold();
        $this->agent->givePermissionTo(['inventory.release', 'inventory.block']);
        $this->actingAs($this->agent)->post('/inventory/holds/'.$hold->id.'/release', ['key' => (string) Str::uuid(), 'reason' => 'Forbidden'])->assertForbidden();
        $this->post('/inventory/plots/'.$this->plot->id.'/holds', ['kind' => 'block', 'key' => (string) Str::uuid(), 'reason' => 'Forbidden'])->assertForbidden();
        $admin = User::factory()->create()->assignRole('admin');
        $this->actingAs($admin)->post('/inventory/holds/'.$hold->id.'/release', ['key' => (string) Str::uuid(), 'reason' => 'Visit cancelled'])->assertSessionHasNoErrors();
        $block = $this->createHold('block', $admin);
        $this->assertSame('block', $block->kind);
        $this->assertSame(1, PlotHold::whereNotNull('active_plot_id')->count());
    }

    public function test_unauthenticated_ungranted_and_revoked_users_are_denied(): void
    {
        $this->get('/inventory/plots/'.$this->plot->id.'/hold')->assertRedirect('/login');
        $this->post('/inventory/plots/'.$this->plot->id.'/holds', [])->assertRedirect('/login');
        $ungranted = User::factory()->create();
        $this->actingAs($ungranted)->get('/inventory/plots/'.$this->plot->id.'/hold')->assertForbidden();
        $this->agent->removeRole('sales_agent');
        $this->expectException(AuthorizationException::class);
        $this->createHold();
    }

    public function test_conflict_preserves_existing_holder_and_cannot_block_over_it(): void
    {
        $hold = $this->createHold();
        $this->actingAs($this->admin)->from('/inventory/plots/'.$this->plot->id.'/hold')->post('/inventory/plots/'.$this->plot->id.'/holds', [
            'kind' => 'block', 'key' => (string) Str::uuid(), 'reason' => 'New admin block',
        ])->assertRedirect()->assertSessionHasErrors('conflict');
        $this->assertDatabaseCount('plot_holds', 1);
        $this->assertSame($hold->id, PlotHold::whereNotNull('active_plot_id')->firstOrFail()->id);
    }

    public function test_idempotency_replay_changed_payload_and_stale_release_are_safe(): void
    {
        $service = app(Holds::class);
        $key = (string) Str::uuid();
        $hold = $service->execute($this->agent, $this->plot->id, 'reservation', $key, 'Original purpose');
        $this->assertSame($hold->id, $service->execute($this->agent, $this->plot->id, 'reservation', $key, 'Original purpose')->id);
        try {
            $service->execute($this->agent, $this->plot->id, 'reservation', $key, 'Changed purpose');
            $this->fail('Changed payload must conflict.');
        } catch (ConflictHttpException) {
            $this->assertDatabaseCount('inventory_commands', 1);
        }
        $releaseKey = (string) Str::uuid();
        $service->execute($this->admin, $this->plot->id, 'release', $releaseKey, 'Finished', $hold->id);
        $next = $this->createHold();
        $service->execute($this->admin, $this->plot->id, 'release', $releaseKey, 'Finished', $hold->id);
        $service->execute($this->admin, $this->plot->id, 'release', (string) Str::uuid(), 'Stale screen', $hold->id);
        $this->assertSame($next->id, PlotHold::whereNotNull('active_plot_id')->firstOrFail()->id);
        $this->assertSame(1, InventoryEvent::where('event', 'reservation.released')->count());
        $this->assertDatabaseCount('plot_holds', 2);
    }

    public function test_database_unique_active_plot_covers_reservations_and_blocks(): void
    {
        $this->createHold();
        try {
            DB::table('plot_holds')->insert(['plot_id' => $this->plot->id, 'kind' => 'block', 'state' => 'active', 'purpose' => 'Bypass', 'created_by' => $this->admin->id]);
            $this->fail('The DB must reject overlapping occupancy.');
        } catch (QueryException $exception) {
            $this->assertSame(1062, $exception->errorInfo[1]);
        }
    }

    public function test_database_rejects_inconsistent_release_fields(): void
    {
        $hold = $this->createHold();
        try {
            DB::table('plot_holds')->where('id', $hold->id)->update(['state' => 'released']);
            $this->fail('Release metadata is required.');
        } catch (QueryException $exception) {
            $this->assertSame(3819, $exception->errorInfo[1]);
        }
    }

    public function test_audit_failure_rolls_back_hold_and_command(): void
    {
        InventoryEvent::creating(function (InventoryEvent $event) {
            if ($event->subject_type === 'plot_hold') {
                throw new RuntimeException('Simulated audit outage');
            }
        });
        try {
            $this->createHold();
            $this->fail('Audit should fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated audit outage', $exception->getMessage());
            $this->assertDatabaseCount('plot_holds', 0);
            $this->assertDatabaseCount('inventory_commands', 0);
        } finally {
            InventoryEvent::flushEventListeners();
            InventoryEvent::clearBootedModels();
        }
    }

    public function test_blank_reason_and_injected_actor_expiry_customer_and_status_are_rejected(): void
    {
        $this->actingAs($this->agent)->post('/inventory/plots/'.$this->plot->id.'/holds', ['kind' => 'reservation', 'key' => (string) Str::uuid(), 'reason' => '  ', 'created_by' => $this->admin->id, 'expires_at' => '2030-01-01', 'customer_id' => 1, 'state' => 'sold'])->assertSessionHasErrors(['reason', 'created_by', 'expires_at', 'customer_id', 'state']);
        $this->assertDatabaseCount('plot_holds', 0);
    }

    public function test_release_audit_failure_preserves_occupancy_and_original_history(): void
    {
        $hold = $this->createHold();
        InventoryEvent::creating(function (InventoryEvent $event) {
            if ($event->event === 'reservation.released') {
                throw new RuntimeException('Simulated release audit outage');
            }
        });
        try {
            app(Holds::class)->execute($this->admin, $this->plot->id, 'release', (string) Str::uuid(), 'Attempted release', $hold->id);
            $this->fail('Release audit must fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated release audit outage', $exception->getMessage());
            $this->assertDatabaseHas('plot_holds', ['id' => $hold->id, 'state' => 'active', 'active_plot_id' => $this->plot->id, 'released_by' => null]);
            $this->assertDatabaseCount('inventory_commands', 1);
            $this->assertSame(0, InventoryEvent::where('event', 'reservation.released')->count());
        } finally {
            InventoryEvent::flushEventListeners();
            InventoryEvent::clearBootedModels();
        }
    }

    public function test_hold_filters_and_export_use_only_current_occupancy_with_other_filters(): void
    {
        $hold = $this->createHold();
        $free = app(Catalogue::class)->plot($this->admin, ['number' => 'FREE-02', 'block_id' => $this->plot->block_id, 'plot_size_id' => $this->plot->plot_size_id]);
        $this->actingAs($this->agent)->get('/inventory?hold=reservation')->assertInertia(fn (Assert $page) => $page->has('plots.data', 1)->where('plots.data.0.id', $this->plot->id)->where('filters.hold', 'reservation'));
        $this->get('/inventory?hold=none')->assertInertia(fn (Assert $page) => $page->has('plots.data', 1)->where('plots.data.0.id', $free->id));
        $this->get('/inventory?hold=block')->assertInertia(fn (Assert $page) => $page->has('plots.data', 0));
        $this->get('/inventory?hold=reservation&q=FREE')->assertInertia(fn (Assert $page) => $page->has('plots.data', 0));
        $this->getJson('/inventory?hold=sold')->assertUnprocessable();
        $csv = $this->actingAs($this->admin)->get('/inventory/export?hold=reservation')->assertOk()->streamedContent();
        $this->assertStringContainsString('HOLD-01', $csv);
        $this->assertStringNotContainsString('FREE-02', $csv);
        app(Holds::class)->execute($this->admin, $this->plot->id, 'release', (string) Str::uuid(), 'Finished', $hold->id);
        $this->get('/inventory?hold=reservation')->assertInertia(fn (Assert $page) => $page->has('plots.data', 0));
        $this->get('/inventory?hold=none')->assertInertia(fn (Assert $page) => $page->has('plots.data', 2));
        $this->createHold('block', $this->admin);
        $this->get('/inventory?hold=block&view=grid')->assertInertia(fn (Assert $page) => $page->has('plots.data', 1)->where('plots.data.0.id', $this->plot->id));
    }
}
