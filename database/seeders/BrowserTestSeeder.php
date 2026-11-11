<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BrowserTestSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing', 'e2e']) || DB::connection()->getDatabaseName() !== 'estateflow_test') {
            throw new RuntimeException('Browser fixtures require a local/testing estateflow_test database.');
        }
        $password = getenv('E2E_PASSWORD');
        if (! is_string($password) || strlen($password) < 12) {
            throw new RuntimeException('Set a synthetic E2E_PASSWORD of at least 12 characters.');
        }
        $this->call(RolePermissionSeeder::class);
        $fixtures = [
            'admin' => ['DEMO Admin', 'super_admin'],
            'office-admin' => ['DEMO Office Admin', 'admin'],
            'accountant' => ['DEMO Accountant', 'accountant'],
            'agent' => ['DEMO Agent', 'sales_agent'],
            'booking' => ['DEMO Booking Agent', 'sales_agent'],
        ];
        foreach ($fixtures as $name => [$displayName, $role]) {
            $user = User::firstOrNew(['email' => 'demo.'.$name.'@estateflow.test']);
            $user->fill(['name' => $displayName, 'password' => $password]);
            $user->email_verified_at = Carbon::now();
            $user->save();
            $user->syncRoles([$role]);
        }
    }
}
