import { Head, Link } from '@inertiajs/react';
import {
    ArrowDownLeft,
    ArrowUpRight,
    HandCoins,
    Landmark,
    ReceiptText,
    RotateCcw,
} from 'lucide-react';
import { PageHeading, StatCard, StatusBadge } from '@/components/office-ui';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatPkr } from '@/lib/format-pkr';
import { useTranslate } from '@/lib/i18n';

type Props = {
    businessDate: string;
    filters: { from: string; to: string };
    summary: {
        inflow: string;
        reversals: string;
        expenses: string;
        outflow: string;
        net: string;
    };
    workflow: {
        prepared: number;
        pendingClearance: number;
        recognized: number;
    };
    accounts: {
        id: number;
        code: string;
        name: string;
        type: string;
        active: boolean;
        net_movement: string;
    }[];
};

export default function FinanceOverview({
    businessDate,
    filters,
    summary,
    workflow,
    accounts,
}: Props) {
    const t = useTranslate();
    const query = new URLSearchParams(filters).toString();

    return (
        <>
            <Head title={t('Finance overview')} />
            <div className="ef-page">
                <PageHeading
                    eyebrow={t('Finance · Operational control')}
                    title={t('Finance overview')}
                    description={t(
                        'Review posted cash movement and finance work waiting for action from one controlled workspace.',
                    )}
                >
                    <Button asChild variant="outline">
                        <Link href="/finance/receipts">
                            {t('Customer receipts')}
                        </Link>
                    </Button>
                    <Button asChild variant="outline">
                        <Link href="/finance/expenses">
                            {t('Office expenses')}
                        </Link>
                    </Button>
                    <Button asChild>
                        <Link href={`/finance/movements?${query}`}>
                            {t('Open cashbook')}
                        </Link>
                    </Button>
                </PageHeading>

                <section className="ef-panel p-5">
                    <form
                        className="flex flex-wrap items-end gap-3"
                        method="get"
                    >
                        <label className="min-w-44 flex-1 text-sm font-medium">
                            <span className="mb-2 block">{t('From date')}</span>
                            <Input
                                name="from"
                                type="date"
                                defaultValue={filters.from}
                            />
                        </label>
                        <label className="min-w-44 flex-1 text-sm font-medium">
                            <span className="mb-2 block">{t('To date')}</span>
                            <Input
                                name="to"
                                type="date"
                                defaultValue={filters.to}
                            />
                        </label>
                        <Button variant="outline">{t('Apply period')}</Button>
                    </form>
                    <p className="mt-3 text-xs leading-5 text-muted-foreground">
                        {t('Karachi business date')}: {businessDate}
                    </p>
                </section>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        title={t('Receipt inflow')}
                        value={formatPkr(summary.inflow)}
                        detail={t('Recognized posted receipts')}
                        icon={ArrowDownLeft}
                        tone="green"
                    />
                    <StatCard
                        title={t('Receipt reversals')}
                        value={formatPkr(summary.reversals)}
                        detail={t('Accounting reversals only')}
                        icon={RotateCcw}
                        tone="rose"
                    />
                    <StatCard
                        title={t('Office expense outflow')}
                        value={formatPkr(summary.expenses)}
                        detail={t('Posted office expenses')}
                        icon={HandCoins}
                        tone="amber"
                    />
                    <StatCard
                        title={t('Net movement')}
                        value={formatPkr(summary.net)}
                        detail={t('Inflow minus period outflows')}
                        icon={
                            summary.net.startsWith('-')
                                ? ArrowUpRight
                                : Landmark
                        }
                        tone={summary.net.startsWith('-') ? 'rose' : 'blue'}
                    />
                </div>

                <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-950 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-100">
                    {t(
                        'These figures are period movements. Opening balances are not configured, so they are not account balances, a bank reconciliation, profit and loss, or a formal general ledger.',
                    )}
                </div>

                <div className="grid gap-5 xl:grid-cols-[minmax(0,1.35fr)_minmax(18rem,.65fr)]">
                    <section className="ef-panel overflow-hidden">
                        <div className="border-b p-5">
                            <h2 className="text-lg font-semibold">
                                {t('Movement by financial account')}
                            </h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {t(
                                    'Net posted movement for the selected period, excluding any opening balance.',
                                )}
                            </p>
                        </div>
                        <ul className="divide-y">
                            {accounts.map((account) => (
                                <li
                                    key={account.id}
                                    className="flex flex-wrap items-center justify-between gap-3 p-5"
                                >
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <Link
                                                className="ef-text-link"
                                                href={`/finance/movements?account=${account.id}&${query}`}
                                            >
                                                {account.code}
                                            </Link>
                                            <StatusBadge
                                                label={
                                                    account.active
                                                        ? t('Active')
                                                        : t('Inactive')
                                                }
                                                tone={
                                                    account.active
                                                        ? 'success'
                                                        : 'neutral'
                                                }
                                            />
                                        </div>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            {account.name} · {t(account.type)}
                                        </p>
                                    </div>
                                    <strong
                                        className={
                                            account.net_movement.startsWith('-')
                                                ? 'text-rose-700 dark:text-rose-300'
                                                : 'text-emerald-700 dark:text-emerald-300'
                                        }
                                    >
                                        {formatPkr(account.net_movement)}
                                    </strong>
                                </li>
                            ))}
                        </ul>
                    </section>

                    <section className="ef-panel p-5">
                        <h2 className="text-lg font-semibold">
                            {t('Finance work queue')}
                        </h2>
                        <p className="mt-1 text-sm leading-6 text-muted-foreground">
                            {t(
                                'Counts show current workflow state and do not add to cash totals.',
                            )}
                        </p>
                        <div className="mt-5 space-y-3">
                            <Link
                                href="/finance/receipts"
                                className="flex items-center justify-between gap-4 rounded-xl border p-4 transition hover:border-primary/40"
                            >
                                <span className="flex items-center gap-3">
                                    <ReceiptText className="size-5 text-primary" />
                                    {t('Prepared · Unposted')}
                                </span>
                                <strong>{workflow.prepared}</strong>
                            </Link>
                            <Link
                                href="/finance/receipts"
                                className="flex items-center justify-between gap-4 rounded-xl border p-4 transition hover:border-primary/40"
                            >
                                <span className="flex items-center gap-3">
                                    <Landmark className="size-5 text-amber-700" />
                                    {t('Pending cheque clearance')}
                                </span>
                                <strong>{workflow.pendingClearance}</strong>
                            </Link>
                            <Link
                                href="/finance/receipts"
                                className="flex items-center justify-between gap-4 rounded-xl border p-4 transition hover:border-primary/40"
                            >
                                <span className="flex items-center gap-3">
                                    <ArrowDownLeft className="size-5 text-emerald-700" />
                                    {t('Recognized receipts')}
                                </span>
                                <strong>{workflow.recognized}</strong>
                            </Link>
                        </div>
                    </section>
                </div>
            </div>
        </>
    );
}
