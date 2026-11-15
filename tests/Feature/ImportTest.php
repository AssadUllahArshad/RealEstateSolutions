<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\ImportBatch;
use App\Models\Plot;
use App\Models\PlotOwner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Support\FinalBookingFixtures;
use Tests\TestCase;

class ImportTest extends TestCase
{
    use FinalBookingFixtures, RefreshDatabase;

    public function test_import_is_gated_on_import_run_ability(): void
    {
        $fixture = $this->fixture();

        $this->actingAs($fixture['agent'])->get('/imports')->assertForbidden();
        $this->get('/imports/template/customers')->assertForbidden();
        $this->post('/imports/dry-run', ['template' => 'customers'])->assertForbidden();
    }

    public function test_template_download_streams_csv_with_headers(): void
    {
        $fixture = $this->fixture();

        $response = $this->actingAs($fixture['admin'])->get('/imports/template/customers')->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('name,phone,email,cnic,address,assigned_to_email', $csv);
    }

    public function test_dry_run_validates_rows_and_reports_errors(): void
    {
        $fixture = $this->fixture();

        $csv = "name,phone,email,cnic,address,assigned_to_email\n"
            ."DEMO Import Customer,03009998877,import@example.com,3520212345671,DEMO Address,\n"
            .",03001112233,bad-email,123,DEMO Address,\n";

        $file = UploadedFile::fake()->createWithContent('customers.csv', $csv);

        $response = $this->actingAs($fixture['admin'])->post('/imports/dry-run', [
            'template' => 'customers',
            'file' => $file,
        ]);

        $response->assertRedirect();
        $batch = ImportBatch::latest('id')->first();
        $this->assertNotNull($batch);
        $this->assertSame('dry_run', $batch->state);
        $this->assertSame(2, $batch->row_count);
        $this->assertSame(1, $batch->error_count);

        $rows = $batch->rows()->orderBy('row_number')->get();
        $this->assertSame('valid', $rows[0]->state);
        $this->assertSame('invalid', $rows[1]->state);
        $this->assertNotEmpty($rows[1]->errors);
    }

    public function test_commit_creates_customers_from_valid_rows(): void
    {
        $fixture = $this->fixture();

        $csv = "name,phone,email,cnic,address,assigned_to_email\n"
            ."DEMO Commit Customer,03007776655,commit@example.com,3520212345671,DEMO Address,\n";

        $file = UploadedFile::fake()->createWithContent('customers.csv', $csv);

        $this->actingAs($fixture['admin'])->post('/imports/dry-run', [
            'template' => 'customers',
            'file' => $file,
        ]);

        $batch = ImportBatch::latest('id')->first();
        $this->assertSame(0, $batch->error_count);

        $this->actingAs($fixture['admin'])->post("/imports/{$batch->id}/commit")->assertRedirect();

        $batch->refresh();
        $this->assertSame('committed', $batch->state);
        $this->assertNotNull($batch->committed_at);
        $this->assertTrue(Customer::where('phone', '03007776655')->exists());
    }

    public function test_commit_is_rejected_when_dry_run_has_errors(): void
    {
        $fixture = $this->fixture();

        $csv = "name,phone,email,cnic,address,assigned_to_email\n"
            .",03001112233,bad-email,123,DEMO Address,\n";

        $file = UploadedFile::fake()->createWithContent('customers.csv', $csv);

        $this->actingAs($fixture['admin'])->post('/imports/dry-run', [
            'template' => 'customers',
            'file' => $file,
        ]);

        $batch = ImportBatch::latest('id')->first();
        $this->assertGreaterThan(0, $batch->error_count);

        $this->actingAs($fixture['admin'])->post("/imports/{$batch->id}/commit")->assertSessionHasErrors('batch');
        $this->assertFalse(Customer::where('phone', '03001112233')->exists());
    }

    public function test_inventory_import_creates_plots(): void
    {
        $fixture = $this->fixture();
        $owner = PlotOwner::create(['name' => 'DEMO Import Owner', 'admin_user_id' => $fixture['admin']->id, 'default_percentage' => '50.0000', 'created_by' => $fixture['admin']->id]);

        $csv = "block_code,size_label,number,list_price,owner_name,owner_percentage\n"
            ."BOOK,DEMO 5 Marla,BOOK-IMPORT-1,5000000.00,{$owner->name},50.0000\n";

        $file = UploadedFile::fake()->createWithContent('inventory.csv', $csv);

        $this->actingAs($fixture['admin'])->post('/imports/dry-run', [
            'template' => 'inventory',
            'file' => $file,
        ]);

        $batch = ImportBatch::latest('id')->first();
        $this->assertSame(0, $batch->error_count);

        $this->actingAs($fixture['admin'])->post("/imports/{$batch->id}/commit")->assertRedirect();

        $this->assertTrue(Plot::where('number', 'BOOK-IMPORT-1')->exists());
    }

    public function test_inventory_import_rejects_a_missing_owner(): void
    {
        $fixture = $this->fixture();
        $csv = "block_code,size_label,number,list_price,owner_name,owner_percentage\n"
            ."BOOK,DEMO 5 Marla,BOOK-IMPORT-NO-OWNER,5000000.00,,\n";

        $this->actingAs($fixture['admin'])->post('/imports/dry-run', [
            'template' => 'inventory',
            'file' => UploadedFile::fake()->createWithContent('inventory.csv', $csv),
        ]);

        $batch = ImportBatch::latest('id')->firstOrFail();
        $this->assertSame(1, $batch->error_count);
        $this->assertContains('Owner name is required.', $batch->rows()->firstOrFail()->errors);
    }

    public function test_bookings_import_creates_confirmed_booking(): void
    {
        $fixture = $this->fixture();
        $fixture['customer']->update(['phone' => '03005554433']);

        $csv = "customer_phone,block_code,plot_number,confirmed_on,net,down_payment,frequency,count,first_due_date\n"
            ."03005554433,BOOK,BOOK-1,2028-03-01,1000.01,100.01,monthly,3,2028-04-01\n";

        $file = UploadedFile::fake()->createWithContent('bookings.csv', $csv);

        $this->actingAs($fixture['admin'])->post('/imports/dry-run', [
            'template' => 'bookings',
            'file' => $file,
        ]);

        $batch = ImportBatch::latest('id')->first();
        $this->assertSame(0, $batch->error_count);

        $this->actingAs($fixture['admin'])->post("/imports/{$batch->id}/commit")->assertRedirect();

        $this->assertTrue(Booking::where('plot_id', $fixture['plot']->id)->exists());
    }
}
