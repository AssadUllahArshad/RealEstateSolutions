<?php

namespace Tests\Feature;

use App\Domain\CRM\Customers;
use App\Domain\Inventory\Holds;
use App\Domain\Sales\DraftPurchasers;
use App\Domain\Sales\FinalBookings;
use App\Models\BookingEvent;
use App\Models\BusinessPolicy;
use App\Models\CustomerDocument;
use App\Models\PlotHold;
use App\Models\User;
use App\Support\BusinessClock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\Support\FinalBookingFixtures;
use Tests\TestCase;

class FinalBookingTest extends TestCase
{
    use FinalBookingFixtures, RefreshDatabase;

    public function test_agent_can_confirm_exact_credit_snapshot_replay_and_freeze(): void
    {
        $f = $this->fixture();
        $hold = app(Holds::class)->execute($f['agent'], $f['plot']->id, 'reservation', (string) Str::uuid(), 'DEMO purchaser review');
        $command = $this->confirmation($f['receipt']->id);
        $booking = app(FinalBookings::class)->confirm($f['agent'], $f['draft']->id, $command);
        $this->assertSame('1000.01', $booking->net);
        $this->assertSame('100.01', $booking->down_payment);
        $this->assertSame('released', $hold->fresh()->state);
        $this->assertSame('900.00', $booking->snapshot['terms']['balance']);
        $this->assertSame('3520212345671', $booking->private_snapshot['purchasers'][0]['cnic']);
        $this->assertStringNotContainsString('3520212345671', DB::table('bookings')->value('private_snapshot'));
        $this->assertArrayNotHasKey('private_snapshot', $booking->toArray());
        $this->assertSame($booking->id, app(FinalBookings::class)->confirm($f['agent'], $f['draft']->id, $command)->id);
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('booking_events', 1);
        $this->assertDatabaseCount('booking_allocations', 1);
        $review = app(FinalBookings::class)->review($f['agent'], $f['draft']->id);
        $this->assertSame('50.01', $review['credits'][0]['available']);
        $this->actingAs($f['agent'])->get('/bookings/'.$booking->id)->assertInertia(fn (Assert $page) => $page->component('sales/booked')->missing('booking.private_snapshot')->where('allocations.0.amount', '100.01'));
        $this->get('/sales/'.$f['draft']->id.'/confirm')->assertRedirect('/bookings/'.$booking->id);
        $this->post('/sales/'.$f['draft']->id.'/confirm', [...$command, 'terms_version' => 2])->assertSessionHasErrors('conflict');
        $this->patch('/sales/'.$f['draft']->id.'/purchasers', ['version' => 1, 'purchasers' => [['customer_id' => $f['customer']->id, 'share' => '100']]])->assertSessionHasErrors('purchasers_conflict');
        $this->patch('/sales/'.$f['draft']->id, ['version' => 1, 'customer_id' => $f['customer']->id, 'plot_id' => $f['plot']->id, 'quoted_price' => '1001'])->assertSessionHasErrors('conflict');
        $this->post('/inventory/plots/'.$f['plot']->id.'/holds', ['kind' => 'reservation', 'key' => (string) Str::uuid(), 'reason' => 'DEMO late hold'])->assertSessionHasErrors('conflict');
        $this->actingAs($f['admin'])->post('/finance/posted/'.$f['receipt']->id.'/reverse', ['command_key' => (string) Str::uuid(), 'expected_version' => 1, 'posting_version' => 1, 'business_date' => app(BusinessClock::class)->today(), 'note' => 'DEMO cannot detach confirmed booking funds', 'confirmed' => true])->assertSessionHasErrors('posting');
        $this->assertDatabaseCount('account_movements', 1);
        $this->get('/inventory?hold=none')->assertInertia(fn (Assert $page) => $page->has('plots.data', 0));
        $this->get('/inventory?hold=booked')->assertInertia(fn (Assert $page) => $page->has('plots.data', 1)->where('plots.data.0.active_booking.id', $booking->id));
    }

