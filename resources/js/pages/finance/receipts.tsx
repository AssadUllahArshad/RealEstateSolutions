import { Head, Link } from '@inertiajs/react';
import { FileClock, Plus } from 'lucide-react';
import {
    EmptyState,
    PageHeading,
    Pager,
    StatusBadge,
} from '@/components/office-ui';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatPkr } from '@/lib/format-pkr';
import { useTranslate } from '@/lib/i18n';

export const methodLabels: Record<string, string> = {
    cash: 'Cash',
    bank_transfer: 'Bank transfer',
    cheque: 'Cheque',
    mobile_wallet: 'Mobile wallet',
    other: 'Other',
};
export type Receipt = {
    posted_receipt?: { id: number; number: string; state: string } | null;
    id: number;
    customer_id: number;
    customer: { id: number; name: string };
    financial_account_id: number;
    account_snapshot: { code: string; name: string; type: string };
    booking_draft_id: number | null;
    amount: string;
    reported_on: string;
    method: string;
    version: number;
};
function status(receipt: Receipt, t: (text: string) => string) {
    return receipt.posted_receipt
        ? ({
              recognized: t('Money recognized'),
              pending_clearance: t('Awaiting clearance'),
              reversed: t('Reversed'),
          }[receipt.posted_receipt.state] ?? receipt.posted_receipt.state)
        : t('Prepared · Unposted');
}
function statusTone(receipt: Receipt) {
    if (!receipt.posted_receipt) return 'info' as const;
    return receipt.posted_receipt.state === 'recognized'
        ? ('success' as const)
        : receipt.posted_receipt.state === 'reversed'
          ? ('danger' as const)
          : ('warning' as const);
}
type Props = {
    receipts: {
        data: Receipt[];
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    q: string;
    canPrepare: boolean;
    canViewAccounts: boolean;
};

export default function Receipts({
    receipts,
    q,
    canPrepare,
    canViewAccounts,
}: Props) {
    const t = useTranslate();
    return (
        <>
            <Head title={t('Receipt preparations')} />
            <div className="ef-page">
                <PageHeading
                    eyebrow={t('Finance · Customer collections')}
                    title={t('Customer receipts')}
                    description={t(
                        'Prepare, verify and track customer payments. Preparation, money recognition and bank clearance remain distinct states.',
                    )}
                >
                    {canViewAccounts && (
                        <Button asChild variant="outline">
                            <Link href="/finance/accounts">
                                {t('Financial accounts')}
                            </Link>
                        </Button>
                    )}
                    {canPrepare && (
                        <Button asChild>
                            <Link href="/finance/receipts/new">
                                <Plus className="size-4" />
                                {t('Prepare receipt')}
                            </Link>
                        </Button>
                    )}
                </PageHeading>
                <div className="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900">
                    <FileClock className="mt-1 size-5 shrink-0" />
                    <p>
                        <strong>{t('Prepared · Unposted')}</strong>{' '}
                        {t(
                            'Prepared reports do not establish funds. Issued receipts show their official number and recognition state. Recognition does not allocate installments or finalize a booking.',
                        )}
                    </p>
                </div>
                <section
                    className="ef-panel overflow-hidden"
                    aria-label={t('Receipt preparations')}
                >
                    <div className="ef-toolbar">
                        <form
                            action="/finance/receipts"
                            method="get"
                            className="flex w-full max-w-lg gap-2"
                        >
                            <Input
                                aria-label={t('Search receipt customers')}
                                name="q"
                                maxLength={160}
                                defaultValue={q}
                                placeholder={t('Search customer name')}
                            />
                            <Button variant="outline">{t('Search')}</Button>
                        </form>
                        <span className="text-xs text-muted-foreground">
                            {receipts.total} {t('prepared records')}
                        </span>
                    </div>
                    {receipts.data.length ? (
                        <>
                            <ul
                                aria-label={t('Receipt summaries')}
                                className="divide-y md:hidden"
                            >
                                {receipts.data.map((receipt) => (
                                    <li
                                        key={receipt.id}
                                        className="space-y-3 p-5"
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <Link
                                                className="ef-text-link"
                                                href={`/finance/receipts/${receipt.id}`}
                                            >
                                                {receipt.posted_receipt
                                                    ?.number ??
                                                    `PREP-${receipt.id}`}
                                            </Link>
                                            <StatusBadge
                                                label={status(receipt, t)}
                                                tone={statusTone(receipt)}
                                            />
                                        </div>
                                        <p className="text-xl font-semibold break-words">
                                            {formatPkr(receipt.amount)}
                                        </p>
                                        <p className="text-sm">
                                            {receipt.customer.name}
                                        </p>
                                        <div className="flex flex-wrap justify-between gap-2 text-xs leading-5 text-muted-foreground">
                                            <span>
                                                {t(
                                                    methodLabels[
                                                        receipt.method
                                                    ],
                                                )}{' '}
                                                ·{' '}
                                                {receipt.account_snapshot.code}
                                            </span>
                                            <span>{receipt.reported_on}</span>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                            <div className="hidden overflow-x-auto md:block">
                                <table className="ef-table">
                                    <caption className="sr-only">
                                        {t('Customer receipt states')}
                                    </caption>
                                    <thead>
                                        <tr>
                                            <th>{t('Preparation')}</th>
                                            <th>{t('Customer')}</th>
                                            <th>{t('Reported amount')}</th>
                                            <th>{t('Method & account')}</th>
                                            <th>{t('Reported date')}</th>
                                            <th>{t('Status')}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {receipts.data.map((receipt) => (
                                            <tr key={receipt.id}>
                                                <td>
                                                    <Link
                                                        className="ef-text-link whitespace-nowrap"
                                                        href={`/finance/receipts/${receipt.id}`}
                                                    >
                                                        {receipt.posted_receipt
                                                            ?.number ??
                                                            `PREP-${receipt.id}`}
                                                    </Link>
                                                </td>
                                                <td>{receipt.customer.name}</td>
                                                <td className="font-semibold whitespace-nowrap">
                                                    {formatPkr(receipt.amount)}
                                                </td>
                                                <td>
                                                    <p>
                                                        {t(
                                                            methodLabels[
                                                                receipt.method
                                                            ],
                                                        )}
                                                    </p>
                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                        {
                                                            receipt
                                                                .account_snapshot
                                                                .code
                                                        }
                                                    </p>
                                                </td>
                                                <td className="whitespace-nowrap">
                                                    {receipt.reported_on}
                                                </td>
                                                <td>
                                                    <StatusBadge
                                                        label={status(
                                                            receipt,
                                                            t,
                                                        )}
                                                        tone={statusTone(
                                                            receipt,
                                                        )}
                                                    />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </>
                    ) : (
                        <EmptyState
                            title={t('No receipt preparations found')}
                            description={t(
                                'Prepare a customer payment after an admin configures accounts and accepted payment methods.',
                            )}
                        />
                    )}
                    <Pager {...receipts} />
                </section>
            </div>
        </>
    );
}
Receipts.layout = {
    breadcrumbs: [{ title: 'Receipt preparations', href: '/finance/receipts' }],
};
