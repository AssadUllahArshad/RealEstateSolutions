<?php

// Trusted development launcher only; no HTTP route invokes this script.
use App\Models\AuditEvent;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
if (! $app->environment('local') || config('database.default') !== 'mysql' || config('database.connections.mysql.host') !== '127.0.0.1' || (string) config('database.connections.mysql.port') !== '33084' || config('database.connections.mysql.database') !== 'estateflow') {
    fwrite(STDERR, "Launcher requires the existing local estateflow database at 127.0.0.1:33084. No data was changed.\n");
    exit(1);
}
if (($argv[1] ?? '') === 'config') {
    echo "Local configuration verified.\n";
    exit(0);
}
if (! str_starts_with(DB::selectOne('SELECT VERSION() AS version')->version, '8.4.')) {
    throw new RuntimeException('Actual MySQL 8.4 required.');
}
if (! in_array($argv[1] ?? '', ['login', 'accountant'], true)) {
    throw new RuntimeException('Expected config or login command.');
}
$migrationStatus = $kernel->call('migrate', ['--no-interaction' => true]);
echo $kernel->output();
if ($migrationStatus !== 0) {
    throw new RuntimeException('Local migrations failed.');
}
if ($kernel->call('db:seed', ['--class' => RolePermissionSeeder::class, '--no-interaction' => true]) !== 0) {
    throw new RuntimeException('Local permissions could not be prepared.');
}
$accountant = ($argv[1] ?? '') === 'accountant';
$email = $accountant ? 'local.accountant@estateflow.test' : 'local.admin@estateflow.test';
if (User::where('email', $email)->exists()) {
    echo "Existing local test account retained; no password or role was reset.\n";
    exit(0);
}
DB::transaction(function () use ($email, $accountant): void {
    $password = bin2hex(random_bytes(18));
    $user = new User(['name' => $accountant ? 'Local Test Accountant' : 'Local Test Admin', 'email' => $email, 'password' => $password]);
    $user->email_verified_at = now();
    $user->save();
    $role = $accountant ? 'accountant' : 'super_admin';
    $user->assignRole($role);
    AuditEvent::create([
        'actor_id' => null, 'subject_user_id' => $user->id, 'event' => 'access.staff_provisioned',
        'metadata' => ['channel' => 'guarded_local_launcher', 'role' => $role, 'verified_for_local_testing' => true],
    ]);
    $path = base_path($accountant ? '.runtime/local-accountant-login.txt' : '.runtime/local-login.txt');
    if (file_put_contents($path, "EstateFlow LOCAL TESTING ONLY\r\nURL: http://127.0.0.1:18080/login\r\nEmail: {$email}\r\nPassword: {$password}\r\n\r\nPrivate local credentials. Do not commit or share this file.\r\n") === false) {
        throw new RuntimeException('Could not save local login details; account creation rolled back.');
    }
});
echo "Local test account created. Credentials saved privately under .runtime/.\n";
