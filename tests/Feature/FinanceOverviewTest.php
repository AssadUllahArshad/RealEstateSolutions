<?php

namespace Tests\Feature;

use App\Domain\Finance\Accounts;
use App\Domain\Finance\OfficeExpenses;
use App\Domain\Inventory\Catalogue;
use App\Models\User;
use App\Support\BusinessClock;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FinanceOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_accountant_sees_exact_period_movement_without_implied_balance(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create()->assignRole('super_admin');
        $accountant = User::factory()->create()->assignRole('accountant');
        app(Catalogue::class)->society($admin, ['name' => 'DEMO Finance Society']);
        $account = app(Accounts::class)->save($admin, [
            'code' => 'DEMO-CASH', 'name' => 'DEMO Office Cash', 'type' => 'cash',
            'active' => true, 'creation_key' => (string) Str::uuid(),
        ]);
        app(OfficeExpenses::class)->post($accountant, [
            'financial_account_id' => $account->id, 'category' => 'DEMO utilities',
            'amount' => '9999999999999999.99', 'business_date' => app(BusinessClock::class)->today(),
            'payee' => 'DEMO supplier', 'reference' => null, 'note' => 'DEMO verified expense.',
            'command_key' => (string) Str::uuid(), 'confirmed' => true,
        ]);

        $this->actingAs($accountant)->get('/finance')->assertInertia(fn (Assert $page) => $page
            ->component('finance/overview')
            ->where('summary.inflow', '0.00')
            ->where('summary.expenses', '9999999999999999.99')
            ->where('summary.outflow', '9999999999999999.99')
            ->where('summary.net', '-9999999999999999.99')
            ->where('accounts.0.net_movement', '-9999999999999999.99')
            ->where('workflow.prepared', 0)
            ->where('workflow.pendingClearance', 0));

        $this->get('/finance?from=2099-01-01&to=2099-01-31')->assertInertia(fn (Assert $page) => $page
            ->where('summary.expenses', '0.00')
            ->where('summary.net', '0.00')
            ->where('accounts.0.net_movement', '0.00'));
    }

    public function test_sales_agent_cannot_open_wide_finance_overview_and_invalid_period_is_rejected(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $agent = User::factory()->create()->assignRole('sales_agent');
        $accountant = User::factory()->create()->assignRole('accountant');

        $this->actingAs($agent)->get('/finance')->assertForbidden();
        $this->actingAs($accountant)->get('/finance?from=2026-11-08&to=2026-11-01')->assertSessionHasErrors('to');
    }
}
