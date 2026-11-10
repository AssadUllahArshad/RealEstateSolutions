<?php

namespace Tests\Feature;

use App\Domain\CRM\Customers;
use App\Models\Customer;
use App\Models\CustomerActivity;
use App\Models\CustomerDocument;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

class CustomerIdentityTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    private User $other;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('customer_documents');
        $this->agent = User::factory()->create()->assignRole('sales_agent');
        $this->other = User::factory()->create()->assignRole('sales_agent');
        $this->customer = app(Customers::class)->save($this->agent, ['name' => 'DEMO Identity', 'creation_key' => (string) Str::uuid()]);
    }

    public function test_identity_is_encrypted_normalized_scoped_and_absent_from_directory_search_and_audit(): void
    {
        $this->actingAs($this->agent)->patch('/customers/'.$this->customer->id, ['name' => $this->customer->name, 'cnic' => '35202-1234567-1', 'address' => 'DEMO private postal address'])->assertSessionHasNoErrors();
        $raw = DB::table('customers')->where('id', $this->customer->id)->first();
        $this->assertNotSame('3520212345671', $raw->cnic);
        $this->assertNotSame('DEMO private postal address', $raw->address);
        $this->assertSame('3520212345671', $this->customer->fresh()->cnic);
        $this->get('/customers/'.$this->customer->id)->assertInertia(fn (Assert $page) => $page->where('identity.cnic', '3520212345671')->where('identity.address', 'DEMO private postal address')->missing('customer.cnic')->missing('customer.address'));
        $this->get('/customers/'.$this->customer->id)->assertViewHas('page.encryptHistory', true);
        $this->get('/customers')->assertInertia(fn (Assert $page) => $page->missing('customers.data.0.cnic')->missing('customers.data.0.address'));
        $this->get('/search?q=3520212345671')->assertInertia(fn (Assert $page) => $page->has('customers', 0));
        $this->assertStringNotContainsString('3520212345671', CustomerActivity::all()->toJson());
        $this->assertStringNotContainsString('private postal', CustomerActivity::all()->toJson());
        $this->actingAs($this->other)->get('/customers/'.$this->customer->id)->assertNotFound();
    }

    public function test_identity_creation_replay_is_normalized_and_old_contact_only_keys_still_replay(): void
    {
        $payload = ['name' => 'DEMO New identity', 'cnic' => '35202-1234567-1', 'address' => 'DEMO Address', 'creation_key' => (string) Str::uuid()];
        $this->actingAs($this->agent)->post('/customers', $payload)->assertSessionHasNoErrors();
        $this->post('/customers', [...$payload, 'cnic' => '3520212345671'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('customers', 2);
        $this->post('/customers', [...$payload, 'cnic' => '3520212345672'])->assertSessionHasErrors('conflict');
        $legacy = ['name' => 'DEMO Legacy', 'creation_key' => (string) Str::uuid()];
        $this->post('/customers', $legacy)->assertSessionHasNoErrors();
        $this->post('/customers', $legacy)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('customers', 3);
        $currentKey = config('app.key');
        $previousKeys = config('app.previous_keys');
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32)), 'app.previous_keys' => [$currentKey]]);
        $this->post('/customers', $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('customers', 3);
        config(['app.key' => $currentKey, 'app.previous_keys' => $previousKeys]);
        $this->patch('/customers/'.$this->customer->id, ['name' => $this->customer->name, 'cnic' => 'not-an-id'])->assertSessionHasErrors('cnic');
    }

    public function test_private_photo_upload_and_download_enforce_current_assignment_and_safe_headers(): void
    {
        $this->actingAs($this->agent)->post('/customers/'.$this->customer->id.'/documents', ['kind' => 'customer_photo', 'file' => UploadedFile::fake()->image('customer.png')])->assertSessionHasNoErrors();
        $document = CustomerDocument::firstOrFail();
        Storage::disk('customer_documents')->assertExists($document->path);
        $this->get('/customers/'.$this->customer->id)->assertInertia(fn (Assert $page) => $page->has('documents.data', 1)->missing('documents.data.0.path'));
        $url = '/customers/'.$this->customer->id.'/documents/'.$document->id;
        $this->get($url)->assertOk()->assertDownload('customer_photo-'.$document->id.'.png')->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Content-Type', 'image/png');
        $this->actingAs($this->other)->get($url)->assertNotFound();
        $admin = User::factory()->create()->assignRole('super_admin');
        app(Customers::class)->save($admin, ['name' => $this->customer->name, 'assigned_to' => $this->other->id], $this->customer->id);
        $this->actingAs($this->agent)->get($url)->assertNotFound();
        $this->actingAs($this->other)->get($url)->assertOk();
        $this->assertDatabaseHas('customer_activities', ['event' => 'document.downloaded']);
    }

    public function test_foreign_customer_upload_and_cross_customer_document_ids_are_rejected(): void
    {
        $this->actingAs($this->other)->post('/customers/'.$this->customer->id.'/documents', ['kind' => 'cnic_copy', 'file' => UploadedFile::fake()->image('copy.jpg')])->assertNotFound();
        $this->assertDatabaseCount('customer_documents', 0);
        $this->actingAs($this->agent)->post('/customers/'.$this->customer->id.'/documents', ['kind' => 'cnic_copy', 'file' => UploadedFile::fake()->image('copy.jpg')])->assertSessionHasNoErrors();
        $document = CustomerDocument::firstOrFail();
        $another = app(Customers::class)->save($this->agent, ['name' => 'DEMO Another', 'creation_key' => (string) Str::uuid()]);
        $this->get('/customers/'.$another->id.'/documents/'.$document->id)->assertNotFound();
    }

    public function test_files_have_whitelisted_content_types_sizes_and_cannot_supply_storage_paths(): void
    {
        $url = '/customers/'.$this->customer->id.'/documents';
        $this->actingAs($this->agent)->post($url, ['kind' => 'customer_photo', 'file' => UploadedFile::fake()->create('photo.pdf', 10, 'application/pdf')])->assertSessionHasErrors('file');
        $spoof = UploadedFile::fake()->createWithContent('fake.pdf', '<?php echo 1;');
        $realUpload = new UploadedFile($spoof->getPathname(), 'fake.pdf', 'application/pdf', null, true);
        $this->post($url, ['kind' => 'other', 'file' => $realUpload])->assertSessionHasErrors('file');
        $this->post($url, ['kind' => 'other', 'file' => UploadedFile::fake()->create('large.pdf', 10241, 'application/pdf')])->assertSessionHasErrors('file');
        $this->post($url, ['kind' => 'invalid', 'path' => '../secret', 'uploaded_by' => $this->other->id, 'file' => UploadedFile::fake()->image('photo.jpg')])->assertSessionHasErrors(['kind', 'path', 'uploaded_by']);
        $this->assertDatabaseCount('customer_documents', 0);
        $this->assertSame([], Storage::disk('customer_documents')->allFiles());
        $this->post($url, ['kind' => 'signed_booking_form', 'file' => UploadedFile::fake()->create('form.pdf', 10, 'application/pdf')])->assertSessionHasNoErrors();
    }

    public function test_document_audit_failure_rolls_back_metadata_and_cleans_up_the_private_file(): void
    {
        $this->withoutExceptionHandling();
        CustomerActivity::creating(fn () => throw new RuntimeException('DEMO document audit outage'));
        try {
            $this->actingAs($this->agent)->post('/customers/'.$this->customer->id.'/documents', ['kind' => 'customer_photo', 'file' => UploadedFile::fake()->image('photo.png')]);
            $this->fail('Expected audit failure');
        } catch (RuntimeException $exception) {
            $this->assertSame('DEMO document audit outage', $exception->getMessage());
            $this->assertDatabaseCount('customer_documents', 0);
            $this->assertSame([], Storage::disk('customer_documents')->allFiles());
        } finally {
            CustomerActivity::flushEventListeners();
        }
    }
}
