<?php

namespace Tests\Feature;

use App\Models\BusinessPolicy;
use App\Models\OpeningBalanceActivation;
use App\Models\OpeningCommissionBalance;
use App\Models\OpeningCustomerBalance;
use App\Models\OpeningOwnerBalance;
use App\Models\PlotOwner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\FinalBookingFixtures;
use Tests\TestCase;

class OpeningBalanceTest extends TestCase
{
    use FinalBookingFixtures, RefreshDatabase;

    public function test_opening_balances_are_gated_on_finance_accounts_manage(): void
    {
        $fixture = $this->fixture();

        $this->actingAs($fixture['agent'])->get('/finance/opening-balances')->assertForbidden();
        $this->post('/finance/opening-balances/customer', [])->assertForbidden();
    }

    public function test_opening_balance_requires_cutover_date(): void
    {
        $fixture = $this->fixture();

        $response = $this->actingAs($fixture['admin'])->post('/finance/opening-balances/customer', [
            'customer_id' => $fixture['customer']->id,
            'amount' => '1000.00',
            'effective_on' => '2026-01-01',
            'source_note' => 'DEMO legacy ledger balance verified',
            'command_key' => (string) Str::uuid(),
            'confirmed' => true,
        ]);

        $response->assertSessionHasErrors('effective_on');
        $this->assertSame(0, OpeningCustomerBalance::count());
    }

    public function test_opening_customer_balance_is_recorded_on_cutover_date(): void
    {
        $fixture = $this->fixture();
        BusinessPolicy::create(['key' => 'cutover', 'values' => ['cutover_date' => '2026-01-01'], 'version' => 1, 'updated_by' => $fixture['admin']->id]);

        $response = $this->actingAs($fixture['admin'])->post('/finance/opening-balances/customer', [
            'customer_id' => $fixture['customer']->id,
            'amount' => '1000.00',
            'effective_on' => '2026-01-01',
            'source_note' => 'DEMO legacy ledger balance verified',
            'command_key' => (string) Str::uuid(),
            'confirmed' => true,
        ]);

        $response->assertRedirect();
        $this->assertSame(1, OpeningCustomerBalance::count());
        $this->assertSame('1000.00', OpeningCustomerBalance::first()->amount);
    }

    public function test_opening_owner_balance_is_recorded_on_cutover_date(): void
    {
        $fixture = $this->fixture();
        BusinessPolicy::create(['key' => 'cutover', 'values' => ['cutover_date' => '2026-01-01'], 'version' => 1, 'updated_by' => $fixture['admin']->id]);
        $owner = PlotOwner::create(['name' => 'DEMO Opening Owner', 'admin_user_id' => $fixture['admin']->id, 'default_percentage' => '20.0000', 'created_by' => $fixture['admin']->id]);

        $response = $this->actingAs($fixture['admin'])->post('/finance/opening-balances/owner', [
            'plot_owner_id' => $owner->id,
            'amount' => '5000.00',
            'effective_on' => '2026-01-01',
            'source_note' => 'DEMO legacy owner ledger balance verified',
            'command_key' => (string) Str::uuid(),
            'confirmed' => true,
        ]);

        $response->assertRedirect();
        $this->assertSame(1, OpeningOwnerBalance::count());
        $this->assertSame('5000.00', OpeningOwnerBalance::first()->amount);
    }

    public function test_opening_commission_balance_is_recorded_on_cutover_date(): void
    {
        $fixture = $this->fixture();
        BusinessPolicy::create(['key' => 'cutover', 'values' => ['cutover_date' => '2026-01-01'], 'version' => 1, 'updated_by' => $fixture['admin']->id]);

        $response = $this->actingAs($fixture['admin'])->post('/finance/opening-balances/commission', [
            'agent_id' => $fixture['agent']->id,
            'amount' => '2500.00',
            'effective_on' => '2026-01-01',
            'source_note' => 'DEMO legacy commission ledger balance verified',
            'command_key' => (string) Str::uuid(),
            'confirmed' => true,
        ]);

        $response->assertRedirect();
        $this->assertSame(1, OpeningCommissionBalance::count());
        $this->assertSame('2500.00', OpeningCommissionBalance::first()->amount);
    }

    public function test_opening_balance_activation_is_recorded(): void
    {
        $fixture = $this->fixture();
        BusinessPolicy::create(['key' => 'cutover', 'values' => ['cutover_date' => '2026-01-01'], 'version' => 1, 'updated_by' => $fixture['admin']->id]);

        $this->actingAs($fixture['admin'])->post('/finance/opening-balances/customer', [
            'customer_id' => $fixture['customer']->id,
            'amount' => '1000.00',
            'effective_on' => '2026-01-01',
            'source_note' => 'DEMO legacy ledger balance verified',
            'command_key' => (string) Str::uuid(),
            'confirmed' => true,
        ]);

        $opening = OpeningCustomerBalance::first();
        $response = $this->actingAs($fixture['admin'])->post("/finance/opening-balances/customer/{$opening->id}/activate", [
            'command_key' => (string) Str::uuid(),
            'confirmed' => true,
        ]);

        $response->assertRedirect();
        $this->assertSame(1, OpeningBalanceActivation::count());
        $this->assertSame('customer', OpeningBalanceActivation::first()->kind);
        $this->assertSame($opening->id, OpeningBalanceActivation::first()->opening_id);
    }

    public function test_opening_balance_activation_is_idempotent(): void
    {
        $fixture = $this->fixture();
        BusinessPolicy::create(['key' => 'cutover', 'values' => ['cutover_date' => '2026-01-01'], 'version' => 1, 'updated_by' => $fixture['admin']->id]);

        $this->actingAs($fixture['admin'])->post('/finance/opening-balances/customer', [
            'customer_id' => $fixture['customer']->id,
            'amount' => '1000.00',
            'effective_on' => '2026-01-01',
            'source_note' => 'DEMO legacy ledger balance verified',
            'command_key' => (string) Str::uuid(),
            'confirmed' => true,
        ]);

        $opening = OpeningCustomerBalance::first();
        $commandKey = (string) Str::uuid();
        $this->actingAs($fixture['admin'])->post("/finance/opening-balances/customer/{$opening->id}/activate", [
            'command_key' => $commandKey,
            'confirmed' => true,
        ]);
        $this->actingAs($fixture['admin'])->post("/finance/opening-balances/customer/{$opening->id}/activate", [
            'command_key' => $commandKey,
            'confirmed' => true,
        ]);

        $this->assertSame(1, OpeningBalanceActivation::count());
    }
}
