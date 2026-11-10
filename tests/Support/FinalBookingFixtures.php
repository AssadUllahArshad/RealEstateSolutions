<?php

namespace Tests\Support;

use App\Domain\CRM\Customers;
use App\Domain\Finance\Accounts;
use App\Domain\Finance\ReceiptDrafts;
use App\Domain\Finance\ReceiptPosting;
use App\Domain\Inventory\Catalogue;
use App\Domain\Sales\BookingDrafts;
use App\Domain\Sales\DraftPurchasers;
use App\Domain\Sales\DraftTerms;
use App\Models\BusinessPolicy;
use App\Models\CustomerDocument;
use App\Models\User;
use App\Support\BusinessClock;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Str;

trait FinalBookingFixtures
{
    /** @return array<string,mixed> */
    private function fixture(string $receiptAmount = '150.02'): array
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create()->assignRole('super_admin');
        $agent = User::factory()->create()->assignRole('sales_agent');
        $customer = app(Customers::class)->save($agent, ['name' => 'DEMO Booking purchaser', 'cnic' => '3520212345671', 'address' => 'DEMO Private address', 'creation_key' => (string) Str::uuid()]);
        $catalogue = app(Catalogue::class);
        $catalogue->society($admin, ['name' => 'DEMO Booking Society']);
        $block = $catalogue->block($admin, ['code' => 'BOOK', 'name' => 'DEMO Booking']);
        $size = $catalogue->size($admin, ['label' => 'DEMO 5 Marla', 'area_marla' => '5']);
        $plot = $catalogue->plot($admin, ['block_id' => $block->id, 'plot_size_id' => $size->id, 'number' => 'BOOK-1']);
        $draft = app(BookingDrafts::class)->save($agent, ['customer_id' => $customer->id, 'plot_id' => $plot->id, 'quoted_price' => '1000.01', 'creation_key' => (string) Str::uuid()]);
        foreach (['booking' => ['down_payment_type' => 'fixed', 'down_payment_value' => '100.01', 'required_documents' => ['signed_booking_form'], 'held_plot_access' => 'holder_or_admin'],
            'installments' => ['frequencies' => ['monthly'], 'first_due_date' => 'entered_per_booking', 'month_end' => 'clamp', 'rounding' => 'half_up', 'residual' => 'last'],
            'collections' => ['methods' => ['cash', 'cheque'], 'allocation' => 'oldest_first', 'cheque_recognition' => 'cleared', 'overpayment' => 'unallocated'],
            'receipt_posting' => ['prefix' => 'BOOK', 'starting_number' => 1, 'padding' => 6, 'open_from' => app(BusinessClock::class)->today(), 'max_backdate_days' => 0, 'unallocated' => 'allow', 'routing' => 'matching', 'duplicates' => 'global_method'],
        ] as $key => $values) {
            BusinessPolicy::create(['key' => $key, 'values' => $values, 'version' => 1, 'updated_by' => $admin->id]);
        }
        app(DraftPurchasers::class)->save($agent, $draft->id, ['version' => 0, 'purchasers' => [['customer_id' => $customer->id, 'share' => '100']]]);
        app(DraftTerms::class)->save($agent, $draft->id, ['version' => 0, 'source_version' => 1, 'policy_versions' => ['booking' => 1, 'installments' => 1], 'discount' => '0', 'down_payment' => '100.01', 'frequency' => 'monthly', 'count' => 3, 'first_due_date' => '2028-01-31', 'custom' => []]);
        CustomerDocument::create(['customer_id' => $customer->id, 'kind' => 'signed_booking_form', 'path' => 'test/not-a-production-document.pdf', 'mime' => 'application/pdf', 'bytes' => 100, 'uploaded_by' => $agent->id]);
        $account = app(Accounts::class)->save($admin, ['code' => 'BOOK-CASH', 'name' => 'DEMO Cash', 'type' => 'cash', 'active' => true, 'creation_key' => (string) Str::uuid()]);
        $prep = app(ReceiptDrafts::class)->save($agent, ['customer_id' => $customer->id, 'financial_account_id' => $account->id, 'amount' => $receiptAmount, 'reported_on' => app(BusinessClock::class)->today(), 'method' => 'cash', 'policy_version' => 1, 'creation_key' => (string) Str::uuid()]);
        $receipt = app(ReceiptPosting::class)->accept($admin, $prep->id, ['command_key' => (string) Str::uuid(), 'expected_version' => 1, 'posting_version' => 1, 'collection_version' => 1, 'business_date' => app(BusinessClock::class)->today(), 'note' => 'DEMO actual receipt evidence verified', 'confirmed' => true, 'stage' => 'confirmed']);

        return compact('admin', 'agent', 'customer', 'plot', 'draft', 'receipt', 'account');
    }

    /** @return array<string,mixed> */
    private function confirmation(int $receipt): array
    {
        return ['command_key' => (string) Str::uuid(), 'source_version' => 1, 'purchasers_version' => 1, 'terms_version' => 1, 'confirmed' => true, 'allocations' => [['receipt_id' => $receipt, 'amount' => '100.01']]];
    }
}
