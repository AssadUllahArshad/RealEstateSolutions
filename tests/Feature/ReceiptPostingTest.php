<?php

namespace Tests\Feature;

use App\Domain\CRM\Customers;
use App\Domain\Finance\Accounts;
use App\Domain\Finance\ReceiptDrafts;
use App\Domain\Finance\ReceiptPosting;
use App\Domain\Inventory\Catalogue;
use App\Models\AccountMovement;
use App\Models\BusinessPolicy;
use App\Models\PaymentReceiptEvent;
use App\Models\ReceiptDraft;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class ReceiptPostingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $accountant;

    private User $agent;

    private ReceiptDraft $draft;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-11-04T08:00:00Z');
        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->create()->assignRole('super_admin');
        $this->accountant = User::factory()->create()->assignRole('accountant');
        $this->agent = User::factory()->create()->assignRole('sales_agent');
        app(Catalogue::class)->society($this->admin, ['name' => 'DEMO Posting Society']);
        $customer = app(Customers::class)->save($this->agent, ['name' => 'DEMO Receipt payer', 'creation_key' => (string) Str::uuid()]);
        $account = app(Accounts::class)->save($this->admin, ['code' => 'POST-BANK', 'name' => 'DEMO Posting bank', 'type' => 'bank', 'active' => true, 'creation_key' => (string) Str::uuid()]);
        BusinessPolicy::create(['key' => 'collections', 'version' => 1, 'updated_by' => $this->admin->id, 'values' => ['methods' => ['cash', 'bank_transfer', 'cheque', 'mobile_wallet', 'other'], 'allocation' => 'oldest_first', 'cheque_recognition' => 'cleared', 'overpayment' => 'unallocated']]);
        BusinessPolicy::create(['key' => 'receipt_posting', 'version' => 1, 'updated_by' => $this->admin->id, 'values' => $this->postingValues()]);
        $this->draft = app(ReceiptDrafts::class)->save($this->agent, ['customer_id' => $customer->id, 'financial_account_id' => $account->id, 'amount' => '9999999999999999.99', 'reported_on' => '2026-11-03', 'method' => 'bank_transfer', 'reference' => 'DEMO PRIVATE EXTERNAL REF', 'policy_version' => 1, 'creation_key' => (string) Str::uuid()]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    /** @return array<string,mixed> */
    private function postingValues(): array
    {
        return ['prefix' => 'DEMO', 'starting_number' => 100, 'padding' => 6, 'open_from' => '2026-11-01', 'max_backdate_days' => 3, 'unallocated' => 'allow', 'routing' => 'matching', 'duplicates' => 'global_method'];
    }

    /** @return array<string,mixed> */
    private function command(): array
    {
        return ['command_key' => (string) Str::uuid(), 'expected_version' => 1, 'posting_version' => 1, 'collection_version' => 1, 'business_date' => '2026-11-04', 'note' => 'DEMO checked source bank evidence and exact amount', 'confirmed' => true, 'stage' => 'confirmed'];
    }

    /** @return array<string,mixed> */
    private function change(int $version): array
    {
        return ['command_key' => (string) Str::uuid(), 'expected_version' => $version, 'posting_version' => 1, 'business_date' => '2026-11-04', 'note' => 'DEMO verified bank evidence or accounting correction', 'confirmed' => true];
    }

    public function test_posting_is_exact_atomic_idempotent_and_freezes_preparation(): void
    {
        $command = $this->command();
        $receipt = app(ReceiptPosting::class)->accept($this->accountant, $this->draft->id, $command);
        $this->assertSame('DEMO-000100', $receipt->number);
        $this->assertSame('9999999999999999.99', $receipt->amount);
        $this->assertSame('recognized', $receipt->state);
        $this->assertSame($receipt->amount, AccountMovement::firstOrFail()->amount);
        $this->assertSame(101, DB::table('receipt_series')->value('next_number'));
        $this->assertNotSame($receipt->reference, DB::table('payment_receipts')->value('reference'));
        $this->assertArrayNotHasKey('account_reference_hash', $receipt->toArray());
        $this->assertSame($receipt->id, app(ReceiptPosting::class)->accept($this->accountant, $this->draft->id, $command)->id);
        $this->assertDatabaseCount('account_movements', 1);
        $this->assertDatabaseCount('payment_receipt_events', 1);
        $this->actingAs($this->accountant)->post('/finance/receipts/'.$this->draft->id.'/issue', [...$command, 'note' => 'DEMO altered duplicate command'])->assertSessionHasErrors('conflict');
        $this->actingAs($this->agent)->patch('/finance/receipts/'.$this->draft->id, ['customer_id' => $this->draft->customer_id, 'financial_account_id' => $this->draft->financial_account_id, 'amount' => '1', 'reported_on' => '2026-11-03', 'method' => 'bank_transfer', 'reference' => 'DEMO PRIVATE EXTERNAL REF', 'policy_version' => 1, 'version' => 1])->assertSessionHasErrors('conflict');
        $this->get('/finance/receipts/'.$this->draft->id)->assertRedirect('/finance/posted/'.$receipt->id);
        $this->get('/finance/posted/'.$receipt->id)->assertInertia(fn (Assert $page) => $page->component('finance/posted-receipt')->where('receipt.amount', '9999999999999999.99')->where('posting.canPost', false)->missing('receipt.verification_note')->where('history.0.note', null));
        $this->actingAs($this->accountant)->get('/finance/movements')->assertInertia(fn (Assert $page) => $page
            ->component('finance/movements')->where('movements.data.0.entry_kind', 'receipt_credit')
            ->where('movements.data.0.direction', 'inflow')->where('summary.inflow', '9999999999999999.99')
            ->where('summary.outflow', '0.00')->where('summary.net', '9999999999999999.99'));
        try {
            AccountMovement::firstOrFail()->update(['amount' => '1']);
            $this->fail('Movements must be immutable.');
        } catch (LogicException) {
            $this->assertSame('9999999999999999.99', AccountMovement::firstOrFail()->amount);
        }
    }

    public function test_cheque_clearance_recognizes_once_and_admin_reversal_offsets_it(): void
    {
        $this->draft->update(['method' => 'cheque']);
        $receipt = app(ReceiptPosting::class)->accept($this->accountant, $this->draft->id, [...$this->command(), 'stage' => 'deposited']);
        $this->assertSame('pending_clearance', $receipt->state);
        $this->assertDatabaseCount('account_movements', 0);
        $clear = $this->change(1);
        $receipt = app(ReceiptPosting::class)->advance($this->accountant, $receipt->id, 'clear', $clear);
        $this->assertSame('recognized', $receipt->state);
        $this->assertSame('cleared', $receipt->bank_stage);
        app(ReceiptPosting::class)->advance($this->accountant, $receipt->id, 'clear', $clear);
        $this->assertDatabaseCount('account_movements', 1);
        $this->accountant->givePermissionTo('finance.receipts.reverse');
        $this->actingAs($this->accountant)->post('/finance/posted/'.$receipt->id.'/reverse', $this->change(2))->assertForbidden();
        $reverse = $this->change(2);
        $reversed = app(ReceiptPosting::class)->advance($this->admin, $receipt->id, 'reverse', $reverse);
        $this->assertSame('reversed', $reversed->state);
        app(ReceiptPosting::class)->advance($this->admin, $receipt->id, 'reverse', $reverse);
        $this->assertDatabaseCount('account_movements', 2);
        $this->assertSame('0.00', bcsub(AccountMovement::where('kind', 'receipt_credit')->firstOrFail()->amount, AccountMovement::where('kind', 'receipt_reversal')->firstOrFail()->amount, 2));
        $this->assertSame(3, PaymentReceiptEvent::count());
    }

    public function test_deposit_recognition_and_later_clearance_never_double_credit(): void
    {
        $policy = BusinessPolicy::findOrFail('collections');
        $policy->update(['values' => [...$policy->values, 'cheque_recognition' => 'deposited']]);
        $this->draft->update(['method' => 'cheque']);
        $receipt = app(ReceiptPosting::class)->accept($this->accountant, $this->draft->id, [...$this->command(), 'stage' => 'deposited']);
        $this->assertSame('recognized', $receipt->state);
        $this->assertNull($receipt->cleared_on);
        app(ReceiptPosting::class)->advance($this->accountant, $receipt->id, 'clear', $this->change(1));
        $this->assertDatabaseCount('account_movements', 1);
        $this->assertSame('2026-11-04', $receipt->fresh()->cleared_on);
    }

    public function test_missing_policy_dates_roles_and_routing_block_posting(): void
    {
        $url = '/finance/receipts/'.$this->draft->id.'/issue';
        $this->agent->givePermissionTo('finance.receipts.post');
        $this->actingAs($this->agent)->post($url, $this->command())->assertForbidden();
        $this->actingAs($this->accountant)->post($url, [...$this->command(), 'business_date' => '2026-11-05'])->assertSessionHasErrors('business_date');
        $this->post($url, [...$this->command(), 'business_date' => '2026-10-31'])->assertSessionHasErrors('business_date');
        $this->post($url, [...$this->command(), 'business_date' => '2026-11-02'])->assertSessionHasErrors('business_date');
        $this->post($url, [...$this->command(), 'amount' => '1', 'confirmed' => false])->assertSessionHasErrors(['amount', 'confirmed']);
        BusinessPolicy::whereKey('receipt_posting')->update(['values' => [...$this->postingValues(), 'unallocated' => 'block']]);
        $this->post($url, $this->command())->assertSessionHasErrors('posting');
        BusinessPolicy::whereKey('receipt_posting')->update(['values' => [...$this->postingValues(), 'open_from' => null]]);
        $this->post($url, $this->command())->assertSessionHasErrors('posting');
        BusinessPolicy::whereKey('receipt_posting')->update(['values' => $this->postingValues()]);
        $this->draft->update(['method' => 'cash']);
        $this->post($url, $this->command())->assertSessionHasErrors('posting');
        $this->assertDatabaseCount('payment_receipts', 0);
        $this->assertDatabaseCount('receipt_series', 0);
    }

    public function test_duplicate_reference_keys_survive_key_rotation_and_series_cannot_reset(): void
    {
        $receipt = app(ReceiptPosting::class)->accept($this->accountant, $this->draft->id, $this->command());
        $copy = app(ReceiptDrafts::class)->save($this->agent, ['customer_id' => $this->draft->customer_id, 'financial_account_id' => $this->draft->financial_account_id, 'amount' => '100', 'reported_on' => '2026-11-03', 'method' => 'bank_transfer', 'reference' => 'DEMO PRIVATE EXTERNAL REF', 'policy_version' => 1, 'creation_key' => (string) Str::uuid()]);
        $previous = config('app.key');
        config(['app.previous_keys' => [$previous], 'app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->actingAs($this->accountant)->post('/finance/receipts/'.$copy->id.'/issue', $this->command())->assertSessionHasErrors('posting');
        $this->actingAs($this->admin)->patch('/business-settings/receipt_posting', ['version' => 1, 'values' => [...$this->postingValues(), 'starting_number' => 200]])->assertSessionHasErrors('values.starting_number');
        $this->assertDatabaseCount('payment_receipts', 1);
        $this->assertSame('DEMO-000100', $receipt->number);
    }

    public function test_audit_failure_rolls_back_number_credit_receipt_and_command(): void
    {
        PaymentReceiptEvent::creating(fn () => throw new RuntimeException('DEMO posting audit failed'));
        try {
            app(ReceiptPosting::class)->accept($this->accountant, $this->draft->id, $this->command());
            $this->fail('Expected posting audit failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('DEMO posting audit failed', $exception->getMessage());
        } finally {
            PaymentReceiptEvent::flushEventListeners();
        }
        foreach (['payment_receipts', 'receipt_series', 'account_movements', 'receipt_posting_commands'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertSame(1, $this->draft->fresh()->version);
    }
}
