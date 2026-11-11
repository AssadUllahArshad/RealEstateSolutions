<?php

namespace Tests\Feature;

use App\Domain\Finance\Accounts;
use App\Domain\Finance\CashbookLedger;
use App\Domain\Finance\FinancialAccountOpenings;
use App\Domain\Finance\OfficeExpenses;
use App\Domain\Inventory\Catalogue;
use App\Models\BankReconciliation;
use App\Models\FinancialAccount;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BankReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $accountant;

    private FinancialAccount $bank;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-11-04T08:00:00Z');
        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->create()->assignRole('super_admin');
        $this->accountant = User::factory()->create()->assignRole('accountant');
        app(Catalogue::class)->society($this->admin, ['name' => 'DEMO Reconciliation Society']);
        $this->bank = app(Accounts::class)->save($this->admin, [
            'code' => 'DEMO-BANK',
            'name' => 'DEMO Operating bank',
            'type' => 'bank',
            'active' => true,
            'creation_key' => (string) Str::uuid(),
        ]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    /** @return array<string,mixed> */
    private function openingPayload(string $amount = '5000.00'): array
    {
        return [
            'amount' => $amount,
            'effective_on' => '2026-11-04',
            'source_note' => 'DEMO verified bank statement opening amount',
            'source_reference' => 'DEMO-STATEMENT-OPEN',
            'command_key' => (string) Str::uuid(),
            'confirmed' => true,
        ];
    }

    /** @return array<string,mixed> */
    private function expensePayload(string $key): array
    {
        return [
            'financial_account_id' => $this->bank->id,
            'category' => 'DEMO bank service',
            'amount' => '1000.00',
            'business_date' => '2026-11-04',
            'payee' => 'DEMO bank',
            'reference' => 'DEMO fee',
            'note' => 'DEMO verified bank service fee',
            'command_key' => $key,
            'confirmed' => true,
        ];
    }

    /** @return array<string,mixed> */
    private function reconciliationPayload(string $key, string $lineAmount = '1000.00'): array
    {
        return [
            'financial_account_id' => $this->bank->id,
            'period_start' => '2026-11-04',
            'period_end' => '2026-11-04',
            'statement_opening_balance' => '5000.00',
            'statement_closing_balance' => '4000.00',
            'note' => 'DEMO statement checked against exact posted bank activity',
            'lines' => [[
                'statement_date' => '2026-11-04',
                'amount' => $lineAmount,
                'direction' => 'outflow',
                'description' => 'DEMO bank service fee',
                'reference' => 'DEMO fee',
                'source_type' => 'office_expense',
                'source_id' => 1,
            ]],
            'command_key' => $key,
            'confirmed' => true,
        ];
    }

    public function test_opening_balance_is_immutable_and_corrections_are_linked_idempotent_entries(): void
    {
        $opening = app(FinancialAccountOpenings::class)->create(
            $this->admin,
            $this->bank->id,
            $this->openingPayload(),
        );
        $payload = [
            'amount' => '250.00',
            'reason' => 'DEMO verified omitted bank opening adjustment',
            'command_key' => (string) Str::uuid(),
            'confirmed' => true,
        ];

        $correction = app(FinancialAccountOpenings::class)->correct($this->admin, $opening->id, $payload);
        app(OfficeExpenses::class)->post($this->accountant, $this->expensePayload((string) Str::uuid()));

        $this->assertSame('250.00', $correction->amount);
        $this->assertSame($correction->id, app(FinancialAccountOpenings::class)->correct($this->admin, $opening->id, $payload)->id);
        $ledger = app(CashbookLedger::class);
        $this->assertSame('5250.00', $ledger->balanceAtStart($this->bank->id, '2026-11-04'));
        $this->assertSame('4250.00', $ledger->balanceAtEnd($this->bank->id, '2026-11-04'));
        $this->assertDatabaseCount('financial_account_opening_corrections', 1);

        $this->expectException(\LogicException::class);
        $opening->update(['amount' => '1.00']);
    }

    public function test_only_bank_openings_may_be_negative(): void
    {
        $overdraft = app(Accounts::class)->save($this->admin, [
            'code' => 'DEMO-OVERDRAFT',
            'name' => 'DEMO Overdraft account',
            'type' => 'bank',
            'active' => true,
            'creation_key' => (string) Str::uuid(),
        ]);
        $cash = app(Accounts::class)->save($this->admin, [
            'code' => 'DEMO-CASH',
            'name' => 'DEMO Cash account',
            'type' => 'cash',
            'active' => true,
            'creation_key' => (string) Str::uuid(),
        ]);

        $this->actingAs($this->admin)
            ->post('/finance/accounts/'.$overdraft->id.'/opening-balance', $this->openingPayload('-1200.00'))
            ->assertRedirect('/finance/accounts/'.$overdraft->id);

        $this->post('/finance/accounts/'.$cash->id.'/opening-balance', $this->openingPayload('-10.00'))
            ->assertSessionHasErrors('amount');

        $this->assertDatabaseCount('financial_account_opening_balances', 1);
    }

    public function test_exact_statement_match_requires_different_admin_and_finalized_period_blocks_postings(): void
    {
        app(FinancialAccountOpenings::class)->create($this->admin, $this->bank->id, $this->openingPayload());
        $expense = app(OfficeExpenses::class)->post($this->accountant, $this->expensePayload((string) Str::uuid()));

        $invalid = $this->reconciliationPayload((string) Str::uuid(), '999.00');
        $invalid['lines'][0]['source_id'] = $expense->id;
        $this->actingAs($this->accountant)
            ->post('/finance/reconciliation', $invalid)
            ->assertSessionHasErrors('lines');
        $this->assertDatabaseCount('bank_reconciliations', 0);

        $maker = User::factory()->create()->assignRole('accountant');
        $maker->assignRole('admin');
        $key = (string) Str::uuid();
        $payload = $this->reconciliationPayload($key);
        $payload['lines'][0]['source_id'] = $expense->id;
        $this->actingAs($maker)->post('/finance/reconciliation', $payload)->assertSessionHasNoErrors();
        $reconciliation = BankReconciliation::query()->sole();

        $this->post('/finance/reconciliation/'.$reconciliation->id.'/decision', [
            'decision' => 'finalize',
            'decision_note' => 'DEMO checked exact statement and book totals',
            'decision_key' => (string) Str::uuid(),
            'confirmed' => true,
        ])->assertSessionHasErrors('decision');
        $this->assertSame('prepared', $reconciliation->fresh()->state);

        $this->actingAs($this->admin)
            ->post('/finance/reconciliation/'.$reconciliation->id.'/decision', [
                'decision' => 'finalize',
                'decision_note' => 'DEMO checked exact statement and book totals',
                'decision_key' => (string) Str::uuid(),
                'confirmed' => true,
            ])
            ->assertRedirect('/finance/reconciliation');
        $this->assertSame('finalized', $reconciliation->fresh()->state);

        $this->actingAs($this->accountant)
            ->post('/finance/expenses', $this->expensePayload((string) Str::uuid()))
            ->assertSessionHasErrors('business_date');
        $this->assertDatabaseCount('office_expenses', 1);
    }

    public function test_prepared_periods_cannot_overlap(): void
    {
        app(FinancialAccountOpenings::class)->create($this->admin, $this->bank->id, $this->openingPayload());
        $key = (string) Str::uuid();
        $payload = $this->reconciliationPayload($key);
        $payload['lines'] = [];
        $payload['statement_closing_balance'] = '5000.00';
        $this->actingAs($this->accountant)->post('/finance/reconciliation', $payload)->assertSessionHasNoErrors();

        $overlap = [...$payload, 'command_key' => (string) Str::uuid()];
        $this->post('/finance/reconciliation', $overlap)->assertSessionHasErrors('conflict');
        $this->assertDatabaseCount('bank_reconciliations', 1);
    }
}
