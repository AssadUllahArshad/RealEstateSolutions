import { Head, Link, router } from '@inertiajs/react';
import { CalendarDays, ArrowUpRight } from 'lucide-react';
import { EmptyState, PageHeading, Pager } from '@/components/office-ui';
import type { FollowUpRecord } from '@/pages/customers/show';
export default function FollowUps({
    followUps,
    state,
    today,
}: {
    followUps: {
        data: FollowUpRecord[];
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    state: string;
    today: string;
}) {
    return (
        <>
            <Head title="Follow-ups" />
            <div className="ef-page">
                <PageHeading
                    eyebrow="Relationship desk"
                    title="Follow-ups"
                    description="A clear view of the calls, conversations and visits that need your attention."
                />
                <div className="ef-panel overflow-hidden">
                    <div className="flex flex-wrap items-center justify-between gap-4 border-b p-5">
                        <div className="flex flex-wrap gap-1 rounded-xl bg-muted/60 p-1">
                            {[
                                ['open', 'Open tasks'],
                                ['today', 'Due today'],
                                ['overdue', 'Overdue'],
                                ['completed', 'Completed'],
                            ].map(([value, label]) => (
                                <button
                                    className={`rounded-lg px-4 py-2 text-xs font-semibold transition ${state === value ? 'bg-card text-blue-600 shadow-sm' : 'text-muted-foreground hover:text-foreground'}`}
                                    aria-pressed={state === value}
                                    key={value}
                                    onClick={() =>
                                        router.get('/follow-ups', {
                                            state: value,
                                        })
                                    }
                                >
                                    {label}
                                </button>
                            ))}
                        </div>
                        <span className="text-xs text-muted-foreground">
                            {followUps.total} matching tasks
                        </span>
                    </div>
                    {followUps.data.length ? (
                        <div className="overflow-x-auto">
                            <table className="ef-table">
                                <caption className="sr-only">
                                    Customer follow-up tasks
                                </caption>
                                <thead>
                                    <tr>
                                        {[
                                            'Customer',
                                            'Follow-up',
                                            'Due date',
                                            'Status',
                                            '',
                                        ].map((s, i) => (
                                            <th key={i}>{s}</th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {followUps.data.map((task) => (
                                        <tr key={task.id}>
                                            <td className="font-semibold">
                                                {task.customer?.name}
                                                <p className="mt-1 text-xs font-normal text-muted-foreground">
                                                    {task.customer?.phone}
                                                </p>
                                            </td>
                                            <td className="max-w-md min-w-56 leading-6 break-words whitespace-pre-wrap">
                                                {task.note}
                                            </td>
                                            <td className="whitespace-nowrap">
                                                <span className="inline-flex items-center gap-2 text-xs">
                                                    <CalendarDays className="size-4 text-muted-foreground" />
                                                    {task.due_date}
                                                </span>
                                            </td>
                                            <td>
                                                <span
                                                    className={`rounded-full px-2.5 py-1 text-[11px] font-semibold whitespace-nowrap ${task.completed_at ? 'bg-emerald-50 text-emerald-700' : task.due_date < today ? 'bg-rose-50 text-rose-700' : 'bg-blue-50 text-blue-700'}`}
                                                >
                                                    {task.completed_at
                                                        ? 'Completed'
                                                        : task.due_date < today
                                                          ? 'Overdue'
                                                          : task.due_date ===
                                                              today
                                                            ? 'Due today'
                                                            : 'Upcoming'}
                                                </span>
                                            </td>
                                            <td>
                                                <Link
                                                    className="ef-text-link whitespace-nowrap"
                                                    href={`/customers/${task.customer?.id}`}
                                                >
                                                    Open customer
                                                    <ArrowUpRight className="size-4" />
                                                </Link>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <EmptyState
                            title="Your desk is clear"
                            description="No follow-ups match this view. Add a follow-up from any customer profile."
                        >
                            <Link href="/customers" className="ef-text-link">
                                Browse customers
                                <ArrowUpRight className="size-4" />
                            </Link>
                        </EmptyState>
                    )}
                    <Pager {...followUps} />
                </div>
            </div>
        </>
    );
}
FollowUps.layout = {
    breadcrumbs: [{ title: 'Follow-ups', href: '/follow-ups' }],
};
