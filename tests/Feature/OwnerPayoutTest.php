<?php

namespace Tests\Feature;

use App\Domain\Finance\OwnerPayouts;
use App\Models\Block;
use App\Models\Booking;
use App\Models\BookingDraft;
use App\Models\Customer;
use App\Models\FinancialAccount;
use App\Models\OwnerEntitlementEvent;
use App\Models\OwnerPayout;
use App\Models\Plot;
use App\Models\PlotOwner;
use App\Models\PlotSize;
use App\Models\User;
use App\Support\BusinessClock;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OwnerPayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_accountant_prepares_partial_payout_and_admin_approval_reduces_balance(): void
    {
        $this->seed(RolePermissionSeeder::class);
        [$owner, $account] = $this->entitlement('100.00');
        $accountant = User::factory()->create()->assignRole('accountant');
        $admin = User::factory()->create()->assignRole('admin');
        $service = app(OwnerPayouts::class);
        $input = ['plot_owner_id' => $owner->id, 'financial_account_id' => $account->id, 'amount' => '40.25', 'business_date' => app(BusinessClock::class)->today(), 'reference' => '', 'verification_note' => 'Cash payment checked by accountant.', 'command_key' => (string) Str::uuid(), 'confirmed' => true];

        $payout = $service->prepare($accountant, $input);
        $retry = $service->prepare($accountant, $input);
        $this->assertSame($payout->id, $retry->id);
        $this->assertSame('prepared', $payout->state);
        $this->assertSame('100.00', $service->payable($owner->id));

        $service->decide($admin, $payout, ['decision' => 'approve', 'decision_note' => 'Approved against entitlement.', 'decision_key' => (string) Str::uuid(), 'confirmed' => true]);
        $this->assertSame('59.75', $service->payable($owner->id));
        $this->assertDatabaseHas('owner_payouts', ['id' => $payout->id, 'state' => 'approved', 'amount' => '40.25']);
        $this->actingAs($admin)->get('/finance/movements')->assertInertia(fn ($page) => $page
            ->where('summary.outflow', '40.25')
            ->where('movements.data.0.entry_kind', 'owner_payout')
            ->where('movements.data.0.source_number', $payout->number));
        $this->actingAs($admin)->get('/reports/owners/'.$owner->id.'/statement/print')
            ->assertOk()->assertSee('Owner settlement statement')->assertSee('PKR 59.75')->assertDontSee('Cash payment checked by accountant.');
    }

    public function test_roles_are_separated_between_preparer_and_approver(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $accountant = User::factory()->create()->assignRole('accountant');
        $admin = User::factory()->create()->assignRole('admin');
        $this->actingAs($accountant)->get('/finance/owner-payouts')->assertOk()->assertInertia(fn ($page) => $page->where('canPrepare', true)->where('canApprove', false));
        $this->actingAs($admin)->get('/finance/owner-payouts')->assertOk()->assertInertia(fn ($page) => $page->where('canPrepare', false)->where('canApprove', true));
    }

    public function test_refund_recoverable_balance_offsets_future_entitlements_before_payouts(): void
    {
        $this->seed(RolePermissionSeeder::class);
        [$owner, $account] = $this->entitlement('100.00');
        $date = app(BusinessClock::class)->today();
        $booking = Booking::firstOrFail();
        $service = app(OwnerPayouts::class);
        OwnerEntitlementEvent::create(['plot_owner_id' => $owner->id, 'booking_id' => $booking->id, 'source_kind' => 'customer_refund', 'source_id' => 1000, 'eligible_amount' => '-1200.00', 'percentage' => '10.0000', 'entitlement_amount' => '-120.00', 'business_date' => $date, 'policy_snapshot' => []]);
        $this->assertSame('-20.00', $service->payable($owner->id));

        $accountant = User::factory()->create()->assignRole('accountant');
        $admin = User::factory()->create()->assignRole('admin');
        $this->actingAs($admin)->get('/finance/owner-payouts')->assertOk()->assertInertia(fn ($page) => $page->where('balances.'.$owner->id, '-20.00'));
        $this->get('/reports/owners/'.$owner->id.'/statement/print')->assertOk()->assertSee('Recoverable from owner')->assertSee('PKR 20.00')->assertSee('-PKR 120.00')->assertSee('Future entitlements will offset this balance before payouts become available.');

        $input = ['plot_owner_id' => $owner->id, 'financial_account_id' => $account->id, 'amount' => '5.00', 'business_date' => $date, 'reference' => '', 'verification_note' => 'Attempted payout while refund balance remains recoverable.', 'command_key' => (string) Str::uuid(), 'confirmed' => true];
        $this->actingAs($accountant)->post('/finance/owner-payouts', $input)->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('owner_payouts', 0);

        OwnerEntitlementEvent::create(['plot_owner_id' => $owner->id, 'booking_id' => $booking->id, 'source_kind' => 'booking_allocation', 'source_id' => 1001, 'eligible_amount' => '100.00', 'percentage' => '10.0000', 'entitlement_amount' => '10.00', 'business_date' => $date, 'policy_snapshot' => []]);
        $this->assertSame('-10.00', $service->payable($owner->id));
        OwnerEntitlementEvent::create(['plot_owner_id' => $owner->id, 'booking_id' => $booking->id, 'source_kind' => 'booking_allocation', 'source_id' => 1002, 'eligible_amount' => '150.00', 'percentage' => '10.0000', 'entitlement_amount' => '15.00', 'business_date' => $date, 'policy_snapshot' => []]);
        $this->assertSame('5.00', $service->payable($owner->id));

        $input['command_key'] = (string) Str::uuid();
        $this->post('/finance/owner-payouts', $input)->assertSessionHasNoErrors();
        $payout = OwnerPayout::firstOrFail();
        $service->decide($admin, $payout, ['decision' => 'approve', 'decision_note' => 'Approved after recovery offset from future entitlements.', 'decision_key' => (string) Str::uuid(), 'confirmed' => true]);
        $this->assertSame('0.00', $service->payable($owner->id));
    }

    /** @return array{PlotOwner,FinancialAccount} */
    private function entitlement(string $amount): array
    {
        $actor = User::factory()->create();
        $owner = PlotOwner::create(['name' => 'Settlement Owner', 'admin_user_id' => $actor->id, 'default_percentage' => '10', 'created_by' => $actor->id]);
        $block = Block::forceCreate(['code' => 'PX', 'name' => 'Payout', 'created_by' => $actor->id, 'updated_by' => $actor->id]);
        $size = PlotSize::forceCreate(['label' => 'Payout Size', 'area_marla' => '5', 'created_by' => $actor->id, 'updated_by' => $actor->id]);
        $plot = Plot::forceCreate(['block_id' => $block->id, 'plot_size_id' => $size->id, 'plot_owner_id' => $owner->id, 'owner_percentage' => '10', 'number' => 'PX-1', 'created_by' => $actor->id, 'updated_by' => $actor->id]);
        $customer = Customer::create(['name' => 'Buyer', 'assigned_to' => $actor->id, 'created_by' => $actor->id, 'creation_key' => Str::uuid(), 'creation_hash' => str_repeat('a', 64)]);
        $draft = BookingDraft::create(['customer_id' => $customer->id, 'plot_id' => $plot->id, 'created_by' => $actor->id, 'quoted_price' => '1000', 'version' => 1, 'creation_key' => Str::uuid(), 'creation_hash' => str_repeat('b', 64)]);
        $booking = Booking::create(['booking_draft_id' => $draft->id, 'plot_id' => $plot->id, 'customer_id' => $customer->id, 'confirmed_by' => $actor->id, 'confirmed_on' => '2026-11-09', 'net' => '1000', 'down_payment' => '100', 'snapshot' => ['customer_name' => 'Buyer', 'plot' => ['number' => 'PX-1', 'owner_percentage' => '10', 'owner' => ['id' => $owner->id, 'name' => $owner->name]]], 'private_snapshot' => [], 'command_key' => Str::uuid(), 'payload_hash' => str_repeat('c', 64)]);
        OwnerEntitlementEvent::create(['plot_owner_id' => $owner->id, 'booking_id' => $booking->id, 'source_kind' => 'booking_allocation', 'source_id' => 999, 'eligible_amount' => '1000', 'percentage' => '10', 'entitlement_amount' => $amount, 'business_date' => '2026-11-09', 'policy_snapshot' => []]);
        $account = FinancialAccount::create(['code' => 'CASH-P', 'name' => 'Payout Cash', 'type' => 'cash', 'active' => true, 'version' => 1, 'created_by' => $actor->id, 'creation_key' => Str::uuid(), 'creation_hash' => str_repeat('d', 64)]);

        return [$owner, $account];
    }
}
