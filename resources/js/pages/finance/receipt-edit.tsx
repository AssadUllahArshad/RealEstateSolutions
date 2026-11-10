import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, FileClock, Save, ShieldCheck } from 'lucide-react';
import { useEffect, useState } from 'react';
import ReceiptPostingPanel, {
    type PostingContext,
} from '@/components/receipt-posting';
import InputError from '@/components/input-error';
import { PageHeading } from '@/components/office-ui';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useUnsavedChanges } from '@/hooks/use-unsaved-changes';
import { formatPkr } from '@/lib/format-pkr';
import { useTranslate } from '@/lib/i18n';
import type { Account } from './accounts';
import { methodLabels, type Receipt } from './receipts';

type Props = {
    posting: PostingContext | null;
    receipt: Receipt | null;
    privateDetails: { reference: string | null; notes: string | null } | null;
    creationKey: string;
    customers: { id: number; name: string }[];
    customerQuery: string;
    accounts: Account[];
    draftOptions: { id: number; customer_id: number }[];
    policyVersion: number;
    methods: Record<string, string>;
    postingMissing: string[];
    canPrepare: boolean;
    canConfigure: boolean;
    policyChanged: boolean;
    history: {
        id: number;
        actor_id: number;
        version: number;
        details: {
            amount: string;
            method: string;
            changed_fields: string[];
            reported_on: string;
        };
        created_at: string;
    }[];
};

