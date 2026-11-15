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
    protected $signature = 'estateflow:staff {email} {--name=} {--phone=} {--role=sales_agent} {--match-email=} {--password-file=}';

    protected $description = 'Provision a staff account from a trusted operator console (password is prompted securely)';

    public function handle(): int
    {
        $matchEmail = strtolower((string) ($this->option('match-email') ?: $this->argument('email')));
        $existing = User::query()->where('email', $matchEmail)->first();
        $password = null;
        if (! $existing) {
            $passwordFile = (string) $this->option('password-file');
            if ($passwordFile !== '') {
                if (! is_file($passwordFile) || ! is_readable($passwordFile)) {
                    $this->error('The password file is not readable.');

                    return self::FAILURE;
                }
                $contents = file_get_contents($passwordFile);
                if ($contents === false) {
                    $this->error('The password file could not be read.');

                    return self::FAILURE;
                }
                $password = rtrim($contents, "\r\n");
            } else {
                $password = $this->secret('Password (at least 12 characters)');
            }
        }
        $values = [
            'email' => strtolower((string) $this->argument('email')),
            'name' => $this->option('name'),
            'phone' => $this->option('phone'),
            'role' => $this->option('role'),
            'password' => $password,
        ];
        $validator = Validator::make($values, [
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($existing?->id)],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'regex:/^03[0-9]{9}$/'],
            'role' => ['required', Rule::in(['super_admin', 'admin', 'accountant', 'sales_agent'])],
            'password' => [$existing ? 'nullable' : 'required', 'string', 'min:12'],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $this->call('db:seed', ['--class' => RolePermissionSeeder::class, '--force' => true]);
        DB::transaction(function () use ($values, $existing, $matchEmail) {
            $user = $existing
                ? User::query()->lockForUpdate()->findOrFail($existing->id)
                : new User;
            $before = $user->exists ? $user->only(['name', 'email', 'phone']) + ['roles' => $user->getRoleNames()->all()] : null;
            $emailChanged = $user->exists && $user->email !== $values['email'];
            $user->fill(collect($values)->only(['name', 'email', 'phone', 'password'])->filter(fn ($value) => $value !== null)->all());
            if ($emailChanged) {
                $user->email_verified_at = null;
            }
            $user->save();
            $user->syncRoles([$values['role']]);
            AuditEvent::create([
                'actor_id' => null,
                'subject_user_id' => $user->id,
                'event' => $existing ? 'access.staff_profile_updated' : 'access.staff_provisioned',
                'metadata' => ['channel' => 'trusted_console', 'matched_email' => $matchEmail, 'before' => $before, 'after' => $user->only(['name', 'email', 'phone']) + ['roles' => [$values['role']]]],
            ]);
        });

        $this->info($existing ? 'Staff identity and role updated; the existing password was preserved.' : 'Staff account created. Email verification is required before workspace access.');

        return self::SUCCESS;
    }
}
