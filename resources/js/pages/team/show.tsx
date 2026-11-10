import { Head } from '@inertiajs/react';

export default function StaffProfile({
    staff,
}: {
    staff: { name: string; email: string; roles: string[] };
}) {
    return (
        <>
            <Head title="Staff profile" />
            <div className="mx-auto w-full max-w-3xl p-6 md:p-8">
                <h1 className="text-2xl font-semibold">{staff.name}</h1>
                <dl className="mt-6 space-y-5 rounded-xl border bg-card p-6">
                    <div>
                        <dt className="text-sm text-muted-foreground">Email</dt>
                        <dd className="mt-1">{staff.email}</dd>
                    </div>
                    <div>
                        <dt className="text-sm text-muted-foreground">
                            Assigned roles
                        </dt>
                        <dd className="mt-1">
                            {staff.roles.join(', ') || 'No role'}
                        </dd>
                    </div>
                </dl>
            </div>
        </>
    );
}
StaffProfile.layout = { breadcrumbs: [{ title: 'Staff profile', href: '#' }] };
