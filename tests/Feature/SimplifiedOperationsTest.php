<?php

namespace Tests\Feature;

use App\Domain\Inventory\Catalogue;
use App\Domain\Sales\FinalBookings;
use App\Models\BookingDraft;
use App\Models\SalaryPayment;
use App\Models\User;
use App\Support\BusinessClock;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FinalBookingFixtures;
use Tests\TestCase;

class SimplifiedOperationsTest extends TestCase
{
    use FinalBookingFixtures, RefreshDatabase;

    public function test_admin_and_accountant_can_record_and_view_salaries(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create()->assignRole('admin');
        $accountant = User::factory()->create()->assignRole('accountant');
        $agent = User::factory()->create()->assignRole('sales_agent');
        $payload = [
            'employee_name' => 'DEMO Staff Member',
            'salary_month' => '2026-11',
            'amount' => '45000.00',
            'paid_on' => '2026-11-17',
            'method' => 'cash',
            'reference' => null,
            'note' => 'DEMO monthly salary',
        ];

        $this->actingAs($accountant)->post('/finance/salaries', $payload)->assertRedirect();
        $this->assertDatabaseHas('salary_payments', ['employee_name' => 'DEMO Staff Member', 'amount' => '45000.00', 'recorded_by' => $accountant->id]);
        $this->get('/finance/salaries?month=2026-11')->assertInertia(fn (Assert $page) => $page->component('finance/salaries')->where('total', '45000.00')->has('payments', 1));
        $this->actingAs($admin)->post('/finance/salaries', [...$payload, 'employee_name' => 'DEMO Second Staff'])->assertRedirect();
        $this->actingAs($agent)->get('/finance/salaries')->assertForbidden();
        $this->post('/finance/salaries', $payload)->assertForbidden();
        $this->assertSame(2, SalaryPayment::count());
    }

    public function test_booking_can_be_marked_file_submitted_once(): void
    {
        $fixture = $this->fixture();
        $booking = app(FinalBookings::class)->confirm($fixture['agent'], $fixture['draft']->id, $this->confirmation($fixture['receipt']->id));
        $payload = ['submitted_on' => app(BusinessClock::class)->today(), 'note' => 'DEMO delivered to society', 'command_key' => (string) Str::uuid()];

        $this->actingAs($fixture['agent'])->post('/bookings/'.$booking->id.'/file-submission', $payload)->assertRedirect();
        $this->post('/bookings/'.$booking->id.'/file-submission', [...$payload, 'command_key' => (string) Str::uuid()])->assertRedirect();
        $this->assertDatabaseCount('booking_file_submissions', 1);
        $this->get('/inventory?hold=file_submitted')->assertInertia(fn (Assert $page) => $page->has('plots.data', 1)->where('plots.data.0.active_booking.file_submission.note', 'DEMO delivered to society'));
        $this->get('/inventory?hold=booked')->assertInertia(fn (Assert $page) => $page->has('plots.data', 0));
    }

    public function test_new_customer_and_single_purchaser_are_created_with_booking_start(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create()->assignRole('admin');
        $agent = User::factory()->create()->assignRole('sales_agent');
        $catalogue = app(Catalogue::class);
        $catalogue->society($admin, ['name' => 'DEMO Simple Society']);
        $block = $catalogue->block($admin, ['code' => 'SIMPLE', 'name' => 'DEMO Simple Block']);
        $size = $catalogue->size($admin, ['label' => 'DEMO Simple Size', 'area_marla' => '5']);
        $plot = $catalogue->plot($admin, ['block_id' => $block->id, 'plot_size_id' => $size->id, 'number' => 'S-1']);

        $response = $this->actingAs($agent)->post('/sales', [
            'customer_id' => '',
            'customer_name' => 'DEMO Walk-in Customer',
            'customer_phone' => '03001234567',
            'customer_cnic' => '3520212345671',
            'customer_address' => 'DEMO Customer Address',
            'customer_creation_key' => (string) Str::uuid(),
            'plot_id' => $plot->id,
            'quoted_price' => '4500000.00',
            'notes' => '',
            'creation_key' => (string) Str::uuid(),
        ]);

        $draftId = (int) BookingDraft::query()->value('id');
        $response->assertRedirect('/sales/'.$draftId.'/terms');
        $this->assertDatabaseHas('customers', ['name' => 'DEMO Walk-in Customer', 'phone' => '03001234567']);
        $this->assertDatabaseHas('draft_purchasers', ['booking_draft_id' => $draftId, 'share' => '100.0000']);
    }
}
