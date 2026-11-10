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

class OfficeExpenseTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string,mixed> */
    private function fixture(): array
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create()->assignRole('super_admin');
        $accountant = User::factory()->create()->assignRole('accountant');
        $agent = User::factory()->create()->assignRole('sales_agent');
        app(Catalogue::class)->society($admin, ['name' => 'DEMO Expense Society']);
        $account = app(Accounts::class)->save($admin, ['code' => 'OFFICE-CASH', 'name' => 'DEMO Office Cash', 'type' => 'cash', 'active' => true, 'creation_key' => (string) Str::uuid()]);

        return compact('admin', 'accountant', 'agent', 'account');
    }

    /** @return array<string,mixed> */
    private function payload(int $account, string $key): array
    {
        return ['financial_account_id' => $account, 'category' => 'DEMO utilities', 'amount' => '1250.50',
            'business_date' => app(BusinessClock::class)->today(), 'payee' => 'DEMO Power supplier',
            'reference' => 'DEMO invoice 42', 'note' => 'DEMO verified office electricity expense.',
            'command_key' => $key, 'confirmed' => true];
    }

    public function test_accountant_posts_idempotent_immutable_expense_and_sees_register(): void
    {
        $f = $this->fixture();
        $key = (string) Str::uuid();
        $expense = app(OfficeExpenses::class)->post($f['accountant'], $this->payload($f['account']->id, $key));
        $this->assertSame('EXP-000001', $expense->number);
        $this->assertSame('1250.50', $expense->amount);
        $this->assertSame('OFFICE-CASH', $expense->account_snapshot['code']);
        $this->assertSame($expense->id, app(OfficeExpenses::class)->post($f['accountant'], $this->payload($f['account']->id, $key))->id);
        $this->assertDatabaseCount('office_expenses', 1);
        $this->actingAs($f['accountant'])->get('/finance/expenses')->assertInertia(fn (Assert $page) => $page
            ->component('finance/expenses')->has('expenses.data', 1)->where('total', '1250.50')->where('canPost', true));
        $this->get('/finance/movements')->assertInertia(fn (Assert $page) => $page
            ->component('finance/movements')->has('movements.data', 1)
            ->where('movements.data.0.entry_kind', 'office_expense')->where('movements.data.0.direction', 'outflow')
            ->where('summary.inflow', '0.00')->where('summary.outflow', '1250.50')->where('summary.net', '-1250.50'));
        $this->get('/finance/movements?from=2099-01-01')->assertInertia(fn (Assert $page) => $page
            ->has('movements.data', 0)->where('summary.outflow', '0.00')->where('summary.net', '0.00'));
        $export = $this->get('/finance/movements/export')->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertDownload('estateflow-cashbook.csv');
        $csv = $export->streamedContent();
        $this->assertStringContainsString('EXP-000001', $csv);
        $this->assertStringContainsString('office_expense,Outflow,1250.50', $csv);
        $filtered = $this->get('/finance/movements/export?from=2099-01-01')->assertOk()->streamedContent();
        $this->assertStringNotContainsString('EXP-000001', $filtered);
        $voucher = $this->withUnencryptedCookie('locale', 'ur')->get('/finance/expenses/'.$expense->id.'/print')->assertOk()
            ->assertSee('lang="ur"', false)->assertSee('dir="rtl"', false)
            ->assertSee('Data Real Estate')->assertSee('Green Vista Housing Scheme')
            ->assertSee('EXP-000001')->assertSee('PKR 1,250.50')->assertSee('DEMO utilities')
            ->assertSee('DEMO Power supplier')->assertSee('DEMO invoice 42')->assertSee('DEMO verified office electricity expense.');
        $this->assertStringContainsString('no-store', $voucher->headers->get('Cache-Control'));
        $this->expectException(\LogicException::class);
        $expense->update(['amount' => '1.00']);
    }

    public function test_role_date_confirmation_and_command_conflict_are_enforced(): void
    {
        $f = $this->fixture();
        $key = (string) Str::uuid();
        $this->actingAs($f['agent'])->get('/finance/expenses')->assertForbidden();
        $this->get('/finance/movements/export')->assertForbidden();
        $this->get('/finance/expenses/1/print')->assertForbidden();
        $this->post('/finance/expenses', $this->payload($f['account']->id, $key))->assertForbidden();
        $this->actingAs($f['accountant'])->post('/finance/expenses', [...$this->payload($f['account']->id, $key), 'confirmed' => false])->assertSessionHasErrors('confirmed');
        $this->post('/finance/expenses', [...$this->payload($f['account']->id, $key), 'business_date' => '2020-01-01'])->assertSessionHasErrors('business_date');
        $this->post('/finance/expenses', $this->payload($f['account']->id, $key))->assertSessionHasNoErrors();
        $this->post('/finance/expenses', [...$this->payload($f['account']->id, $key), 'amount' => '1251.00'])->assertSessionHasErrors('conflict');
        $this->assertDatabaseCount('office_expenses', 1);
    }

    public function test_admin_can_post_and_inactive_account_is_rejected(): void
    {
        $f = $this->fixture();
        $f['account']->forceFill(['active' => false])->save();
        $this->actingAs($f['admin'])->post('/finance/expenses', $this->payload($f['account']->id, (string) Str::uuid()))->assertSessionHasErrors('financial_account_id');
        $this->assertDatabaseCount('office_expenses', 0);
    }
}