    public function test_scope_role_and_attestation_are_enforced(): void
    {
        $f = $this->fixture();
        $command = $this->confirmation($f['receipt']->id);
        $url = '/sales/'.$f['draft']->id.'/confirm';
        $outsider = User::factory()->create()->assignRole('sales_agent');
        $this->actingAs($outsider)->get($url)->assertNotFound();
        $this->post($url, $command)->assertNotFound();
        $this->actingAs($f['agent'])->post($url, [...$command, 'confirmed' => false])->assertSessionHasErrors('confirmed');
        $this->post($url, [...$command, 'net' => '1'])->assertSessionHasErrors('net');
        $this->assertDatabaseCount('bookings', 0);
        $accountant = User::factory()->create()->assignRole('accountant');
        $this->actingAs($accountant)->get($url)->assertOk();
        $this->post($url, $command)->assertRedirect();
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_joint_purchasers_are_snapshotted_and_unrecognized_receipts_are_rejected(): void
    {
        $f = $this->fixture();
        $joint = app(Customers::class)->save($f['agent'], ['name' => 'DEMO Joint purchaser', 'cnic' => '3520298765431', 'address' => 'DEMO Joint private address', 'creation_key' => (string) Str::uuid()]);
        app(DraftPurchasers::class)->save($f['agent'], $f['draft']->id, ['version' => 1, 'purchasers' => [['customer_id' => $f['customer']->id, 'share' => '33.3333'], ['customer_id' => $joint->id, 'share' => '66.6667']]]);
        $command = [...$this->confirmation($f['receipt']->id), 'purchasers_version' => 2];
        DB::table('payment_receipts')->where('id', $f['receipt']->id)->update(['method' => 'cheque', 'state' => 'pending_clearance', 'bank_stage' => 'deposited', 'recognized_on' => null]);
        $url = '/sales/'.$f['draft']->id.'/confirm';
        $this->actingAs($f['agent'])->post($url, $command)->assertSessionHasErrors('allocations');
        DB::table('payment_receipts')->where('id', $f['receipt']->id)->update(['state' => 'recognized', 'recognized_on' => app(BusinessClock::class)->today()]);
        $booking = app(FinalBookings::class)->confirm($f['agent'], $f['draft']->id, $command);
        $this->assertSame(['33.3333', '66.6667'], array_column($booking->snapshot['purchasers'], 'share'));
        $this->assertCount(2, $booking->private_snapshot['purchasers']);
        $this->assertArrayNotHasKey('private_snapshot', $booking->toArray());
        $this->patch('/sales/'.$f['draft']->id.'/terms', [...$booking->snapshot['terms']['input'], 'version' => 1, 'source_version' => 1, 'policy_versions' => ['booking' => 1, 'installments' => 1]])->assertSessionHasErrors('conflict');
        $this->expectException(\LogicException::class);
        $booking->update(['net' => '1.00']);
    }

    public function test_missing_documents_stale_terms_and_insufficient_allocation_block_confirmation(): void
    {
        $f = $this->fixture();
        $url = '/sales/'.$f['draft']->id.'/confirm';
        $command = $this->confirmation($f['receipt']->id);
        $this->actingAs($f['agent'])->post($url, [...$command, 'allocations' => [['receipt_id' => $f['receipt']->id, 'amount' => '99.99']]])->assertSessionHasErrors('allocations');
        $this->post($url, [...$command, 'allocations' => [['receipt_id' => $f['receipt']->id, 'amount' => '150.03']]])->assertSessionHasErrors('allocations');
        $doc = CustomerDocument::firstOrFail();
        DB::table('customer_documents')->where('id', $doc->id)->update(['kind' => 'cnic_copy']);
        $this->post($url, $command)->assertSessionHasErrors('confirmation');
        DB::table('customer_documents')->where('id', $doc->id)->update(['kind' => 'signed_booking_form']);
        BusinessPolicy::whereKey('booking')->update(['version' => 2]);
        $this->post($url, $command)->assertSessionHasErrors('conflict');
        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('booking_allocations', 0);
    }

    public function test_hold_access_and_transactional_audit_failure_preserve_original_state(): void
    {
        $f = $this->fixture();
        app(Holds::class)->execute($f['admin'], $f['plot']->id, 'block', (string) Str::uuid(), 'DEMO admin block');
        $this->actingAs($f['agent'])->post('/sales/'.$f['draft']->id.'/confirm', $this->confirmation($f['receipt']->id))->assertSessionHasErrors('confirmation');
        $this->assertDatabaseCount('bookings', 0);
        $blocked = PlotHold::firstOrFail();
        app(Holds::class)->execute($f['admin'], $f['plot']->id, 'release', (string) Str::uuid(), 'DEMO cleared admin block', $blocked->id);
        BookingEvent::creating(fn () => throw new RuntimeException('DEMO forced audit failure'));
        try {
            app(FinalBookings::class)->confirm($f['agent'], $f['draft']->id, $this->confirmation($f['receipt']->id));
            $this->fail('Audit failure must roll back all booking writes.');
        } catch (RuntimeException $exception) {
            $this->assertSame('DEMO forced audit failure', $exception->getMessage());
        } finally {
            BookingEvent::flushEventListeners();
        }
        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('booking_allocations', 0);
        $this->assertDatabaseCount('account_movements', 1);
    }
}
