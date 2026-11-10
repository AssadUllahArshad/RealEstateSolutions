import { Head, Link } from '@inertiajs/react';
import { ArrowDownLeft, ArrowUpRight } from 'lucide-react';
import { EmptyState, PageHeading, Pager } from '@/components/office-ui';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatPkr } from '@/lib/format-pkr';
import { useTranslate } from '@/lib/i18n';

type Entry = {
    id: number;
    financial_account_id: number;
    business_date: string;
    amount: string;
    direction: 'inflow' | 'outflow';
    entry_kind:
        | 'receipt_credit'
        | 'receipt_reversal'
        | 'office_expense'
        | 'owner_payout'
        | 'customer_refund'
        | 'commission_payment';
    source_number: string;
    source_id: number;
};
type Props = {
    accounts: { id: number; code: string; name: string }[];
    filters: { account: string; from: string; to: string };
    summary: { inflow: string; outflow: string; net: string };
    movements: {
        data: Entry[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
};

export default function Movements({
    accounts,
    filters,
    summary,
    movements,
}: Props) {
    const t = useTranslate();
    const names = new Map(
        accounts.map((account) => [account.id, account.code]),
    );
    const exportQuery = new URLSearchParams(
        Object.entries(filters).filter(([, value]) => value !== ''),
    ).toString();
    const label = (entry: Entry) =>
        ({
            receipt_credit: t('Recognized receipt'),
            receipt_reversal: t('Receipt accounting reversal'),
            office_expense: t('Office expense'),
            owner_payout: t('Owner payout'),
            customer_refund: t('Customer refund'),
            commission_payment: t('Commission payment'),
        })[entry.entry_kind];
    const source = (entry: Entry) =>
        entry.entry_kind === 'office_expense'
            ? '/finance/expenses'
            : entry.entry_kind === 'owner_payout'
              ? '/finance/owner-payouts'
              : entry.entry_kind === 'customer_refund'
                ? '/finance/refunds'
                : entry.entry_kind === 'commission_payment'
                  ? '/finance/commissions'
                  : `/finance/posted/${entry.source_id}`;
    return (
        <>
            <Head title={t('Cashbook')} />
            <div className="ef-page">
                <PageHeading
                    eyebrow={t('Finance · Account activity')}
                    title={t('Cashbook')}
                    description={t(
                        'Recognized receipt inflows, accounting reversals and posted office expenses in one operational view.',
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
                    <Button asChild variant="outline">
                        <a
                            href={`/finance/movements/export${exportQuery ? `?${exportQuery}` : ''}`}
                        >
                            {t('Export CSV')}
                        </a>
                    </Button>
                </PageHeading>
                <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-950 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-100">
                    {t(
                        'Net movement is inflow minus reversal and expense outflows for the selected period. Opening balances are not configured, so this is not an account balance, bank reconciliation or formal financial statement.',
                    )}
                </div>
                <div className="grid gap-4 sm:grid-cols-3">
                    <div className="ef-panel p-5">
                        <p className="text-sm text-muted-foreground">
                            {t('Receipt inflow')}
                        </p>
                        <p className="mt-2 text-2xl font-semibold text-emerald-700">
                            {formatPkr(summary.inflow)}
                        </p>
                    </div>
                    <div className="ef-panel p-5">
                        <p className="text-sm text-muted-foreground">
                            {t('Total outflow')}
                        </p>
                        <p className="mt-2 text-2xl font-semibold text-rose-700">
                            {formatPkr(summary.outflow)}
                        </p>
                    </div>
                    <div className="ef-panel p-5">
                        <p className="text-sm text-muted-foreground">
                            {t('Net movement')}
                        </p>
                        <p
                            className={`mt-2 text-2xl font-semibold ${Number(summary.net) < 0 ? 'text-rose-700' : 'text-emerald-700'}`}
                        >
                            {formatPkr(summary.net)}
                        </p>
                    </div>
                </div>
                <section className="ef-panel overflow-hidden">
                    <form
                        method="get"
                        className="grid gap-3 border-b p-5 md:grid-cols-[minmax(12rem,1fr)_auto_auto_auto]"
                    >
                        <select
                            aria-label={t('Filter financial account')}
                            className="ef-input"
                            name="account"
                            defaultValue={filters.account}
                        >
                            <option value="">
                                {t('All financial accounts')}
                            </option>
                            {accounts.map((account) => (
                                <option value={account.id} key={account.id}>
                                    {account.code} · {account.name}
                                </option>
                            ))}
                        </select>
                        <Input
                            aria-label={t('From date')}
                            name="from"
                            type="date"
                            defaultValue={filters.from}
                        />
                        <Input
                            aria-label={t('To date')}
                            name="to"
                            type="date"
                            defaultValue={filters.to}
                        />
                        <Button variant="outline">{t('Filter')}</Button>
                    </form>
                    {movements.data.length ? (
                        <>
                            <ul className="divide-y">
                                {movements.data.map((entry) => (
                                    <li
                                        key={`${entry.entry_kind}-${entry.id}`}
                                        className="flex flex-wrap items-start justify-between gap-4 p-5 text-sm"
                                    >
                                        <div className="flex min-w-0 items-start gap-3">
                                            {entry.direction === 'inflow' ? (
                                                <ArrowDownLeft className="mt-0.5 size-5 shrink-0 text-emerald-700" />
                                            ) : (
                                                <ArrowUpRight className="mt-0.5 size-5 shrink-0 text-rose-700" />
                                            )}
                                            <div>
                                                <Link
                                                    className="ef-text-link"
                                                    href={source(entry)}
                                                >
                                                    {entry.source_number}
                                                </Link>
                                                <p className="mt-1 text-xs leading-5 text-muted-foreground">
                                                    {label(entry)} ·{' '}
                                                    {names.get(
                                                        entry.financial_account_id,
                                                    )}{' '}
                                                    · {entry.business_date}
                                                </p>
                                            </div>
                                        </div>
                                        <span
                                            className={`font-semibold ${entry.direction === 'inflow' ? 'text-emerald-700' : 'text-rose-700'}`}
                                        >
                                            {entry.direction === 'inflow'
                                                ? '+'
                                                : '−'}
                                            {formatPkr(entry.amount)}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                            <Pager
                                prev_page_url={movements.prev_page_url}
                                next_page_url={movements.next_page_url}
                            />
                        </>
                    ) : (
                        <EmptyState
                            title={t('No cashbook activity')}
                            description={t(
                                'Recognized receipts, reversals and posted office expenses will appear here.',
                            )}
                        />
                    )}
                </section>
            </div>
        </>
    );
}
