import { Head, Link, useForm, usePage } from '@inertiajs/react';
import BrandHeader from '@/components/brand-header';
import { PageHeading } from '@/components/office-ui';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatPkr } from '@/lib/format-pkr';
import { useUnsavedChanges } from '@/hooks/use-unsaved-changes';
import { useTranslate } from '@/lib/i18n';

type Props = {
    booking: {
        id: number;
        net: string;
        down_payment: string;
        customer_name: string;
        plot: string;
    };
    outstanding: string;
    outstandingCharges: string;
    overdue: string;
    paidPrincipal: string;
    businessDate: string;
    version: number;
    policyVersion: number;
    rule: string | null;
    canAllocate: boolean;
    canRequestCorrection: boolean;
    canApproveCorrections: boolean;
    commandKey: string;
    correctionCommandKey: string;
    credits: { id: number; number: string; available: string }[];
    schedule: {
        sequence: number;
        due_date: string;
        amount: string;
        paid: string;
        remaining: string;
        status: string;
    }[];
    history: {
        version: number;
        amount: string;
        business_date: string;
        actor_id: number;
        number: string;
    }[];
    charges: {
        id: number;
        number: string;
        amount: string;
        business_date: string;
        reason: string;
        paid: string;
        waived: string;
        remaining: string;
    }[];
    chargeHistory: {
        version: number;
        amount: string;
        business_date: string;
        number: string;
        reversed: boolean;
    }[];
    correctionItems: {
        id: number;
        sequence: number;
        amount: string;
        available: string;
        business_date: string;
        number: string;
    }[];
    corrections: {
        id: number;
        amount: string;
        reason: string;
        state: string;
        decision_note: string | null;
        business_date: string | null;
        ledger_version: number | null;
        sequence: number;
        number: string;
        preparer_name: string;
        decider_name: string | null;
        decision_key: string;
    }[];
};
function AllocationForm(props: Props) {
    const t = useTranslate();
    const form = useForm({
        command_key: props.commandKey,
        version: props.version,
        policy_version: props.policyVersion,
        receipt_id: '',
        amount: '',
        mode: props.rule === 'manual' ? 'manual' : 'oldest_first',
        rows: [] as { sequence: number; amount: string }[],
        charge_rows: [] as { charge_id: number; amount: string }[],
        confirmed: false,
    });
    useUnsavedChanges(form.isDirty);
    return (
        <form
            className="ef-panel space-y-5 p-6"
            onSubmit={(event) => {
                event.preventDefault();
                form.post(`/bookings/${props.booking.id}/allocations`);
            }}
        >
            <h2 className="text-lg font-semibold">
                {t('Apply recognized customer credit')}
            </h2>
            <p className="text-sm leading-6 text-muted-foreground">
                {t(
                    'Applies payment to principal first. If the payment exceeds remaining principal, select the manual charges to receive the remainder. Installment allocation follows the saved rule; unused credit stays with the customer.',
                )}
            </p>
            <p className="text-sm text-muted-foreground">
                {t('Principal remaining')} {formatPkr(props.outstanding)} ·{' '}
                {t('Charges remaining')} {formatPkr(props.outstandingCharges)}
            </p>
            {!props.rule && (
                <p className="text-sm text-amber-700">
                    {t(
                        'Main admin must save a collection allocation rule before credit can be applied.',
                    )}
                </p>
            )}
            {props.credits.length === 0 && (
                <p className="text-sm text-muted-foreground">
                    {t(
                        'No unallocated recognized customer credit is available. Verify a customer receipt first.',
                    )}
                </p>
            )}
            <fieldset
                disabled={form.processing || !props.rule}
                className="space-y-5"
            >
                <div>
                    <Label htmlFor="credit">{t('Receipt credit')}</Label>
                    <select
                        id="credit"
                        className="mt-2 w-full rounded-md border bg-background p-2"
                        value={form.data.receipt_id}
                        onChange={(event) =>
                            form.setData('receipt_id', event.target.value)
                        }
                    >
                        <option value="">
                            {t('Select a recognized receipt')}
                        </option>
                        {props.credits.map((credit) => (
                            <option key={credit.id} value={credit.id}>
                                {credit.number} · {t('Available')}{' '}
                                {formatPkr(credit.available)}
                            </option>
                        ))}
                    </select>
                </div>
                <div>
                    <Label htmlFor="amount">
                        {t('Amount to allocate (PKR)')}
                    </Label>
                    <Input
                        id="amount"
                        className="mt-2"
                        inputMode="decimal"
                        value={form.data.amount}
                        onChange={(event) =>
                            form.setData('amount', event.target.value)
                        }
                    />
                </div>
                {props.rule === 'hybrid' && (
                    <div>
                        <Label htmlFor="mode">{t('Allocation method')}</Label>
                        <select
                            id="mode"
                            className="mt-2 w-full rounded-md border bg-background p-2"
                            value={form.data.mode}
                            onChange={(event) =>
                                form.setData('mode', event.target.value)
                            }
                        >
                            <option value="oldest_first">
                                {t('Oldest unpaid first')}
                            </option>
                            <option value="manual">
                                {t('Select installments')}
                            </option>
                        </select>
                    </div>
                )}
                {form.data.mode === 'manual' && (
                    <div className="space-y-3">
                        {props.outstanding !== '0.00' && (
                            <p className="text-sm text-muted-foreground">
                                {t(
                                    'Enter installment rows for the principal portion only. If the payment exceeds remaining principal, assign the remainder to manual charges below.',
                                )}
                            </p>
                        )}
                        {props.schedule
                            .filter((entry) => entry.remaining !== '0.00')
                            .map((entry) => (
                                <div key={entry.sequence}>
                                    <Label htmlFor={`row-${entry.sequence}`}>
                                        {t('Installment')} {entry.sequence} ·{' '}
                                        {entry.due_date} · {t('Remaining')}{' '}
                                        {formatPkr(entry.remaining)}
                                    </Label>
                                    <Input
                                        id={`row-${entry.sequence}`}
                                        inputMode="decimal"
                                        className="mt-2"
                                        placeholder={t('Leave blank to skip')}
                                        value={
                                            form.data.rows.find(
                                                (row) =>
                                                    row.sequence ===
                                                    entry.sequence,
                                            )?.amount ?? ''
                                        }
                                        onChange={(event) =>
                                            form.setData('rows', [
                                                ...form.data.rows.filter(
                                                    (row) =>
                                                        row.sequence !==
                                                        entry.sequence,
                                                ),
                                                ...(event.target.value
                                                    ? [
                                                          {
                                                              sequence:
                                                                  entry.sequence,
                                                              amount: event
                                                                  .target.value,
                                                          },
                                                      ]
                                                    : []),
                                            ])
                                        }
                                    />
                                </div>
                            ))}
                    </div>
                )}
                {props.outstandingCharges !== '0.00' && (
                    <div className="space-y-3">
                        <div>
                            <h3 className="font-semibold">
                                {t('Manual charge allocation')}
                            </h3>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {t(
                                    'Choose which charges receive any payment left after principal. Charge amounts must equal the payment remainder exactly.',
                                )}
                            </p>
                        </div>
                        {props.charges
                            .filter((charge) => charge.remaining !== '0.00')
                            .map((charge) => (
                                <div key={charge.id}>
                                    <Label htmlFor={`charge-row-${charge.id}`}>
                                        {charge.number} · {charge.business_date} ·{' '}
                                        {t('Remaining')}{' '}
                                        {formatPkr(charge.remaining)}
                                    </Label>
                                    <Input
                                        id={`charge-row-${charge.id}`}
                                        inputMode="decimal"
                                        className="mt-2"
                                        placeholder={t('Leave blank to skip')}
                                        value={
                                            form.data.charge_rows.find(
                                                (row) =>
                                                    row.charge_id === charge.id,
                                            )?.amount ?? ''
                                        }
                                        onChange={(event) =>
                                            form.setData('charge_rows', [
                                                ...form.data.charge_rows.filter(
                                                    (row) =>
                                                        row.charge_id !==
                                                        charge.id,
                                                ),
                                                ...(event.target.value
                                                    ? [
                                                          {
                                                              charge_id:
                                                                  charge.id,
                                                              amount: event
                                                                  .target.value,
                                                          },
                                                      ]
                                                    : []),
                                            ])
                                        }
                                    />
                                </div>
                            ))}
                    </div>
                )}
                <label className="flex items-start gap-3 rounded-lg border p-4 text-sm">
                    <input
                        type="checkbox"
                        className="mt-1 size-4"
                        checked={form.data.confirmed}
                        onChange={(event) =>
                            form.setData('confirmed', event.target.checked)
                        }
                    />
                    <span>
                        {t(
                            'I verified the customer, recognized receipt credit, principal-first allocation and the selected charges.',
                        )}
                    </span>
                </label>
                <Button
                    disabled={
                        !form.data.confirmed || props.credits.length === 0
                    }
                >
                    {t('Allocate receipt credit')}
                </Button>
            </fieldset>
            {Object.entries(form.errors).map(([key, message]) => (
                <p role="alert" key={key} className="text-sm text-destructive">
                    {message}
                </p>
            ))}
        </form>
    );
}
function CorrectionRequestForm(props: Props) {
    const t = useTranslate();
    const form = useForm({
        command_key: props.correctionCommandKey,
        version: props.version,
        allocation_id: '',
        amount: '',
        reason: '',
        confirmed: false,
    });
    useUnsavedChanges(form.isDirty);
    return (
        <form
            className="space-y-4 border-t pt-5"
            onSubmit={(event) => {
                event.preventDefault();
                form.post(
                    `/bookings/${props.booking.id}/allocation-corrections`,
                );
            }}
        >
            <h3 className="font-semibold">
                {t('Request an allocation correction')}
            </h3>
            <p className="text-sm text-muted-foreground">
                {t(
                    'An approved correction reverses this installment allocation and restores receipt credit only. Reallocation is a separate action.',
                )}
            </p>
            <div>
                <Label htmlFor="correction-allocation">
                    {t('Original allocation')}
                </Label>
                <select
                    id="correction-allocation"
                    className="mt-2 w-full rounded-md border bg-background p-2"
                    value={form.data.allocation_id}
                    onChange={(event) =>
                        form.setData('allocation_id', event.target.value)
                    }
                >
                    <option value="">{t('Select an allocation')}</option>
                    {props.correctionItems.map((item) => (
                        <option key={item.id} value={item.id}>
                            {item.number} · {t('Installment')} {item.sequence} ·{' '}
                            {item.business_date} · {t('Correctable')}{' '}
                            {formatPkr(item.available)}
                        </option>
                    ))}
                </select>
            </div>
            <div>
                <Label htmlFor="correction-amount">
                    {t('Amount to reverse (PKR)')}
                </Label>
                <Input
                    id="correction-amount"
                    className="mt-2"
                    inputMode="decimal"
                    value={form.data.amount}
                    onChange={(event) =>
                        form.setData('amount', event.target.value)
                    }
                />
            </div>
            <div>
                <Label htmlFor="correction-reason">{t('Reason')}</Label>
                <Input
                    id="correction-reason"
                    className="mt-2"
                    value={form.data.reason}
                    onChange={(event) =>
                        form.setData('reason', event.target.value)
                    }
                />
            </div>
            <label className="flex items-start gap-3 rounded-lg border p-4 text-sm">
                <input
                    type="checkbox"
                    className="mt-1 size-4"
                    checked={form.data.confirmed}
                    onChange={(event) =>
                        form.setData('confirmed', event.target.checked)
                    }
                />
                <span>
                    {t(
                        'I verified the original allocation and the correction evidence.',
                    )}
                </span>
            </label>
            <Button
                disabled={
                    !form.data.confirmed ||
                    !form.data.allocation_id ||
                    !form.data.amount ||
                    !form.data.reason
                }
            >
                {t('Submit for admin approval')}
            </Button>
            {Object.entries(form.errors).map(([key, message]) => (
                <p role="alert" key={key} className="text-sm text-destructive">
                    {message}
                </p>
            ))}
        </form>
    );
}
function CorrectionDecisionForm({
    correction,
}: {
    correction: Props['corrections'][number];
}) {
    const t = useTranslate();
    const form = useForm({
        decision: 'approve',
        decision_note: '',
        decision_key: correction.decision_key,
        confirmed: false,
    });
    useUnsavedChanges(form.isDirty);
    const submitDecision = (decision: 'approve' | 'reject') => {
        form.transform((data) => ({ ...data, decision }));
        form.post(`/allocation-corrections/${correction.id}/decision`);
    };
    return (
        <form
            className="mt-4 space-y-3 rounded-lg border p-4"
            onSubmit={(event) => {
                event.preventDefault();
                submitDecision('approve');
            }}
        >
            <Label htmlFor={`decision-note-${correction.id}`}>
                {t('Decision note')}
            </Label>
            <Input
                id={`decision-note-${correction.id}`}
                value={form.data.decision_note}
                onChange={(event) =>
                    form.setData('decision_note', event.target.value)
                }
            />
            <label className="flex items-start gap-3 text-sm">
                <input
                    type="checkbox"
                    className="mt-1 size-4"
                    checked={form.data.confirmed}
                    onChange={(event) =>
                        form.setData('confirmed', event.target.checked)
                    }
                />
                <span>
                    {t('I reviewed the source allocation and evidence.')}
                </span>
            </label>
            <div className="flex flex-wrap gap-2">
                <Button
                    disabled={!form.data.confirmed || !form.data.decision_note}
                >
                    {t('Approve correction')}
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    disabled={!form.data.confirmed || !form.data.decision_note}
                    onClick={() => submitDecision('reject')}
                >
                    {t('Reject correction')}
                </Button>
            </div>
            {Object.entries(form.errors).map(([key, message]) => (
                <p role="alert" key={key} className="text-sm text-destructive">
                    {message}
                </p>
            ))}
        </form>
    );
}
export default function Ledger(props: Props) {
    const { errors } = usePage().props;
    const t = useTranslate();
    return (
        <>
            <Head title={`Booking ${props.booking.id} ledger`} />
            <div className="ef-page">
                <BrandHeader />
                <PageHeading
                    eyebrow={t('Finance · Live contract ledger')}
                    title={`Booking #${props.booking.id} ${t('payments')}`}
                    description={`${props.booking.customer_name} · Plot ${props.booking.plot} · As of ${props.businessDate}`}
                >
                    <Button asChild variant="outline">
                        <a
                            href={`/bookings/${props.booking.id}/statement/print`}
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            {t('Print statement / Save PDF')}
                        </a>
                    </Button>
                    <Button asChild variant="outline">
                        <Link href="/finance/receipts">
                            {t('Customer receipts')}
                        </Link>
                    </Button>
                </PageHeading>
                <div className="grid gap-4 sm:grid-cols-3">
                    {[
                        ['Paid principal', props.paidPrincipal],
                        ['Remaining principal', props.outstanding],
                        ['Overdue principal', props.overdue],
                    ].map(([label, value]) => (
                        <section key={label} className="ef-panel p-5">
                            <p className="text-xs text-muted-foreground">
                                {t(label)}
                            </p>
                            <p className="mt-3 text-2xl font-semibold break-words">
                                {formatPkr(value)}
                            </p>
                        </section>
                    ))}
                </div>
                <p className="text-sm leading-6 text-muted-foreground">
                    {t(
                        'Paid principal includes the original down payment and later allocations. Overdue means an unpaid installment due before today in Karachi; amounts due today are separate. Late charges, refunds and adjustments are not included.',
                    )}
                </p>
                {errors.conflict && (
                    <p
                        role="alert"
                        className="rounded-lg bg-amber-50 p-4 text-sm text-amber-900"
                    >
                        {errors.conflict}
                    </p>
                )}
                <section className="ef-panel p-6">
                    <h2 className="font-semibold">
                        {t('Installment balances')}
                    </h2>
                    <ol className="mt-5 divide-y">
                        {props.schedule.map((entry) => (
                            <li
                                key={entry.sequence}
                                className="grid min-w-0 gap-3 py-4 text-sm break-words sm:grid-cols-4"
                            >
                                <div>
                                    <span className="font-semibold">
                                        {t('Installment')} {entry.sequence}
                                    </span>
                                    <p className="mt-1 text-muted-foreground">
                                        {entry.due_date}
                                    </p>
                                </div>
                                <div>
                                    {t('Agreed')} {formatPkr(entry.amount)}
                                    <p className="mt-1">
                                        {t('Applied')} {formatPkr(entry.paid)}
                                    </p>
                                </div>
                                <p>
                                    {t('Remaining')}{' '}
                                    {formatPkr(entry.remaining)}
                                </p>
                                <span className="font-semibold">
                                    {entry.status}
                                </span>
                            </li>
                        ))}
                    </ol>
                    {props.schedule.length === 0 && (
                        <p className="mt-4 text-sm">
                            {t(
                                'Full-payment contract. No installments remain.',
                            )}
                        </p>
                    )}
                </section>
                {props.canAllocate &&
                    (props.outstanding !== '0.00' ||
                        props.outstandingCharges !== '0.00') && (
                    <AllocationForm
                        key={`${props.booking.id}-${props.version}-${props.policyVersion}`}
                        {...props}
                    />
                )}
                <section className="ef-panel p-6">
                    <h2 className="font-semibold">{t('Manual charge balances')}</h2>
                    <p className="mt-2 text-sm text-muted-foreground">
                        {t('Charges are separate from principal. Any payment remainder is assigned to charges selected above.')}
                    </p>
                    {props.charges.length === 0 ? (
                        <p className="mt-4 text-sm text-muted-foreground">
                            {t('No charges have been posted to this booking.')}
                        </p>
                    ) : (
                        <ul className="mt-4 divide-y">
                            {props.charges.map((charge) => (
                                <li key={charge.id} className="py-3 text-sm">
                                    <div className="flex flex-wrap justify-between gap-2">
                                        <strong>{charge.number} · {charge.business_date}</strong>
                                        <strong>{formatPkr(charge.remaining)}</strong>
                                    </div>
                                    <p className="mt-1 text-muted-foreground">{charge.reason}</p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {t('Charge')} {formatPkr(charge.amount)} · {t('Paid')} {formatPkr(charge.paid)} · {t('Waived')} {formatPkr(charge.waived)}
                                    </p>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
                <section className="ef-panel p-6">
                    <h2 className="font-semibold">{t('Installment allocation history')}</h2>
                    {props.history.length === 0 && (
                        <p className="mt-4 text-sm text-muted-foreground">
                            {t('No later installment allocations recorded.')}
                        </p>
                    )}
                    {props.history.map((row) => (
                        <div
                            key={row.version}
                            className="mt-4 flex flex-wrap justify-between gap-3 border-t pt-4 text-sm"
                        >
                            <span>
                                {row.number} · {row.business_date} ·{' '}
                                {t('Revision')} {row.version}
                            </span>
                            <strong>{formatPkr(row.amount)}</strong>
                        </div>
                    ))}
                    <div className="mt-6 border-t pt-5">
                        <h3 className="font-semibold">{t('Charge payment history')}</h3>
                        {props.chargeHistory.length === 0 ? (
                            <p className="mt-3 text-sm text-muted-foreground">
                                {t('No charge payments recorded.')}
                            </p>
                        ) : (
                            props.chargeHistory.map((row) => (
                                <div
                                    key={row.version}
                                    className="mt-4 flex flex-wrap justify-between gap-3 border-t pt-4 text-sm"
                                >
                                    <span>{row.number} · {row.business_date} · {t('Revision')} {row.version}{row.reversed ? ` · ${t('Reversed')}` : ''}</span>
                                    <strong className={row.reversed ? 'line-through text-muted-foreground' : ''}>{formatPkr(row.amount)}</strong>
                                </div>
                            ))
                        )}
                    </div>
                    {props.canRequestCorrection &&
                        props.correctionItems.length > 0 && (
                            <CorrectionRequestForm {...props} />
                        )}
                    {props.corrections.length > 0 && (
                        <div className="mt-6 border-t pt-5">
                            <h3 className="font-semibold">
                                {t('Allocation correction requests')}
                            </h3>
                            {props.corrections.map((correction) => (
                                <article
                                    key={correction.id}
                                    className="mt-4 rounded-lg border p-4 text-sm"
                                >
                                    <p className="font-medium">
                                        {correction.number} · {t('Installment')}{' '}
                                        {correction.sequence} ·{' '}
                                        {formatPkr(correction.amount)} ·{' '}
                                        {t(
                                            correction.state === 'prepared'
                                                ? 'Prepared'
                                                : correction.state ===
                                                    'approved'
                                                  ? 'Approved'
                                                  : 'Rejected',
                                        )}
                                    </p>
                                    <p className="mt-2">
                                        {t('Reason')}: {correction.reason}
                                    </p>
                                    <p className="mt-1 text-muted-foreground">
                                        {t('Prepared by')}:{' '}
                                        {correction.preparer_name}
                                        {correction.decider_name &&
                                            ` · ${t('Decided by')}: ${correction.decider_name}`}
                                        {correction.business_date &&
                                            ` · ${correction.business_date}`}
                                    </p>
                                    {correction.decision_note && (
                                        <p className="mt-1">
                                            {t('Decision note')}:{' '}
                                            {correction.decision_note}
                                        </p>
                                    )}
                                    {props.canApproveCorrections &&
                                        correction.state === 'prepared' && (
                                            <CorrectionDecisionForm
                                                correction={correction}
                                            />
                                        )}
                                </article>
                            ))}
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}
