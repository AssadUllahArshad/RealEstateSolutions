import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, Landmark, Undo2 } from 'lucide-react';
import InputError from '@/components/input-error';
import BrandHeader from '@/components/brand-header';
import { PageHeading } from '@/components/office-ui';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { PostingSettings } from '@/components/receipt-posting';
import { useUnsavedChanges } from '@/hooks/use-unsaved-changes';
import { formatPkr } from '@/lib/format-pkr';
import { useTranslate } from '@/lib/i18n';
import { methodLabels } from './receipts';

type Posted = {
    id: number;
    number: string;
    version: number;
    receipt_draft_id: number;
    amount: string;
    method: string;
    state: string;
    bank_stage: string | null;
    accepted_on: string;
    recognized_on: string | null;
    cleared_on: string | null;
    reversed_on: string | null;
    customer: { name: string };
    snapshot: {
        customer_name: string;
        posted_account: { code: string; name: string };
        collections: { values: { cheque_recognition: string | null } };
    };
};
type Props = {
    bookingLedgers: { id: number; confirmed_on: string }[];
    receipt: Posted;
    reference: string | null;
    movements: {
        id: number;
        kind: string;
        amount: string;
        business_date: string;
    }[];
    history: {
        id: number;
        event: string;
        actor_id: number;
        version: number;
        details: { business_date: string; state: string };
        note: string | null;
        created_at: string;
    }[];
    posting: {
        settings: PostingSettings;
        canPost: boolean;
        canReverse: boolean;
        clearKey: string;
        reverseKey: string;
    };
};
const eventLabels: Record<string, string> = {
    accepted_recognized: 'Receipt accepted and money recognized',
    accepted_pending: 'Cheque accepted, awaiting clearance',
    cheque_cleared: 'Bank clearance recorded',
    accounting_reversal: 'Accounting reversal recorded',
};

function Action({
    receipt,
    operation,
    commandKey,
    settings,
}: {
    receipt: Posted;
    operation: 'clear' | 'reverse';
    commandKey: string;
    settings: PostingSettings;
}) {
    const t = useTranslate();
    const reverse = operation === 'reverse';
    const form = useForm({
        command_key: commandKey,
        expected_version: receipt.version,
        posting_version: settings.versions.receipt_posting,
        business_date: '',
        note: '',
        confirmed: false,
    });
    useUnsavedChanges(form.isDirty);
    const blocked = settings.missing.receipt_posting.length > 0;
    return (
        <form
            className="ef-panel overflow-hidden"
            onSubmit={(event) => {
                event.preventDefault();
                form.post(`/finance/posted/${receipt.id}/${operation}`);
            }}
        >
            <div className="border-b p-5">
                <h2 className="font-semibold">
                    {reverse
                        ? t('Reverse accounting entry')
                        : t('Record bank clearance')}
                </h2>
                <p className="mt-2 text-sm leading-6 text-muted-foreground">
                    {reverse
                        ? t(
                              'Admin-only correction. Retains the original number and history; offsets any recognized receipt credit. This does not pay a cash refund.',
                          )
                        : t(
                              'Verify actual bank clearance. A receipt recognized on deposit will not create a second credit.',
                          )}
                </p>
            </div>
            <fieldset
                className="space-y-5 p-5"
                disabled={form.processing || blocked}
            >
                {blocked && (
                    <p role="status" className="text-sm text-amber-900">
                        {t(
                            'Main admin must complete receipt posting controls first.',
                        )}
                    </p>
                )}
                <div>
                    <Label htmlFor={`${operation}-date`}>
                        {reverse ? t('Reversal') : t('Clearance')}{' '}
                        {t('business date *')}
                    </Label>
                    <Input
                        className="mt-2"
                        id={`${operation}-date`}
                        type="date"
                        required
                        value={form.data.business_date}
                        onChange={(event) =>
                            form.setData('business_date', event.target.value)
                        }
                    />
                </div>
                <div>
                    <Label htmlFor={`${operation}-note`}>
                        {reverse
                            ? t('Reversal reason')
                            : t('Clearance evidence')}{' '}
                        *
                    </Label>
                    <textarea
                        className="mt-2 min-h-24 w-full rounded-lg border bg-background p-3 text-sm"
                        id={`${operation}-note`}
                        required
                        minLength={10}
                        maxLength={1000}
                        value={form.data.note}
                        onChange={(event) =>
                            form.setData('note', event.target.value)
                        }
                    />
                </div>
                <label className="flex items-start gap-3 rounded-xl border p-4 text-sm leading-6">
                    <input
                        type="checkbox"
                        className="mt-1 size-4 shrink-0 accent-blue-600"
                        required
                        checked={form.data.confirmed}
                        onChange={(event) =>
                            form.setData('confirmed', event.target.checked)
                        }
                    />
                    {reverse
                        ? t(
                              'I authorize this full accounting reversal and have reviewed the supporting reason. No refund is being paid by this action.',
                          )
                        : t(
                              'I checked bank clearance evidence for this exact cheque and amount.',
                          )}
                </label>
                <div role="alert">
                    {Object.entries(form.errors).map(([key, message]) => (
                        <InputError key={key} message={message} />
                    ))}
                </div>
                <Button
                    variant={reverse ? 'destructive' : 'default'}
                    disabled={
                        form.processing || blocked || !form.data.confirmed
                    }
                >
                    {reverse ? (
                        <Undo2 className="size-4" />
                    ) : (
                        <CheckCircle2 className="size-4" />
                    )}
                    {form.processing
                        ? 'Recording…'
                        : reverse
                          ? t('Record full accounting reversal')
                          : t('Confirm bank clearance')}
                </Button>
            </fieldset>
        </form>
    );
}

