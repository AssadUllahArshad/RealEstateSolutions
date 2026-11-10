<?php

namespace App\Http\Controllers;

use App\Domain\Access\Actions\AssignUserRole;
use App\Http\Requests\UpdateUserAccessRequest;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', User::class);

        return Inertia::render('team/index', [
            'users' => User::query()->with('roles')->orderBy('name')->paginate(20)
                ->through(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $user->getRoleNames(),
                ]),
            'recentChanges' => AuditEvent::query()->where('event', 'access.role_changed')
                ->latest('id')->limit(10)->get(['id', 'actor_id', 'subject_user_id', 'metadata', 'created_at']),
        ]);
    }

    public function show(User $user): Response
    {
        Gate::authorize('view', $user);

        return Inertia::render('team/show', [
            'staff' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames(),
            ],
        ]);
    }

    public function update(UpdateUserAccessRequest $request, User $user, AssignUserRole $action): RedirectResponse
    {
        $action->handle($request->user(), $user, $request->validated('role'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Staff role updated.']);

        return back();
    }
}
