<?php

namespace Tests\Feature;

use App\Domain\Inventory\Catalogue;
use App\Models\Block;
use App\Models\InventoryEvent;
use App\Models\Plot;
use App\Models\PlotOwner;
use App\Models\PlotSize;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

class InventoryCatalogueTest extends TestCase
{
    use RefreshDatabase;

    private function administrator(): User
    {
        $this->seed(RolePermissionSeeder::class);

        return User::factory()->create()->assignRole('super_admin');
    }

    /** @return array{Block, PlotSize} */
    private function catalogue(User $actor): array
    {
        $service = app(Catalogue::class);
        $service->society($actor, ['name' => 'DEMO Society', 'office_name' => 'DEMO Office']);

        return [
            $service->block($actor, ['code' => 'NORTH', 'name' => 'North Gardens']),
            $service->size($actor, ['label' => 'Custom 6.25', 'area_marla' => '6.25']),
        ];
    }

    public function test_export_requires_explicit_permission_and_valid_filters(): void
    {
        $this->get('/inventory/export')->assertRedirect('/login');
        $actor = $this->administrator();
        $reader = User::factory()->create()->givePermissionTo('inventory.view');
        $this->actingAs($reader)->get('/inventory/export')->assertForbidden();
        $this->actingAs($actor)->getJson('/inventory/export?sort=unsafe')->assertUnprocessable();
    }

