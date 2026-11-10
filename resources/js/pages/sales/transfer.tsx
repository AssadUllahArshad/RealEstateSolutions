import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import BrandHeader from '@/components/brand-header';
import { PageHeading } from '@/components/office-ui';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { formatPkr } from '@/lib/format-pkr';
import { useTranslate } from '@/lib/i18n';

type Party = { customer_id: number; name: string; share: string };
type Transfer = {
    id: number;
    state: 'pending' | 'approved' | 'rejected';
    reason: string;
    outgoing_snapshot: Party[];
    incoming_snapshot: Party[];
    outstanding_snapshot: string;
    requester_name: string;
    decider_name: string | null;
    decision_note: string | null;
    effective_on: string | null;
    created_at: string;
};
type Props = {
    booking: {
        id: number;
        plot: { number: string; block: { code: string } };
        activePurchasers: Party[];
        outstanding: string;
    };
    customers: { id: number; name: string; ready: boolean }[];
    documents: {
        id: number;
        kind: 'signed_transfer_form' | 'payment_evidence';
        created_at: string;
    }[];
    transfers: Transfer[];
    commandKey: string;
    canRequest: boolean;
    canApprove: boolean;
};

export default function TransferPage({
    booking,
    customers,
    documents,
    transfers,
    commandKey,
    canRequest,
    canApprove,
}: Props) {
    const t = useTranslate();
    const form = useForm({
        reason: '',
        transfer_form_document_id: '',
        payment_evidence_document_id: '',
        purchasers: [{ customer_id: '', share: '100.0000' }],
        command_key: commandKey,
    });
    const [notes, setNotes] = useState<Record<number, string>>({});
    const pending = transfers.some((item) => item.state === 'pending');
    const forms = documents.filter(
        (item) => item.kind === 'signed_transfer_form',
    );
    const evidence = documents.filter(
        (item) => item.kind === 'payment_evidence',
    );
    const setParty = (
        index: number,
        key: 'customer_id' | 'share',
        value: string,
    ) =>
        form.setData(
            'purchasers',
            form.data.purchasers.map((row, position) =>
                position === index ? { ...row, [key]: value } : row,
            ),
        );
    const decide = (id: number, decision: 'approved' | 'rejected') => {
        if ((notes[id] ?? '').trim().length < 5) return;
        router.post(
            `/booking-transfers/${id}/decision`,
            { decision, decision_note: notes[id], confirmed: true },
            { preserveScroll: true },
        );
    };
    return (
        <>
            <Head title={t('Purchaser transfer')} />
            <div className="ef-page">
                <BrandHeader />
                <PageHeading
                    eyebrow={t('Sales · Controlled ownership change')}
                    title={t('Purchaser transfer')}
                    description={`${t('Block')} ${booking.plot.block.code} / ${t('Plot')} ${booking.plot.number}`}
                >
                    <Button asChild variant="outline">
                        <Link href={`/bookings/${booking.id}`}>
                            {t('Back to booking')}
                        </Link>
                    </Button>
                </PageHeading>
                <section className="grid gap-4 rounded-2xl bg-linear-to-br from-slate-950 to-blue-950 p-6 text-white shadow-lg sm:grid-cols-2">
                    <div>
                        <p className="text-xs text-blue-200">
                            {t('Active legal purchasers')}
                        </p>
                        {booking.activePurchasers.map((p) => (
                            <p
                                className="mt-2 font-semibold"
                                key={p.customer_id}
                            >
                                {p.name} · {p.share}%
                            </p>
                        ))}
                    </div>
                    <div>
                        <p className="text-xs text-blue-200">
                            {t('Unpaid installment balance carried forward')}
                        </p>
                        <p className="mt-2 text-xl font-semibold">
                            {formatPkr(booking.outstanding)}
                        </p>
                    </div>
                </section>
                {canRequest && !pending && (
                    <form
                        className="ef-panel grid gap-5 p-6"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.post(`/bookings/${booking.id}/transfers`, {
                                preserveScroll: true,
                            });
                        }}
                    >
                        <div>
                            <h2 className="font-semibold">
                                {t('Prepare transfer request')}
                            </h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {t(
                                    'Approval preserves prior purchasers and payments, then makes the incoming purchasers responsible for the remaining schedule.',
                                )}
                            </p>
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="transfer-form">
                                    {t('Signed transfer form')}
                                </Label>
                                <select
                                    id="transfer-form"
                                    className="ef-input"
                                    required
                                    value={form.data.transfer_form_document_id}
                                    onChange={(e) =>
                                        form.setData(
                                            'transfer_form_document_id',
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="">
                                        {t('Select document')}
                                    </option>
                                    {forms.map((d) => (
                                        <option key={d.id} value={d.id}>
                                            #{d.id} · {d.created_at}
                                        </option>
                                    ))}
                                </select>
                                <p className="text-xs text-muted-foreground">
                                    {t(
                                        'Upload this document in the original customer workspace first.',
                                    )}
                                </p>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="payment-evidence">
                                    {t('Payment evidence (if applicable)')}
                                </Label>
                                <select
                                    id="payment-evidence"
                                    className="ef-input"
                                    value={
                                        form.data.payment_evidence_document_id
                                    }
                                    onChange={(e) =>
                                        form.setData(
                                            'payment_evidence_document_id',
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="">
                                        {t('No payment evidence')}
                                    </option>
                                    {evidence.map((d) => (
                                        <option key={d.id} value={d.id}>
                                            #{d.id} · {d.created_at}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>
                        <div className="grid gap-3">
                            <Label>
                                {t(
                                    'Incoming legal purchasers · shares must total exactly 100%',
                                )}
                            </Label>
                            {form.data.purchasers.map((row, index) => (
                                <div
                                    className="grid gap-3 sm:grid-cols-[1fr_10rem_auto]"
                                    key={index}
                                >
                                    <select
                                        className="ef-input"
                                        required
                                        value={row.customer_id}
                                        onChange={(e) =>
                                            setParty(
                                                index,
                                                'customer_id',
                                                e.target.value,
                                            )
                                        }
                                    >
                                        <option value="">
                                            {t('Select purchaser')}
                                        </option>
                                        {customers.map((c) => (
                                            <option
                                                disabled={!c.ready}
                                                key={c.id}
                                                value={c.id}
                                            >
                                                {c.name}
                                                {c.ready
                                                    ? ''
                                                    : ` · ${t('identity pack incomplete')}`}
                                            </option>
                                        ))}
                                    </select>
                                    <input
                                        className="ef-input"
                                        aria-label={t('Ownership share')}
                                        inputMode="decimal"
                                        required
                                        value={row.share}
                                        onChange={(e) =>
                                            setParty(
                                                index,
                                                'share',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={
                                            form.data.purchasers.length === 1
                                        }
                                        onClick={() =>
                                            form.setData(
                                                'purchasers',
                                                form.data.purchasers.filter(
                                                    (_, position) =>
                                                        position !== index,
                                                ),
                                            )
                                        }
                                    >
                                        {t('Remove')}
                                    </Button>
                                </div>
                            ))}
                            <Button
                                className="w-fit"
                                type="button"
                                variant="outline"
                                disabled={form.data.purchasers.length >= 10}
                                onClick={() =>
                                    form.setData('purchasers', [
                                        ...form.data.purchasers,
                                        { customer_id: '', share: '' },
                                    ])
                                }
                            >
                                {t('Add joint purchaser')}
                            </Button>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="reason">
                                {t('Transfer reason')}
                            </Label>
                            <textarea
                                id="reason"
                                className="ef-input min-h-24"
                                minLength={10}
                                maxLength={2000}
                                required
                                value={form.data.reason}
                                onChange={(e) =>
                                    form.setData('reason', e.target.value)
                                }
                            />
                        </div>
                        {Object.values(form.errors).map((error, index) => (
                            <p className="text-sm text-destructive" key={index}>
                                {error}
                            </p>
                        ))}
                        <p className="text-sm text-muted-foreground">
                            {t(
                                'Any transfer fee must be posted separately as an approved manual charge. No fee is created automatically.',
                            )}
                        </p>
                        <Button
                            className="w-fit"
                            disabled={form.processing || forms.length === 0}
                        >
                            {t('Submit for admin approval')}
                        </Button>
                    </form>
                )}
                <section className="ef-panel overflow-hidden">
                    <div className="ef-panel-heading">
                        <h2 className="font-semibold">
                            {t('Immutable transfer history')}
                        </h2>
                    </div>
                    <div className="divide-y">
                        {transfers.length === 0 ? (
                            <p className="p-6 text-sm text-muted-foreground">
                                {t('No purchaser transfers recorded.')}
                            </p>
                        ) : (
                            transfers.map((item) => (
                                <article
                                    className="grid gap-3 p-6 text-sm"
                                    key={item.id}
                                >
                                    <div className="flex flex-wrap items-center justify-between gap-3">
                                        <strong>
                                            #{item.id} ·{' '}
                                            {item.incoming_snapshot
                                                .map(
                                                    (p) =>
                                                        `${p.name} (${p.share}%)`,
                                                )
                                                .join(', ')}
                                        </strong>
                                        <span className="ef-status">
                                            {t(item.state)}
                                        </span>
                                    </div>
                                    <p>{item.reason}</p>
                                    <p className="text-muted-foreground">
                                        {t('Outstanding snapshot')}:{' '}
                                        {formatPkr(item.outstanding_snapshot)} ·{' '}
                                        {t('Requested by')}:{' '}
                                        {item.requester_name}
                                    </p>
                                    {item.effective_on && (
                                        <p>
                                            {t('Effective date')}:{' '}
                                            {item.effective_on}
                                        </p>
                                    )}
                                    {item.decision_note && (
                                        <p>
                                            {t('Admin decision')}:{' '}
                                            {item.decision_note} ·{' '}
                                            {item.decider_name}
                                        </p>
                                    )}
                                    {canApprove && item.state === 'pending' && (
                                        <div className="grid gap-2">
                                            <Label htmlFor={`note-${item.id}`}>
                                                {t('Admin decision note')}
                                            </Label>
                                            <textarea
                                                id={`note-${item.id}`}
                                                className="ef-input min-h-20"
                                                value={notes[item.id] ?? ''}
                                                onChange={(e) =>
                                                    setNotes((current) => ({
                                                        ...current,
                                                        [item.id]:
                                                            e.target.value,
                                                    }))
                                                }
                                            />
                                            <div className="flex gap-2">
                                                <Button
                                                    type="button"
                                                    onClick={() =>
                                                        decide(
                                                            item.id,
                                                            'approved',
                                                        )
                                                    }
                                                >
                                                    {t('Approve transfer')}
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant="destructive"
                                                    onClick={() =>
                                                        decide(
                                                            item.id,
                                                            'rejected',
                                                        )
                                                    }
                                                >
                                                    {t('Reject')}
                                                </Button>
                                            </div>
                                        </div>
                                    )}
                                </article>
                            ))
                        )}
                    </div>
                </section>
            </div>
        </>
    );
}
