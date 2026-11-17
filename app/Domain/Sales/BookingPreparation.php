<?php

namespace App\Domain\Sales;

use App\Domain\Configuration\BusinessPolicies;
use App\Domain\Configuration\PolicyDefinitions;
use App\Domain\CRM\Customers;
use App\Domain\Finance\ReceiptCredit;
use App\Models\BusinessPolicy;
use App\Models\CustomerDocument;
use App\Models\PaymentReceipt;
use App\Models\User;
use App\Support\BusinessClock;

class BookingPreparation
{
    /**
     * Read-only guidance for a saved proposal, never authority to finalize a booking.
     *
     * @return array{checks:list<array{key:string,title:string,state:string,detail:string,href:?string}>,observed_at:string}
     */
    public function forDraft(User $actor, int $id): array
    {
        $draft = app(BookingDrafts::class)->query($actor)->with('plot.activeHold')->findOrFail($id);
        $customer = app(Customers::class)->query($actor)->findOrFail($draft->customer_id);
        $policies = app(BusinessPolicies::class);
        $booking = BusinessPolicy::query()->whereKey('booking')->first();
        $values = $policies->effective('booking', $booking?->values);
        $checks = [];
        $add = function (string $key, string $title, string $state, string $detail, ?string $href = null) use (&$checks): void {
            $checks[] = compact('key', 'title', 'state', 'detail', 'href');
        };
        $identity = filled($customer->cnic) && filled($customer->address);
        $add('identity', 'Customer identity', $identity ? 'recorded' : 'action', $identity ? 'CNIC and address recorded. Check accuracy with the purchaser.' : 'Add the customer CNIC and address to the private profile.', '/customers/'.$customer->id);

        $kinds = CustomerDocument::where('customer_id', $customer->id)->distinct()->pluck('kind')->all();
        $required = $values['required_documents'];
        $labels = PolicyDefinitions::all()['booking']['fields']['required_documents']['options'];
        $missing = array_values(array_diff($required, $kinds));
        $configured = in_array('signed_booking_form', $required, true);
        $add('documents', 'Booking documents', ! $configured ? 'configuration' : ($missing === [] ? 'recorded' : 'action'), ! $configured ? 'Main admin must save a document checklist including the signed booking form.' : ($missing === [] ? 'Required document types uploaded. Staff must verify their contents and signatures.' : 'Upload: '.implode(', ', array_map(fn ($kind) => $labels[$kind], $missing)).'.'), '/customers/'.$customer->id);

        $hold = $draft->plot->activeHold;
        $state = 'recorded';
        $detail = 'No active hold. Availability can change before final booking.';
        if ($draft->plot->activeBooking) {
            $state = 'action';
            $detail = 'This plot has a confirmed booking.';
        } elseif ($hold?->kind === 'block') {
            $state = 'action';
            $detail = 'Plot is blocked. An authorized admin must review it.';
        } elseif ($hold) {
            if (! $values['held_plot_access']) {
                $state = 'configuration';
                $detail = 'Plot is on hold. Main admin must configure who may book held plots.';
            } elseif ($values['held_plot_access'] === 'holder_or_admin' && $hold->created_by !== $actor->id && ! $actor->hasAnyRole(['admin', 'super_admin'])) {
                $state = 'action';
                $detail = 'Another staff member holds this plot. Ask the holder or an authorized admin to proceed.';
            } else {
                $detail = 'Plot is on hold; your role meets the configured access rule. Final booking must recheck availability.';
            }
        }
        $add('plot', 'Plot availability', $state, $detail, '/inventory/plots/'.$draft->plot_id.'/hold');

        $missingSettings = $policies->missing('booking', $values);
        $add('policy', 'Booking rules', $missingSettings === [] ? 'recorded' : 'configuration', $missingSettings === [] ? 'Booking rules are ready. Final contracts retain the effective rule snapshot.' : 'Main admin must finish the booking requirements in Business settings.');
        $purchasers = app(DraftPurchasers::class)->listing($actor, $id);
        $complete = $purchasers !== [] && collect($purchasers)->every(fn ($row) => $row['identity_complete']);
        $add('purchasers', 'Legal purchasers', $complete ? 'recorded' : 'action', $complete ? 'Proposed purchasers and shares recorded. Verify identity, consent and supporting documents before final booking.' : 'Record single or joint purchasers below. Each purchaser needs a complete private identity profile.');
        $terms = app(DraftTerms::class)->context($actor, $id);
        $prepared = $terms['term'] && ! $terms['stale'];
        $receiptQuery = http_build_query(array_filter([
            'customer_id' => $customer->id,
            'booking_draft_id' => $id,
            'amount' => $prepared ? $terms['term']->snapshot['down_payment'] : null,
        ], fn ($value) => $value !== null));
        $availableCredit = '0.00';
        foreach (PaymentReceipt::query()
            ->where('customer_id', $customer->id)
            ->where('state', 'recognized')
            ->whereDate('recognized_on', '<=', app(BusinessClock::class)->today())
            ->orderBy('id')->get() as $receipt) {
            $available = bcsub($receipt->amount, app(ReceiptCredit::class)->used($receipt->id), 2);
            if (bccomp($available, '0', 2) > 0) {
                $availableCredit = bcadd($availableCredit, $available, 2);
            }
        }
        $payableDownPayment = $prepared ? $terms['term']->snapshot['down_payment'] : null;
        $paymentReady = $payableDownPayment !== null && bccomp($availableCredit, $payableDownPayment, 2) >= 0;
        if ($paymentReady) {
            $planned = $terms['term']->snapshot['planned_down_payment'] ?? $payableDownPayment;
            $discount = $terms['term']->snapshot['discount_applied_to_down_payment'] ?? '0.00';
            $surplus = bcsub($availableCredit, $payableDownPayment, 2);
            $paymentDetail = 'Recognized credit '.$this->money($availableCredit).' is sufficient. Agreed down payment '.$this->money($planned).' less discount '.$this->money($discount).' gives payable '.$this->money($payableDownPayment).'. Allocate the payable amount during confirmation; '.$this->money($surplus).' remains customer credit.';
        } elseif ($payableDownPayment !== null && bccomp($availableCredit, '0', 2) > 0) {
            $shortfall = bcsub($payableDownPayment, $availableCredit, 2);
            $paymentDetail = 'Recognized credit '.$this->money($availableCredit).' is short of the payable down payment '.$this->money($payableDownPayment).' by '.$this->money($shortfall).'.';
        } else {
            $paymentDetail = 'Prepare and issue the customer receipt so it becomes recognized credit, then allocate it during final confirmation.';
        }
        $add('payment', 'Verified down payment', $paymentReady ? 'recorded' : 'action', $paymentDetail, $paymentReady ? '/sales/'.$id.'/confirm' : '/finance/receipts/new?'.$receiptQuery);
        $add('contract', 'Pricing and payment plan', $prepared ? 'recorded' : 'action', $prepared ? 'Proposed pricing and schedule saved for the current proposal and policy versions. Final contract and booking confirmation remain separate.' : 'Prepare pricing and a payment plan, or recalculate when the proposal or settings change.', '/sales/'.$id.'/terms');

        return ['checks' => $checks, 'observed_at' => now()->toIso8601String()];
    }

    /** @param numeric-string $amount */
    private function money(string $amount): string
    {
        [$whole, $decimal] = array_pad(explode('.', bcadd($amount, '0', 2), 2), 2, '00');

        return 'PKR '.preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $whole).'.'.$decimal;
    }
}
