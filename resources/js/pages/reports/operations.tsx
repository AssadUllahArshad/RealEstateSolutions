import { Head, router } from '@inertiajs/react';
import {
    Building2,
    ChartNoAxesColumnIncreasing,
    CircleDollarSign,
    FileCheck2,
    HandCoins,
    ReceiptText,
    WalletCards,
} from 'lucide-react';
import { PageHeading, StatCard } from '@/components/office-ui';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatPkr } from '@/lib/format-pkr';
import { useTranslate } from '@/lib/i18n';

type BlockRow = { code: string; name: string; plots: number };
type SizeRow = { label: string; area_marla: string; plots: number };
type OwnerRow = { owner_id: number | null; owner_name: string; plots: number };
type AgingRow = { bucket: string; installments: number; remaining: string };
type OutstandingRow = { owner_id?: number; agent_id?: number; owner_name?: string; agent_name?: string; outstanding: string };

type Report = {
    inventory: {
        total: number;
        available: number;
        held: number;
        booked: number;
        byBlock: BlockRow[];
        bySize: SizeRow[];
        byOwner: OwnerRow[];
    };
    bookings: {
        count: number;
        contract_value: string;
        down_payment: string;
    };
    collections: {
        recognized_count: number;
        recognized_amount: string;
        reversals: string;
        allocated_down: string;
        allocated_installments: string;
        unallocated: string;
    };
    aging: AgingRow[];
    ownerPayouts: {
        approved_total: string;
        outstanding: OutstandingRow[];
    };
    commissions: {
        approved_total: string;
        outstanding: OutstandingRow[];
    };
};