export default function PostedReceipt(props: Props) {
    const t = useTranslate();
    const { errors } = usePage().props;
    const receipt = props.receipt;
    const reversed = receipt.state === 'reversed';
    const recognized = receipt.state === 'recognized';
    const canClear =
        !reversed &&
        receipt.method === 'cheque' &&
        receipt.bank_stage === 'deposited' &&
        props.posting.canPost;
    return (
        <>
            <Head title={receipt.number} />
            <div className="ef-page">
                <BrandHeader />
                {props.bookingLedgers.length > 0 && (
                    <section
                        className="ef-panel flex flex-wrap items-center gap-3 p-4"
                        aria-label={t('Customer booking ledgers')}
                    >
                        <span className="text-sm font-semibold">
                            {t('Customer bookings')}
                        </span>
                        {props.bookingLedgers.map((booking) => (
                            <Button
                                asChild
                                key={booking.id}
                                variant="outline"
                                size="sm"
                            >
                                <Link href={`/bookings/${booking.id}/ledger`}>
                                    {t('Booking')} #{booking.id} {t('ledger')}
                                </Link>
                            </Button>
                        ))}
                    </section>
                )}
                <PageHeading
                    eyebrow="Finance · Issued receipt"
                    title={receipt.number}
                    description={`Customer: ${receipt.snapshot.customer_name} · Source PREP-${receipt.receipt_draft_id} · Version ${receipt.version}`}
                >
                    <Button asChild variant="outline">
                        <a
                            href={`/finance/posted/${receipt.id}/print`}
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            {t('Print / Save PDF')}
                        </a>
                    </Button>
                    <Button asChild variant="outline">
                        <Link href="/finance/receipts">
                            <ArrowLeft className="size-4" />
                            {t('All receipts')}
                        </Link>
                    </Button>
                </PageHeading>
                {errors.conflict && (
                    <p
                        role="alert"
                        className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"
                    >
                        {errors.conflict}
                    </p>
                )}
                <section className="rounded-2xl bg-slate-900 p-6 text-white sm:p-8">
                    <span
                        className={`inline-block rounded-full px-3 py-1 text-xs font-semibold ${reversed ? 'bg-rose-500/20 text-rose-200' : recognized ? 'bg-emerald-500/20 text-emerald-200' : 'bg-amber-400/20 text-amber-200'}`}
                    >
                        {reversed
                            ? t('Reversed')
                            : recognized
                              ? t('Money recognized')
                              : t('Awaiting bank clearance')}
                    </span>
                    <p className="mt-4 text-3xl font-bold break-words">
                        {formatPkr(receipt.amount)}
                    </p>
                    <p className="mt-3 text-sm leading-6 text-slate-300">
                        {receipt.method === 'cheque'
                            ? `Cheque bank stage: ${receipt.bank_stage === 'cleared' ? 'Cleared' : 'Deposited, not bank-cleared'}. Recognition trigger captured at issue: ${receipt.snapshot.collections.values.cheque_recognition}.`
                            : `${methodLabels[receipt.method]} verified by authorized staff.`}
                    </p>
                    {reversed && (
                        <p className="mt-3 text-sm leading-6 text-rose-200">
                            This receipt is reversed and cannot support a
                            booking payment. The original record and movements
                            are retained.
                        </p>
                    )}
                </section>
                <section
                    className="ef-panel p-5 sm:p-6"
                    aria-label={t('Issued receipt details')}
                >
                    <h2 className="font-semibold">
                        {t('Immutable receipt details')}
                    </h2>
                    <dl className="mt-5 grid gap-5 text-sm sm:grid-cols-2 xl:grid-cols-3">
                        {[
                            [
                                'Destination',
                                `${receipt.snapshot.posted_account.code} · ${receipt.snapshot.posted_account.name}`,
                            ],
                            [
                                'External reference',
                                props.reference || 'No external reference',
                            ],
                            ['Accepted on', receipt.accepted_on],
                            [
                                'Money recognized on',
                                receipt.recognized_on || 'Not recognized',
                            ],
                            [
                                'Bank cleared on',
                                receipt.cleared_on ||
                                    'Not bank-cleared / not applicable',
                            ],
                            [
                                'Reversed on',
                                receipt.reversed_on || 'Not reversed',
                            ],
                        ].map(([label, value]) => (
                            <div className="min-w-0" key={label}>
                                <dt className="text-xs text-muted-foreground">
                                    {t(label)}
                                </dt>
                                <dd className="mt-2 break-words">{value}</dd>
                            </div>
                        ))}
                    </dl>
                    <p className="mt-5 border-t pt-4 text-xs leading-6 text-muted-foreground">
                        {t(
                            'The source preparation is frozen. Recognized money is unallocated customer credit; no installment allocation, owner/commission accrual or final booking occurs here.',
                        )}
                    </p>
                </section>
                <section
                    className="ef-panel overflow-hidden"
                    aria-label={t('Receipt movements')}
                >
                    <div className="border-b p-5">
                        <h2 className="font-semibold">
                            {t('Receipt movements')}
                        </h2>
                        <p className="mt-1 text-xs leading-5 text-muted-foreground">
                            Immutable receipt credits and accounting reversals.
                            These are not a complete account balance or a formal
                            balance sheet.
                        </p>
                    </div>
                    {props.movements.length ? (
                        <ul className="divide-y">
                            {props.movements.map((movement) => (
                                <li
                                    key={movement.id}
                                    className="flex flex-wrap justify-between gap-3 p-5 text-sm"
                                >
                                    <div>
                                        <p className="font-medium">
                                            {movement.kind === 'receipt_credit'
                                                ? t('Receipt credit')
                                                : t('Accounting reversal')}
                                        </p>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {movement.business_date} · Karachi
                                            business date
                                        </p>
                                    </div>
                                    <p
                                        className={
                                            movement.kind === 'receipt_credit'
                                                ? 'font-semibold text-emerald-700'
                                                : 'font-semibold text-rose-700'
                                        }
                                    >
                                        {movement.kind === 'receipt_credit'
                                            ? '+'
                                            : '−'}
                                        {formatPkr(movement.amount)}
                                    </p>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p className="p-5 text-sm text-muted-foreground">
                            {t(
                                'No money recognized. This instrument is awaiting bank clearance.',
                            )}
                        </p>
                    )}
                </section>
                {(canClear || (!reversed && props.posting.canReverse)) && (
                    <div className="grid items-start gap-6 xl:grid-cols-2">
                        {canClear && (
                            <Action
                                key={`clear-${receipt.id}-${receipt.version}`}
                                receipt={receipt}
                                operation="clear"
                                commandKey={props.posting.clearKey}
                                settings={props.posting.settings}
                            />
                        )}
                        {!reversed && props.posting.canReverse && (
                            <Action
                                key={`reverse-${receipt.id}-${receipt.version}`}
                                receipt={receipt}
                                operation="reverse"
                                commandKey={props.posting.reverseKey}
                                settings={props.posting.settings}
                            />
                        )}
                    </div>
                )}
                <section
                    className="ef-panel p-5"
                    aria-label={t('Posting history')}
                >
                    <h2 className="font-semibold">
                        {t('Posting audit trail')}
                    </h2>
                    <ol className="mt-5 space-y-4">
                        {props.history.map((event) => (
                            <li
                                key={event.id}
                                className="rounded-xl border p-4 text-sm"
                            >
                                <p className="font-semibold">
                                    v{event.version} ·{' '}
                                    {eventLabels[event.event] ?? event.event}
                                </p>
                                <p className="mt-2 text-xs leading-5 text-muted-foreground">
                                    {t('Business date')}{' '}
                                    {event.details.business_date} · Staff #
                                    {event.actor_id} ·{' '}
                                    {event.created_at
                                        .replace('T', ' ')
                                        .slice(0, 19)}{' '}
                                    UTC
                                </p>
                                {event.note && (
                                    <p className="mt-3 leading-6 break-words">
                                        {event.note}
                                    </p>
                                )}
                            </li>
                        ))}
                    </ol>
                </section>
                <p className="flex items-start gap-2 text-xs leading-6 text-muted-foreground">
                    <Landmark className="mt-1 size-4 shrink-0" />
                    {t(
                        'Receipt evidence is verified manually by authorized staff. No bank API verification or cash refund is performed by this workflow.',
                    )}
                </p>
            </div>
        </>
    );
}
PostedReceipt.layout = {
    breadcrumbs: [
        { title: 'Receipts', href: '/finance/receipts' },
        { title: 'Issued receipt', href: '#' },
    ],
};
