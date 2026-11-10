import { Head, Link } from '@inertiajs/react';
import { Plus, ArrowUpRight, FilePenLine } from 'lucide-react';
import { EmptyState, PageHeading, Pager } from '@/components/office-ui';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatPkr } from '@/lib/format-pkr';
import { useTranslate } from '@/lib/i18n';

export type DraftRecord = {
    booking?: { id: number } | null;
    id: number;
    customer_id: number;
    plot_id: number;
    quoted_price: string;
    catalogue_price_snapshot: string | null;
    notes: string | null;
    version: number;
    customer: { id: number; name: string };
    plot: {
        id: number;
        number: string;
        block: { code: string };
        active_hold?: { kind: string } | null;
    };
};

export default function Sales({
    drafts,
    q,
}: {
    drafts: {
        data: DraftRecord[];
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    q: string;
}) {
    const t = useTranslate();
    return (
        <>
            <Head title={t('Sales desk')} />
            <div className="ef-page">
                <PageHeading
                    eyebrow={t('Sales workspace')}
                    title={t('Sales desk')}
                    description={t(
                        'Keep customer conversations and proposed plot terms together, ready for the next step.',
                    )}
                >
                    <Button asChild>
                        <Link href="/sales/new">
                            <Plus />
                            {t('New booking draft')}
                        </Link>
                    </Button>
                </PageHeading>
                <div className="flex gap-3 rounded-xl border border-blue-100 bg-blue-50 p-4 text-sm leading-6 text-blue-900">
                    <FilePenLine className="mt-1 size-5 shrink-0" />
                    <p>
                        {t(
                            'Drafts are non-binding proposals. They do not hold or allocate a plot, confirm ownership, approve a discount or record a payment.',
                        )}
                    </p>
                </div>
                <section
                    className="ef-panel overflow-hidden"
                    aria-label="Booking drafts"
                >
                    <div className="ef-toolbar">
                        <form
                            action="/sales"
                            method="get"
                            className="flex w-full max-w-lg gap-2"
                        >
                            <Input
                                name="q"
                                defaultValue={q}
                                aria-label={t('Search drafts')}
                                placeholder={t(
                                    'Search customer or plot number',
                                )}
                                maxLength={160}
                            />
                            <Button variant="outline">{t('Search')}</Button>
                        </form>
                        <span className="text-xs text-muted-foreground">
                            {drafts.total} drafts
                        </span>
                    </div>
                    {drafts.data.length ? (
                        <>
                            <ul
                                className="divide-y md:hidden"
                                aria-label={t('Booking drafts')}
                            >
                                {drafts.data.map((draft) => (
                                    <li
                                        key={draft.id}
                                        className="space-y-3 p-5"
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                                    {draft.booking
                                                        ? `Booking #${draft.booking.id}`
                                                        : `DRAFT-${draft.id}`}
                                                </p>
                                                <p className="mt-1 font-semibold">
                                                    {draft.customer.name}
                                                </p>
                                            </div>
                                            <span className="rounded-full bg-primary/10 px-2.5 py-1 text-[11px] font-semibold text-primary">
                                                v{draft.version}
                                            </span>
                                        </div>
                                        <div className="grid grid-cols-2 gap-3 rounded-xl bg-muted/35 p-3 text-sm">
                                            <div>
                                                <p className="text-xs text-muted-foreground">
                                                    {t('Plot')}
                                                </p>
                                                <p className="mt-1 font-medium">
                                                    {draft.plot.block.code} /{' '}
                                                    {draft.plot.number}
                                                </p>
                                            </div>
                                            <div>
                                                <p className="text-xs text-muted-foreground">
                                                    {t('Quoted price')}
                                                </p>
                                                <p className="mt-1 font-semibold tabular-nums">
                                                    {formatPkr(
                                                        draft.quoted_price,
                                                    )}
                                                </p>
                                            </div>
                                        </div>
                                        <Link
                                            className="ef-text-link"
                                            href={
                                                draft.booking
                                                    ? `/bookings/${draft.booking.id}`
                                                    : `/sales/${draft.id}`
                                            }
                                        >
                                            {draft.booking
                                                ? t('View booking')
                                                : t('Open draft')}
                                            <ArrowUpRight className="size-4" />
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                            <div className="hidden overflow-x-auto md:block">
                                <table className="ef-table">
                                    <caption className="sr-only">
                                        Booking drafts
                                    </caption>
                                    <thead>
                                        <tr>
                                            {[
                                                'Reference',
                                                'Customer contact',
                                                'Plot',
                                                'Quoted price',
                                                'Version',
                                                '',
                                            ].map((label, i) => (
                                                <th key={i}>
                                                    {label ? t(label) : ''}
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {drafts.data.map((draft) => (
                                            <tr key={draft.id}>
                                                <td className="font-semibold">
                                                    {draft.booking
                                                        ? `Booking #${draft.booking.id}`
                                                        : `DRAFT-${draft.id}`}
                                                </td>
                                                <td>{draft.customer.name}</td>
                                                <td>
                                                    {draft.plot.block.code} /{' '}
                                                    {draft.plot.number}
                                                </td>
                                                <td className="font-semibold whitespace-nowrap tabular-nums">
                                                    {formatPkr(
                                                        draft.quoted_price,
                                                    )}
                                                </td>
                                                <td>v{draft.version}</td>
                                                <td>
                                                    <Link
                                                        className="ef-text-link whitespace-nowrap"
                                                        href={
                                                            draft.booking
                                                                ? `/bookings/${draft.booking.id}`
                                                                : `/sales/${draft.id}`
                                                        }
                                                    >
                                                        {draft.booking
                                                            ? t('View booking')
                                                            : t('Open draft')}
                                                        <ArrowUpRight className="size-4" />
                                                    </Link>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </>
                    ) : (
                        <EmptyState
                            title={t('Start a customer proposal')}
                            description={t(
                                'Create a draft after adding a customer contact and a plot to your catalogue.',
                            )}
                        >
                            <Link className="ef-text-link" href="/sales/new">
                                {t('Create your first draft')}
                                <ArrowUpRight className="size-4" />
                            </Link>
                        </EmptyState>
                    )}
                    <Pager {...drafts} />
                </section>
            </div>
        </>
    );
}
Sales.layout = { breadcrumbs: [{ title: 'Sales desk', href: '/sales' }] };
