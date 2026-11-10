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
        $this->artisan('estateflow:staff', ['email' => 'staff@example.test', '--name' => 'DEMO Staff'])
            ->expectsQuestion('Password (at least 12 characters)', 'synthetic-password-123')
            ->assertSuccessful();
        $user = User::query()->where('email', 'staff@example.test')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        $this->assertTrue($user->hasRole('sales_agent'));
        $this->assertFalse($user->can('access.manage'));
        $this->assertDatabaseHas('audit_events', [
            'subject_user_id' => $user->id,
            'event' => 'access.staff_provisioned',
        ]);
    }

    public function test_invalid_provisioning_does_not_create_staff_or_audit(): void
    {
        $this->artisan('estateflow:staff', ['email' => 'invalid', '--name' => 'DEMO Staff', '--role' => 'invented'])
            ->expectsQuestion('Password (at least 12 characters)', 'short')
            ->assertFailed();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('audit_events', 0);
    }
}
