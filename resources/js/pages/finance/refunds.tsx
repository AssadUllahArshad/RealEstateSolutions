import { Head, useForm } from '@inertiajs/react';
import { PageHeading, Pager, StatusBadge } from '@/components/office-ui';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatPkr } from '@/lib/format-pkr';
import { useTranslate } from '@/lib/i18n';

type Props = {
    refunds: {
        data: any[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    bookings: { id: number; label: string; available: string }[];
    accounts: { id: number; code: string; name: string; type: string }[];
    businessDate: string;
    commandKey: string;
    decisionKey: string;
    canPrepare: boolean;
    canApprove: boolean;
};
export default function Refunds({
    refunds,
    bookings,
    accounts,
    businessDate,
    commandKey,
    decisionKey,
    canPrepare,
    canApprove,
}: Props) {
    const t = useTranslate();
    const form = useForm({
        booking_id: '',
        financial_account_id: '',
        amount: '',
        deduction_amount: '0',
        deduction_reason: '',
        business_date: businessDate,
        reference: '',
        verification_note: '',
        command_key: commandKey,
        confirmed: false,
    });
    const decision = useForm({
        decision: 'approve',
        decision_note: '',
        decision_key: decisionKey,
        confirmed: false,
    });
    return (
        <>
            <Head title={t('Customer refunds')} />
            <div className="ef-page">
                <PageHeading
                    eyebrow={t('Finance · Controlled cancellation')}
                    title={t('Customer refunds')}
                    description={t(
                        'Accountants prepare partial refunds after an approved cancellation. An admin authorizes every cash movement.',
                    )}
                />
                {canPrepare && (
                    <section className="ef-panel p-6">
                        <h2 className="font-semibold">{t('Prepare refund')}</h2>
                        <form
                            className="mt-5 grid gap-4 md:grid-cols-2"
                            onSubmit={(e) => {
                                e.preventDefault();
                                form.post('/finance/refunds');
                            }}
                        >
                            <div>
                                <Label>{t('Cancelled booking')}</Label>
                                <select
                                    className="ef-input mt-2"
                                    required
                                    value={form.data.booking_id}
                                    onChange={(e) =>
                                        form.setData(
                                            'booking_id',
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="">
                                        {t('Choose a booking')}
                                    </option>
                                    {bookings.map((b) => (
                                        <option value={b.id} key={b.id}>
                                            {b.label} · {formatPkr(b.available)}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <Label>{t('Payment account')}</Label>
                                <select
                                    className="ef-input mt-2"
                                    required
                                    value={form.data.financial_account_id}
                                    onChange={(e) =>
                                        form.setData(
                                            'financial_account_id',
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="">
                                        {t('Choose an account')}
                                    </option>
                                    {accounts.map((a) => (
                                        <option value={a.id} key={a.id}>
                                            {a.code} · {a.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <Label>{t('Refund amount (PKR)')}</Label>
                                <Input
                                    className="mt-2"
                                    inputMode="decimal"
                                    required
                                    value={form.data.amount}
                                    onChange={(e) =>
                                        form.setData('amount', e.target.value)
                                    }
                                />
                            </div>
                            <div>
                                <Label>{t('Approved deduction (PKR)')}</Label>
                                <Input
                                    className="mt-2"
                                    inputMode="decimal"
                                    value={form.data.deduction_amount}
                                    onChange={(e) =>
                                        form.setData(
                                            'deduction_amount',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div className="md:col-span-2">
                                <Label>{t('Deduction reason')}</Label>
                                <textarea
                                    className="ef-input mt-2 min-h-20"
                                    value={form.data.deduction_reason}
                                    onChange={(e) =>
                                        form.setData(
                                            'deduction_reason',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div>
                                <Label>{t('Reference')}</Label>
                                <Input
                                    className="mt-2"
                                    value={form.data.reference}
                                    onChange={(e) =>
                                        form.setData(
                                            'reference',
                                            e.target.value,
                                        )
                                    }
                                />
                                <p className="mt-1 text-xs text-muted-foreground">
                                    {t('Required for bank and wallet payouts.')}
                                </p>
                            </div>
                            <div>
                                <Label>{t('Verification note')}</Label>
                                <textarea
                                    className="ef-input mt-2 min-h-20"
                                    required
                                    minLength={10}
                                    value={form.data.verification_note}
                                    onChange={(e) =>
                                        form.setData(
                                            'verification_note',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <label className="flex gap-3 text-sm md:col-span-2">
                                <input
                                    type="checkbox"
                                    required
                                    checked={form.data.confirmed}
                                    onChange={(e) =>
                                        form.setData(
                                            'confirmed',
                                            e.target.checked,
                                        )
                                    }
                                />
                                {t(
                                    'I verified the cancellation, collected credit, refund, deduction, account and evidence.',
                                )}
                            </label>
                            {Object.values(form.errors).map((e, i) => (
                                <p key={i} className="text-sm text-destructive">
                                    {e}
                                </p>
                            ))}
                            <Button
                                className="w-fit"
                                disabled={form.processing}
                            >
                                {t('Submit for admin approval')}
                            </Button>
                        </form>
                    </section>
                )}
                <section className="ef-panel overflow-hidden">
                    <div className="ef-panel-heading">
                        <h2 className="font-semibold">{t('Refund history')}</h2>
                    </div>
                    <div className="divide-y">
                        {refunds.data.length === 0 ? (
                            <p className="p-6 text-sm text-muted-foreground">
                                {t('No refunds recorded.')}
                            </p>
                        ) : (
                            refunds.data.map((r) => (
                                <article className="p-5" key={r.id}>
                                    <div className="flex flex-wrap justify-between gap-3">
                                        <div>
                                            <strong>{r.number}</strong>{' '}
                                            <StatusBadge
                                                label={t(r.state)}
                                                tone={
                                                    r.state === 'approved'
                                                        ? 'success'
                                                        : r.state === 'rejected'
                                                          ? 'danger'
                                                          : 'warning'
                                                }
                                            />
                                            <p className="mt-2 text-sm">
                                                {
                                                    r.booking_snapshot
                                                        .customer_name
                                                }{' '}
                                                · {r.account_snapshot.code}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {r.business_date} ·{' '}
                                                {t('Deduction')}{' '}
                                                {formatPkr(r.deduction_amount)}
                                            </p>
                                        </div>
                                        <strong>{formatPkr(r.amount)}</strong>
                                    </div>
                                    {r.state === 'approved' && (
                                        <a
                                            className="ef-text-link mt-3 inline-flex text-sm"
                                            href={`/finance/refunds/${r.id}/print`}
                                            target="_blank"
                                            rel="noreferrer"
                                        >
                                            {t('Print / Save PDF')}
                                        </a>
                                    )}
                                    {canApprove && r.state === 'prepared' && (
                                        <form
                                            className="mt-4 grid gap-3 sm:grid-cols-[1fr_auto_auto]"
                                            onSubmit={(e) => {
                                                e.preventDefault();
                                                decision.post(
                                                    `/finance/refunds/${r.id}/decision`,
                                                );
                                            }}
                                        >
                                            <Input
                                                required
                                                minLength={5}
                                                value={
                                                    decision.data.decision_note
                                                }
                                                onChange={(e) =>
                                                    decision.setData(
                                                        'decision_note',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                            <Button
                                                onClick={() => {
                                                    decision.setData(
                                                        'decision',
                                                        'approve',
                                                    );
                                                    decision.setData(
                                                        'confirmed',
                                                        true,
                                                    );
                                                }}
                                            >
                                                {t('Approve')}
                                            </Button>
                                            <Button
                                                variant="destructive"
                                                onClick={() => {
                                                    decision.setData(
                                                        'decision',
                                                        'reject',
                                                    );
                                                    decision.setData(
                                                        'confirmed',
                                                        true,
                                                    );
                                                }}
                                            >
                                                {t('Reject')}
                                            </Button>
                                        </form>
                                    )}
                                </article>
                            ))
                        )}
                    </div>
                    <Pager {...refunds} />
                </section>
            </div>
        </>
    );
}
Refunds.layout = {
    breadcrumbs: [{ title: 'Customer refunds', href: '/finance/refunds' }],
};
