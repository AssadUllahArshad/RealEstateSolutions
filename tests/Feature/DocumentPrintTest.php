<?php

namespace Tests\Feature;

use App\Domain\Finance\InstallmentLedger;
use App\Domain\Finance\ReceiptPosting;
use App\Domain\Sales\DraftTerms;
use App\Domain\Sales\FinalBookings;
use App\Models\CustomerActivity;
use App\Models\DraftTerm;
use App\Models\Society;
use App\Models\User;
use App\Support\BusinessClock;
use App\Support\Pkr;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\FinalBookingFixtures;
use Tests\TestCase;

class DocumentPrintTest extends TestCase
{
    use FinalBookingFixtures, RefreshDatabase;

    public function test_principal_statement_uses_urdu_locale_and_rtl_document_direction(): void
    {
        $f = $this->fixture();
        $booking = app(FinalBookings::class)->confirm($f['agent'], $f['draft']->id, $this->confirmation($f['receipt']->id));

        $this->actingAs($f['agent'])->withUnencryptedCookie('locale', 'ur')
            ->get('/bookings/'.$booking->id.'/statement/print')
            ->assertOk()
            ->assertSee('lang="ur"', false)
            ->assertSee('dir="rtl"', false)
            ->assertSee('موجودہ اصل رقم کا اسٹیٹمنٹ')
            ->assertSee('PKR 900.00')
            ->assertDontSee('3520212345671');
    }

    public function test_current_statement_uses_live_allocations_and_finance_scope_without_private_identity(): void
    {
        $f = $this->fixture();
        $booking = app(FinalBookings::class)->confirm($f['agent'], $f['draft']->id, $this->confirmation($f['receipt']->id));
        $url = '/bookings/'.$booking->id.'/statement/print';
        $this->actingAs($f['agent'])->get($url)->assertOk()->assertSee('PKR 900.00')->assertSee('No installment allocations recorded.');
        app(InstallmentLedger::class)->allocate($f['admin'], $booking->id, [
            'command_key' => (string) Str::uuid(), 'version' => 0, 'policy_version' => 1,
            'receipt_id' => $f['receipt']->id, 'amount' => '50.01', 'mode' => 'oldest_first', 'rows' => [], 'charge_rows' => [], 'confirmed' => true,
        ]);
        $accountant = User::factory()->create()->assignRole('accountant');
        $this->assertTrue($accountant->can('customers.view'));
        $response = $this->actingAs($accountant)->get($url)->assertOk()
            ->assertSee('Live principal balances')->assertSee('PKR 849.99')->assertSee('PKR 150.02')
            ->assertSee('PKR 50.01')->assertSee($f['receipt']->number)->assertSee('Ledger version 1')
            ->assertDontSee('3520212345671')->assertDontSee('DEMO Private address')->assertDontSee('test/not-a-production-document.pdf');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->get($url)->assertOk();
        $this->assertDatabaseCount('installment_batches', 1);
        $this->assertDatabaseCount('account_movements', 1);
        $this->assertSame(3, CustomerActivity::where('event', 'statement.print_previewed')->count());
        $this->actingAs(User::factory()->create()->assignRole('sales_agent'))->get($url)->assertNotFound();
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
    }

    public function test_print_receipt_is_scoped_private_exact_and_uses_immutable_issuer(): void
    {
        $f = $this->fixture();
        $receipt = $f['receipt'];
        Society::whereKey(1)->update(['name' => 'DEMO Renamed Society']);
        $url = '/finance/posted/'.$receipt->id.'/print';
        $response = $this->actingAs($f['agent'])->get($url)->assertOk()
            ->assertSee('Data Real Estate')->assertSee('Green Vista Housing Scheme')
            ->assertSee('Recognized receipt')->assertSee('PKR 150.02')->assertSee('DEMO Booking Society')
            ->assertDontSee('DEMO Renamed Society')->assertDontSee('3520212345671')->assertDontSee('DEMO Private address')
            ->assertDontSee('DEMO actual receipt evidence verified')->assertDontSee('test/not-a-production-document.pdf');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $response->assertHeader('X-Frame-Options', 'DENY')->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $this->assertStringContainsString("script-src 'self'", $response->headers->get('Content-Security-Policy'));
        $this->assertSame(1, CustomerActivity::where('event', 'receipt.print_previewed')->count());
        $this->assertDatabaseCount('account_movements', 1);
        $this->assertDatabaseCount('payment_receipts', 1);
        $accountant = User::factory()->create()->assignRole('accountant');
        $this->actingAs($accountant)->get($url)->assertOk();
        $this->assertTrue($accountant->can('customers.view'));
        $outsider = User::factory()->create()->assignRole('sales_agent');
        $this->actingAs($outsider)->get($url)->assertNotFound();
        $this->assertSame(2, CustomerActivity::where('event', 'receipt.print_previewed')->count());
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
    }

