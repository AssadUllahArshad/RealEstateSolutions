<?php

namespace App\Domain\Access\Actions;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class AssignUserRole
{
    public function handle(User $actor, User $subject, string $roleName): void
    {
        DB::transaction(function () use ($actor, $subject, $roleName) {
            // Serialize all role changes, including revocation of the acting administrator.
            Role::query()->orderBy('id')->lockForUpdate()->get();
            $actor = User::query()->findOrFail($actor->id);
            $subject = User::query()->lockForUpdate()->findOrFail($subject->id);
            Gate::forUser($actor)->authorize('updateAccess', $subject);

            if (! in_array($roleName, ['super_admin', 'admin', 'accountant', 'sales_agent'], true)) {
                throw ValidationException::withMessages(['role' => 'Choose a supported staff role.']);
            }

            $role = Role::findByName($roleName, 'web');
            $before = $subject->getRoleNames()->sort()->values()->all();
            if ($before === [$roleName]) {
                return;
            }

            $subject->syncRoles([$role]);
            AuditEvent::create([
                'actor_id' => $actor->id,
                'subject_user_id' => $subject->id,
                'event' => 'access.role_changed',
                'metadata' => ['before' => $before, 'after' => [$roleName]],
            ]);
        }, attempts: 3);
    }
}
