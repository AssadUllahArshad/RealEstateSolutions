<?php

namespace Tests\Feature;

use App\Domain\Finance\ManualCharges;
use App\Domain\Sales\FinalBookings;
use App\Models\BookingCharge;
use App\Models\BusinessPolicy;
use App\Models\User;
use App\Support\BusinessClock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;
use Tests\Support\FinalBookingFixtures;
use Tests\TestCase;

class ManualChargeTest extends TestCase
{
    use FinalBookingFixtures, RefreshDatabase;

    /** @return array<string,mixed> */
    private function payload(int $booking, string $key): array
    {
        return ['booking_id' => $booking, 'amount' => '125.50', 'business_date' => app(BusinessClock::class)->today(),
            'reason' => 'DEMO approved late documentation charge', 'note' => 'DEMO evidence checked by admin.',
            'evidence_reference' => 'DEMO-CHARGE-42', 'command_key' => $key, 'confirmed' => true];
    }

    public function test_admin_posts_idempotent_charge_and_waives_with_compensating_record(): void
    {
        $f = $this->fixture();
        $booking = app(FinalBookings::class)->confirm($f['agent'], $f['draft']->id, $this->confirmation($f['receipt']->id));
        BusinessPolicy::create(['key' => 'charges', 'version' => 1, 'values' => ['late_fee_mode' => 'manual', 'late_fee_value' => null, 'grace_days' => null, 'basis' => null, 'frequency' => null, 'cap_mode' => null, 'cap_value' => null], 'updated_by' => $f['admin']->id]);
        $key = (string) Str::uuid();
        $service = app(ManualCharges::class);
        $charge = $service->post($f['admin'], $this->payload($booking->id, $key));
        $this->assertSame('CHG-000001', $charge->number);
        $this->assertSame('125.50', $charge->amount);
        $this->assertSame($charge->id, $service->post($f['admin'], $this->payload($booking->id, $key))->id);
        $this->assertDatabaseCount('booking_charges', 1);

        $this->actingAs($f['agent'])->get('/finance/charges')->assertInertia(fn (Assert $page) => $page
            ->component('finance/charges')->where('canManage', false)->where('activeTotal', '125.50')->has('charges.data', 1));
        $accountant = User::factory()->create()->assignRole('accountant');
        $this->actingAs($accountant)->get('/finance/charges')->assertInertia(fn (Assert $page) => $page->where('canManage', false)->has('charges.data', 1));
        $this->actingAs($f['admin'])->get('/finance/charges')->assertInertia(fn (Assert $page) => $page->where('canManage', true));

        $waiveKey = (string) Str::uuid();
        $waiverInput = ['amount' => '125.50', 'business_date' => app(BusinessClock::class)->today(), 'reason' => 'DEMO admin-approved full waiver', 'command_key' => $waiveKey, 'confirmed' => true];
        $waiver = $service->waive($f['admin'], $charge->id, $waiverInput);
        $this->assertSame('125.50', $waiver->amount);
        $this->assertSame($waiver->id, $service->waive($f['admin'], $charge->id, $waiverInput)->id);
        $this->actingAs($f['admin'])->get('/finance/charges?state=active')->assertInertia(fn (Assert $page) => $page->where('activeTotal', '0.00')->has('charges.data', 0));
        $this->get('/finance/charges?state=waived')->assertInertia(fn (Assert $page) => $page->has('charges.data', 1)->where('charges.data.0.waivers.0.reason', 'DEMO admin-approved full waiver')->where('charges.data.0.waivedAmount', '125.50'));
        $this->expectException(LogicException::class);
        BookingCharge::findOrFail($charge->id)->update(['amount' => '1.00']);
    }

    public function test_exact_partial_waivers_accumulate_without_exceeding_the_remaining_charge(): void
    {
        $f = $this->fixture();
        $booking = app(FinalBookings::class)->confirm($f['agent'], $f['draft']->id, $this->confirmation($f['receipt']->id));
        BusinessPolicy::create(['key' => 'charges', 'version' => 1, 'values' => ['late_fee_mode' => 'manual'], 'updated_by' => $f['admin']->id]);
        $charge = app(ManualCharges::class)->post($f['admin'], $this->payload($booking->id, (string) Str::uuid()));
        $base = ['business_date' => app(BusinessClock::class)->today(), 'confirmed' => true];
        $this->actingAs($f['admin'])->post('/finance/charges/'.$charge->id.'/waive', [
            ...$base, 'amount' => '25.50', 'reason' => 'DEMO partial waiver with verified support.', 'command_key' => (string) Str::uuid(),
        ])->assertSessionHasNoErrors();
        $this->post('/finance/charges/'.$charge->id.'/waive', [
            ...$base, 'amount' => '100.01', 'reason' => 'DEMO attempted waiver above the remaining amount.', 'command_key' => (string) Str::uuid(),
        ])->assertSessionHasErrors('amount');
        $this->post('/finance/charges/'.$charge->id.'/waive', [
            ...$base, 'amount' => '100.00', 'reason' => 'DEMO final exact waiver with verified support.', 'command_key' => (string) Str::uuid(),
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('booking_charge_waivers', 2);
        $this->assertSame('125.50', $charge->fresh()->amount);
        $this->get('/finance/charges?state=waived')->assertInertia(fn (Assert $page) => $page
            ->where('activeTotal', '0.00')
            ->where('charges.data.0.waivedAmount', '125.50')
            ->where('charges.data.0.remainingAmount', '0.00')
            ->has('charges.data.0.waivers', 2));
    }

    public function test_policy_role_date_confirmation_and_command_conflict_are_enforced(): void
    {
        $f = $this->fixture();
        $booking = app(FinalBookings::class)->confirm($f['agent'], $f['draft']->id, $this->confirmation($f['receipt']->id));
        $key = (string) Str::uuid();
        $payload = $this->payload($booking->id, $key);
        $this->actingAs($f['agent'])->post('/finance/charges', $payload)->assertForbidden();
        $this->actingAs($f['admin'])->post('/finance/charges', $payload)->assertSessionHasErrors('amount');
        BusinessPolicy::create(['key' => 'charges', 'version' => 1, 'values' => ['late_fee_mode' => 'manual'], 'updated_by' => $f['admin']->id]);
        $this->post('/finance/charges', [...$payload, 'confirmed' => false])->assertSessionHasErrors('confirmed');
        $this->post('/finance/charges', [...$payload, 'business_date' => '2020-01-01'])->assertSessionHasErrors('business_date');
        $this->post('/finance/charges', $payload)->assertSessionHasNoErrors();
        $this->post('/finance/charges', [...$payload, 'amount' => '126.00'])->assertSessionHasErrors('conflict');
        $this->assertDatabaseCount('booking_charges', 1);
    }
}
