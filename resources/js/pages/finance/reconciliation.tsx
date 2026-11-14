import { Head, Link, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { PageHeading, StatusBadge } from '@/components/office-ui';
import ExportMenu from '@/components/export-menu';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatPkr } from '@/lib/format-pkr';
import { useUnsavedChanges } from '@/hooks/use-unsaved-changes';
import { useTranslate } from '@/lib/i18n';

type Account = { id: number; code: string; name: string };
type BookEntry = {
    source_type: string;
    source_id: number;
    business_date: string;
    direction: 'inflow' | 'outflow';
    amount: string;
    source_number: string;
};
type StatementLine = {
    statement_date: string;
    amount: string;
    direction: BookEntry['direction'] | '';
    description: string;
    reference: string;
    source_type: string;
    source_id: number | '';
};
type ReconciliationForm = {
    command_key: string;
    financial_account_id: number | '';
    period_start: string;
    period_end: string;
    statement_opening_balance: string;
    statement_closing_balance: string;
    note: string;
    lines: StatementLine[];
    confirmed: boolean;
};
type Reconciliation = {
    id: number;
    period_start: string;
    period_end: string;
    statement_opening_balance: string;
    statement_closing_balance: string;
    state: 'prepared' | 'finalized' | 'rejected';
    note: string;
    decision_note: string | null;
    prepared_by: number;
    decider: { name: string } | null;
    preparer: { name: string };
    account: Account;
    decision_key: string | null;
    lines: {
        id: number;
        statement_date: string;
        amount: string;
        direction: string;
        description: string;
        reference: string | null;
        source_type: string;
        source_id: number;
    }[];
};
type Props = {
    accounts: Account[];
    selectedAccount: Account | null;
    filters: { account: string; from: string; to: string };
    openingEffectiveOn: string | null;
    bookOpeningBalance: string | null;
    bookClosingBalance: string | null;
    bookEntries: BookEntry[];
    records: Reconciliation[];
    businessDate: string;
    commandKey: string;
    canPrepare: boolean;
    canFinalize: boolean;
};

