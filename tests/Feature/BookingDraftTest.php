<?php

namespace Tests\Feature;

use App\Domain\CRM\Customers;
use App\Domain\Inventory\Catalogue;
use App\Domain\Sales\BookingDrafts;
use App\Domain\Sales\BookingPreparation;
use App\Domain\Sales\DraftPurchasers;
use App\Models\BookingDraft;
use App\Models\BookingDraftEvent;
use App\Models\BusinessPolicy;
use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Models\DraftPurchaserEvent;
use App\Models\Plot;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

class BookingDraftTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $agent;

    private User $other;

    private Customer $customer;

    private Plot $plot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->create()->assignRole('super_admin');
        $this->agent = User::factory()->create()->assignRole('sales_agent');
        $this->other = User::factory()->create()->assignRole('sales_agent');
        $this->customer = app(Customers::class)->save($this->agent, ['name' => 'DEMO Draft customer', 'creation_key' => (string) Str::uuid()]);
        $catalogue = app(Catalogue::class);
        $catalogue->society($this->admin, ['name' => 'DEMO Society', 'office_name' => 'DEMO Office']);
        $block = $catalogue->block($this->admin, ['code' => 'A', 'name' => 'DEMO Block']);
        $size = $catalogue->size($this->admin, ['label' => 'DEMO size', 'area_marla' => '5.00']);
        $this->plot = $catalogue->plot($this->admin, ['block_id' => $block->id, 'plot_size_id' => $size->id, 'number' => 'A-0', 'list_price' => '9999999999999999.99']);
    }

    /** @return array<string,mixed> */
    private function payload(): array
    {
        return ['customer_id' => $this->customer->id, 'plot_id' => $this->plot->id, 'quoted_price' => '9999999999999999.98', 'notes' => 'DEMO proposal only', 'creation_key' => (string) Str::uuid()];
    }

    public function test_preparation_is_scoped_read_only_and_never_treats_uploads_as_received_money(): void
    {
        $draft = app(BookingDrafts::class)->save($this->agent, $this->payload());
        $prepare = app(BookingPreparation::class);
        $checks = collect($prepare->forDraft($this->agent, $draft->id)['checks'])->keyBy('key');
        $this->assertSame('action', $checks['identity']['state']);
        $this->assertSame('configuration', $checks['documents']['state']);
        $this->assertSame('configuration', $checks['policy']['state']);
        $this->assertSame('recorded', $checks['plot']['state']);
        $this->assertSame('action', $checks['payment']['state']);
        $this->customer->update(['cnic' => '1234512345671', 'address' => 'DEMO private address']);
        BusinessPolicy::create(['key' => 'booking', 'version' => 1, 'updated_by' => $this->admin->id, 'values' => ['required_documents' => ['signed_booking_form', 'payment_evidence'], 'down_payment_type' => 'fixed', 'down_payment_value' => '100.00', 'held_plot_access' => 'holder_or_admin']]);
        foreach (['signed_booking_form', 'payment_evidence'] as $kind) {
            CustomerDocument::create(['customer_id' => $this->customer->id, 'uploaded_by' => $this->agent->id, 'kind' => $kind, 'path' => 'DEMO/'.$kind.'.pdf', 'mime' => 'application/pdf', 'bytes' => 20]);
        }
        $checks = collect($prepare->forDraft($this->agent, $draft->id)['checks'])->keyBy('key');
        $this->assertSame('recorded', $checks['identity']['state']);
        $this->assertSame('recorded', $checks['documents']['state']);
        $this->assertSame('recorded', $checks['policy']['state']);
        $this->assertSame('action', $checks['payment']['state']);
        $this->actingAs($this->agent)->get('/sales/'.$draft->id)->assertInertia(fn (Assert $page) => $page->has('preparation.checks', 7)->missing('preparation.cnic')->missing('preparation.address'));
        $this->assertDatabaseCount('plot_holds', 0);
        $this->assertDatabaseCount('booking_draft_events', 1);
        $this->actingAs($this->other)->get('/sales/'.$draft->id)->assertNotFound();
    }

    public function test_joint_purchasers_use_exact_shares_and_atomic_separate_history(): void
    {
        $draft = app(BookingDrafts::class)->save($this->agent, $this->payload());
        $this->customer->update(['cnic' => '1234512345671', 'address' => 'DEMO private address']);
        $joint = app(Customers::class)->save($this->agent, ['name' => 'DEMO Joint', 'cnic' => '1234512345672', 'address' => 'DEMO Joint private', 'creation_key' => (string) Str::uuid()]);
        $payload = ['version' => 0, 'purchasers' => [['customer_id' => $this->customer->id, 'share' => '33.3333'], ['customer_id' => $joint->id, 'share' => '66.6667']]];
        $url = '/sales/'.$draft->id.'/purchasers';
        $this->actingAs($this->agent)->patch($url, $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('draft_purchasers', ['customer_id' => $joint->id, 'share' => '66.6667']);
        $this->assertSame(1, $draft->fresh()->purchasers_version);
        $this->assertSame(1, $draft->fresh()->version);
        $this->patch($url, $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('draft_purchaser_events', 1);
        $this->assertDatabaseCount('booking_draft_events', 1);
        $this->assertDatabaseCount('plot_holds', 0);
        $this->get('/sales/'.$draft->id)->assertInertia(fn (Assert $page) => $page->has('purchasers', 2)->where('purchasers.0.share', '33.3333')->where('purchasers.0.identity_complete', true)->missing('purchasers.0.cnic')->where('preparation.checks.4.state', 'recorded')->has('purchaserHistory', 1));
        $this->patch($url, ['version' => 0, 'purchasers' => [['customer_id' => $joint->id, 'share' => '100']]])->assertSessionHasErrors('purchasers_conflict');
        $this->assertDatabaseCount('draft_purchasers', 2);
        $joint->update(['cnic' => $this->customer->cnic]);
        $this->patch($url, [...$payload, 'version' => 1])->assertSessionHasErrors('purchasers');
        $this->get('/sales/'.$draft->id)->assertInertia(fn (Assert $page) => $page->where('preparation.checks.4.state', 'action'));
        $joint->update(['cnic' => '1234512345672']);
        $joint->update(['assigned_to' => $this->other->id]);
        $this->get('/sales/'.$draft->id)->assertInertia(fn (Assert $page) => $page->where('purchasers.1.customer_id', null)->where('purchasers.1.name', 'Customer access changed')->where('purchasers.1.identity_complete', false)->where('preparation.checks.4.state', 'action')->missing('purchaserHistory.0.after'));
        $this->patch($url, [...$payload, 'version' => 1])->assertNotFound();
    }

    public function test_purchaser_validation_scope_and_reassignment_preserve_private_history(): void
    {
        $draft = app(BookingDrafts::class)->save($this->agent, $this->payload());
        $url = '/sales/'.$draft->id.'/purchasers';
        $row = ['customer_id' => $this->customer->id, 'share' => '100'];
        $this->actingAs($this->agent)->patch($url, ['version' => 0, 'purchasers' => [$row]])->assertSessionHasErrors('purchasers');
        $this->customer->update(['cnic' => '1234512345671', 'address' => 'DEMO private address']);
        foreach (['0', '99.9999', '100.0001', '1e2', '100.00000', 100] as $share) {
            $this->patch($url, ['version' => 0, 'purchasers' => [[...$row, 'share' => $share]]])->assertSessionHasErrors();
        }
        $this->patch($url, ['version' => 0, 'purchasers' => [$row, $row]])->assertSessionHasErrors('purchasers.0.customer_id');
        $this->patch($url, ['version' => 0, 'purchasers' => [[...$row, 'is_admin' => true]]])->assertSessionHasErrors();
        $foreign = app(Customers::class)->save($this->other, ['name' => 'DEMO Foreign purchaser', 'cnic' => '1234512345672', 'address' => 'DEMO Private', 'creation_key' => (string) Str::uuid()]);
        $this->patch($url, ['version' => 0, 'purchasers' => [['customer_id' => $foreign->id, 'share' => '100']]])->assertNotFound();
        $this->patch($url, ['version' => 0, 'purchasers' => [$row]])->assertSessionHasNoErrors();
        $this->actingAs($this->other)->patch($url, ['version' => 1, 'purchasers' => [$row]])->assertNotFound();
        $this->customer->update(['assigned_to' => $this->other->id]);
        $this->actingAs($this->agent)->get('/sales/'.$draft->id)->assertNotFound();
        $this->actingAs($this->other)->get('/sales/'.$draft->id)->assertOk();
    }

    public function test_purchaser_audit_failure_rolls_back_replacement_and_version(): void
    {
        $draft = app(BookingDrafts::class)->save($this->agent, $this->payload());
        $this->customer->update(['cnic' => '1234512345671', 'address' => 'DEMO private address']);
        DraftPurchaserEvent::creating(fn () => throw new RuntimeException('DEMO audit unavailable'));
        try {
            app(DraftPurchasers::class)->save($this->agent, $draft->id, ['version' => 0, 'purchasers' => [['customer_id' => $this->customer->id, 'share' => '100']]]);
            $this->fail('Audit failure must abort the transaction.');
        } catch (RuntimeException $exception) {
            $this->assertSame('DEMO audit unavailable', $exception->getMessage());
        } finally {
            DraftPurchaserEvent::flushEventListeners();
        }
        $this->assertDatabaseCount('draft_purchasers', 0);
        $this->assertDatabaseCount('draft_purchaser_events', 0);
        $this->assertSame(0, $draft->fresh()->purchasers_version);
    }

    public function test_exact_quote_snapshot_and_replay_have_no_occupancy_or_payment_side_effects(): void
    {
        $payload = $this->payload();
        $this->actingAs($this->agent)->post('/sales', $payload)->assertSessionHasNoErrors();
        $draft = BookingDraft::firstOrFail();
        $this->assertSame('9999999999999999.98', $draft->quoted_price);
        $this->assertSame('9999999999999999.99', $draft->catalogue_price_snapshot);
        $this->post('/sales', $payload)->assertRedirect('/sales/'.$draft->id);
        $this->assertDatabaseCount('booking_drafts', 1);
        $this->assertDatabaseCount('booking_draft_events', 1);
        $this->assertDatabaseCount('plot_holds', 0);
        $this->post('/sales', [...$payload, 'quoted_price' => '1.00'])->assertSessionHasErrors('conflict');
        $this->get('/sales/'.$draft->id)->assertInertia(fn (Assert $page) => $page->component('sales/edit')->where('draft.quoted_price', '9999999999999999.98')->missing('draft.creation_hash')->missing('draft.creation_key'));
    }

    public function test_current_assignment_scopes_every_read_lookup_and_write(): void
    {
        $draft = app(BookingDrafts::class)->save($this->agent, $this->payload());
        $this->actingAs($this->other)->get('/sales')->assertInertia(fn (Assert $page) => $page->has('drafts.data', 0));
        $this->get('/sales/new')->assertInertia(fn (Assert $page) => $page->has('customers', 0));
        $this->get('/sales/'.$draft->id)->assertNotFound();
        $this->post('/sales', $this->payload())->assertNotFound();
        $update = $this->payload();
        unset($update['creation_key']);
        $this->patch('/sales/'.$draft->id, [...$update, 'version' => 1])->assertNotFound();
        app(Customers::class)->save($this->admin, ['name' => $this->customer->name, 'assigned_to' => $this->other->id], $this->customer->id);
        $this->actingAs($this->agent)->get('/sales/'.$draft->id)->assertNotFound();
        $this->actingAs($this->other)->get('/sales/'.$draft->id)->assertOk();
    }

    public function test_stale_versions_cannot_overwrite_and_catalogue_changes_do_not_reprice(): void
    {
        $payload = $this->payload();
        $draft = app(BookingDrafts::class)->save($this->agent, $payload);
        unset($payload['creation_key']);
        $this->plot->update(['list_price' => '1.00']);
        $this->actingAs($this->agent)->patch('/sales/'.$draft->id, [...$payload, 'quoted_price' => '500.10', 'version' => 1])->assertSessionHasNoErrors();
        $this->patch('/sales/'.$draft->id, [...$payload, 'quoted_price' => '600.10', 'version' => 1])->assertSessionHasErrors('conflict');
        $draft->refresh();
        $this->assertSame(2, $draft->version);
        $this->assertSame('500.10', $draft->quoted_price);
        $this->assertSame('9999999999999999.99', $draft->catalogue_price_snapshot);
        $this->assertDatabaseCount('booking_draft_events', 2);
        $this->patch('/sales/'.$draft->id, [...$payload, 'quoted_price' => '500.1', 'version' => 2])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('booking_draft_events', 2);
    }

    public function test_validation_permissions_and_literal_search(): void
    {
        $this->actingAs($this->agent)->post('/sales', [...$this->payload(), 'quoted_price' => 123.45, 'created_by' => $this->admin->id, 'status' => 'confirmed', 'catalogue_price_snapshot' => '1.00'])->assertSessionHasErrors(['quoted_price', 'created_by', 'status', 'catalogue_price_snapshot']);
        foreach (['0', '-1', '1.001', '1e4', '10000000000000000.00'] as $price) {
            $this->post('/sales', [...$this->payload(), 'quoted_price' => $price])->assertSessionHasErrors('quoted_price');
        }
        app(BookingDrafts::class)->save($this->agent, $this->payload());
        $this->get('/sales?q=0')->assertInertia(fn (Assert $page) => $page->has('drafts.data', 1));
        $this->get('/sales?q=%25')->assertInertia(fn (Assert $page) => $page->has('drafts.data', 0));
        $accountant = User::factory()->create()->assignRole('accountant');
        $this->actingAs($accountant)->get('/sales')->assertOk();
        $this->get('/sales/new')->assertOk();
        $ungranted = User::factory()->create();
        $this->actingAs($ungranted)->get('/sales')->assertForbidden();
    }

    public function test_audit_failure_rolls_back_draft_atomically(): void
    {
        BookingDraftEvent::creating(fn () => throw new RuntimeException('DEMO audit failure'));
        try {
            app(BookingDrafts::class)->save($this->agent, $this->payload());
            $this->fail('Expected audit failure');
        } catch (RuntimeException $exception) {
            $this->assertSame('DEMO audit failure', $exception->getMessage());
            $this->assertDatabaseCount('booking_drafts', 0);
        } finally {
            BookingDraftEvent::flushEventListeners();
        }
    }
}
