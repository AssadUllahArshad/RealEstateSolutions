import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    ArrowUpRight,
    Building2,
    CalendarCheck2,
    ChevronRight,
    ClipboardList,
    MapPinned,
    Plus,
    UsersRound,
    Activity,
    Settings2,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { EmptyState, PageHeading, StatCard } from '@/components/office-ui';
import { useTranslate } from '@/lib/i18n';
type Props = {
    businessDate: string;
    timezone: string;
    currency: string;
    society: { name: string | null; office_name: string | null } | null;
    stats: {
        drafts: number | null;
        plots: number | null;
        held: number | null;
        blocked: number | null;
        unheld: number | null;
        customers: number | null;
        followUps: number | null;
        overdue: number | null;
    };
    blocks: { id: number; code: string; name: string; plots_count: number }[];
    activity: {
        id: number;
        event: string;
        subject_id: number;
        created_at: string;
    }[];
    tasks: {
        id: number;
        note: string;
        due_date: string;
        customer: { id: number; name: string };
    }[];
};
export default function Workspace({
    businessDate,
    timezone,
    currency,
    society,
    stats,
    blocks,
    activity,
    tasks,
}: Props) {
    const { auth } = usePage().props;
    const t = useTranslate();
    const canConfigure = auth.abilities.includes('configuration.manage');
    return (
        <>
            <Head title="Overview" />
            <div className="ef-page">
                <PageHeading
                    eyebrow={t('Society operations')}
                    title={`${t('Welcome,')} ${auth.user.name.split(' ')[0]}.`}
                    description={`${society?.name ?? t('Your society')}${society?.office_name ? ` · ${society.office_name}` : ''} — ${t('your office at a glance.')}`}
                >
                    {auth.abilities.includes('customers.manage') && (
                        <Button asChild>
                            <Link href="/customers?new=1">
                                <Plus className="size-4" />
                                {t('New customer')}
                            </Link>
                        </Button>
                    )}
                    {stats.plots !== null && (
                        <Button variant="outline" asChild>
                            <Link href="/inventory">
                                <MapPinned className="size-4" />
                                {t('Browse plots')}
                            </Link>
                        </Button>
                    )}
                </PageHeading>
                <div className="flex flex-wrap items-center gap-x-5 gap-y-2 text-xs text-muted-foreground">
                    <span className="inline-flex items-center gap-2">
                        <span className="size-2 rounded-full bg-emerald-500" />
                        {t('Live office data')}
                    </span>
                    <span>{businessDate}</span>
                    <span>
                        {timezone} · {currency}
                    </span>
                </div>
                <section
                    aria-label={t('Office overview')}
                    className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
                >
                    {stats.plots !== null && (
                        <StatCard
                            title={t('Total plots')}
                            value={stats.plots}
                            detail={`${stats.unheld} ${t('with no active hold')} · ${stats.blocked} ${t('blocked')}`}
                            icon={Building2}
                            href="/inventory"
                        />
                    )}
                    {stats.held !== null && (
                        <StatCard
                            title={t('Active holds')}
                            value={stats.held}
                            detail={t('Manual release by authorized admins')}
                            icon={MapPinned}
                            href="/inventory?hold=reservation"
                            tone="amber"
                        />
                    )}
                    {stats.customers !== null && (
                        <StatCard
                            title={
                                auth.abilities.includes('customers.view_all')
                                    ? t('Customers')
                                    : t('My customers')
                            }
                            value={stats.customers}
                            detail={t('Contact records in your workspace')}
                            icon={UsersRound}
                            href="/customers"
                            tone="green"
                        />
                    )}
                    {stats.followUps !== null && (
                        <StatCard
                            title={t('Open follow-ups')}
                            value={stats.followUps}
                            detail={`${stats.overdue} ${t('past their follow-up date')}`}
                            icon={CalendarCheck2}
                            href="/follow-ups"
                            tone="rose"
                        />
                    )}
                </section>
                {stats.drafts !== null && (
                    <section className="ef-panel flex flex-wrap items-center justify-between gap-4 border-s-4 border-s-primary p-5 sm:p-6">
                        <div className="flex items-center gap-4">
                            <span className="rounded-xl bg-primary/10 p-3 text-primary">
                                <ClipboardList className="size-6" />
                            </span>
                            <div>
                                <h2 className="font-semibold">
                                    {t('Sales desk')} · {stats.drafts}{' '}
                                    {t('booking drafts')}
                                </h2>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    {t(
                                        'Customer proposals and quoted terms, ready to review.',
                                    )}
                                </p>
                            </div>
                        </div>
                        <Link className="ef-text-link" href="/sales">
                            {t('Open sales desk')}
                            <ArrowRight className="size-4" />
                        </Link>
                    </section>
                )}
                {canConfigure && !society?.name && (
                    <section className="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-blue-200 bg-blue-50 p-5 dark:border-blue-900 dark:bg-blue-950">
                        <div className="flex items-center gap-4">
                            <span className="rounded-xl bg-white p-3 text-blue-600 dark:bg-blue-900">
                                <Settings2 className="size-6" />
                            </span>
                            <div>
                                <h2 className="font-semibold">
                                    {t('Make this workspace yours')}
                                </h2>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {t(
                                        'Add your society name, office details, blocks and plot sizes.',
                                    )}
                                </p>
                            </div>
                        </div>
                        <Button asChild>
                            <Link href="/inventory/setup">
                                {t('Set up society')}
                                <ArrowRight className="size-4" />
                            </Link>
                        </Button>
                    </section>
                )}
                <div className="grid items-start gap-6 xl:grid-cols-[1.5fr_1fr]">
                    <section className="ef-panel overflow-hidden">
                        <div className="ef-panel-heading">
                            <div>
                                <h2 className="font-semibold">
                                    {t('Inventory by block')}
                                </h2>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    {t(
                                        'Your configured blocks and plot distribution',
                                    )}
                                </p>
                            </div>
                            {stats.plots !== null && (
                                <Link
                                    className="ef-text-link"
                                    href="/inventory"
                                >
                                    {t('View inventory')}
                                    <ArrowUpRight className="size-4" />
                                </Link>
                            )}
                        </div>
                        {blocks.length ? (
                            <div className="space-y-5 p-6">
                                {blocks.map((block) => (
                                    <Link
                                        href={`/inventory?block_id=${block.id}`}
                                        key={block.id}
                                        className="block rounded-xl border border-transparent p-2 transition hover:border-blue-100 hover:bg-blue-50/40 dark:hover:bg-blue-950"
                                    >
                                        <div className="mb-2 flex items-center justify-between">
                                            <div className="flex items-center gap-3">
                                                <span className="flex size-10 items-center justify-center rounded-xl bg-blue-50 text-sm font-bold text-blue-600 dark:bg-blue-950">
                                                    {block.code.slice(0, 3)}
                                                </span>
                                                <div>
                                                    <p className="text-sm font-semibold">
                                                        {block.name}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {t('Block')}{' '}
                                                        {block.code}
                                                    </p>
                                                </div>
                                            </div>
                                            <span className="text-sm font-semibold tabular-nums">
                                                {block.plots_count} {t('plots')}
                                            </span>
                                        </div>
                                        <div className="ms-13 h-2 overflow-hidden rounded-full bg-muted">
                                            <div
                                                className="h-full rounded-full bg-blue-500"
                                                style={{
                                                    width: `${stats.plots ? (block.plots_count / stats.plots) * 100 : 0}%`,
                                                }}
                                            />
                                        </div>
                                    </Link>
                                ))}
                            </div>
                        ) : (
                            <EmptyState
                                title={
                                    stats.plots === null
                                        ? t('Inventory access is restricted')
                                        : t('Your inventory starts here')
                                }
                                description={t(
                                    'Configured blocks and plots will appear here. Start by adding a block in society setup.',
                                )}
                            >
                                {canConfigure && (
                                    <Button variant="outline" asChild>
                                        <Link href="/inventory/setup">
                                            {t('Add your first block')}
                                        </Link>
                                    </Button>
                                )}
                            </EmptyState>
                        )}
                    </section>
                    <section className="ef-panel overflow-hidden">
                        <div className="ef-panel-heading">
                            <div>
                                <h2 className="font-semibold">
                                    {t('Follow-up desk')}
                                </h2>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    {t('The next conversations to pick up')}
                                </p>
                            </div>
                            {stats.followUps !== null && (
                                <Link
                                    className="ef-text-link"
                                    href="/follow-ups"
                                >
                                    {t('View all')}
                                    <ChevronRight className="size-4" />
                                </Link>
                            )}
                        </div>
                        {tasks.length ? (
                            <ul className="divide-y">
                                {tasks.map((task) => (
                                    <li key={task.id}>
                                        <Link
                                            href={`/customers/${task.customer.id}`}
                                            className="flex items-start gap-3 p-5 hover:bg-muted/40"
                                        >
                                            <span className="mt-1 rounded-lg bg-blue-50 p-2 text-blue-600 dark:bg-blue-950">
                                                <ClipboardList className="size-4" />
                                            </span>
                                            <div className="min-w-0 flex-1">
                                                <p className="text-sm font-semibold">
                                                    {task.customer.name}
                                                </p>
                                                <p className="mt-1 line-clamp-2 text-xs leading-5 text-muted-foreground">
                                                    {task.note}
                                                </p>
                                                <p
                                                    className={`mt-2 text-xs font-medium ${task.due_date < businessDate ? 'text-rose-600' : 'text-blue-600'}`}
                                                >
                                                    {t('Due')} {task.due_date}
                                                </p>
                                            </div>
                                            <ChevronRight className="mt-2 size-4 text-muted-foreground" />
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <EmptyState
                                title={t('Nothing waiting for you')}
                                description={t(
                                    'Schedule a follow-up from a customer profile to keep your next conversation on track.',
                                )}
                            />
                        )}
                    </section>
                </div>
                <section className="ef-panel overflow-hidden">
                    <div className="ef-panel-heading">
                        <div>
                            <h2 className="font-semibold">
                                {t('Recent office activity')}
                            </h2>
                            <p className="mt-1 text-xs text-muted-foreground">
                                {t('Changes recorded by your team')}
                            </p>
                        </div>
                        <Activity className="size-5 text-blue-500" />
                    </div>
                    {activity.length ? (
                        <ul className="grid divide-y md:grid-cols-2 md:divide-y-0">
                            {activity.map((event) => (
                                <li
                                    className="flex items-center gap-3 border-b p-5"
                                    key={event.id}
                                >
                                    <span className="rounded-full bg-blue-50 p-2.5 text-blue-500 dark:bg-blue-950">
                                        <Building2 className="size-4" />
                                    </span>
                                    <div>
                                        <p className="text-sm font-medium capitalize">
                                            {event.event
                                                .replaceAll('_', ' ')
                                                .replaceAll('.', ' ')}{' '}
                                            <span className="text-muted-foreground">
                                                #{event.subject_id}
                                            </span>
                                        </p>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {new Date(
                                                event.created_at,
                                            ).toLocaleString('en-PK', {
                                                timeZone: 'Asia/Karachi',
                                            })}
                                        </p>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p className="p-6 text-sm text-muted-foreground">
                            {t(
                                'No inventory activity yet. Changes will appear as your team starts working.',
                            )}
                        </p>
                    )}
                </section>
            </div>
        </>
    );
}
Workspace.layout = { breadcrumbs: [{ title: 'Overview', href: '/dashboard' }] };
