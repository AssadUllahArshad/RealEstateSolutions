<?php

namespace App\Console\Commands;

use App\Models\AuditEvent;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CreateStaff extends Command
{
    protected $signature = 'estateflow:staff {email} {--name=} {--role=sales_agent}';

    protected $description = 'Provision a staff account from a trusted operator console (password is prompted securely)';

    public function handle(): int
    {
        $values = [
            'email' => strtolower((string) $this->argument('email')),
            'name' => $this->option('name'),
            'role' => $this->option('role'),
            'password' => $this->secret('Password (at least 12 characters)'),
        ];
        $validator = Validator::make($values, [
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required', Rule::in(['super_admin', 'admin', 'accountant', 'sales_agent'])],
            'password' => ['required', 'string', 'min:12'],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $this->call('db:seed', ['--class' => RolePermissionSeeder::class, '--force' => true]);
        DB::transaction(function () use ($values) {
            $user = User::create(collect($values)->only(['name', 'email', 'password'])->all());
            $user->assignRole($values['role']);
            AuditEvent::create([
                'actor_id' => null,
                'subject_user_id' => $user->id,
                'event' => 'access.staff_provisioned',
                'metadata' => ['channel' => 'trusted_console', 'role' => $values['role']],
            ]);
        });

        $this->info('Staff account created. Email verification is required before workspace access.');

        return self::SUCCESS;
    }
}