    public function test_reversed_and_pending_printouts_never_claim_usable_credit(): void
    {
        $f = $this->fixture();
        app(ReceiptPosting::class)->advance($f['admin'], $f['receipt']->id, 'reverse', ['command_key' => (string) Str::uuid(), 'expected_version' => 1, 'posting_version' => 1, 'business_date' => app(BusinessClock::class)->today(), 'note' => 'DEMO private reversal investigation note', 'confirmed' => true]);
        $url = '/finance/posted/'.$f['receipt']->id.'/print';
        $this->actingAs($f['agent'])->get($url)->assertOk()->assertSee('REVERSED')->assertSee('Original face amount')->assertSee('PKR 0.00')->assertDontSee('DEMO private reversal investigation note');
        // Legacy pending fixture: state is the authority even if old metadata is incomplete.
        DB::table('payment_receipts')->where('id', $f['receipt']->id)->update(['state' => 'pending_clearance', 'method' => 'cheque', 'bank_stage' => 'deposited', 'recognized_on' => null, 'reversed_on' => null]);
        $this->get($url)->assertOk()->assertSee('Awaiting bank clearance')->assertSee('Funds are not recognized')->assertSee('PKR 0.00')->assertDontSee('REVERSED');
        $this->assertDatabaseCount('account_movements', 2);
    }

    public function test_booking_print_preserves_contract_and_omits_identity_and_private_files(): void
    {
        $f = $this->fixture();
        $booking = app(FinalBookings::class)->confirm($f['agent'], $f['draft']->id, $this->confirmation($f['receipt']->id));
        $url = '/bookings/'.$booking->id.'/print';
        $this->actingAs($f['agent'])->get($url)->assertOk()->assertSee('Booking confirmed')->assertSee('PKR 1,000.01')->assertSee('PKR 100.01')->assertSee('2028-02-29')->assertSee('100.0000%')->assertSee('Signed booking form')->assertSee('not a current outstanding statement')->assertDontSee('3520212345671')->assertDontSee('DEMO Private address')->assertDontSee('test/not-a-production-document.pdf');
        $this->get('/finance/posted/'.$f['receipt']->id.'/print')->assertOk()->assertSee('PKR 50.01');
        $this->assertSame(1, CustomerActivity::where('event', 'booking.print_previewed')->count());
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('booking_allocations', 1);
        $this->actingAs(User::factory()->create()->assignRole('sales_agent'))->get($url)->assertNotFound();
    }

    public function test_print_content_is_escaped_and_old_issuer_details_are_not_fabricated(): void
    {
        $f = $this->fixture('9999999999999999.99');
        $snapshot = $f['receipt']->snapshot;
        unset($snapshot['issuer']);
        $snapshot['customer_name'] = '<script>alert("DEMO")</script>';
        DB::table('payment_receipts')->where('id', $f['receipt']->id)->update(['snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR)]);
        $this->actingAs($f['agent'])->get('/finance/posted/'.$f['receipt']->id.'/print')->assertOk()->assertSee($snapshot['customer_name'])->assertDontSee($snapshot['customer_name'], false)->assertSee('Office profile details were not captured at issue')->assertSee('PKR 9,999,999,999,999,999.99');
        $this->get('/finance/posted/999999999/print')->assertNotFound();
        $this->assertSame('PKR 9,999,999,999,999,999.99', Pkr::format('9999999999999999.99'));
        $this->assertSame('PKR 0.00', Pkr::format('0'));
        $this->assertSame('PKR 1.20', Pkr::format('1.2'));
        $this->assertSame('-PKR 1,234.50', Pkr::formatSigned('-1234.5'));
        $this->assertSame('PKR 0.00', Pkr::formatSigned('-0.00'));
    }

    public function test_long_agreed_schedule_print_contains_every_installment(): void
    {
        $f = $this->fixture();
        $term = DraftTerm::firstOrFail();
        app(DraftTerms::class)->save($f['agent'], $f['draft']->id, [...$term->snapshot['input'], 'count' => 360, 'version' => 1, 'source_version' => 1, 'policy_versions' => ['booking' => 1, 'installments' => 1]]);
        $booking = app(FinalBookings::class)->confirm($f['agent'], $f['draft']->id, [...$this->confirmation($f['receipt']->id), 'terms_version' => 2]);
        $response = $this->actingAs($f['agent'])->get('/bookings/'.$booking->id.'/print')->assertOk()->assertSee('2057-12-31');
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $rows = (new \DOMXPath($document))->query('//section[h2[text()="Agreed installment schedule"]]//tbody/tr');
        $this->assertSame(360, $rows->length);
        $this->assertSame('900.00', $booking->snapshot['terms']['balance']);
        $this->assertDatabaseCount('booking_allocations', 1);
    }
}
