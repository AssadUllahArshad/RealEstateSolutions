import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowUpRight,
    Building2,
    ChartNoAxesColumnIncreasing,
    CircleDollarSign,
    FileCheck2,
} from 'lucide-react';
import { PageHeading, StatCard } from '@/components/office-ui';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatPkr } from '@/lib/format-pkr';
import { useTranslate } from '@/lib/i18n';

type Owner = { id: number; name: string };
type Row = {
    owner_id: number | null;
    owner_name: string;
    inventory_plots: number;
    inventory_booked: number;
    confirmed_bookings: number;
    contract_value: string;
    allocated_collections: string;
    outstanding_principal: string;
    owner_entitlement: string;
};
type BookingRow = {
    id: number;
    owner_name: string;
    customer_name: string;
    plot: string;
    confirmed_on: string;
    contract_value: string;
    allocated_collections: string;
    outstanding_principal: string;
    owner_entitlement: string;
};

export default function OwnerReports({
    owners,
    filters,
    rows,
    recent,
    basis,
}: {
    owners: Owner[];
    filters: { owner_id: number | null; from: string; to: string };
    rows: Row[];
    recent: BookingRow[];
    basis: string;
}) {
    const t = useTranslate();
    const totals = rows.reduce(
        (result, row) => ({
            plots: result.plots + row.inventory_plots,
            bookings: result.bookings + row.confirmed_bookings,
            contract: addMoney(result.contract, row.contract_value),
            collections: addMoney(
                result.collections,
                row.allocated_collections,
            ),
            entitlement: addMoney(result.entitlement, row.owner_entitlement),
        }),
        {
            plots: 0,
            bookings: 0,
            contract: '0.00',
            collections: '0.00',
            entitlement: '0.00',
        },
    );

    return (
        <>
            <Head title={t('Owner reports')} />
            <div className="ef-page">
                <PageHeading
                    eyebrow={t('Operational reporting')}
                    title={t('Owner reports')}
                    description={t(
                        'Compare current plot inventory, confirmed contracts and allocated collections by assigned plot owner.',
                    )}
                />
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    <StatCard
                        title={t('Inventory plots')}
                        value={totals.plots}
                        detail={t('Current catalogue assignment')}
                        icon={Building2}
                    />
                    <StatCard
                        title={t('Confirmed bookings')}
                        value={totals.bookings}
                        detail={t('Within the selected period')}
                        icon={FileCheck2}
                        tone="green"
                    />
                    <StatCard
                        title={t('Contract value')}
                        value={formatPkr(totals.contract)}
                        detail={t('Confirmed principal')}
                        icon={ChartNoAxesColumnIncreasing}
                    />
                    <StatCard
                        title={t('Allocated collections')}
                        value={formatPkr(totals.collections)}
                        detail={t('Recognized and allocated credit')}
                        icon={CircleDollarSign}
                        tone="amber"
                    />
                    <StatCard
                        title={t('Owner entitlement')}
                        value={formatPkr(totals.entitlement)}
                        detail={t('From approved rate snapshots')}
                        icon={CircleDollarSign}
                        tone="green"
                    />
                </div>
                <section className="ef-panel overflow-hidden">
                    <form
                        className="ef-toolbar"
                        onSubmit={(event) => {
                            event.preventDefault();
                            const data = new FormData(event.currentTarget);
                            router.get(
                                '/reports/owners',
                                Object.fromEntries(data.entries()),
                                { preserveState: true },
                            );
                        }}
                    >
                        <div className="grid w-full gap-4 sm:grid-cols-3 lg:max-w-3xl">
                            <div>
                                <Label htmlFor="report-owner">
                                    {t('Plot owner')}
                                </Label>
                                <select
                                    id="report-owner"
                                    name="owner_id"
                                    className="mt-2 w-full"
                                    defaultValue={filters.owner_id ?? ''}
                                >
                                    <option value="">{t('All owners')}</option>
                                    {owners.map((owner) => (
                                        <option key={owner.id} value={owner.id}>
                                            {owner.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <Label htmlFor="report-from">
                                    {t('From date')}
                                </Label>
                                <Input
                                    id="report-from"
                                    name="from"
                                    type="date"
                                    className="mt-2"
                                    defaultValue={filters.from}
                                />
                            </div>
                            <div>
                                <Label htmlFor="report-to">
                                    {t('To date')}
                                </Label>
                                <Input
                                    id="report-to"
                                    name="to"
                                    type="date"
                                    className="mt-2"
                                    defaultValue={filters.to}
                                />
                            </div>
                        </div>
                        <div className="flex gap-2 self-end">
                            <Button>{t('Apply filters')}</Button>
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() => router.get('/reports/owners')}
                            >
                                {t('Clear')}
                            </Button>
                        </div>
                    </form>
                    <div className="border-b bg-blue-500/5 px-5 py-4 text-xs leading-5 text-muted-foreground">
                        <strong className="text-foreground">
                            {t('Reporting basis')}:
                        </strong>{' '}
                        {t(basis)}
                    </div>
                    <div className="grid gap-4 p-5 xl:grid-cols-2">
                        {rows.map((row) => (
                            <article
                                key={row.owner_id ?? 'unassigned'}
                                className="rounded-2xl border bg-card p-5 shadow-xs"
                            >
                                <div className="flex items-start justify-between gap-4">
                                    <div>
                                        <p className="text-xs font-semibold tracking-wide text-primary uppercase">
                                            {t('Plot owner')}
                                        </p>
                                        <h2 className="mt-1 text-xl font-bold">
                                            {t(row.owner_name)}
                                        </h2>
                                    </div>
                                    <span className="rounded-xl bg-primary/10 p-3 text-primary">
                                        <Building2 className="size-5" />
                                    </span>
                                </div>
                                <dl className="mt-5 grid grid-cols-2 gap-4 text-sm">
                                    <Metric
                                        label={t('Inventory plots')}
                                        value={String(row.inventory_plots)}
                                    />
                                    <Metric
                                        label={t('Currently booked plots')}
                                        value={String(row.inventory_booked)}
                                    />
                                    <Metric
                                        label={t('Confirmed bookings')}
                                        value={String(row.confirmed_bookings)}
                                    />
                                    <Metric
                                        label={t('Contract value')}
                                        value={formatPkr(row.contract_value)}
                                    />
                                    <Metric
                                        label={t('Allocated collections')}
                                        value={formatPkr(
                                            row.allocated_collections,
                                        )}
                                    />
                                    <Metric
                                        label={t('Outstanding principal')}
                                        value={formatPkr(
                                            row.outstanding_principal,
                                        )}
                                    />
                                    <Metric
                                        label={t('Owner entitlement')}
                                        value={formatPkr(row.owner_entitlement)}
                                    />
                                </dl>
                                {row.owner_id !== null && (
                                    <Button
                                        asChild
                                        variant="outline"
                                        className="mt-4"
                                    >
                                        <a
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            href={`/reports/owners/${row.owner_id}/statement/print?from=${filters.from}&to=${filters.to}`}
                                        >
                                            {t('Print owner statement')}
                                        </a>
                                    </Button>
                                )}
                            </article>
                        ))}
                    </div>
                </section>
                <section className="ef-panel overflow-hidden">
                    <div className="ef-panel-heading">
                        <div>
                            <h2 className="font-semibold">
                                {t('Recent confirmed bookings')}
                            </h2>
                            <p className="mt-1 text-xs text-muted-foreground">
                                {t(
                                    'Up to 50 bookings within the selected period.',
                                )}
                            </p>
                        </div>
                    </div>
                    {recent.length ? (
                        <div className="overflow-x-auto">
                            <table className="ef-table">
                                <thead>
                                    <tr>
                                        {[
                                            'Booking',
                                            'Owner',
                                            'Customer',
                                            'Plot',
                                            'Confirmed',
                                            'Contract value',
                                            'Allocated collections',
                                            'Outstanding principal',
                                            'Owner entitlement',
                                            '',
                                        ].map((label) => (
                                            <th key={label}>
                                                {label ? t(label) : ''}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {recent.map((booking) => (
                                        <tr key={booking.id}>
                                            <td className="font-semibold">
                                                #{booking.id}
                                            </td>
                                            <td>{booking.owner_name}</td>
                                            <td>{booking.customer_name}</td>
                                            <td>{booking.plot}</td>
                                            <td>{booking.confirmed_on}</td>
                                            <td className="whitespace-nowrap tabular-nums">
                                                {formatPkr(
                                                    booking.contract_value,
                                                )}
                                            </td>
                                            <td className="whitespace-nowrap tabular-nums">
                                                {formatPkr(
                                                    booking.allocated_collections,
                                                )}
                                            </td>
                                            <td className="whitespace-nowrap tabular-nums">
                                                {formatPkr(
                                                    booking.outstanding_principal,
                                                )}
                                            </td>
                                            <td className="whitespace-nowrap tabular-nums">
                                                {formatPkr(
                                                    booking.owner_entitlement,
                                                )}
                                            </td>
                                            <td>
                                                <Link
                                                    className="ef-text-link"
                                                    href={`/bookings/${booking.id}`}
                                                >
                                                    {t('View booking')}
                                                    <ArrowUpRight className="size-4" />
                                                </Link>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <p className="p-8 text-center text-sm text-muted-foreground">
                            {t('No confirmed bookings match these filters.')}
                        </p>
                    )}
                </section>
                <p className="text-xs leading-5 text-muted-foreground">
                    {t(
                        'Owner entitlement uses immutable allocated-collection events and approved booking rate snapshots. Payouts, commission, profit and a formal balance sheet remain separate.',
                    )}
                </p>
            </div>
        </>
    );
}

function Metric({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-xl bg-muted/35 p-3">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="mt-1 font-semibold break-words tabular-nums">
                {value}
            </dd>
        </div>
    );
}

function addMoney(total: string, amount: string) {
    const toMinor = (value: string) => {
        const [whole, fraction = ''] = value.split('.');
        return (
            BigInt(whole) * 100n + BigInt(fraction.padEnd(2, '0').slice(0, 2))
        );
    };
    const minor = toMinor(total) + toMinor(amount);
    return `${minor / 100n}.${String(minor % 100n).padStart(2, '0')}`;
}

OwnerReports.layout = {
    breadcrumbs: [{ title: 'Owner reports', href: '/reports/owners' }],
};