export default function OperationalReports({
    businessDate,
    filters,
    report,
    basis,
}: {
    businessDate: string;
    filters: { from: string; to: string; as_of: string };
    report: Report;
    basis: string;
}) {
    const t = useTranslate();
    const exportQuery = new URLSearchParams(
        Object.entries(filters).filter(([, value]) => value !== ''),
    ).toString();

    return (
        <>
            <Head title={t('Operational reports')} />
            <div className="ef-page">
                <PageHeading
                    eyebrow={t('Operational reporting')}
                    title={t('Operational reports')}
                    description={t(
                        'Current inventory, period bookings and collections, receivables aging and outstanding owner payouts and agent commissions.',
                    )}
                >
                    <Button asChild variant="outline">
                        <a
                            href={`/reports/operations/export${exportQuery ? `?${exportQuery}` : ''}`}
                        >
                            {t('Export CSV')}
                        </a>
                    </Button>
                </PageHeading>

                <section className="ef-panel overflow-hidden">
                    <form
                        aria-label={t('Operational report filters')}
                        className="ef-toolbar"
                        onSubmit={(event) => {
                            event.preventDefault();
                            const data = new FormData(event.currentTarget);
                            router.get(
                                '/reports/operations',
                                Object.fromEntries(data.entries()),
                                { preserveState: true },
                            );
                        }}
                    >
                        <div className="grid w-full min-w-0 gap-4 sm:grid-cols-3 lg:max-w-3xl">
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
                            <div>
                                <Label htmlFor="report-as-of">
                                    {t('As of date')}
                                </Label>
                                <Input
                                    id="report-as-of"
                                    name="as_of"
                                    type="date"
                                    className="mt-2"
                                    defaultValue={filters.as_of}
                                />
                            </div>
                        </div>
                        <div className="flex gap-2 self-end">
                            <Button className="h-11">
                                {t('Apply filters')}
                            </Button>
                            <Button
                                type="button"
                                className="h-11"
                                variant="ghost"
                                onClick={() => router.get('/reports/operations')}
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
                </section>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        title={t('Total plots')}
                        value={report.inventory.total}
                        detail={t('Current catalogue')}
                        icon={Building2}
                    />
                    <StatCard
                        title={t('Available plots')}
                        value={report.inventory.available}
                        detail={t('Not held or booked')}
                        icon={Building2}
                        tone="green"
                    />
                    <StatCard
                        title={t('Held plots')}
                        value={report.inventory.held}
                        detail={t('Active reservations or blocks')}
                        icon={Building2}
                        tone="amber"
                    />
                    <StatCard
                        title={t('Booked plots')}
                        value={report.inventory.booked}
                        detail={t('Confirmed bookings')}
                        icon={FileCheck2}
                        tone="blue"
                    />
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        title={t('Confirmed bookings')}
                        value={report.bookings.count}
                        detail={t('Within the selected period')}
                        icon={FileCheck2}
                        tone="green"
                    />
                    <StatCard
                        title={t('Contract value')}
                        value={formatPkr(report.bookings.contract_value)}
                        detail={t('Confirmed principal')}
                        icon={ChartNoAxesColumnIncreasing}
                    />
                    <StatCard
                        title={t('Down payment')}
                        value={formatPkr(report.bookings.down_payment)}
                        detail={t('Confirmed down payments')}
                        icon={CircleDollarSign}
                        tone="amber"
                    />
                    <StatCard
                        title={t('Recognized receipts')}
                        value={report.collections.recognized_count}
                        detail={t('Within the selected period')}
                        icon={ReceiptText}
                        tone="blue"
                    />
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        title={t('Recognized amount')}
                        value={formatPkr(report.collections.recognized_amount)}
                        detail={t('Recognized receipts')}
                        icon={CircleDollarSign}
                        tone="green"
                    />
                    <StatCard
                        title={t('Reversals')}
                        value={formatPkr(report.collections.reversals)}
                        detail={t('Receipt reversals')}
                        icon={CircleDollarSign}
                        tone="rose"
                    />
                    <StatCard
                        title={t('Allocated to down payments')}
                        value={formatPkr(report.collections.allocated_down)}
                        detail={t('Recognized and allocated')}
                        icon={CircleDollarSign}
                        tone="blue"
                    />
                    <StatCard
                        title={t('Allocated to installments')}
                        value={formatPkr(report.collections.allocated_installments)}
                        detail={t('Recognized and allocated')}
                        icon={CircleDollarSign}
                        tone="blue"
                    />
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        title={t('Unallocated')}
                        value={formatPkr(report.collections.unallocated)}
                        detail={t('Recognized but not allocated')}
                        icon={CircleDollarSign}
                        tone="amber"
                    />
                    <StatCard
                        title={t('Approved owner payouts')}
                        value={formatPkr(report.ownerPayouts.approved_total)}
                        detail={t('Within the selected period')}
                        icon={HandCoins}
                        tone="green"
                    />
                    <StatCard
                        title={t('Approved commission payments')}
                        value={formatPkr(report.commissions.approved_total)}
                        detail={t('Within the selected period')}
                        icon={WalletCards}
                        tone="green"
                    />
                </div>

                <section className="ef-panel overflow-hidden">
                    <div className="ef-panel-heading">
                        <div>
                            <h2 className="font-semibold">
                                {t('Receivables aging')}
                            </h2>
                            <p className="mt-1 text-xs text-muted-foreground">
                                {t('As of')} {filters.as_of}
                            </p>
                        </div>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="ef-table">
                            <thead>
                                <tr>
                                    {['Bucket', 'Installments', 'Remaining (PKR)'].map(
                                        (label) => (
                                            <th key={label}>{t(label)}</th>
                                        ),
                                    )}
                                </tr>
                            </thead>
                            <tbody>
                                {report.aging.map((row) => (
                                    <tr key={row.bucket}>
                                        <td className="font-semibold">
                                            {t(row.bucket)}
                                        </td>
                                        <td className="tabular-nums">
                                            {row.installments}
                                        </td>
                                        <td className="whitespace-nowrap tabular-nums">
                                            {formatPkr(row.remaining)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>

                <div className="grid gap-4 xl:grid-cols-2">
                    <section className="ef-panel overflow-hidden">
                        <div className="ef-panel-heading">
                            <div>
                                <h2 className="font-semibold">
                                    {t('Inventory by block')}
                                </h2>
                            </div>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="ef-table">
                                <thead>
                                    <tr>
                                        {['Block code', 'Block name', 'Plots'].map(
                                            (label) => (
                                                <th key={label}>{t(label)}</th>
                                            ),
                                        )}
                                    </tr>
                                </thead>
                                <tbody>
                                    {report.inventory.byBlock.map((row) => (
                                        <tr key={row.code}>
                                            <td className="font-semibold">
                                                {row.code}
                                            </td>
                                            <td>{row.name}</td>
                                            <td className="tabular-nums">
                                                {row.plots}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section className="ef-panel overflow-hidden">
                        <div className="ef-panel-heading">
                            <div>
                                <h2 className="font-semibold">
                                    {t('Inventory by size')}
                                </h2>
                            </div>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="ef-table">
                                <thead>
                                    <tr>
                                        {['Size label', 'Area (marla)', 'Plots'].map(
                                            (label) => (
                                                <th key={label}>{t(label)}</th>
                                            ),
                                        )}
                                    </tr>
                                </thead>
                                <tbody>
                                    {report.inventory.bySize.map((row) => (
                                        <tr key={row.label}>
                                            <td className="font-semibold">
                                                {row.label}
                                            </td>
                                            <td className="tabular-nums">
                                                {row.area_marla}
                                            </td>
                                            <td className="tabular-nums">
                                                {row.plots}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>

                <div className="grid gap-4 xl:grid-cols-2">
                    <section className="ef-panel overflow-hidden">
                        <div className="ef-panel-heading">
                            <div>
                                <h2 className="font-semibold">
                                    {t('Inventory by owner')}
                                </h2>
                            </div>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="ef-table">
                                <thead>
                                    <tr>
                                        {['Owner', 'Plots'].map((label) => (
                                            <th key={label}>{t(label)}</th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {report.inventory.byOwner.map((row) => (
                                        <tr key={row.owner_id ?? 'unassigned'}>
                                            <td className="font-semibold">
                                                {t(row.owner_name)}
                                            </td>
                                            <td className="tabular-nums">
                                                {row.plots}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section className="ef-panel overflow-hidden">
                        <div className="ef-panel-heading">
                            <div>
                                <h2 className="font-semibold">
                                    {t('Outstanding owner payouts')}
                                </h2>
                            </div>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="ef-table">
                                <thead>
                                    <tr>
                                        {['Owner', 'Outstanding (PKR)'].map(
                                            (label) => (
                                                <th key={label}>{t(label)}</th>
                                            ),
                                        )}
                                    </tr>
                                </thead>
                                <tbody>
                                    {report.ownerPayouts.outstanding.map(
                                        (row) => (
                                            <tr key={row.owner_id}>
                                                <td className="font-semibold">
                                                    {t(row.owner_name ?? '')}
                                                </td>
                                                <td className="whitespace-nowrap tabular-nums">
                                                    {formatPkr(row.outstanding)}
                                                </td>
                                            </tr>
                                        ),
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>

                <section className="ef-panel overflow-hidden">
                    <div className="ef-panel-heading">
                        <div>
                            <h2 className="font-semibold">
                                {t('Outstanding agent commissions')}
                            </h2>
                        </div>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="ef-table">
                            <thead>
                                <tr>
                                    {['Agent', 'Outstanding (PKR)'].map(
                                        (label) => (
                                            <th key={label}>{t(label)}</th>
                                        ),
                                    )}
                                </tr>
                            </thead>
                            <tbody>
                                {report.commissions.outstanding.map((row) => (
                                    <tr key={row.agent_id}>
                                        <td className="font-semibold">
                                            {row.agent_name}
                                        </td>
                                        <td className="whitespace-nowrap tabular-nums">
                                            {formatPkr(row.outstanding)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>

                <p className="text-xs leading-5 text-muted-foreground">
                    {t(
                        'This operational report does not calculate owner entitlement, settlement, commission, profit or a formal balance sheet.',
                    )}
                </p>
            </div>
        </>
    );
}

OperationalReports.layout = {
    breadcrumbs: [
        { title: 'Operational reports', href: '/reports/operations' },
    ],
};
