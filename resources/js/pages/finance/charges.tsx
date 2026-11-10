import { Head, Link, useForm } from '@inertiajs/react';
import { BadgeDollarSign, Ban, CircleDollarSign } from 'lucide-react';
import { FormEvent } from 'react';
import InputError from '@/components/input-error';
import {
    EmptyState,
    PageHeading,
    Pager,
    StatCard,
    StatusBadge,
} from '@/components/office-ui';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatPkr } from '@/lib/format-pkr';
import { useTranslate } from '@/lib/i18n';

type Booking = {
    id: number;
    snapshot: { customer_name: string; plot: { number: string } };
};
type Charge = {
    id: number;
    booking_id: number;
    number: string;
    amount: string;
    business_date: string;
    reason: string;
    evidence_reference: string | null;
    booking: Booking;
    paidAmount: string;
    waivedAmount: string;
    remainingAmount: string;
    status: 'active' | 'settled' | 'waived';
    waivers: { amount: string; business_date: string; reason: string }[];
};
type Props = {
    charges: {
        data: Charge[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: { booking: string; state: string };
    activeTotal: string;
    bookings: Booking[];
    businessDate: string;
    postKey: string;
    waiveKey: string;
    canManage: boolean;
};

export default function Charges({
    charges,
    filters,
    activeTotal,
    bookings,
    businessDate,
    postKey,
    waiveKey,
    canManage,
}: Props) {
    const t = useTranslate();
    const form = useForm({
        booking_id: filters.booking,
        amount: '',
        business_date: businessDate,
        reason: '',
        note: '',
        evidence_reference: '',
        command_key: postKey,
        confirmed: false,
    });
    const waiver = useForm({
        business_date: businessDate,
        amount: '',
        reason: '',
        command_key: waiveKey,
        confirmed: false,
    });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/finance/charges', {
            preserveScroll: true,
            onSuccess: () => {
                form.reset(
                    'amount',
                    'reason',
                    'note',
                    'evidence_reference',
                    'confirmed',
                );
                form.setData('command_key', crypto.randomUUID());
            },
        });
    };

    return (
        <>
            <Head title={t('Manual charges')} />
            <div className="ef-page">
                <PageHeading
                    eyebrow={t('Finance · Admin adjustments')}
                    title={t('Manual charges')}
                    description={t(
                        'Authorized admins record each approved customer charge individually. No automatic penalty is calculated.',
                    )}
                >
                    <Button asChild variant="outline">
                        <Link href="/finance/receivables">
                            {t('Receivables')}
                        </Link>
                    </Button>
                    <Button asChild variant="outline">
                        <Link href="/finance">{t('Finance overview')}</Link>
                    </Button>
                </PageHeading>
                <div className="grid gap-4 sm:grid-cols-2">
                    <StatCard
                        title={t('Active manual charges')}
                        value={formatPkr(activeTotal)}
                        detail={t('Unpaid charges only; separate from principal')}
                        icon={CircleDollarSign}
                        tone="amber"
                    />
                    <StatCard
                        title={t('Charge records')}
                        value={charges.data.length}
                        detail={t('Visible on this page')}
                        icon={BadgeDollarSign}
                    />
                </div>
                <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-950 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-100">
                    {t(
                        'Charges never alter principal or create cashbook movements. Recognized payments apply to principal first, then charges in posting order. Each partial waiver is an immutable compensating record.',
                    )}
                </div>
                {canManage && (
                    <section className="ef-panel p-5">
                        <h2 className="text-lg font-semibold">
                            {t('Post an approved charge')}
                        </h2>
                        <form
                            onSubmit={submit}
                            className="mt-5 grid gap-4 md:grid-cols-2"
                        >
                            <label className="text-sm font-medium">
                                <span className="mb-2 block">
                                    {t('Booking')}
                                </span>
                                <select
                                    className="ef-input"
                                    value={form.data.booking_id}
                                    onChange={(e) =>
                                        form.setData(
                                            'booking_id',
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="">
                                        {t('Select a booking')}
                                    </option>
                                    {bookings.map((booking) => (
                                        <option
                                            key={booking.id}
                                            value={booking.id}
                                        >
                                            #{booking.id} ·{' '}
                                            {booking.snapshot.customer_name} ·{' '}
                                            {booking.snapshot.plot.number}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={form.errors.booking_id} />
                            </label>
                            <div>
                                <Label htmlFor="charge-amount">
                                    {t('Charge amount (PKR)')}
                                </Label>
                                <Input
                                    id="charge-amount"
                                    className="mt-2"
                                    inputMode="decimal"
                                    value={form.data.amount}
                                    onChange={(e) =>
                                        form.setData('amount', e.target.value)
                                    }
                                />
                                <InputError message={form.errors.amount} />
                            </div>
                            <div>
                                <Label htmlFor="charge-reason">
                                    {t('Charge reason')}
                                </Label>
                                <Input
                                    id="charge-reason"
                                    className="mt-2"
                                    value={form.data.reason}
                                    onChange={(e) =>
                                        form.setData('reason', e.target.value)
                                    }
                                />
                                <InputError message={form.errors.reason} />
                            </div>
                            <div>
                                <Label htmlFor="charge-evidence">
                                    {t('Evidence reference')}
                                </Label>
                                <Input
                                    id="charge-evidence"
                                    className="mt-2"
                                    value={form.data.evidence_reference}
                                    onChange={(e) =>
                                        form.setData(
                                            'evidence_reference',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <label className="text-sm font-medium md:col-span-2">
                                <span className="mb-2 block">
                                    {t('Verification note')}
                                </span>
                                <textarea
                                    className="ef-input min-h-24"
                                    value={form.data.note}
                                    onChange={(e) =>
                                        form.setData('note', e.target.value)
                                    }
                                />
                                <InputError message={form.errors.note} />
                            </label>
                            <label className="flex items-start gap-3 text-sm md:col-span-2">
                                <input
                                    type="checkbox"
                                    checked={form.data.confirmed}
                                    onChange={(e) =>
                                        form.setData(
                                            'confirmed',
                                            e.target.checked,
                                        )
                                    }
                                />
                                <span>
                                    {t(
                                        'I verified the approved amount, reason and supporting office evidence.',
                                    )}
                                </span>
                            </label>
                            <div className="md:col-span-2">
                                <Button disabled={form.processing}>
                                    {t('Post manual charge')}
                                </Button>
                                <InputError
                                    message={
                                        (form.errors as Record<string, string>)
                                            .conflict
                                    }
                                />
                            </div>
                        </form>
                    </section>
                )}
                <section className="ef-panel overflow-hidden">
                    <form
                        method="get"
                        className="grid gap-3 border-b p-5 sm:grid-cols-[1fr_1fr_auto]"
                    >
                        <select
                            className="ef-input"
                            name="booking"
                            defaultValue={filters.booking}
                        >
                            <option value="">{t('All bookings')}</option>
                            {bookings.map((booking) => (
                                <option key={booking.id} value={booking.id}>
                                    #{booking.id} ·{' '}
                                    {booking.snapshot.customer_name}
                                </option>
                            ))}
                        </select>
                        <select
                            className="ef-input"
                            name="state"
                            defaultValue={filters.state}
                        >
                            <option value="">{t('All charge states')}</option>
                            <option value="active">{t('Active')}</option>
                            <option value="settled">{t('Settled by payment')}</option>
                            <option value="waived">{t('Waived')}</option>
                        </select>
                        <Button variant="outline">{t('Filter')}</Button>
                    </form>
                    {charges.data.length ? (
                        <>
                            <ul className="divide-y">
                                {charges.data.map((charge) => (
                                    <li key={charge.id} className="p-5">
                                        <div className="flex flex-wrap items-start justify-between gap-4">
                                            <div>
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <strong>
                                                        {charge.number}
                                                    </strong>
                                                    <StatusBadge
                                                        label={t(
                                                            charge.status === 'active'
                                                                ? 'Active'
                                                                : charge.status === 'waived'
                                                                  ? 'Waived'
                                                                  : 'Settled by payment',
                                                        )}
                                                        tone={charge.status === 'active' ? 'warning' : 'neutral'}
                                                    />
                                                </div>
                                                <p className="mt-2 text-sm">
                                                    #{charge.booking_id} ·{' '}
                                                    {
                                                        charge.booking.snapshot
                                                            .customer_name
                                                    }{' '}
                                                    ·{' '}
                                                    {
                                                        charge.booking.snapshot
                                                            .plot.number
                                                    }
                                                </p>
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    {charge.reason} ·{' '}
                                                    {charge.business_date}
                                                    {charge.evidence_reference
                                                        ? ` · ${charge.evidence_reference}`
                                                        : ''}
                                                </p>
                                                <p className="mt-2 text-xs text-muted-foreground">
                                                    {t('Paid')} {formatPkr(charge.paidAmount)} · {t('Waived')} {formatPkr(charge.waivedAmount)} · {t('Remaining')} {formatPkr(charge.remainingAmount)}
                                                </p>
                                                {charge.waivers.map((record) => (
                                                    <p key={`${record.business_date}-${record.amount}-${record.reason}`} className="mt-1 text-xs text-muted-foreground">
                                                        {t('Waiver')} {formatPkr(record.amount)} · {record.business_date} · {record.reason}
                                                    </p>
                                                ))}
                                            </div>
                                            <div className="text-end">
                                                <strong
                                                    className={
                                                        charge.status === 'waived'
                                                            ? 'text-muted-foreground line-through'
                                                            : 'text-amber-700 dark:text-amber-300'
                                                    }
                                                >
                                                    {formatPkr(charge.amount)}
                                                </strong>
                                                {canManage &&
                                                    charge.status === 'active' && (
                                                        <form
                                                            className="mt-3 space-y-2"
                                                            onSubmit={(e) => {
                                                                e.preventDefault();
                                                                waiver.post(
                                                                    `/finance/charges/${charge.id}/waive`,
                                                                    {
                                                                        preserveScroll: true,
                                                                        onSuccess: () => {
                                                                            waiver.reset('amount', 'reason', 'confirmed');
                                                                            waiver.setData('command_key', crypto.randomUUID());
                                                                        },
                                                                    },
                                                                );
                                                            }}
                                                        >
                                                            <Input
                                                                aria-label={t(
                                                                    'Waiver amount (PKR)',
                                                                )}
                                                                placeholder={t(
                                                                    'Waiver amount (PKR)',
                                                                )}
                                                                inputMode="decimal"
                                                                value={waiver.data.amount}
                                                                onChange={(e) =>
                                                                    waiver.setData(
                                                                        'amount',
                                                                        e.target.value,
                                                                    )
                                                                }
                                                            />
                                                            <InputError message={waiver.errors.amount} />
                                                            <Input
                                                                aria-label={t(
                                                                    'Waiver reason',
                                                                )}
                                                                placeholder={t(
                                                                    'Waiver reason',
                                                                )}
                                                                value={
                                                                    waiver.data
                                                                        .reason
                                                                }
                                                                onChange={(e) =>
                                                                    waiver.setData(
                                                                        'reason',
                                                                        e.target
                                                                            .value,
                                                                    )
                                                                }
                                                            />
                                                            <label className="flex items-center justify-end gap-2 text-xs">
                                                                <input
                                                                    type="checkbox"
                                                                    checked={
                                                                        waiver
                                                                            .data
                                                                            .confirmed
                                                                    }
                                                                    onChange={(
                                                                        e,
                                                                    ) =>
                                                                        waiver.setData(
                                                                            'confirmed',
                                                                            e
                                                                                .target
                                                                                .checked,
                                                                        )
                                                                    }
                                                                />
                                                                {t(
                                                                    'Confirm waiver',
                                                                )}
                                                            </label>
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                                disabled={waiver.processing || !waiver.data.amount || !waiver.data.reason || !waiver.data.confirmed}
                                                            >
                                                                <Ban />
                                                                {t(
                                                                    'Record waiver',
                                                                )}
                                                            </Button>
                                                            <InputError message={(waiver.errors as Record<string, string>).conflict} />
                                                        </form>
                                                    )}
                                            </div>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                            <Pager
                                prev_page_url={charges.prev_page_url}
                                next_page_url={charges.next_page_url}
                            />
                        </>
                    ) : (
                        <EmptyState
                            title={t('No manual charges')}
                            description={t(
                                'Approved manual charges will appear here without changing principal or cash totals.',
                            )}
                        />
                    )}
                </section>
            </div>
        </>
    );
}
