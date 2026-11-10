<?php

namespace Tests\Feature;

use App\Domain\Finance\InstallmentLedger;
use App\Domain\Sales\FinalBookings;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FinalBookingFixtures;
use Tests\TestCase;

class ReceivablesTest extends TestCase
{
    use FinalBookingFixtures, RefreshDatabase;

    public function test_scoped_worklist_uses_exact_mysql_schedule_projection_and_filters(): void
    {
        $fixture = $this->fixture();
        $booking = app(FinalBookings::class)->confirm($fixture['agent'], $fixture['draft']->id, $this->confirmation($fixture['receipt']->id));
        CarbonImmutable::setTestNow('2028-03-01T07:00:00Z');

        try {
            $this->actingAs($fixture['agent'])->get('/finance/receivables')->assertInertia(fn (Assert $page) => $page
                ->component('finance/receivables')
                ->where('summary.bookings', 1)
                ->where('summary.outstanding', '900.00')
                ->where('summary.overdue', '600.00')
                ->where('summary.dueToday', '0.00')
                ->has('receivables.data', 1)
                ->where('receivables.data.0.id', $booking->id)
                ->where('receivables.data.0.customer_name', 'DEMO Booking purchaser')
                ->where('receivables.data.0.plot_number', 'BOOK-1')
                ->where('receivables.data.0.next_due_date', '2028-01-31'));
            $this->get('/finance/receivables?state=overdue&search=BOOK-1')->assertInertia(fn (Assert $page) => $page->has('receivables.data', 1));
            $this->get('/finance/receivables?state=due_today')->assertInertia(fn (Assert $page) => $page->where('summary.bookings', 0)->has('receivables.data', 0));

            DB::table('customers')->where('id', $fixture['customer']->id)->update(['name' => '=2+2']);
            $export = $this->get('/finance/receivables/export?state=overdue&search=BOOK-1')->assertOk()
                ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertDownload('estateflow-receivables.csv');
            $csv = $export->streamedContent();
            $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
            $this->assertStringContainsString("'=2+2", $csv);
            $this->assertStringContainsString('BOOK-1', $csv);
            $this->assertStringContainsString('900.00,600.00,0.00,Overdue,2028-03-01', $csv);
            $this->assertStringNotContainsString("\n=2+2,", $csv);
            $this->assertStringNotContainsString('BOOK-1', $this->get('/finance/receivables/export?state=due_today')->assertOk()->streamedContent());

            $outsider = User::factory()->create()->assignRole('sales_agent');
            $this->actingAs($outsider)->get('/finance/receivables')->assertInertia(fn (Assert $page) => $page->where('summary.bookings', 0)->has('receivables.data', 0));
            $this->get('/finance/receivables/export')->assertOk()->assertDownload('estateflow-receivables.csv');
            $this->actingAs($fixture['admin'])->get('/finance/receivables')->assertInertia(fn (Assert $page) => $page->has('receivables.data', 1));

            $state = app(InstallmentLedger::class)->state($booking);
            $this->assertSame($state['outstanding'], '900.00');
            $this->assertSame($state['overdue'], '600.00');
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_invalid_filter_is_rejected(): void
    {
        $fixture = $this->fixture();
        $this->actingAs($fixture['agent'])->get('/finance/receivables?state=penalty')->assertSessionHasErrors('state');
        $this->get('/finance/receivables/export?state=penalty')->assertSessionHasErrors('state');
    }
}
