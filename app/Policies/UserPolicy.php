<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('users.view');
    }

    public function view(User $actor, User $subject): bool
    {
        return $actor->id === $subject->id || $actor->can('users.view');
    }

    public function updateAccess(User $actor, User $subject): bool
    {
        return $actor->id !== $subject->id && $actor->can('access.manage');
    }
}
