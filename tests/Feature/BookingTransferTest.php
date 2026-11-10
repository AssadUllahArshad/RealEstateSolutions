<?php

namespace Tests\Feature;

use App\Domain\CRM\Customers;
use App\Domain\Sales\BookingTransfers;
use App\Domain\Sales\FinalBookings;
use App\Models\BookingTransfer;
use App\Models\CustomerDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FinalBookingFixtures;
use Tests\TestCase;

class BookingTransferTest extends TestCase
{
    use FinalBookingFixtures, RefreshDatabase;

    public function test_agent_prepares_joint_transfer_and_admin_approval_preserves_contract_history(): void
    {
        $f = $this->fixture();
        $booking = app(FinalBookings::class)->confirm($f['agent'], $f['draft']->id, $this->confirmation($f['receipt']->id));
        CustomerDocument::create(['customer_id' => $f['customer']->id, 'kind' => 'cnic_copy', 'path' => 'test/outgoing-cnic.pdf', 'mime' => 'application/pdf', 'bytes' => 100, 'uploaded_by' => $f['agent']->id]);
        $form = CustomerDocument::create(['customer_id' => $f['customer']->id, 'kind' => 'signed_transfer_form', 'path' => 'test/transfer-form.pdf', 'mime' => 'application/pdf', 'bytes' => 100, 'uploaded_by' => $f['agent']->id]);
        $first = $this->incoming($f, 'Incoming One', '3520212345682');
        $second = $this->incoming($f, 'Incoming Two', '3520212345693');
        $payload = ['reason' => 'Purchasers submitted the signed transfer and complete identity pack.', 'transfer_form_document_id' => $form->id, 'payment_evidence_document_id' => null,
            'purchasers' => [['customer_id' => $first->id, 'share' => '40'], ['customer_id' => $second->id, 'share' => '60']], 'command_key' => (string) Str::uuid()];

        $this->actingAs($f['agent'])->post('/bookings/'.$booking->id.'/transfers', $payload)->assertSessionHasNoErrors();
        $this->post('/bookings/'.$booking->id.'/transfers', $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('booking_transfers', 1);
        $this->assertDatabaseCount('booking_transfer_parties', 2);
        $transfer = BookingTransfer::firstOrFail();
        $this->assertSame('900.00', $transfer->outstanding_snapshot);
        $this->post('/booking-transfers/'.$transfer->id.'/decision', ['decision' => 'approved', 'decision_note' => 'Complete identity pack and signed form verified.', 'confirmed' => true])->assertForbidden();

        $this->actingAs($f['admin'])->post('/booking-transfers/'.$transfer->id.'/decision', ['decision' => 'approved', 'decision_note' => 'Complete identity pack and signed form verified.', 'confirmed' => true])->assertSessionHasNoErrors();
        $approved = $transfer->fresh();
        $this->assertSame('approved', $approved->state);
        $this->assertNotNull($approved->effective_on);
        $this->assertSame([$first->id, $second->id], array_column(app(BookingTransfers::class)->activeParties($booking), 'customer_id'));
        $this->assertSame($f['customer']->id, $booking->fresh()->customer_id);
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('booking_allocations', 1);
        $this->assertDatabaseCount('booking_events', 3);

        $this->get('/bookings/'.$booking->id)->assertInertia(fn (Assert $page) => $page->component('sales/booked')->has('activePurchasers', 2)->where('activePurchasers.0.name', 'Incoming One'));
    }

    public function test_transfer_rejects_incomplete_identity_pack_and_non_exact_shares(): void
    {
        $f = $this->fixture();
        $booking = app(FinalBookings::class)->confirm($f['agent'], $f['draft']->id, $this->confirmation($f['receipt']->id));
        $incoming = app(Customers::class)->save($f['agent'], ['name' => 'Incomplete Incoming', 'cnic' => '3520212345682', 'address' => 'Incoming address', 'creation_key' => (string) Str::uuid()]);
        $this->actingAs($f['agent'])->post('/bookings/'.$booking->id.'/transfers', ['reason' => 'This request lacks required identity documents.', 'transfer_form_document_id' => 999, 'purchasers' => [['customer_id' => $incoming->id, 'share' => '99.9999']], 'command_key' => (string) Str::uuid()])
            ->assertSessionHasErrors('purchasers');
        $this->assertDatabaseCount('booking_transfers', 0);
    }

    private function incoming(array $fixture, string $name, string $cnic): \App\Models\Customer
    {
        $customer = app(Customers::class)->save($fixture['agent'], ['name' => $name, 'cnic' => $cnic, 'address' => $name.' private address', 'creation_key' => (string) Str::uuid()]);
        foreach (['cnic_copy', 'customer_photo'] as $kind) {
            CustomerDocument::create(['customer_id' => $customer->id, 'kind' => $kind, 'path' => 'test/'.$customer->id.'-'.$kind.'.pdf', 'mime' => 'application/pdf', 'bytes' => 100, 'uploaded_by' => $fixture['agent']->id]);
        }

        return $customer;
    }
}
