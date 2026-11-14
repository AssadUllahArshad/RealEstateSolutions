<?php

namespace Database\Seeders;

use App\Domain\Configuration\BusinessPolicies;
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
use App\Models\Society;
use App\Models\User;
use App\Support\BusinessClock;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class BrowserBookingSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing', 'e2e']) || DB::connection()->getDatabaseName() !== 'estateflow_test') {
            throw new RuntimeException('Browser booking fixtures require the isolated estateflow_test database.');
        }

        $this->call(BrowserTestSeeder::class);

        $admin = User::where('email', 'demo.admin@estateflow.test')->firstOrFail();
        $agent = User::where('email', 'demo.booking@estateflow.test')->firstOrFail();
        $this->configurePolicies($admin);

        $society = Society::findOrFail(1);
        if (! filled($society->name)) {
            app(Catalogue::class)->society($admin, [
                'name' => 'DEMO Browser Estate',
                'office_name' => 'DEMO Browser Office',
            ]);
        }

        $fixtures = [];
        foreach (['desktop', 'mobile'] as $project) {
            $fixtures[$project] = $this->createFixture($admin, $agent, $project);
        }

        $directory = base_path('.runtime');
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Could not create the browser fixture directory.');
        }

        $written = file_put_contents(
            $directory.DIRECTORY_SEPARATOR.'booking-browser.json',
            json_encode($fixtures, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
            LOCK_EX,
        );
        if ($written === false) {
            throw new RuntimeException('Could not write browser booking fixture IDs.');
        }
    }

    private function configurePolicies(User $admin): void
    {
        $policies = app(BusinessPolicies::class);
        $today = app(BusinessClock::class)->today();
        $values = [
            'booking' => [
                'down_payment_type' => 'fixed',
                'down_payment_value' => '100.01',
                'required_documents' => ['signed_booking_form'],
                'held_plot_access' => 'any_agent',
            ],
            'installments' => [
                'frequencies' => ['monthly'],
                'first_due_date' => 'entered_per_booking',
                'month_end' => 'clamp',
                'rounding' => 'half_up',
                'residual' => 'last',
            ],
            'collections' => [
                'methods' => ['cash'],
                'allocation' => 'oldest_first',
                'cheque_recognition' => 'cleared',
                'overpayment' => 'unallocated',
            ],
            'receipt_posting' => [
                'prefix' => 'E2EBOOK',
                'starting_number' => 1,
                'padding' => 6,
                'open_from' => $today,
                'max_backdate_days' => 0,
                'unallocated' => 'allow',
                'routing' => 'matching',
                'duplicates' => 'global_method',
            ],
        ];

        foreach ($values as $key => $overrides) {
            $record = BusinessPolicy::find($key);
            $policies->save($admin, $key, [
                'version' => $record->version ?? 0,
                'values' => array_replace($policies->defaults($key), $overrides),
            ]);
        }
    }

    /** @return array{draft:int,receipt:string} */
    private function createFixture(User $admin, User $agent, string $project): array
    {
        $token = strtoupper(Str::random(8));
        $catalogue = app(Catalogue::class);
        $block = $catalogue->block($admin, [
            'code' => 'E2E-'.$token,
            'name' => 'DEMO '.$project.' booking block '.$token,
        ]);
        $size = $catalogue->size($admin, [
            'label' => 'DEMO '.$project.' booking size '.$token,
            'area_marla' => '5.00',
        ]);
        $plot = $catalogue->plot($admin, [
            'block_id' => $block->id,
            'plot_size_id' => $size->id,
            'number' => 'E2E-'.$project.'-'.$token,
            'list_price' => '1000.01',
        ]);
        $customer = app(Customers::class)->save($agent, [
            'name' => 'DEMO '.$project.' booking purchaser '.$token,
            'cnic' => (string) random_int(1000000000000, 9999999999999),
            'address' => 'DEMO browser test address '.$token,
            'creation_key' => (string) Str::uuid(),
        ]);
        $draft = app(BookingDrafts::class)->save($agent, [
            'customer_id' => $customer->id,
            'plot_id' => $plot->id,
            'quoted_price' => '1000.01',
            'creation_key' => (string) Str::uuid(),
        ]);
        $policyVersions = BusinessPolicy::whereIn('key', ['booking', 'installments'])
            ->pluck('version', 'key')
            ->all();

        app(DraftPurchasers::class)->save($agent, $draft->id, [
            'version' => 0,
            'purchasers' => [['customer_id' => $customer->id, 'share' => '100']],
        ]);
        app(DraftTerms::class)->save($agent, $draft->id, [
            'version' => 0,
            'source_version' => $draft->version,
            'policy_versions' => [
                'booking' => (int) $policyVersions['booking'],
                'installments' => (int) $policyVersions['installments'],
            ],
            'discount' => '0',
            'down_payment' => '100.01',
            'frequency' => 'monthly',
            'count' => 3,
            'first_due_date' => '2028-01-31',
            'custom' => [],
        ]);
        CustomerDocument::create([
            'customer_id' => $customer->id,
            'kind' => 'signed_booking_form',
            'path' => 'test/final-booking-'.$project.'-'.$token.'.pdf',
            'mime' => 'application/pdf',
            'bytes' => 100,
            'uploaded_by' => $agent->id,
        ]);

        $account = app(Accounts::class)->save($admin, [
            'code' => 'E2E-'.$token,
            'name' => 'DEMO browser cash '.$token,
            'type' => 'cash',
            'active' => true,
            'creation_key' => (string) Str::uuid(),
        ]);
        $collections = BusinessPolicy::findOrFail('collections');
        $preparation = app(ReceiptDrafts::class)->save($agent, [
            'customer_id' => $customer->id,
            'financial_account_id' => $account->id,
            'amount' => '1000.01',
            'reported_on' => app(BusinessClock::class)->today(),
            'method' => 'cash',
            'policy_version' => $collections->version,
            'creation_key' => (string) Str::uuid(),
        ]);
        $receipt = app(ReceiptPosting::class)->accept($admin, $preparation->id, [
            'command_key' => (string) Str::uuid(),
            'expected_version' => 1,
            'posting_version' => BusinessPolicy::findOrFail('receipt_posting')->version,
            'collection_version' => $collections->version,
            'business_date' => app(BusinessClock::class)->today(),
            'note' => 'DEMO browser test payment evidence verified.',
            'confirmed' => true,
            'stage' => 'confirmed',
        ]);

        return ['draft' => $draft->id, 'receipt' => $receipt->number];
    }
}
