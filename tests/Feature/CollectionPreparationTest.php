<?php

namespace Tests\Feature;

use App\Domain\CRM\Customers;
use App\Domain\Finance\Accounts;
use App\Domain\Finance\ReceiptDrafts;
use App\Domain\Inventory\Catalogue;
use App\Domain\Sales\BookingDrafts;
use App\Models\BusinessPolicy;
use App\Models\Customer;
use App\Models\FinancialAccount;
use App\Models\FinancialAccountEvent;
use App\Models\ReceiptDraft;
use App\Models\ReceiptDraftEvent;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

class CollectionPreparationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $agent;

    private User $other;

    private User $accountant;

    private Customer $customer;

    private FinancialAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->create()->assignRole('super_admin');
        $this->agent = User::factory()->create()->assignRole('sales_agent');
        $this->other = User::factory()->create()->assignRole('sales_agent');
        $this->accountant = User::factory()->create()->assignRole('accountant');
        app(Catalogue::class)->society($this->admin, ['name' => 'DEMO Collection Society']);
        $this->customer = app(Customers::class)->save($this->agent, ['name' => 'DEMO Collection 0', 'cnic' => '1234512345671', 'address' => 'DEMO private address', 'creation_key' => (string) Str::uuid()]);
        $this->account = app(Accounts::class)->save($this->admin, ['code' => 'BANK-1', 'name' => 'DEMO Office bank', 'type' => 'bank', 'active' => true, 'creation_key' => (string) Str::uuid()]);
        BusinessPolicy::create(['key' => 'collections', 'version' => 1, 'values' => ['methods' => ['cash', 'bank_transfer', 'cheque', 'mobile_wallet', 'other'], 'allocation' => null, 'cheque_recognition' => null, 'overpayment' => null], 'updated_by' => $this->admin->id]);
    }

    /** @return array<string,mixed> */
    private function payload(): array
    {
        return ['customer_id' => $this->customer->id, 'financial_account_id' => $this->account->id, 'amount' => '9999999999999999.99', 'reported_on' => '2026-11-04', 'method' => 'bank_transfer', 'reference' => 'DEMO PRIVATE BANK REF', 'notes' => 'DEMO private verification note', 'policy_version' => 1, 'creation_key' => (string) Str::uuid()];
    }

    public function test_exact_preparation_replay_privacy_and_changed_payload_conflict(): void
    {
        $payload = $this->payload();
        $this->actingAs($this->agent)->post('/finance/receipts', $payload)->assertSessionHasNoErrors();
        $receipt = ReceiptDraft::firstOrFail();
        $this->assertSame('9999999999999999.99', $receipt->amount);
        $this->assertSame('DEMO PRIVATE BANK REF', $receipt->reference);
        $this->assertNotSame($receipt->reference, DB::table('receipt_drafts')->value('reference'));
        $this->assertNotSame($receipt->notes, DB::table('receipt_drafts')->value('notes'));
        $this->assertArrayNotHasKey('reference', $receipt->toArray());
        $this->assertStringNotContainsString('DEMO PRIVATE', json_encode(ReceiptDraftEvent::firstOrFail()->details));
        $this->post('/finance/receipts', $payload)->assertRedirect('/finance/receipts/'.$receipt->id);
        $this->assertDatabaseCount('receipt_drafts', 1);
        $this->assertDatabaseCount('receipt_draft_events', 1);
        $this->post('/finance/receipts', [...$payload, 'amount' => '1.00'])->assertSessionHasErrors('conflict');
        $this->get('/finance/receipts')->assertInertia(fn (Assert $page) => $page->has('receipts.data', 1)->missing('receipts.data.0.reference')->missing('receipts.data.0.notes')->missing('receipts.data.0.customer.cnic')->missing('receipts.data.0.creation_hash'));
        $this->get('/finance/receipts/'.$receipt->id)->assertInertia(fn (Assert $page) => $page->component('finance/receipt-edit')->where('privateDetails.reference', 'DEMO PRIVATE BANK REF')->has('postingMissing', 3));
        $this->assertDatabaseCount('plot_holds', 0);
    }

    public function test_assignment_scope_and_accountant_shared_office_access(): void
    {
        $receipt = app(ReceiptDrafts::class)->save($this->agent, $this->payload());
        $url = '/finance/receipts/'.$receipt->id;
        $this->actingAs($this->other)->get($url)->assertNotFound();
        $this->other->givePermissionTo('finance.receipts.view_all');
        $this->get($url)->assertNotFound();
        $this->post('/finance/receipts', $this->payload())->assertNotFound();
        $this->get('/finance/receipts')->assertInertia(fn (Assert $page) => $page->has('receipts.data', 0));
        $this->actingAs($this->accountant)->get($url)->assertOk();
        $this->get('/finance/receipts/new')->assertInertia(fn (Assert $page) => $page->has('customers', 1)->missing('customers.0.cnic')->missing('customers.0.address')->missing('customers.0.email')->missing('customers.0.phone'));
        $this->get('/customers/'.$this->customer->id)->assertInertia(fn (Assert $page) => $page
            ->component('customers/show')
            ->where('customer.id', $this->customer->id)
            ->missing('customer.cnic')
            ->missing('customer.address'));
        $this->get('/finance/accounts')->assertOk();
        $this->post('/finance/accounts', ['code' => 'DENIED', 'name' => 'DEMO', 'type' => 'cash', 'active' => true, 'creation_key' => (string) Str::uuid()])->assertForbidden();
        $this->customer->update(['assigned_to' => $this->other->id]);
        $this->actingAs($this->agent)->get($url)->assertNotFound();
        $this->actingAs($this->other)->get($url)->assertOk();
        $this->actingAs($this->agent)->get('/finance/accounts')->assertForbidden();
    }

    public function test_edits_versions_noop_and_policy_account_history_are_safe(): void
    {
        $payload = $this->payload();
        $receipt = app(ReceiptDrafts::class)->save($this->agent, $payload);
        unset($payload['creation_key']);
        $url = '/finance/receipts/'.$receipt->id;
        $this->actingAs($this->agent)->patch($url, [...$payload, 'version' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('receipt_draft_events', 1);
        $this->patch($url, [...$payload, 'version' => 1, 'amount' => '9999999999999999.98'])->assertSessionHasNoErrors();
        $this->assertSame('9999999999999999.98', $receipt->fresh()->amount);
        $this->patch($url, [...$payload, 'version' => 1, 'amount' => '100'])->assertSessionHasErrors('conflict');
        $this->assertDatabaseCount('receipt_draft_events', 2);
        app(Accounts::class)->save($this->admin, ['code' => 'BANK-1', 'name' => 'DEMO Renamed bank', 'type' => 'bank', 'active' => true, 'version' => 1, 'reason' => 'DEMO rename'], $this->account->id);
        $this->patch($url, [...$payload, 'version' => 2, 'amount' => '100'])->assertSessionHasNoErrors();
        $this->assertSame('DEMO Office bank', $receipt->fresh()->account_snapshot['name']);
        BusinessPolicy::whereKey('collections')->update(['version' => 2]);
        $this->patch($url, [...$payload, 'version' => 3])->assertSessionHasErrors('conflict');
        $this->get($url)->assertInertia(fn (Assert $page) => $page->where('policyChanged', true));
    }

    public function test_disabled_methods_accounts_and_counterfeit_posting_are_rejected(): void
    {
        $payload = $this->payload();
        $this->actingAs($this->agent)->post('/finance/receipts', [...$payload, 'status' => 'cleared', 'recognized_at' => '2026-11-04', 'receipt_number' => 'FAKE', 'amount' => 100.25])->assertSessionHasErrors(['status', 'recognized_at', 'receipt_number', 'amount']);
        $this->post('/finance/receipts', [...$payload, 'reference' => ' '])->assertSessionHasErrors('reference');
        foreach (['0', '-1', '1e3', '1.001', '10000000000000000.00'] as $amount) {
            $this->post('/finance/receipts', [...$payload, 'amount' => $amount])->assertSessionHasErrors('amount');
        }
        $this->account->update(['active' => false]);
        $this->post('/finance/receipts', $payload)->assertSessionHasErrors('financial_account_id');
        $this->account->update(['active' => true]);
        BusinessPolicy::whereKey('collections')->update(['values' => ['methods' => ['cash']]]);
        $this->post('/finance/receipts', $payload)->assertSessionHasErrors('method');
        $this->post('/finance/receipts', [...$payload, 'method' => 'cash', 'reference' => ''])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('receipt_drafts', 1);
    }

    public function test_account_codes_type_versions_replay_and_history(): void
    {
        $payload = ['code' => 'cash-1', 'name' => 'DEMO Cash', 'type' => 'cash', 'active' => true, 'creation_key' => (string) Str::uuid()];
        $this->actingAs($this->admin)->post('/finance/accounts', $payload)->assertSessionHasNoErrors();
        $account = FinancialAccount::where('code', 'CASH-1')->firstOrFail();
        $this->post('/finance/accounts', $payload)->assertRedirect('/finance/accounts/'.$account->id);
        $this->assertDatabaseCount('financial_accounts', 2);
        $this->post('/finance/accounts', [...$payload, 'name' => 'DEMO Changed'])->assertSessionHasErrors('conflict');
        $this->post('/finance/accounts', [...$payload, 'creation_key' => (string) Str::uuid()])->assertSessionHasErrors('code');
        unset($payload['creation_key']);
        $url = '/finance/accounts/'.$account->id;
        $this->patch($url, [...$payload, 'version' => 1, 'reason' => 'DEMO invalid type change', 'type' => 'bank'])->assertSessionHasErrors('code');
        $this->patch($url, [...$payload, 'version' => 1, 'reason' => 'DEMO retire', 'active' => false])->assertSessionHasNoErrors();
        $this->patch($url, [...$payload, 'version' => 1, 'reason' => 'DEMO stale'])->assertSessionHasErrors('conflict');
        $this->assertFalse($account->fresh()->active);
        $this->assertSame(2, $account->fresh()->version);
        $this->assertDatabaseCount('financial_account_events', 3);
        $this->agent->givePermissionTo('finance.accounts.manage');
        $this->actingAs($this->agent)->post('/finance/accounts', [...$payload, 'creation_key' => (string) Str::uuid()])->assertForbidden();
    }

    public function test_audit_failures_roll_back_accounts_and_receipts(): void
    {
        ReceiptDraftEvent::creating(fn () => throw new RuntimeException('DEMO receipt audit unavailable'));
        try {
            app(ReceiptDrafts::class)->save($this->agent, $this->payload());
            $this->fail('Expected receipt audit failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('DEMO receipt audit unavailable', $exception->getMessage());
        } finally {
            ReceiptDraftEvent::flushEventListeners();
        }
        $this->assertDatabaseCount('receipt_drafts', 0);
        FinancialAccountEvent::creating(fn () => throw new RuntimeException('DEMO account audit unavailable'));
        try {
            app(Accounts::class)->save($this->admin, ['code' => 'FAIL', 'name' => 'DEMO Failed', 'type' => 'cash', 'active' => true, 'creation_key' => (string) Str::uuid()]);
            $this->fail('Expected account audit failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('DEMO account audit unavailable', $exception->getMessage());
        } finally {
            FinancialAccountEvent::flushEventListeners();
        }
        $this->assertDatabaseCount('financial_accounts', 1);
    }

    public function test_optional_booking_link_is_customer_bound_and_key_rotation_preserves_replay(): void
    {
        $catalogue = app(Catalogue::class);
        $block = $catalogue->block($this->admin, ['code' => 'FIN', 'name' => 'DEMO Finance Block']);
        $size = $catalogue->size($this->admin, ['label' => 'DEMO Finance Size', 'area_marla' => '5']);
        $plot = $catalogue->plot($this->admin, ['block_id' => $block->id, 'plot_size_id' => $size->id, 'number' => 'FIN-1']);
        $foreign = app(Customers::class)->save($this->other, ['name' => 'DEMO Foreign payment customer', 'creation_key' => (string) Str::uuid()]);
        $draft = app(BookingDrafts::class)->save($this->other, ['customer_id' => $foreign->id, 'plot_id' => $plot->id, 'quoted_price' => '1000.00', 'creation_key' => (string) Str::uuid()]);
        $this->actingAs($this->agent)->post('/finance/receipts', [...$this->payload(), 'booking_draft_id' => $draft->id])->assertNotFound();
        $payload = $this->payload();
        $receipt = app(ReceiptDrafts::class)->save($this->agent, $payload);
        $previous = config('app.key');
        config(['app.previous_keys' => [$previous], 'app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->assertSame($receipt->id, app(ReceiptDrafts::class)->save($this->agent, $payload)->id);
        $this->assertDatabaseCount('receipt_drafts', 1);
        $this->assertDatabaseCount('receipt_draft_events', 1);
    }
}