function PrepareForm(props: Props) {
    const t = useTranslate();
    const form = useForm<ReconciliationForm>({
        command_key: props.commandKey,
        financial_account_id: props.selectedAccount?.id ?? '',
        period_start: props.filters.from,
        period_end: props.filters.to,
        statement_opening_balance: '',
        statement_closing_balance: '',
        note: '',
        lines: props.bookEntries.map(() => ({
            statement_date: '',
            amount: '',
            direction: '',
            description: '',
            reference: '',
            source_type: '',
            source_id: '',
        })),
        confirmed: false,
    });
    useUnsavedChanges(form.isDirty);

    return (
        <form
            className="ef-panel space-y-5 p-5"
            onSubmit={(event) => {
                event.preventDefault();
                form.post('/finance/reconciliation', {
                    preserveScroll: true,
                });
            }}
        >
            <div>
                <h2 className="text-lg font-semibold">
                    {t('Prepare bank reconciliation')}
                </h2>
                <p className="mt-1 text-sm leading-6 text-muted-foreground">
                    {t(
                        'Enter statement values for every posted cashbook entry. Each line must match exactly; unmatched items and differences block submission.',
                    )}
                </p>
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
                <div>
                    <Label htmlFor="statement-opening">
                        {t('Statement opening balance')}
                    </Label>
                    <Input
                        id="statement-opening"
                        className="mt-2"
                        inputMode="decimal"
                        required
                        value={form.data.statement_opening_balance}
                        onChange={(event) =>
                            form.setData(
                                'statement_opening_balance',
                                event.target.value,
                            )
                        }
                    />
                    <p className="mt-1 text-xs text-muted-foreground">
                        {t('Expected book balance')}:{' '}
                        {formatPkr(props.bookOpeningBalance ?? '0.00')}
                    </p>
                </div>
                <div>
                    <Label htmlFor="statement-closing">
                        {t('Statement closing balance')}
                    </Label>
                    <Input
                        id="statement-closing"
                        className="mt-2"
                        inputMode="decimal"
                        required
                        value={form.data.statement_closing_balance}
                        onChange={(event) =>
                            form.setData(
                                'statement_closing_balance',
                                event.target.value,
                            )
                        }
                    />
                    <p className="mt-1 text-xs text-muted-foreground">
                        {t('Expected book balance')}:{' '}
                        {formatPkr(props.bookClosingBalance ?? '0.00')}
                    </p>
                </div>
            </div>
            <div>
                <Label htmlFor="reconciliation-note">{t('Review note')}</Label>
                <textarea
                    id="reconciliation-note"
                    className="ef-input mt-2 min-h-24"
                    required
                    value={form.data.note}
                    onChange={(event) =>
                        form.setData('note', event.target.value)
                    }
                />
            </div>
            <section className="space-y-3">
                <div>
                    <h3 className="font-semibold">{t('Statement lines')}</h3>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {t(
                            'Statement lines must match one-to-one with the listed posted entries. Statement dates may differ from book dates.',
                        )}
                    </p>
                </div>
                {props.bookEntries.length === 0 ? (
                    <p className="rounded-lg border p-4 text-sm text-muted-foreground">
                        {t(
                            'No posted cashbook entries fall within this period. Opening and closing statement balances must still agree.',
                        )}
                    </p>
                ) : (
                    <div className="space-y-3">
                        {form.data.lines.map((line, index) => {
                            const entry = props.bookEntries.find(
                                (candidate) =>
                                    candidate.source_type ===
                                        line.source_type &&
                                    candidate.source_id === line.source_id,
                            );

                            return (
                                <div
                                    key={index}
                                    className="rounded-lg border p-4"
                                >
                                    <p className="text-sm font-semibold">
                                        {entry
                                            ? `${entry.source_number} · ${entry.business_date} · ${t(entry.direction)} · ${formatPkr(entry.amount)}`
                                            : t(
                                                  'Select a cashbook entry to match.',
                                              )}
                                    </p>
                                    <div className="mt-3 grid gap-3 sm:grid-cols-2">
                                        <div>
                                            <Label
                                                htmlFor={`statement-match-${index}`}
                                            >
                                                {t('Match cashbook entry')}
                                            </Label>
                                            <select
                                                id={`statement-match-${index}`}
                                                className="ef-input mt-2"
                                                required
                                                value={
                                                    line.source_type &&
                                                    line.source_id !== ''
                                                        ? `${line.source_type}:${line.source_id}`
                                                        : ''
                                                }
                                                onChange={(event) => {
                                                    const matched =
                                                        props.bookEntries.find(
                                                            (candidate) =>
                                                                `${candidate.source_type}:${candidate.source_id}` ===
                                                                event.target
                                                                    .value,
                                                        );
                                                    if (!matched) return;
                                                    form.setData(
                                                        'lines',
                                                        form.data.lines.map(
                                                            (
                                                                item,
                                                                itemIndex,
                                                            ) =>
                                                                itemIndex ===
                                                                index
                                                                    ? {
                                                                          ...item,
                                                                          source_type:
                                                                              matched.source_type,
                                                                          source_id:
                                                                              matched.source_id,
                                                                      }
                                                                    : item,
                                                        ),
                                                    );
                                                }}
                                            >
                                                <option value="" disabled>
                                                    {t(
                                                        'Select a cashbook entry to match.',
                                                    )}
                                                </option>
                                                {props.bookEntries.map(
                                                    (candidate) => (
                                                        <option
                                                            key={`${candidate.source_type}-${candidate.source_id}`}
                                                            value={`${candidate.source_type}:${candidate.source_id}`}
                                                        >
                                                            {
                                                                candidate.source_number
                                                            }{' '}
                                                            ·{' '}
                                                            {
                                                                candidate.business_date
                                                            }{' '}
                                                            ·{' '}
                                                            {t(
                                                                candidate.direction,
                                                            )}{' '}
                                                            ·{' '}
                                                            {formatPkr(
                                                                candidate.amount,
                                                            )}
                                                        </option>
                                                    ),
                                                )}
                                            </select>
                                        </div>
                                        <div>
                                            <Label
                                                htmlFor={`statement-direction-${index}`}
                                            >
                                                {t('Statement direction')}
                                            </Label>
                                            <select
                                                id={`statement-direction-${index}`}
                                                className="ef-input mt-2"
                                                required
                                                value={line.direction}
                                                onChange={(event) => {
                                                    const direction =
                                                        event.target.value;
                                                    if (
                                                        direction !==
                                                            'inflow' &&
                                                        direction !== 'outflow'
                                                    ) {
                                                        return;
                                                    }
                                                    form.setData(
                                                        'lines',
                                                        form.data.lines.map(
                                                            (
                                                                item,
                                                                itemIndex,
                                                            ) =>
                                                                itemIndex ===
                                                                index
                                                                    ? {
                                                                          ...item,
                                                                          direction,
                                                                      }
                                                                    : item,
                                                        ),
                                                    );
                                                }}
                                            >
                                                <option value="" disabled>
                                                    {t(
                                                        'Select statement direction',
                                                    )}
                                                </option>
                                                <option value="inflow">
                                                    {t('inflow')}
                                                </option>
                                                <option value="outflow">
                                                    {t('outflow')}
                                                </option>
                                            </select>
                                        </div>
                                        <div>
                                            <Label
                                                htmlFor={`statement-date-${index}`}
                                            >
                                                {t('Statement date')}
                                            </Label>
                                            <Input
                                                id={`statement-date-${index}`}
                                                className="mt-2"
                                                type="date"
                                                required
                                                min={props.filters.from}
                                                max={props.filters.to}
                                                value={line.statement_date}
                                                onChange={(event) =>
                                                    form.setData(
                                                        'lines',
                                                        form.data.lines.map(
                                                            (
                                                                item,
                                                                itemIndex,
                                                            ) =>
                                                                itemIndex ===
                                                                index
                                                                    ? {
                                                                          ...item,
                                                                          statement_date:
                                                                              event
                                                                                  .target
                                                                                  .value,
                                                                      }
                                                                    : item,
                                                        ),
                                                    )
                                                }
                                            />
                                        </div>
                                        <div>
                                            <Label
                                                htmlFor={`statement-description-${index}`}
                                            >
                                                {t('Statement description')}
                                            </Label>
                                            <Input
                                                id={`statement-description-${index}`}
                                                className="mt-2"
                                                maxLength={200}
                                                required
                                                value={line.description}
                                                onChange={(event) =>
                                                    form.setData(
                                                        'lines',
                                                        form.data.lines.map(
                                                            (
                                                                item,
                                                                itemIndex,
                                                            ) =>
                                                                itemIndex ===
                                                                index
                                                                    ? {
                                                                          ...item,
                                                                          description:
                                                                              event
                                                                                  .target
                                                                                  .value,
                                                                      }
                                                                    : item,
                                                        ),
                                                    )
                                                }
                                            />
                                        </div>
                                        <div>
                                            <Label
                                                htmlFor={`statement-amount-${index}`}
                                            >
                                                {t('Statement amount (PKR)')}
                                            </Label>
                                            <Input
                                                id={`statement-amount-${index}`}
                                                className="mt-2"
                                                inputMode="decimal"
                                                required
                                                value={line.amount}
                                                onChange={(event) =>
                                                    form.setData(
                                                        'lines',
                                                        form.data.lines.map(
                                                            (
                                                                item,
                                                                itemIndex,
                                                            ) =>
                                                                itemIndex ===
                                                                index
                                                                    ? {
                                                                          ...item,
                                                                          amount: event
                                                                              .target
                                                                              .value,
                                                                      }
                                                                    : item,
                                                        ),
                                                    )
                                                }
                                            />
                                        </div>
                                        <div>
                                            <Label
                                                htmlFor={`statement-reference-${index}`}
                                            >
                                                {t('Statement reference')}
                                            </Label>
                                            <Input
                                                id={`statement-reference-${index}`}
                                                className="mt-2"
                                                maxLength={160}
                                                value={line.reference}
                                                onChange={(event) =>
                                                    form.setData(
                                                        'lines',
                                                        form.data.lines.map(
                                                            (
                                                                item,
                                                                itemIndex,
                                                            ) =>
                                                                itemIndex ===
                                                                index
                                                                    ? {
                                                                          ...item,
                                                                          reference:
                                                                              event
                                                                                  .target
                                                                                  .value,
                                                                      }
                                                                    : item,
                                                        ),
                                                    )
                                                }
                                            />
                                        </div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}
            </section>
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
                        'I verified the source statement and every exact cashbook match.',
                    )}
                </span>
            </label>
            <div role="alert">
                {Object.entries(form.errors).map(([key, message]) => (
                    <InputError key={key} message={message} />
                ))}
            </div>
            <Button disabled={form.processing || !form.data.confirmed}>
                {t('Submit for admin review')}
            </Button>
        </form>
    );
}

function DecisionForm({ record }: { record: Reconciliation }) {
    const t = useTranslate();
    const form = useForm({
        decision: 'finalize',
        decision_note: '',
        decision_key: record.decision_key ?? '',
        confirmed: false,
    });
    useUnsavedChanges(form.isDirty);
    const decide = (decision: 'finalize' | 'reject') => {
        form.transform((data) => ({ ...data, decision }));
        form.post(`/finance/reconciliation/${record.id}/decision`, {
            preserveScroll: true,
        });
    };

    return (
        <form
            className="mt-4 space-y-3 border-t pt-4"
            onSubmit={(event) => {
                event.preventDefault();
                decide('finalize');
            }}
        >
            <Label htmlFor={`decision-note-${record.id}`}>
                {t('Admin review note')}
            </Label>
            <Input
                id={`decision-note-${record.id}`}
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
                    {t(
                        'I reviewed the statement, exact matches and balance totals.',
                    )}
                </span>
            </label>
            <div className="flex flex-wrap gap-2">
                <Button
                    disabled={
                        form.processing ||
                        !form.data.confirmed ||
                        !form.data.decision_note
                    }
                >
                    {t('Finalize reconciliation')}
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    disabled={
                        form.processing ||
                        !form.data.confirmed ||
                        !form.data.decision_note
                    }
                    onClick={() => decide('reject')}
                >
                    {t('Reject reconciliation')}
                </Button>
            </div>
            <div role="alert">
                {Object.entries(form.errors).map(([key, message]) => (
                    <InputError key={key} message={message} />
                ))}
            </div>
        </form>
    );
}

export default function Reconciliation(props: Props) {
    const t = useTranslate();
    const preparedRecords = props.records.filter(
        (record) => record.state === 'prepared',
    );

    return (
        <>
            <Head title={t('Bank reconciliation')} />
            <div className="ef-page">
                <PageHeading
                    eyebrow={t('Finance · Bank control')}
                    title={t('Bank reconciliation')}
                    description={t(
                        'Compare verified bank statements with posted cashbook activity. Finalized periods are locked against later postings.',
                    )}
                >
                    <ExportMenu href="/finance/reconciliation/export" />
                    <Button asChild variant="outline">
                        <Link href="/finance/accounts">
                            {t('Financial accounts')}
                        </Link>
                    </Button>
                    <Button asChild variant="outline">
                        <Link href="/finance/movements">{t('Cashbook')}</Link>
                    </Button>
                </PageHeading>
                <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-950 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-100">
                    {t(
                        'This first version requires exact one-to-one matches. Unmatched statement items, timing differences and balance variances cannot be finalized or adjusted here.',
                    )}
                </div>
                <section className="ef-panel p-5">
                    <h2 className="font-semibold">
                        {t('Choose bank statement period')}
                    </h2>
                    <form
                        aria-label={t('Statement period filters')}
                        method="get"
                        action="/finance/reconciliation"
                        className="mt-4 grid grid-cols-1 gap-3 md:grid-cols-[minmax(12rem,1fr)_auto_auto_auto]"
                    >
                        <select
                            className="ef-input w-full min-w-0"
                            name="account"
                            aria-label={t('Bank account')}
                            defaultValue={props.filters.account}
                        >
                            <option value="">
                                {t('Select a bank account')}
                            </option>
                            {props.accounts.map((account) => (
                                <option key={account.id} value={account.id}>
                                    {account.code} · {account.name}
                                </option>
                            ))}
                        </select>
                        <Input
                            aria-label={t('Statement period start')}
                            name="from"
                            type="date"
                            max={props.businessDate}
                            defaultValue={props.filters.from}
                        />
                        <Input
                            aria-label={t('Statement period end')}
                            name="to"
                            type="date"
                            max={props.businessDate}
                            defaultValue={props.filters.to}
                        />
                        <Button className="h-11" variant="outline">
                            {t('Load statement period')}
                        </Button>
                    </form>
                </section>
                {props.selectedAccount && (
                    <section className="ef-panel space-y-4 p-5">
                        <h2 className="font-semibold">
                            {props.selectedAccount.code} ·{' '}
                            {props.selectedAccount.name}
                        </h2>
                        {!props.openingEffectiveOn ? (
                            <p className="text-sm text-amber-700">
                                {t(
                                    'Record a verified opening balance before reconciling this account.',
                                )}{' '}
                                <Link
                                    className="ef-text-link"
                                    href={`/finance/accounts/${props.selectedAccount.id}`}
                                >
                                    {t('Configure opening balance')}
                                </Link>
                            </p>
                        ) : props.filters.from < props.openingEffectiveOn ? (
                            <p className="text-sm text-amber-700">
                                {t(
                                    'The selected period begins before the opening balance takes effect.',
                                )}{' '}
                                {t('Effective from')} {props.openingEffectiveOn}
                            </p>
                        ) : props.bookOpeningBalance === null ||
                          props.bookClosingBalance === null ? (
                            <p className="text-sm text-muted-foreground">
                                {t(
                                    'Load a valid statement date range to continue.',
                                )}
                            </p>
                        ) : (
                            <>
                                <div className="grid gap-3 sm:grid-cols-3">
                                    <div className="rounded-lg border p-4">
                                        <p className="text-xs text-muted-foreground">
                                            {t('Expected opening balance')}
                                        </p>
                                        <p className="mt-1 font-semibold">
                                            {formatPkr(
                                                props.bookOpeningBalance,
                                            )}
                                        </p>
                                    </div>
                                    <div className="rounded-lg border p-4">
                                        <p className="text-xs text-muted-foreground">
                                            {t('Expected closing balance')}
                                        </p>
                                        <p className="mt-1 font-semibold">
                                            {formatPkr(
                                                props.bookClosingBalance,
                                            )}
                                        </p>
                                    </div>
                                    <div className="rounded-lg border p-4">
                                        <p className="text-xs text-muted-foreground">
                                            {t('Cashbook entries to match')}
                                        </p>
                                        <p className="mt-1 font-semibold">
                                            {props.bookEntries.length}
                                        </p>
                                    </div>
                                </div>
                                {props.canPrepare && (
                                    <PrepareForm
                                        key={`${props.selectedAccount.id}-${props.filters.from}-${props.filters.to}-${props.commandKey}`}
                                        {...props}
                                    />
                                )}
                            </>
                        )}
                    </section>
                )}
                {preparedRecords.length > 0 && (
                    <section className="space-y-4">
                        <h2 className="text-xl font-semibold">
                            {t('Awaiting admin review')}
                        </h2>
                        {preparedRecords.map((record) => (
                            <article key={record.id} className="ef-panel p-5">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <h3 className="font-semibold">
                                            {record.account.code} ·{' '}
                                            {record.period_start} –{' '}
                                            {record.period_end}
                                        </h3>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            {t('Prepared by')}{' '}
                                            {record.preparer.name} ·{' '}
                                            {record.lines.length}{' '}
                                            {t('matched statement lines')}
                                        </p>
                                    </div>
                                    <StatusBadge
                                        label={t('Prepared')}
                                        tone="warning"
                                    />
                                </div>
                                <p className="mt-3 text-sm">{record.note}</p>
                                <p className="mt-3 text-sm">
                                    {t('Opening')}{' '}
                                    {formatPkr(
                                        record.statement_opening_balance,
                                    )}{' '}
                                    · {t('Closing')}{' '}
                                    {formatPkr(
                                        record.statement_closing_balance,
                                    )}
                                </p>
                                {props.canFinalize && record.decision_key && (
                                    <DecisionForm
                                        key={`${record.id}-${record.decision_key}`}
                                        record={record}
                                    />
                                )}
                            </article>
                        ))}
                    </section>
                )}
                <section className="space-y-4">
                    <h2 className="text-xl font-semibold">
                        {t('Reconciliation history')}
                    </h2>
                    {props.records.filter(
                        (record) => record.state !== 'prepared',
                    ).length === 0 ? (
                        <p className="rounded-xl border p-5 text-sm text-muted-foreground">
                            {t('No completed reconciliation records.')}
                        </p>
                    ) : (
                        props.records
                            .filter((record) => record.state !== 'prepared')
                            .map((record) => (
                                <article
                                    key={record.id}
                                    className="ef-panel p-5"
                                >
                                    <div className="flex flex-wrap items-center justify-between gap-3">
                                        <h3 className="font-semibold">
                                            {record.account.code} ·{' '}
                                            {record.period_start} –{' '}
                                            {record.period_end}
                                        </h3>
                                        <StatusBadge
                                            label={t(record.state)}
                                            tone={
                                                record.state === 'finalized'
                                                    ? 'success'
                                                    : 'danger'
                                            }
                                        />
                                    </div>
                                    <p className="mt-2 text-sm text-muted-foreground">
                                        {t('Prepared by')}{' '}
                                        {record.preparer.name}
                                        {record.decider &&
                                            ` · ${t('Reviewed by')} ${record.decider.name}`}
                                    </p>
                                    {record.decision_note && (
                                        <p className="mt-2 text-sm">
                                            {record.decision_note}
                                        </p>
                                    )}
                                </article>
                            ))
                    )}
                </section>
            </div>
        </>
    );
}
