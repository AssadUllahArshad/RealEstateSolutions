import { Form, Head, Link, usePage } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';

type Staff = { id: number; name: string; email: string; roles: string[] };
type Props = {
    users: {
        data: Staff[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    recentChanges: {
        id: number;
        actor_id: number;
        subject_user_id: number;
        metadata: { before: string[]; after: string[] };
        created_at: string;
    }[];
};
const labels: Record<string, string> = {
    super_admin: 'Main admin',
    admin: 'Sub admin',
    accountant: 'Accountant',
    sales_agent: 'Sales agent',
};

export default function Team({ users, recentChanges }: Props) {
    const { auth } = usePage().props;
    return (
        <>
            <Head title="Team & access" />
            <div className="mx-auto w-full max-w-6xl space-y-6 p-5 md:p-8">
                <header>
                    <p className="mb-2 text-xs font-semibold tracking-widest text-muted-foreground uppercase">
                        Administration
                    </p>
                    <h1 className="text-3xl font-semibold tracking-tight">
                        Team & access
                    </h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        Review staff accounts and their assigned roles.
                    </p>
                </header>
                <div className="rounded-xl border bg-card p-4 text-sm text-muted-foreground">
                    Role changes are recorded. Your own role cannot be changed
                    here. Operational permissions will be introduced with each
                    module.
                </div>
                <div className="overflow-x-auto rounded-xl border bg-card">
                    <table className="w-full text-start text-sm">
                        <caption className="sr-only">
                            Staff accounts and roles
                        </caption>
                        <thead className="border-b bg-muted/40">
                            <tr>
                                <th className="p-4">Staff member</th>
                                <th className="p-4">Assigned role</th>
                                <th className="p-4">Change access</th>
                            </tr>
                        </thead>
                        <tbody>
                            {users.data.map((staff) => (
                                <tr
                                    key={staff.id}
                                    className="border-b last:border-0"
                                >
                                    <td className="p-4">
                                        <Link
                                            href={`/team/${staff.id}`}
                                            className="font-medium underline-offset-4 hover:underline"
                                        >
                                            {staff.name}
                                        </Link>
                                        <p className="mt-1 text-muted-foreground">
                                            {staff.email}
                                        </p>
                                    </td>
                                    <td className="p-4">
                                        {staff.roles
                                            .map((role) => labels[role] ?? role)
                                            .join(', ') || 'No role'}
                                    </td>
                                    <td className="p-4">
                                        {auth.abilities.includes(
                                            'access.manage',
                                        ) && staff.id !== auth.user.id ? (
                                            <Form
                                                action={`/team/${staff.id}/access`}
                                                method="patch"
                                            >
                                                {({ processing, errors }) => (
                                                    <>
                                                        <div className="flex gap-2">
                                                            <select
                                                                aria-label={`Role for ${staff.name}`}
                                                                name="role"
                                                                defaultValue={
                                                                    staff
                                                                        .roles[0] ??
                                                                    ''
                                                                }
                                                                className="rounded-md border bg-background px-3 py-2"
                                                                required
                                                            >
                                                                <option
                                                                    value=""
                                                                    disabled
                                                                >
                                                                    Choose role
                                                                </option>
                                                                {Object.entries(
                                                                    labels,
                                                                ).map(
                                                                    ([
                                                                        value,
                                                                        label,
                                                                    ]) => (
                                                                        <option
                                                                            key={
                                                                                value
                                                                            }
                                                                            value={
                                                                                value
                                                                            }
                                                                        >
                                                                            {
                                                                                label
                                                                            }
                                                                        </option>
                                                                    ),
                                                                )}
                                                            </select>
                                                            <Button
                                                                variant="outline"
                                                                disabled={
                                                                    processing
                                                                }
                                                            >
                                                                Save role
                                                            </Button>
                                                        </div>
                                                        <InputError
                                                            message={
                                                                errors.role
                                                            }
                                                        />
                                                    </>
                                                )}
                                            </Form>
                                        ) : (
                                            <span className="text-muted-foreground">
                                                {staff.id === auth.user.id
                                                    ? 'Your account'
                                                    : 'View only'}
                                            </span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <nav
                    className="flex justify-between text-sm"
                    aria-label="Team pages"
                >
                    {users.prev_page_url ? (
                        <Link href={users.prev_page_url}>Previous</Link>
                    ) : (
                        <span />
                    )}
                    {users.next_page_url && (
                        <Link href={users.next_page_url}>Next</Link>
                    )}
                </nav>
                <section className="rounded-xl border bg-card p-5">
                    <h2 className="font-semibold">Recent access changes</h2>
                    {recentChanges.length === 0 ? (
                        <p className="mt-3 text-sm text-muted-foreground">
                            No role changes have been recorded.
                        </p>
                    ) : (
                        <ul className="mt-3 divide-y">
                            {recentChanges.map((event) => (
                                <li key={event.id} className="py-3 text-sm">
                                    <p>
                                        Staff #{event.subject_user_id}:{' '}
                                        {event.metadata.before.join(', ') ||
                                            'No role'}{' '}
                                        → {event.metadata.after.join(', ')}
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        By staff #{event.actor_id} ·{' '}
                                        {new Date(
                                            event.created_at,
                                        ).toLocaleString('en-PK', {
                                            timeZone: 'Asia/Karachi',
                                        })}{' '}
                                        (Asia/Karachi)
                                    </p>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </>
    );
}
Team.layout = { breadcrumbs: [{ title: 'Team & access', href: '/team' }] };
