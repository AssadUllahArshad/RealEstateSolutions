<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\Booking;
use App\Models\BookingDraft;
use App\Models\Customer;
use App\Models\Plot;
use App\Models\PlotOwner;
use App\Models\PlotSize;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OwnerReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_report_requires_dedicated_permission(): void
    {
        $this->get('/reports/owners')->assertRedirect('/login');
        $agent = User::factory()->create()->assignRole('sales_agent');
        $this->actingAs($agent)->get('/reports/owners')->assertForbidden();

        foreach (['super_admin', 'admin', 'accountant'] as $role) {
            $actor = User::factory()->create()->assignRole($role);
            $this->actingAs($actor)->get('/reports/owners')->assertOk();
        }
    }

    public function test_report_uses_owner_snapshot_and_exact_allocated_principal(): void
    {
        $admin = User::factory()->create()->assignRole('super_admin');
        $owner = PlotOwner::create(['name' => 'DEMO Principal One', 'admin_user_id' => $admin->id, 'created_by' => $admin->id]);
        $block = Block::forceCreate(['code' => 'A', 'name' => 'Alpha', 'created_by' => $admin->id, 'updated_by' => $admin->id]);
        $size = PlotSize::forceCreate(['label' => 'Five Marla', 'area_marla' => '5.00', 'created_by' => $admin->id, 'updated_by' => $admin->id]);
        $plot = Plot::forceCreate(['block_id' => $block->id, 'plot_size_id' => $size->id, 'plot_owner_id' => $owner->id, 'number' => 'R-1', 'list_price' => '1000.01', 'created_by' => $admin->id, 'updated_by' => $admin->id]);
        $customer = Customer::create(['name' => 'DEMO Buyer', 'assigned_to' => $admin->id, 'created_by' => $admin->id, 'creation_key' => Str::uuid(), 'creation_hash' => str_repeat('a', 64)]);
        $draft = BookingDraft::create(['customer_id' => $customer->id, 'plot_id' => $plot->id, 'created_by' => $admin->id, 'quoted_price' => '1000.01', 'version' => 1, 'creation_key' => Str::uuid(), 'creation_hash' => str_repeat('b', 64)]);
        $booking = Booking::create([
            'booking_draft_id' => $draft->id, 'plot_id' => $plot->id, 'customer_id' => $customer->id, 'confirmed_by' => $admin->id,
            'confirmed_on' => '2026-11-08', 'net' => '1000.01', 'down_payment' => '100.01',
            'snapshot' => ['customer_name' => $customer->name, 'plot' => ['number' => $plot->number, 'block' => ['code' => $block->code], 'owner' => ['id' => $owner->id, 'name' => $owner->name]]],
            'private_snapshot' => [], 'command_key' => Str::uuid(), 'payload_hash' => str_repeat('c', 64),
        ]);
        $receiptId = $this->receipt($admin, $customer);
        DB::table('booking_allocations')->insert(['booking_id' => $booking->id, 'payment_receipt_id' => $receiptId, 'amount' => '100.01']);

        $this->actingAs($admin)->get('/reports/owners?from=2026-11-01&to=2026-11-30')->assertInertia(fn (Assert $page) => $page
            ->component('reports/owners')->where('rows.0.owner_name', 'DEMO Principal One')
            ->where('rows.0.inventory_plots', 1)->where('rows.0.inventory_booked', 1)
            ->where('rows.0.confirmed_bookings', 1)->where('rows.0.contract_value', '1000.01')
            ->where('rows.0.allocated_collections', '100.01')->where('rows.0.outstanding_principal', '900.00')
            ->where('recent.0.allocated_collections', '100.01'));
    }

    private function receipt(User $actor, Customer $customer): int
    {
        $account = DB::table('financial_accounts')->insertGetId(['code' => 'CASH', 'name' => 'Cash', 'type' => 'cash', 'active' => true, 'version' => 1, 'created_by' => $actor->id, 'creation_key' => Str::uuid(), 'creation_hash' => str_repeat('d', 64), 'created_at' => now(), 'updated_at' => now()]);
        $draft = DB::table('receipt_drafts')->insertGetId(['customer_id' => $customer->id, 'financial_account_id' => $account, 'created_by' => $actor->id, 'amount' => '100.01', 'reported_on' => '2026-11-08', 'method' => 'cash', 'policy_snapshot' => json_encode([]), 'account_snapshot' => json_encode([]), 'version' => 1, 'creation_key' => Str::uuid(), 'creation_hash' => str_repeat('e', 64), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('receipt_series')->insert(['prefix' => 'R', 'starting_number' => 1, 'next_number' => 2]);

        return DB::table('payment_receipts')->insertGetId(['receipt_draft_id' => $draft, 'customer_id' => $customer->id, 'financial_account_id' => $account, 'number' => 'R-0001', 'series_prefix' => 'R', 'sequence' => 1, 'amount' => '100.01', 'method' => 'cash', 'state' => 'recognized', 'accepted_on' => '2026-11-08', 'recognized_on' => '2026-11-08', 'verification_note' => '', 'snapshot' => json_encode([]), 'version' => 1, 'accepted_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
    }
}