function Editor(props: Props & { onDirtyChange: (dirty: boolean) => void }) {
    const t = useTranslate();
    const record = props.receipt;
    const form = useForm({
        customer_id: String(record?.customer_id ?? ''),
        financial_account_id: String(record?.financial_account_id ?? ''),
        booking_draft_id: String(record?.booking_draft_id ?? ''),
        amount: record?.amount ?? '',
        reported_on: record?.reported_on ?? '',
        method: record?.method ?? '',
        reference: props.privateDetails?.reference ?? '',
        notes: props.privateDetails?.notes ?? '',
        policy_version: props.policyVersion,
        ...(record
            ? { version: record.version }
            : { creation_key: props.creationKey }),
    });
    useUnsavedChanges(form.isDirty);
    useEffect(
        () => props.onDirtyChange(form.isDirty),
        [props.onDirtyChange, form.isDirty],
    );
    const customers = [...props.customers];
    if (
        record &&
        !customers.some((customer) => customer.id === record.customer_id)
    )
        customers.unshift(record.customer);
    const accounts = [...props.accounts];
    if (
        record &&
        !accounts.some((account) => account.id === record.financial_account_id)
    )
        accounts.unshift({
            id: record.financial_account_id,
            ...record.account_snapshot,
            active: false,
            version: 0,
        });
    const drafts = props.draftOptions.filter(
        (draft) => String(draft.customer_id) === form.data.customer_id,
    );
    if (
        record?.booking_draft_id &&
        !drafts.some((draft) => draft.id === record.booking_draft_id)
    )
        drafts.unshift({
            id: record.booking_draft_id,
            customer_id: record.customer_id,
        });
    const missingSetup =
        !props.policyVersion ||
        Object.keys(props.methods).length === 0 ||
        props.accounts.length === 0;

    return (
        <form
            className="ef-panel overflow-hidden"
            onSubmit={(event) => {
                event.preventDefault();
                const options = {
                    onSuccess: () => form.setDefaults(),
                    preserveScroll: true,
                };
                if (record)
                    form.patch(`/finance/receipts/${record.id}`, options);
                else form.post('/finance/receipts', options);
            }}
        >
            <div className="border-b p-5 sm:p-6">
                <h2 className="font-semibold">
                    {t('Reported payment details')}
                </h2>
                <p className="mt-1 text-sm leading-6 text-muted-foreground">
                    {t(
                        'Capture the report for verification. Saving does not recognize money or issue an official receipt.',
                    )}
                </p>
            </div>
            <fieldset
                disabled={form.processing || !props.canPrepare || missingSetup}
                className="space-y-6 p-5 sm:p-6"
            >
                <div className="grid gap-5 sm:grid-cols-2">
                    <div>
                        <Label htmlFor="receipt-customer">
                            {t('Customer *')}
                        </Label>
                        <select
                            id="receipt-customer"
                            className="mt-2 h-11 w-full rounded-lg border bg-background px-3 text-sm"
                            required
                            disabled={!!record}
                            value={form.data.customer_id}
                            onChange={(event) => {
                                form.setData('customer_id', event.target.value);
                                form.setData('booking_draft_id', '');
                            }}
                        >
                            <option value="">{t('Choose customer')}</option>
                            {customers.map((customer) => (
                                <option key={customer.id} value={customer.id}>
                                    {customer.name} (#{customer.id})
                                </option>
                            ))}
                        </select>
                        {record && (
                            <p className="mt-2 text-xs text-muted-foreground">
                                {t(
                                    'Customer is fixed on an existing preparation.',
                                )}
                            </p>
                        )}
                    </div>
                    <div>
                        <Label htmlFor="receipt-account">
                            {t('Destination account *')}
                        </Label>
                        <select
                            id="receipt-account"
                            className="mt-2 h-11 w-full rounded-lg border bg-background px-3 text-sm"
                            required
                            value={form.data.financial_account_id}
                            onChange={(event) =>
                                form.setData(
                                    'financial_account_id',
                                    event.target.value,
                                )
                            }
                        >
                            <option value="">
                                {t('Choose active account')}
                            </option>
                            {accounts.map((account) => (
                                <option
                                    key={account.id}
                                    value={account.id}
                                    disabled={!account.active}
                                >
                                    {account.code} · {account.name}
                                    {!account.active
                                        ? ` (${t('Inactive')})`
                                        : ''}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <Label htmlFor="receipt-amount">
                            {t('Reported amount (PKR) *')}
                        </Label>
                        <Input
                            id="receipt-amount"
                            className="mt-2"
                            required
                            inputMode="decimal"
                            value={form.data.amount}
                            onChange={(event) =>
                                form.setData('amount', event.target.value)
                            }
                        />
                    </div>
                    <div>
                        <Label htmlFor="receipt-date">
                            {t('Reported payment date *')}
                        </Label>
                        <Input
                            id="receipt-date"
                            className="mt-2"
                            type="date"
                            required
                            value={form.data.reported_on}
                            onChange={(event) =>
                                form.setData('reported_on', event.target.value)
                            }
                        />
                        <p className="mt-2 text-xs text-muted-foreground">
                            {t(
                                'Karachi business date reported by staff; not a ledger posting date.',
                            )}
                        </p>
                    </div>
                </div>
                <fieldset>
                    <legend className="text-sm font-semibold">
                        {t('Payment method *')}
                    </legend>
                    <div className="mt-3 grid gap-3 sm:grid-cols-2">
                        {Object.entries(props.methods).map(
                            ([method, label]) => (
                                <label
                                    key={method}
                                    className="flex cursor-pointer items-center gap-3 rounded-xl border p-4 text-sm has-checked:border-blue-400 has-checked:bg-blue-50 has-checked:text-blue-950"
                                >
                                    <input
                                        name="payment-method"
                                        type="radio"
                                        required
                                        className="size-4 accent-blue-600"
                                        checked={form.data.method === method}
                                        onChange={() =>
                                            form.setData('method', method)
                                        }
                                    />
                                    {t(label)}
                                </label>
                            ),
                        )}
                    </div>
                    {record && !props.methods[record.method] && (
                        <p className="mt-2 text-xs text-amber-800">
                            {t(
                                'The saved method is no longer enabled. Choose an accepted method before saving.',
                            )}
                        </p>
                    )}
                </fieldset>
                <div>
                    <Label htmlFor="receipt-reference">
                        {t('External payment reference')}
                        {form.data.method && form.data.method !== 'cash'
                            ? ' *'
                            : ''}
                    </Label>
                    <Input
                        id="receipt-reference"
                        className="mt-2"
                        maxLength={160}
                        required={
                            !!form.data.method && form.data.method !== 'cash'
                        }
                        value={form.data.reference}
                        onChange={(event) =>
                            form.setData('reference', event.target.value)
                        }
                        placeholder={t(
                            'Transaction or cheque/instrument reference',
                        )}
                    />
                    <p className="mt-2 text-xs leading-5 text-muted-foreground">
                        {t(
                            'Required for non-cash methods. A reference or cheque report is not proof of bank clearance.',
                        )}
                    </p>
                </div>
                <div>
                    <Label htmlFor="receipt-draft">
                        {t('Related booking draft (optional)')}
                    </Label>
                    <select
                        id="receipt-draft"
                        className="mt-2 h-11 w-full rounded-lg border bg-background px-3 text-sm"
                        value={form.data.booking_draft_id}
                        onChange={(event) =>
                            form.setData('booking_draft_id', event.target.value)
                        }
                    >
                        <option value="">
                            {t('Customer-level preparation')}
                        </option>
                        {drafts.map((draft) => (
                            <option key={draft.id} value={draft.id}>
                                DRAFT-{draft.id}
                            </option>
                        ))}
                    </select>
                    <p className="mt-2 text-xs leading-5 text-muted-foreground">
                        {t(
                            'Shows up to 100 recent drafts for the selected customer/search matches. This is context, not installment allocation.',
                        )}
                    </p>
                </div>
                <div>
                    <Label htmlFor="receipt-notes">
                        {t('Verification notes')}
                    </Label>
                    <textarea
                        id="receipt-notes"
                        className="mt-2 min-h-24 w-full rounded-lg border bg-background p-3 text-sm"
                        maxLength={1000}
                        value={form.data.notes}
                        onChange={(event) =>
                            form.setData('notes', event.target.value)
                        }
                        placeholder={t(
                            'Relevant payment context. Avoid CNICs, passwords and full bank-account details.',
                        )}
                    />
                </div>
                <div role="alert">
                    {Object.entries(form.errors).map(([key, message]) => (
                        <InputError key={key} message={message} />
                    ))}
                </div>
                <div className="flex flex-wrap items-center justify-between gap-3 border-t pt-5">
                    <span className="text-xs text-muted-foreground">
                        {record
                            ? `Saved preparation version ${record.version}`
                            : t('Not saved yet')}
                    </span>
                    <Button
                        disabled={
                            form.processing || !props.canPrepare || missingSetup
                        }
                    >
                        <Save className="size-4" />
                        {form.processing
                            ? 'Saving…'
                            : t('Save receipt preparation')}
                    </Button>
                </div>
            </fieldset>
        </form>
    );
}

export default function ReceiptEdit(props: Props) {
    const t = useTranslate();
    const { errors } = usePage().props;
    const [preparationDirty, setPreparationDirty] = useState(false);
    const missingSetup =
        !props.policyVersion ||
        Object.keys(props.methods).length === 0 ||
        props.accounts.length === 0;
    return (
        <>
            <Head
                title={
                    props.receipt
                        ? `PREP-${props.receipt.id}`
                        : t('Prepare receipt')
                }
            />
            <div className="ef-page">
                <PageHeading
                    eyebrow="Finance · Unposted preparation"
                    title={
                        props.receipt
                            ? `PREP-${props.receipt.id}`
                            : t('Prepare receipt')
                    }
                    description={t(
                        'Capture a reported customer payment, its destination and verification context.',
                    )}
                >
                    <Button asChild variant="outline">
                        <Link href="/finance/receipts">
                            <ArrowLeft className="size-4" />
                            {t('All preparations')}
                        </Link>
                    </Button>
                </PageHeading>
                <section className="rounded-2xl bg-slate-900 p-6 text-white">
                    <div className="flex items-center gap-3">
                        <FileClock className="size-6 text-blue-300" />
                        <span className="rounded-full border border-amber-300/40 bg-amber-400/10 px-3 py-1 text-xs font-semibold text-amber-200">
                            Prepared · Unposted
                        </span>
                    </div>
                    <p className="mt-4 text-3xl font-bold break-words">
                        {props.receipt
                            ? formatPkr(props.receipt.amount)
                            : t('Report now. Verify before posting.')}
                    </p>
                    <p className="mt-3 max-w-2xl text-sm leading-6 text-slate-300">
                        {t(
                            'PREP references are internal tracking IDs. This record does not issue an official receipt, change account balances, allocate installments or satisfy a booking down payment.',
                        )}
                    </p>
                </section>
                {errors.conflict && (
                    <p
                        role="alert"
                        className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"
                    >
                        {errors.conflict}
                    </p>
                )}
                {missingSetup && (
                    <section
                        role="status"
                        className="rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm leading-6 text-amber-900"
                    >
                        <h2 className="font-semibold">
                            {t('Preparation setup is incomplete')}
                        </h2>
                        <p className="mt-2">
                            {t(
                                'An admin must configure an active financial account. Main admin must save the accepted payment methods in Business settings.',
                            )}
                        </p>
                        {props.canConfigure && (
                            <Link
                                className="mt-3 inline-block underline"
                                href="/business-settings/collections"
                            >
                                {t('Configure accepted payment methods')}
                            </Link>
                        )}
                    </section>
                )}
                {props.policyChanged && (
                    <p
                        role="status"
                        className="rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900"
                    >
                        {t(
                            'Payment settings changed after this record was prepared. Saving again will capture the current policy version.',
                        )}
                    </p>
                )}
                {!props.receipt && (
                    <details className="ef-panel p-5">
                        <summary className="cursor-pointer text-sm font-semibold">
                            {t('Find a customer')}
                        </summary>
                        <p className="mt-3 text-xs text-muted-foreground">
                            {t(
                                'Lists show up to 30 matches. Search before entering details; this reloads the form.',
                            )}
                        </p>
                        <form method="get" className="mt-4 flex gap-2">
                            <Input
                                name="customer_q"
                                aria-label={t('Find receipt customer')}
                                maxLength={160}
                                defaultValue={props.customerQuery}
                                placeholder={t('Customer name')}
                            />
                            <Button variant="outline">
                                {t('Find matches')}
                            </Button>
                        </form>
                    </details>
                )}
                <div className="grid items-start gap-6 xl:grid-cols-[1fr_300px]">
                    <Editor
                        key={`receipt-editor-${props.receipt?.id ?? 'new'}-${props.receipt?.version ?? props.creationKey}`}
                        {...props}
                        onDirtyChange={setPreparationDirty}
                    />
                    <aside className="ef-panel p-5">
                        <ShieldCheck className="mb-3 size-6 text-blue-600" />
                        <h2 className="font-semibold">
                            {t('Before recognition')}
                        </h2>
                        <p className="mt-2 text-xs leading-6 text-muted-foreground">
                            {t(
                                'Save the preparation first. An authorized accountant or admin can then verify and issue it using the saved numbering, business-date and recognition rules.',
                            )}
                        </p>
                        {props.postingMissing.length > 0 && (
                            <>
                                <h3 className="mt-5 text-xs font-semibold">
                                    {t('Payment settings still missing')}
                                </h3>
                                <ul className="mt-3 space-y-2 text-xs leading-5 text-muted-foreground">
                                    {props.postingMissing.map((field) => (
                                        <li key={field}>• {field}</li>
                                    ))}
                                </ul>
                            </>
                        )}
                        <p className="mt-5 border-t pt-4 text-xs leading-6 text-muted-foreground">
                            {t(
                                'A later controlled posting workflow must verify evidence, destination, payment state and duplicate references. Cheques cannot become cleared through this form.',
                            )}
                        </p>
                    </aside>
                </div>
                {props.receipt && props.posting && (
                    <ReceiptPostingPanel
                        key={`issue-${props.receipt.id}-${props.receipt.version}-${props.posting.settings.versions.collections}-${props.posting.settings.versions.receipt_posting}`}
                        receipt={props.receipt}
                        posting={props.posting}
                        canConfigure={props.canConfigure}
                        preparationDirty={preparationDirty}
                    />
                )}
                {props.receipt && (
                    <section
                        className="ef-panel p-5"
                        aria-label={t('Preparation history')}
                    >
                        <h2 className="font-semibold">
                            {t('Recent preparation revisions')}
                        </h2>
                        <div className="mt-4 space-y-3">
                            {props.history.map((event) => (
                                <div
                                    key={event.id}
                                    className="rounded-lg border p-4 text-sm"
                                >
                                    <p className="font-semibold">
                                        v{event.version} ·{' '}
                                        {formatPkr(event.details.amount)} ·{' '}
                                        {methodLabels[event.details.method]}
                                    </p>
                                    <p className="mt-1 text-xs leading-5 text-muted-foreground">
                                        {t('Reported date')}{' '}
                                        {event.details.reported_on} · Staff #
                                        {event.actor_id} ·{' '}
                                        {event.created_at
                                            .replace('T', ' ')
                                            .slice(0, 19)}{' '}
                                        UTC
                                    </p>
                                </div>
                            ))}
                        </div>
                        <p className="mt-4 text-xs text-muted-foreground">
                            {t(
                                'Latest 10 revisions. Private references and notes are not copied into history.',
                            )}
                        </p>
                    </section>
                )}
            </div>
        </>
    );
}
ReceiptEdit.layout = {
    breadcrumbs: [
        { title: 'Receipt preparations', href: '/finance/receipts' },
        { title: 'Prepare receipt', href: '#' },
    ],
};
