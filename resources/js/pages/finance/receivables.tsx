import { Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    CalendarClock,
    CircleDollarSign,
    ListChecks,
} from 'lucide-react';
import {
    EmptyState,
    PageHeading,
    Pager,
    StatCard,
    StatusBadge,
} from '@/components/office-ui';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatPkr } from '@/lib/format-pkr';
import { useTranslate } from '@/lib/i18n';

type Entry = {
    id: number;
    customer_name: string;
    plot_number: string;
    confirmed_on: string;
    outstanding: string;
    overdue: string;
    due_today: string;
    next_due_date: string | null;
};

type Props = {
    businessDate: string;
    filters: { state: string; search: string };
    summary: {
        bookings: number;
        outstanding: string;
        overdue: string;
        dueToday: string;
    };
    receivables: {
        data: Entry[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
};

export default function Receivables({
    businessDate,
    filters,
    summary,
    receivables,
}: Props) {
    const t = useTranslate();
    const exportQuery = new URLSearchParams(
        Object.entries(filters).filter(([, value]) => value !== ''),
    ).toString();
    const state = (entry: Entry) =>
        Number(entry.overdue) > 0
            ? { label: t('Overdue'), tone: 'danger' as const }
            : Number(entry.due_today) > 0
              ? { label: t('Due today'), tone: 'warning' as const }
              : { label: t('Upcoming'), tone: 'info' as const };

    return (
        <>
            <Head title={t('Receivables')} />
            <div className="ef-page">
                <PageHeading
                    eyebrow={t('Finance · Principal collection')}
                    title={t('Receivables')}
                    description={t(
                        'Review unpaid scheduled principal and open each booking ledger for collection follow-up or allocation.',
                    )}
                >
                    <Button asChild variant="outline">
                        <Link href="/finance">{t('Finance overview')}</Link>
                    </Button>
                    <Button asChild variant="outline">
                        <a
                            href={`/finance/receivables/export${exportQuery ? `?${exportQuery}` : ''}`}
                        >
                            {t('Export CSV')}
                        </a>
                    </Button>
                    <Button asChild>
                        <Link href="/finance/receipts">
                            {t('Customer receipts')}
                        </Link>
                    </Button>
                </PageHeading>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        title={t('Bookings with schedules')}
                        value={summary.bookings}
                        detail={t('Matching current filters')}
                        icon={ListChecks}
                    />
                    <StatCard
                        title={t('Outstanding principal')}
                        value={formatPkr(summary.outstanding)}
                        detail={t('Unpaid scheduled principal')}
                        icon={CircleDollarSign}
                        tone="blue"
                    />
                    <StatCard
                        title={t('Overdue principal')}
                        value={formatPkr(summary.overdue)}
                        detail={t('Due before the Karachi business date')}
                        icon={AlertTriangle}
                        tone="rose"
                    />
                    <StatCard
                        title={t('Due today')}
                        value={formatPkr(summary.dueToday)}
                        detail={`${t('Karachi business date')} · ${businessDate}`}
                        icon={CalendarClock}
                        tone="amber"
                    />
                </div>

                <div className="rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm leading-6 text-blue-950 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-100">
                    {t(
                        'This worklist reports confirmed principal only. It does not calculate late charges, penalties, refunds, commissions or owner entitlements.',
                    )}
                </div>

                <section className="ef-panel overflow-hidden">
                    <form
                        method="get"
                        className="grid gap-3 border-b p-5 md:grid-cols-[minmax(14rem,1fr)_minmax(12rem,.5fr)_auto]"
                    >
                        <Input
                            aria-label={t('Search receivables')}
                            name="search"
                            defaultValue={filters.search}
                            placeholder={t('Search customer or plot number')}
                        />
                        <select
                            aria-label={t('Filter receivable state')}
                            className="ef-input"
                            name="state"
                            defaultValue={filters.state}
                        >
                            <option value="">
                                {t('All scheduled receivables')}
                            </option>
                            <option value="overdue">{t('Overdue')}</option>
                            <option value="due_today">{t('Due today')}</option>
                            <option value="outstanding">
                                {t('Any outstanding')}
                            </option>
                        </select>
                        <Button variant="outline">{t('Filter')}</Button>
                    </form>

                    {receivables.data.length ? (
                        <>
                            <div className="hidden overflow-x-auto md:block">
                                <table className="w-full text-sm">
                                    <thead className="bg-muted/45 text-start text-xs text-muted-foreground">
                                        <tr>
                                            <th className="p-4 text-start">
                                                {t('Customer / plot')}
                                            </th>
                                            <th className="p-4 text-start">
                                                {t('Next due date')}
                                            </th>
                                            <th className="p-4 text-end">
                                                {t('Outstanding')}
                                            </th>
                                            <th className="p-4 text-end">
                                                {t('Overdue')}
                                            </th>
                                            <th className="p-4 text-end">
                                                {t('Status')}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {receivables.data.map((entry) => {
                                            const badge = state(entry);
                                            return (
                                                <tr key={entry.id}>
                                                    <td className="p-4">
                                                        <Link
                                                            className="ef-text-link font-semibold"
                                                            href={`/bookings/${entry.id}/ledger`}
                                                        >
                                                            {
                                                                entry.customer_name
                                                            }
                                                        </Link>
                                                        <p className="mt-1 text-xs text-muted-foreground">
                                                            {t('Plot')}{' '}
                                                            {entry.plot_number}{' '}
                                                            · {t('Confirmed')}{' '}
                                                            {entry.confirmed_on}
                                                        </p>
                                                    </td>
                                                    <td className="p-4">
                                                        {entry.next_due_date ??
                                                            '—'}
                                                    </td>
                                                    <td className="p-4 text-end font-semibold">
                                                        {formatPkr(
                                                            entry.outstanding,
                                                        )}
                                                    </td>
                                                    <td className="p-4 text-end font-semibold text-rose-700 dark:text-rose-300">
                                                        {formatPkr(
                                                            entry.overdue,
                                                        )}
                                                    </td>
                                                    <td className="p-4 text-end">
                                                        <StatusBadge
                                                            label={badge.label}
                                                            tone={badge.tone}
                                                        />
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                            <ul className="divide-y md:hidden">
                                {receivables.data.map((entry) => {
                                    const badge = state(entry);
                                    return (
                                        <li
                                            className="space-y-3 p-5"
                                            key={entry.id}
                                        >
                                            <div className="flex items-start justify-between gap-3">
                                                <div>
                                                    <Link
                                                        className="ef-text-link font-semibold"
                                                        href={`/bookings/${entry.id}/ledger`}
                                                    >
                                                        {entry.customer_name}
                                                    </Link>
                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                        {t('Plot')}{' '}
                                                        {entry.plot_number}
                                                    </p>
                                                </div>
                                                <StatusBadge
                                                    label={badge.label}
                                                    tone={badge.tone}
                                                />
                                            </div>
                                            <dl className="grid grid-cols-2 gap-3 text-sm">
                                                <div>
                                                    <dt className="text-xs text-muted-foreground">
                                                        {t('Outstanding')}
                                                    </dt>
                                                    <dd className="mt-1 font-semibold">
                                                        {formatPkr(
                                                            entry.outstanding,
                                                        )}
                                                    </dd>
                                                </div>
                                                <div>
                                                    <dt className="text-xs text-muted-foreground">
                                                        {t('Overdue')}
                                                    </dt>
                                                    <dd className="mt-1 font-semibold text-rose-700 dark:text-rose-300">
                                                        {formatPkr(
                                                            entry.overdue,
                                                        )}
                                                    </dd>
                                                </div>
                                                <div className="col-span-2">
                                                    <dt className="text-xs text-muted-foreground">
                                                        {t('Next due date')}
                                                    </dt>
                                                    <dd className="mt-1">
                                                        {entry.next_due_date ??
                                                            '—'}
                                                    </dd>
                                                </div>
                                            </dl>
                                        </li>
                                    );
                                })}
                            </ul>
                            <Pager
                                prev_page_url={receivables.prev_page_url}
                                next_page_url={receivables.next_page_url}
                            />
                        </>
                    ) : (
                        <EmptyState
                            title={t('No receivables match these filters')}
                            description={t(
                                'Change the search or state filter to review other confirmed installment schedules.',
                            )}
                        />
                    )}
                </section>
            </div>
        </>
    );
}
