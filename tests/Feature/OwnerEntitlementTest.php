<?php

namespace Tests\Feature;

use App\Domain\Finance\OwnerEntitlements;
use App\Models\Block;
use App\Models\Booking;
use App\Models\BookingDraft;
use App\Models\Customer;
use App\Models\OwnerEntitlementEvent;
use App\Models\Plot;
use App\Models\PlotOwner;
use App\Models\PlotSize;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class OwnerEntitlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_uses_booking_rate_snapshot_and_half_up_paisa_rounding_idempotently(): void
    {
        $booking = $this->booking('12.3456');
        $service = app(OwnerEntitlements::class);
        $first = $service->record($booking, 'booking_allocation', 91, '100.01', '2026-11-09');
        $retry = $service->record($booking, 'booking_allocation', 91, '100.01', '2026-11-09');

        $this->assertSame($first->id, $retry->id);
        $this->assertSame('100.01', $first->eligible_amount);
        $this->assertSame('12.3456', $first->percentage);
        $this->assertSame('12.35', $first->entitlement_amount);
        $this->assertDatabaseCount('owner_entitlement_events', 1);
    }

    public function test_event_is_immutable_and_missing_snapshot_rate_is_rejected(): void
    {
        $booking = $this->booking('10.0000');
        $event = app(OwnerEntitlements::class)->record($booking, 'installment_batch', 72, '50.00', '2026-11-09');
        $this->expectException(LogicException::class);
        $event->update(['entitlement_amount' => '1.00']);
    }

    public function test_same_source_cannot_be_reused_with_different_amount(): void
    {
        $booking = $this->booking('10.0000');
        $service = app(OwnerEntitlements::class);
        $service->record($booking, 'booking_allocation', 55, '50.00', '2026-11-09');
        $this->expectException(ConflictHttpException::class);
        $service->record($booking, 'booking_allocation', 55, '51.00', '2026-11-09');
    }

    public function test_missing_owner_rate_does_not_create_an_entitlement(): void
    {
        $booking = $this->booking(null);
        $this->expectException(ValidationException::class);
        try {
            app(OwnerEntitlements::class)->record($booking, 'booking_allocation', 100, '50.00', '2026-11-09');
        } finally {
            $this->assertSame(0, OwnerEntitlementEvent::count());
        }
    }

    private function booking(?string $rate): Booking
    {
        $admin = User::factory()->create();
        $owner = PlotOwner::create(['name' => 'Owner '.Str::random(5), 'admin_user_id' => $admin->id, 'default_percentage' => $rate, 'created_by' => $admin->id]);
        $block = Block::forceCreate(['code' => Str::upper(Str::random(3)), 'name' => 'Block', 'created_by' => $admin->id, 'updated_by' => $admin->id]);
        $size = PlotSize::forceCreate(['label' => 'Five '.Str::random(4), 'area_marla' => '5.00', 'created_by' => $admin->id, 'updated_by' => $admin->id]);
        $plot = Plot::forceCreate(['block_id' => $block->id, 'plot_size_id' => $size->id, 'plot_owner_id' => $owner->id, 'owner_percentage' => $rate, 'number' => Str::upper(Str::random(5)), 'created_by' => $admin->id, 'updated_by' => $admin->id]);
        $customer = Customer::create(['name' => 'Buyer', 'assigned_to' => $admin->id, 'created_by' => $admin->id, 'creation_key' => Str::uuid(), 'creation_hash' => str_repeat('a', 64)]);
        $draft = BookingDraft::create(['customer_id' => $customer->id, 'plot_id' => $plot->id, 'created_by' => $admin->id, 'quoted_price' => '1000.00', 'version' => 1, 'creation_key' => Str::uuid(), 'creation_hash' => str_repeat('b', 64)]);

        return Booking::create(['booking_draft_id' => $draft->id, 'plot_id' => $plot->id, 'customer_id' => $customer->id, 'confirmed_by' => $admin->id,
            'confirmed_on' => '2026-11-09', 'net' => '1000.00', 'down_payment' => '100.00',
            'snapshot' => ['customer_name' => 'Buyer', 'plot' => ['number' => $plot->number, 'owner_percentage' => $rate, 'owner' => ['id' => $owner->id, 'name' => $owner->name]]],
            'private_snapshot' => [], 'command_key' => Str::uuid(), 'payload_hash' => str_repeat('c', 64)]);
    }
}
