import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import BrandHeader from '@/components/brand-header';
import { PageHeading } from '@/components/office-ui';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { formatPkr } from '@/lib/format-pkr';
import { useTranslate } from '@/lib/i18n';

type Props = {
    booking: {
        id: number;
        confirmed_on: string;
        net: string;
        down_payment: string;
        snapshot: {
            customer_name: string;
            plot: { number: string; block: { code: string } };
            purchasers: { customer_id: number; name: string; share: string }[];
            terms: {
                balance: string;
                schedule: {
                    sequence: number;
                    due_date: string;
                    amount: string;
                }[];
            };
        };
    };
    allocations: { number: string; amount: string }[];
    activePurchasers: { customer_id: number; name: string; share: string }[];
    changes: {
        id: number;
        kind: 'correction' | 'cancellation';
        reason: string;
        proposed_change: string | null;
        state: 'pending' | 'approved' | 'rejected';
        requester_name: string;
        decider_name: string | null;
        decision_note: string | null;
        created_at: string;
    }[];
    changeCommandKey: string;
    canRequestChange: boolean;
    canApproveChange: boolean;
};
export default function Booked({
    booking,
    allocations,
    activePurchasers,
    changes,
    changeCommandKey,
    canRequestChange,
    canApproveChange,
}: Props) {
    const t = useTranslate();
    const change = useForm({
        kind: 'correction',
        reason: '',
        proposed_change: '',
        command_key: changeCommandKey,
    });
    const changeError = (change.errors as Record<string, string>).change;
    const [decisionNotes, setDecisionNotes] = useState<Record<number, string>>(
        {},
    );
    const submitChange = (event: React.FormEvent) => {
        event.preventDefault();
        change.post(`/bookings/${booking.id}/changes`, {
            preserveScroll: true,
            onSuccess: () => change.reset('reason', 'proposed_change'),
        });
    };
    const decide = (id: number, decision: 'approved' | 'rejected') => {
        const decisionNote = decisionNotes[id]?.trim();
        if (!decisionNote || decisionNote.length < 5) return;
        router.post(
            `/booking-changes/${id}/decision`,
            { decision, decision_note: decisionNote, confirmed: true },
            {
                preserveScroll: true,
            },
        );
    };
    return (
        <>
            <Head title={`Booking ${booking.id}`} />
            <div className="ef-page">
                <BrandHeader />
                <PageHeading
                    eyebrow="Sales · Confirmed booking"
                    title={`Booking #${booking.id}`}
                    description={`${booking.snapshot.customer_name} · Confirmed ${booking.confirmed_on}`}
                >
                    <Button asChild>
                        <Link href={`/bookings/${booking.id}/ledger`}>
                            {t('Payments & ledger')}
                        </Link>
                    </Button>
                    <Button asChild variant="outline">
                        <Link href={`/bookings/${booking.id}/transfer`}>
                            {t('Purchaser transfer')}
                        </Link>
                    </Button>
                    <Button asChild variant="outline">
                        <a
                            href={`/bookings/${booking.id}/print`}
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            {t('Print / Save PDF')}
                        </a>
                    </Button>
                    <Button asChild variant="outline">
                        <Link href="/sales">{t('Sales desk')}</Link>
                    </Button>
                </PageHeading>
                <section className="relative overflow-hidden rounded-2xl bg-linear-to-br from-emerald-950 to-slate-950 p-6 text-white shadow-lg">
                    <span
                        aria-hidden="true"
                        className="absolute -end-10 -top-16 size-44 rounded-full bg-emerald-400/15 blur-2xl"
                    />
                    <div className="relative">
                        <p className="text-xs font-semibold tracking-widest uppercase">
                            {t('Plot booked')}
                        </p>
                        <h2 className="mt-3 text-2xl font-semibold">
                            {t('Block')} {booking.snapshot.plot.block.code} /{' '}
                            {t('Plot')} {booking.snapshot.plot.number}
                        </h2>
                        <div className="mt-6 grid gap-3 sm:grid-cols-3">
                            {[
                                ['Contract price', booking.net],
                                [
                                    'Allocated down payment',
                                    booking.down_payment,
                                ],
                                [
                                    'Scheduled balance',
                                    booking.snapshot.terms.balance,
                                ],
                            ].map(([label, value]) => (
                                <div
                                    key={label}
                                    className="rounded-xl border border-white/10 bg-white/5 p-4 backdrop-blur-sm"
                                >
                                    <p className="text-xs text-emerald-100">
                                        {t(label)}
                                    </p>
                                    <p className="mt-2 text-xl font-semibold">
                                        {formatPkr(value)}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>
                <div className="grid gap-6 lg:grid-cols-2">
                    <section className="ef-panel overflow-hidden">
                        <h2 className="ef-panel-heading font-semibold">
                            {t('Legal purchasers')}
                        </h2>
                        {activePurchasers.map((person) => (
                            <div
                                key={person.customer_id}
                                className="flex justify-between gap-4 border-t px-6 py-4 text-sm first:border-t-0"
                            >
                                <span>{person.name}</span>
                                <span>{person.share}%</span>
                            </div>
                        ))}
                    </section>
                    <section className="ef-panel overflow-hidden">
                        <h2 className="ef-panel-heading font-semibold">
                            {t('Down payment sources')}
                        </h2>
                        {allocations.map((row) => (
                            <div
                                key={row.number}
                                className="flex flex-wrap justify-between gap-4 border-t px-6 py-4 text-sm first:border-t-0"
                            >
                                <span>{row.number}</span>
                                <span>{formatPkr(row.amount)}</span>
                            </div>
                        ))}
                    </section>
                </div>
                <section className="ef-panel overflow-hidden">
                    <h2 className="ef-panel-heading font-semibold">
                        {t('Agreed installment schedule')}
                    </h2>
                    {booking.snapshot.terms.schedule.length === 0 ? (
                        <p className="p-6 text-sm text-muted-foreground">
                            {t('Full-payment plan. No scheduled balance.')}
                        </p>
                    ) : (
                        <ol className="divide-y">
                            {booking.snapshot.terms.schedule.map((row) => (
                                <li
                                    key={row.sequence}
                                    className="flex flex-wrap justify-between gap-4 px-6 py-4 text-sm"
                                >
                                    <span>
                                        {t('Installment')} {row.sequence} ·{' '}
                                        {row.due_date}
                                    </span>
                                    <span className="font-semibold">
                                        {formatPkr(row.amount)}
                                    </span>
                                </li>
                            ))}
                        </ol>
                    )}
                </section>
                <section className="ef-panel overflow-hidden">
                    <div className="ef-panel-heading">
                        <h2 className="font-semibold">
                            {t('Corrections and cancellation')}
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {t(
                                'Requests require an admin decision. The original contract and payment records remain preserved.',
                            )}
                        </p>
                    </div>
                    {canRequestChange &&
                        !changes.some((item) => item.state === 'pending') && (
                            <form
                                onSubmit={submitChange}
                                className="grid gap-4 border-t p-6"
                            >
                                <div className="grid gap-2 sm:max-w-xs">
                                    <Label htmlFor="change-kind">
                                        {t('Request type')}
                                    </Label>
                                    <select
                                        id="change-kind"
                                        className="ef-input"
                                        value={change.data.kind}
                                        onChange={(event) =>
                                            change.setData(
                                                'kind',
                                                event.target.value,
                                            )
                                        }
                                    >
                                        <option value="correction">
                                            {t('Record correction')}
                                        </option>
                                        <option value="cancellation">
                                            {t('Booking cancellation')}
                                        </option>
                                    </select>
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="change-reason">
                                        {t('Reason')}
                                    </Label>
                                    <textarea
                                        className="ef-input min-h-24"
                                        id="change-reason"
                                        value={change.data.reason}
                                        onChange={(event) =>
                                            change.setData(
                                                'reason',
                                                event.target.value,
                                            )
                                        }
                                        minLength={10}
                                        required
                                    />
                                    {change.errors.reason && (
                                        <p className="text-sm text-destructive">
                                            {change.errors.reason}
                                        </p>
                                    )}
                                </div>
                                {change.data.kind === 'correction' && (
                                    <div className="grid gap-2">
                                        <Label htmlFor="proposed-change">
                                            {t('Exact correction requested')}
                                        </Label>
                                        <textarea
                                            className="ef-input min-h-24"
                                            id="proposed-change"
                                            value={change.data.proposed_change}
                                            onChange={(event) =>
                                                change.setData(
                                                    'proposed_change',
                                                    event.target.value,
                                                )
                                            }
                                            required
                                        />
                                    </div>
                                )}
                                {changeError && (
                                    <p className="text-sm text-destructive">
                                        {changeError}
                                    </p>
                                )}
                                <p className="text-sm text-muted-foreground">
                                    {t(
                                        'Approval records the decision only. Refunds and receipt reversals require their own controlled finance action.',
                                    )}
                                </p>
                                <Button
                                    type="submit"
                                    disabled={change.processing}
                                    className="w-fit"
                                >
                                    {t('Submit for admin review')}
                                </Button>
                            </form>
                        )}
                    <div className="divide-y border-t">
                        {changes.length === 0 ? (
                            <p className="p-6 text-sm text-muted-foreground">
                                {t('No correction or cancellation requests.')}
                            </p>
                        ) : (
                            changes.map((item) => (
                                <article
                                    key={item.id}
                                    className="grid gap-3 p-6 text-sm"
                                >
                                    <div className="flex flex-wrap items-center justify-between gap-3">
                                        <p className="font-semibold">
                                            #{item.id} ·{' '}
                                            {t(
                                                item.kind === 'correction'
                                                    ? 'Record correction'
                                                    : 'Booking cancellation',
                                            )}
                                        </p>
                                        <span className="ef-status">
                                            {t(item.state)}
                                        </span>
                                    </div>
                                    <p>
                                        <span className="text-muted-foreground">
                                            {t('Requested by')}:
                                        </span>{' '}
                                        {item.requester_name}
                                    </p>
                                    <p className="whitespace-pre-wrap">
                                        {item.reason}
                                    </p>
                                    {item.proposed_change && (
                                        <div className="rounded-xl bg-muted/50 p-4 whitespace-pre-wrap">
                                            <strong>
                                                {t('Requested correction')}:
                                            </strong>{' '}
                                            {item.proposed_change}
                                        </div>
                                    )}
                                    {item.decision_note && (
                                        <p>
                                            <span className="text-muted-foreground">
                                                {t('Admin decision')}:
                                            </span>{' '}
                                            {item.decision_note} ·{' '}
                                            {item.decider_name}
                                        </p>
                                    )}
                                    {canApproveChange &&
                                        item.state === 'pending' && (
                                            <div className="flex flex-wrap gap-2">
                                                <Label
                                                    className="w-full"
                                                    htmlFor={`decision-${item.id}`}
                                                >
                                                    {t('Admin decision note')}
                                                </Label>
                                                <textarea
                                                    id={`decision-${item.id}`}
                                                    className="ef-input min-h-20 w-full"
                                                    value={
                                                        decisionNotes[
                                                            item.id
                                                        ] ?? ''
                                                    }
                                                    onChange={(event) =>
                                                        setDecisionNotes(
                                                            (notes) => ({
                                                                ...notes,
                                                                [item.id]:
                                                                    event.target
                                                                        .value,
                                                            }),
                                                        )
                                                    }
                                                    minLength={5}
                                                    required
                                                />
                                                <Button
                                                    type="button"
                                                    disabled={
                                                        (decisionNotes[
                                                            item.id
                                                        ]?.trim().length ?? 0) <
                                                        5
                                                    }
                                                    onClick={() =>
                                                        decide(
                                                            item.id,
                                                            'approved',
                                                        )
                                                    }
                                                >
                                                    {t('Approve request')}
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    disabled={
                                                        (decisionNotes[
                                                            item.id
                                                        ]?.trim().length ?? 0) <
                                                        5
                                                    }
                                                    onClick={() =>
                                                        decide(
                                                            item.id,
                                                            'rejected',
                                                        )
                                                    }
                                                >
                                                    {t('Reject request')}
                                                </Button>
                                            </div>
                                        )}
                                </article>
                            ))
                        )}
                    </div>
                </section>
                <p className="text-sm leading-6 text-muted-foreground">
                    {t(
                        'The confirmed contract and down payment allocation are retained as a permanent record. Transfers, cancellation, refunds and later installment collections require separate controlled workflows. No landowner or commission entitlement has been posted.',
                    )}
                </p>
            </div>
        </>
    );
}
