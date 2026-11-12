<?php

namespace Tests\Feature;

use App\Domain\Sales\FinalBookings;
use App\Models\BusinessPolicy;
use App\Models\PlotOwner;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FinalBookingFixtures;
use Tests\TestCase;

class OperationalReportTest extends TestCase
{
    use FinalBookingFixtures, RefreshDatabase;

    public function test_operational_report_gates_and_exact_totals(): void
    {
        CarbonImmutable::setTestNow('2028-03-01T07:00:00Z');

        try {
            $fixture = $this->fixture();
            $owner = PlotOwner::create([
                'name' => 'DEMO Report Owner',
                'admin_user_id' => $fixture['admin']->id,
                'default_percentage' => '20.0000',
                'created_by' => $fixture['admin']->id,
            ]);
            $fixture['plot']->update(['plot_owner_id' => $owner->id, 'owner_percentage' => '20.0000']);
            BusinessPolicy::create(['key' => 'commissions', 'values' => ['type' => 'percentage', 'value' => '5.0000', 'basis' => 'cleared_collections', 'trigger' => 'collection_cleared', 'cancellation' => 'reverse_and_recover'], 'version' => 1, 'updated_by' => $fixture['admin']->id]);
            app(FinalBookings::class)->confirm($fixture['agent'], $fixture['draft']->id, $this->confirmation($fixture['receipt']->id));

            $this->actingAs($fixture['agent'])->get('/reports/operations')->assertForbidden();
            $this->get('/reports/operations/export')->assertForbidden();

            $this->actingAs($fixture['admin'])->get('/reports/operations?from=2028-03-01&to=2028-03-01&as_of=2028-03-01')->assertInertia(fn (Assert $page) => $page
                ->component('reports/operations')
                ->where('report.inventory.total', 1)
                ->where('report.inventory.booked', 1)
                ->where('report.inventory.available', 0)
                ->where('report.inventory.held', 0)
                ->where('report.bookings.count', 1)
                ->where('report.bookings.contract_value', '1000.01')
                ->where('report.bookings.down_payment', '100.01')
                ->where('report.collections.recognized_count', 1)
                ->where('report.collections.recognized_amount', '150.02')
                ->where('report.collections.allocated_down', '100.01')
                ->where('report.collections.allocated_installments', '0.00')
                ->where('report.collections.unallocated', '50.01')
                ->where('report.ownerPayouts.approved_total', '0.00')
                ->where('report.commissions.approved_total', '0.00')
                ->where('report.ownerPayouts.outstanding.0.owner_name', 'DEMO Report Owner')
                ->where('report.ownerPayouts.outstanding.0.outstanding', '20.00')
                ->where('report.commissions.outstanding.0.agent_name', $fixture['agent']->name)
                ->where('report.commissions.outstanding.0.outstanding', '5.00'));

            $aging = $this->actingAs($fixture['admin'])->get('/reports/operations?from=2028-03-01&to=2028-03-01&as_of=2028-03-01')->viewData('page')['props']['report']['aging'];
            $this->assertSame('900.00', bcadd(bcadd(bcadd(bcadd($aging[0]['remaining'], $aging[1]['remaining'], 2), $aging[2]['remaining'], 2), $aging[3]['remaining'], 2), $aging[4]['remaining'], 2));

            $export = $this->get('/reports/operations/export?from=2028-03-01&to=2028-03-01&as_of=2028-03-01')->assertOk()
                ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertDownload('estateflow-operational-report.csv');
            $csv = $export->streamedContent();
            $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
            $this->assertStringContainsString('1000.01', $csv);
            $this->assertStringContainsString('150.02', $csv);
            $this->assertStringContainsString('100.01', $csv);
            $this->assertStringContainsString('50.01', $csv);
            $this->assertStringContainsString('DEMO Report Owner', $csv);
            $this->assertStringContainsString('20.00', $csv);
            $this->assertStringContainsString('5.00', $csv);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_aging_buckets_change_with_as_of_date(): void
    {
        CarbonImmutable::setTestNow('2028-03-01T07:00:00Z');

        try {
            $fixture = $this->fixture();
            app(FinalBookings::class)->confirm($fixture['agent'], $fixture['draft']->id, $this->confirmation($fixture['receipt']->id));

            $early = $this->actingAs($fixture['admin'])->get('/reports/operations?from=2028-03-01&to=2028-03-01&as_of=2028-01-01')->viewData('page')['props']['report']['aging'];
            $this->assertSame('900.00', $early[0]['remaining']);
            $this->assertSame(0, $early[1]['installments']);
            $this->assertSame(0, $early[2]['installments']);
            $this->assertSame(0, $early[3]['installments']);
            $this->assertSame(0, $early[4]['installments']);

            $late = $this->actingAs($fixture['admin'])->get('/reports/operations?from=2028-03-01&to=2028-03-01&as_of=2028-03-01')->viewData('page')['props']['report']['aging'];
            $this->assertSame('300.00', $late[0]['remaining']);
            $this->assertSame('600.00', bcadd($late[1]['remaining'], $late[2]['remaining'], 2));
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_invalid_filter_is_rejected(): void
    {
        $fixture = $this->fixture();
        $this->actingAs($fixture['admin'])->get('/reports/operations?from=2028-03-01&to=2028-02-01')->assertSessionHasErrors('to');
        $this->get('/reports/operations/export?as_of=not-a-date')->assertSessionHasErrors('as_of');
    }
}