    public function test_export_includes_all_filtered_rows_in_order_with_exact_safe_cells(): void
    {
        $actor = $this->administrator();
        [$block, $size] = $this->catalogue($actor);
        $service = app(Catalogue::class);
        for ($i = 0; $i < 26; $i++) {
            $service->plot($actor, ['block_id' => $block->id, 'plot_size_id' => $size->id,
                'number' => sprintf('MATCH-%02d', $i), 'list_price' => '9999999999999999.99']);
        }
        $service->plot($actor, ['block_id' => $block->id, 'plot_size_id' => $size->id,
            'number' => '=HYPERLINK("unsafe")', 'list_price' => null]);
        $response = $this->actingAs($actor)->get('/inventory/export?q=MATCH&sort=number_desc&page=2');
        $response->assertOk()->assertDownload('estateflow-inventory.csv');
        $csv = $response->streamedContent();
        $rows = array_map(static fn ($line) => str_getcsv($line, ',', '"', ''), explode("\n", trim($csv)));
        $this->assertCount(27, $rows);
        $this->assertSame('MATCH-25', $rows[1][2]);
        $this->assertSame('9999999999999999.99', $rows[1][5]);
        $this->assertSame('MATCH-00', $rows[26][2]);
        $safe = $this->get('/inventory/export?q=%3DHYPERLINK')->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $safe);
        $this->assertStringNotContainsString('office@example', $safe);
    }

    public function test_guests_and_ungranted_roles_cannot_access_or_modify_inventory(): void
    {
        $this->get('/inventory')->assertRedirect('/login');
        $this->get('/inventory/setup')->assertRedirect('/login');
        $actor = $this->administrator();
        $agent = User::factory()->create()->assignRole('sales_agent');
        $this->actingAs($agent)->get('/inventory')->assertOk();
        $this->get('/inventory/setup')->assertForbidden();
        $this->patch('/inventory/society', ['name' => 'Unauthorized'])->assertForbidden();
        $this->post('/inventory/blocks', ['code' => 'A', 'name' => 'A'])->assertForbidden();
        $this->post('/inventory/sizes', ['label' => 'A', 'area_marla' => '3'])->assertForbidden();
        $this->post('/inventory/plots', [])->assertForbidden();
        $this->assertDatabaseCount('inventory_events', 0);
        $this->assertDatabaseCount('plots', 0);
    }

    public function test_profile_and_custom_catalogue_are_dynamic_and_audited(): void
    {
        $actor = $this->administrator();
        $this->actingAs($actor)->patch('/inventory/society', [
            'name' => 'DEMO River Gardens', 'office_name' => 'Main Sales Office', 'email' => 'office@example.test',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->post('/inventory/blocks', ['code' => ' west   2 ', 'name' => 'West Annex'])->assertSessionHasNoErrors();
        $this->post('/inventory/sizes', ['label' => 'Custom 8.75 marla', 'area_marla' => '8.75'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('societies', ['id' => 1, 'name' => 'DEMO River Gardens']);
        $this->assertDatabaseHas('blocks', ['code' => 'WEST 2', 'name' => 'West Annex']);
        $this->assertDatabaseHas('plot_sizes', ['area_marla' => '8.75']);
        $this->assertDatabaseCount('inventory_events', 3);
        $this->assertDatabaseHas('inventory_events', ['actor_id' => $actor->id, 'subject_type' => 'society']);
    }

    public function test_database_rejects_a_second_society(): void
    {
        try {
            DB::table('societies')->insert(['id' => 2, 'name' => 'Second society']);
            $this->fail('The singleton constraint must reject a second society.');
        } catch (QueryException $exception) {
            $this->assertSame(3819, $exception->errorInfo[1]);
        }
        $this->assertDatabaseCount('societies', 1);
    }

    public function test_two_distinct_owner_admins_can_be_created_assigned_and_filtered(): void
    {
        $actor = $this->administrator();
        [$block, $size] = $this->catalogue($actor);
        $secondAdmin = User::factory()->create()->assignRole('admin');
        $this->actingAs($actor)->post('/inventory/owners', ['name' => 'DEMO Owner One', 'admin_user_id' => $actor->id])->assertSessionHasNoErrors();
        $this->post('/inventory/owners', ['name' => 'DEMO Owner Two', 'admin_user_id' => $secondAdmin->id])->assertSessionHasNoErrors();
        $thirdAdmin = User::factory()->create()->assignRole('admin');
        $this->post('/inventory/owners', ['name' => 'DEMO Owner Three', 'admin_user_id' => $thirdAdmin->id])->assertSessionHasErrors('name');
        $owner = PlotOwner::where('name', 'DEMO Owner One')->firstOrFail();
        $this->post('/inventory/plots', ['number' => 'OWNER-1', 'block_id' => $block->id, 'plot_size_id' => $size->id, 'plot_owner_id' => $owner->id])->assertSessionHasNoErrors();
        $this->get('/inventory?owner_id='.$owner->id)->assertInertia(fn (Assert $page) => $page->has('plots.data', 1)->where('plots.data.0.owner.name', 'DEMO Owner One'));
        $this->assertDatabaseHas('inventory_events', ['event' => 'plot_owner.created', 'subject_id' => $owner->id]);
    }

    public function test_owner_name_and_default_rate_can_be_updated_without_changing_plot_snapshots(): void
    {
        $actor = $this->administrator();
        [$block, $size] = $this->catalogue($actor);
        $this->actingAs($actor)->post('/inventory/owners', ['name' => 'DEMO Owner One', 'admin_user_id' => $actor->id, 'default_percentage' => '10.0000'])->assertSessionHasNoErrors();
        $owner = PlotOwner::where('name', 'DEMO Owner One')->firstOrFail();
        $this->post('/inventory/plots', ['number' => 'OWNER-RATE-1', 'block_id' => $block->id, 'plot_size_id' => $size->id, 'plot_owner_id' => $owner->id])->assertSessionHasNoErrors();
        $plot = Plot::where('number', 'OWNER-RATE-1')->firstOrFail();
        $this->assertSame('10.0000', $plot->owner_percentage);

        $agent = User::factory()->create()->assignRole('sales_agent');
        $this->actingAs($agent)->patch('/inventory/owners/'.$owner->id, ['name' => 'Amir Qureshi', 'default_percentage' => '50'])->assertForbidden();
        $this->actingAs($actor)->patch('/inventory/owners/'.$owner->id, ['name' => 'Amir Qureshi', 'default_percentage' => '50'])->assertSessionHasNoErrors();

        $this->assertSame('Amir Qureshi', $owner->fresh()->name);
        $this->assertSame('50.0000', $owner->fresh()->default_percentage);
        $this->assertSame('10.0000', $plot->fresh()->owner_percentage);
        $event = InventoryEvent::where('subject_type', 'plot_owner')->where('subject_id', $owner->id)->where('event', 'plot_owner.updated')->firstOrFail();
        $this->assertSame('10.0000', $event->before['default_percentage']);
        $this->assertSame('50.0000', $event->after['default_percentage']);
    }

    public function test_profile_is_required_before_creating_catalogue_entries(): void
    {
        $actor = $this->administrator();
        $this->actingAs($actor)->post('/inventory/blocks', ['code' => 'A', 'name' => 'Block A'])
            ->assertSessionHasErrors('society');
        $this->assertDatabaseCount('blocks', 0);
    }

    public function test_exact_price_strings_are_stored_without_float_conversion(): void
    {
        $actor = $this->administrator();
        [$block, $size] = $this->catalogue($actor);
        $this->actingAs($actor)->post('/inventory/plots', [
            'number' => ' custom   001 ', 'block_id' => $block->id, 'plot_size_id' => $size->id, 'list_price' => '9999999999999999.99',
        ])->assertSessionHasNoErrors();
        $plot = Plot::firstOrFail();
        $this->assertSame('CUSTOM 001', $plot->number);
        $this->assertSame('9999999999999999.99', $plot->list_price);
        $this->assertSame($actor->id, $plot->created_by);
        $this->get('/inventory')->assertInertia(fn (Assert $page) => $page->component('inventory/index')
            ->where('plots.data.0.list_price', '9999999999999999.99')->missing('plots.data.0.status'));
    }

    public function test_plot_numbers_are_unique_per_block_and_case_insensitive(): void
    {
        $actor = $this->administrator();
        [$block, $size] = $this->catalogue($actor);
        $second = app(Catalogue::class)->block($actor, ['code' => 'SOUTH', 'name' => 'South Gardens']);
        $data = ['number' => 'P-01', 'block_id' => $block->id, 'plot_size_id' => $size->id];
        $this->actingAs($actor)->post('/inventory/plots', $data)->assertSessionHasNoErrors();
        $this->post('/inventory/plots', [...$data, 'number' => ' p-01 '])->assertSessionHasErrors('number');
        $this->post('/inventory/plots', [...$data, 'block_id' => $second->id])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('plots', 2);
        // Database uniqueness also protects writers that bypass request validation.
        try {
            DB::table('plots')->insert([...$data, 'created_by' => $actor->id, 'updated_by' => $actor->id]);
            $this->fail('The database must reject duplicates.');
        } catch (QueryException $exception) {
            $this->assertSame(1062, $exception->errorInfo[1]);
        }
    }

    public function test_invalid_prices_sizes_and_injected_lifecycle_fields_are_rejected(): void
    {
        $actor = $this->administrator();
        [$block, $size] = $this->catalogue($actor);
        $this->actingAs($actor);
        $data = ['number' => 'P-01', 'block_id' => $block->id, 'plot_size_id' => $size->id];
        foreach (['0', '-1', '1.001', '1e6', '10000000000000000.00'] as $price) {
            $this->post('/inventory/plots', [...$data, 'list_price' => $price])->assertSessionHasErrors('list_price');
        }
        $this->post('/inventory/plots', [...$data, 'status' => 'sold', 'society_id' => 2, 'created_by' => 999])
            ->assertSessionHasErrors(['status', 'society_id', 'created_by']);
        $this->post('/inventory/plots', [...$data, 'block_id' => 999999])->assertSessionHasErrors('block_id');
        $this->post('/inventory/sizes', ['label' => 'Invalid', 'area_marla' => '0'])->assertSessionHasErrors('area_marla');
        $this->assertDatabaseCount('plots', 0);
    }

    public function test_updates_retain_history_and_repeated_identical_save_is_no_op(): void
    {
        $actor = $this->administrator();
        [$block, $size] = $this->catalogue($actor);
        $plot = app(Catalogue::class)->plot($actor, ['number' => 'P-01', 'block_id' => $block->id, 'plot_size_id' => $size->id, 'list_price' => '100.25']);
        $data = ['number' => 'P-02', 'block_id' => $block->id, 'plot_size_id' => $size->id, 'list_price' => '200.50'];
        $this->actingAs($actor)->patch('/inventory/plots/'.$plot->id, $data)->assertSessionHasNoErrors();
        $count = InventoryEvent::count();
        $this->patch('/inventory/plots/'.$plot->id, $data)->assertSessionHasNoErrors();
        $this->assertSame($count, InventoryEvent::count());
        $event = InventoryEvent::where('event', 'plot.updated')->firstOrFail();
        $this->assertSame('100.25', $event->before['list_price']);
        $this->assertSame('200.50', $event->after['list_price']);
        $this->patch('/inventory/blocks/'.$block->id, ['code' => 'EAST', 'name' => 'Renamed'])->assertSessionHasNoErrors();
        $this->patch('/inventory/sizes/'.$size->id, ['label' => 'Custom renamed', 'area_marla' => '7.75'])->assertSessionHasNoErrors();
        $this->assertSame($block->id, $plot->fresh()->block_id);
    }

    public function test_read_only_ability_cannot_modify_configuration_or_plots(): void
    {
        $actor = $this->administrator();
        [$block, $size] = $this->catalogue($actor);
        $plot = app(Catalogue::class)->plot($actor, ['number' => 'P-01', 'block_id' => $block->id, 'plot_size_id' => $size->id]);
        $reader = User::factory()->create();
        $reader->givePermissionTo('inventory.view');
        $this->actingAs($reader)->get('/inventory')->assertOk();
        $this->get('/inventory/setup')->assertForbidden();
        $this->patch('/inventory/plots/'.$plot->id, ['number' => 'Tampered'])->assertForbidden();
        $this->assertSame('P-01', $plot->fresh()->number);
    }

    public function test_search_filters_pagination_and_grid_return_matching_rows(): void
    {
        $actor = $this->administrator();
        [$block, $size] = $this->catalogue($actor);
        for ($i = 1; $i <= 26; $i++) {
            app(Catalogue::class)->plot($actor, ['number' => 'P-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT), 'block_id' => $block->id, 'plot_size_id' => $size->id]);
        }
        $this->actingAs($actor)->get('/inventory')->assertInertia(fn (Assert $page) => $page->has('plots.data', 24)->where('plots.total', 26)->where('filters.sort', 'number')->where('filters.view', 'table'));
        $this->get('/inventory?page=2')->assertInertia(fn (Assert $page) => $page->has('plots.data', 2));
        $this->get('/inventory?q=P-01&block_id='.$block->id.'&size_id='.$size->id.'&view=grid')
            ->assertInertia(fn (Assert $page) => $page->has('plots.data', 1)->where('plots.data.0.number', 'P-01')->where('filters.view', 'grid'));
        $this->get('/inventory?q=%25')->assertInertia(fn (Assert $page) => $page->has('plots.data', 0));
        $this->get('/inventory?q=0')->assertInertia(fn (Assert $page) => $page->where('plots.total', 11));
        $this->get('/inventory?sort=untrusted')->assertSessionHasErrors('sort');
    }

    public function test_audit_failure_rolls_back_catalogue_write(): void
    {
        $actor = $this->administrator();
        [$block, $size] = $this->catalogue($actor);
        InventoryEvent::creating(fn () => throw new RuntimeException('Simulated inventory audit failure'));
        try {
            app(Catalogue::class)->plot($actor, ['number' => 'P-01', 'block_id' => $block->id, 'plot_size_id' => $size->id]);
            $this->fail('Expected the audit failure to roll back.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated inventory audit failure', $exception->getMessage());
        } finally {
            InventoryEvent::flushEventListeners();
        }
        $this->assertDatabaseCount('plots', 0);
    }
}
