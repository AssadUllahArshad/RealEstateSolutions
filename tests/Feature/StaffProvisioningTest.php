<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_console_provisions_unverified_staff_with_an_audited_role(): void
    {
        $this->artisan('estateflow:staff', ['email' => 'staff@example.test', '--name' => 'DEMO Staff', '--phone' => '03001234567'])
            ->expectsQuestion('Password (at least 12 characters)', 'synthetic-password-123')
            ->assertSuccessful();
        $user = User::query()->where('email', 'staff@example.test')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        $this->assertSame('03001234567', $user->phone);
        $this->assertTrue($user->hasRole('sales_agent'));
        $this->assertFalse($user->can('access.manage'));
        $this->assertDatabaseHas('audit_events', [
            'subject_user_id' => $user->id,
            'event' => 'access.staff_provisioned',
        ]);
    }

    public function test_invalid_provisioning_does_not_create_staff_or_audit(): void
    {
        $this->artisan('estateflow:staff', ['email' => 'invalid', '--name' => 'DEMO Staff', '--phone' => 'bad', '--role' => 'invented'])
            ->expectsQuestion('Password (at least 12 characters)', 'short')
            ->assertFailed();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_console_can_rename_existing_staff_without_replacing_password(): void
    {
        $user = User::factory()->create(['name' => 'Old Name', 'email' => 'old@example.test', 'phone' => null]);
        $password = $user->password;

        $this->artisan('estateflow:staff', ['email' => 'amir.ali@datarealestate.online', '--match-email' => 'old@example.test', '--name' => 'Amir Ali', '--phone' => '03001234567', '--role' => 'super_admin'])
            ->assertSuccessful();

        $user->refresh();
        $this->assertSame('Amir Ali', $user->name);
        $this->assertSame('amir.ali@datarealestate.online', $user->email);
        $this->assertSame('03001234567', $user->phone);
        $this->assertSame($password, $user->password);
        $this->assertNull($user->email_verified_at);
        $this->assertTrue($user->hasRole('super_admin'));
        $this->assertDatabaseHas('audit_events', ['subject_user_id' => $user->id, 'event' => 'access.staff_profile_updated']);
    }

    public function test_console_can_read_a_new_password_from_a_private_file(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'estateflow-password-');
        $this->assertNotFalse($path);
        file_put_contents($path, "synthetic-file-password-123\n");

        try {
            $this->artisan('estateflow:staff', [
                'email' => 'file.staff@example.test',
                '--name' => 'File Staff',
                '--phone' => '03001234567',
                '--password-file' => $path,
            ])->assertSuccessful();
        } finally {
            @unlink($path);
        }

        $this->assertTrue(password_verify('synthetic-file-password-123', User::query()->where('email', 'file.staff@example.test')->firstOrFail()->password));
    }
}
