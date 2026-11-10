import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Calculator, Plus, Trash2 } from 'lucide-react';
import InputError from '@/components/input-error';
import { PageHeading } from '@/components/office-ui';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useUnsavedChanges } from '@/hooks/use-unsaved-changes';
import { formatPkr } from '@/lib/format-pkr';
import { useTranslate } from '@/lib/i18n';

const frequencies: Record<string, string> = {
    full: 'Full payment',
    monthly: 'Monthly',
    quarterly: 'Quarterly',
    half_yearly: 'Half-yearly',
    yearly: 'Yearly',
    custom: 'Custom dates and amounts',
};
type Row = { due_date: string; amount: string };
type TermsInput = {
    discount: string;
    discount_reason: string;
    down_payment: string;
    frequency: string;
    count: number | null;
    first_due_date: string | null;
    custom: Row[];
};
type Props = {
    draft: {
        id: number;
        version: number;
        quoted_price: string;
        customer: { name: string };
        plot: { number: string; block: { code: string } };
    };
    term: {
        version: number;
        snapshot: {
            gross: string;
            discount: string;
            net: string;
            down_payment: string;
            minimum_down_payment: string;
            balance: string;
            input: TermsInput;
            schedule: (Row & { sequence: number })[];
        };
    } | null;
    canDiscount: boolean;
    canConfigure: boolean;
    missingSetup: string[];
    stale: boolean;
    policies: {
        installments: {
            frequencies: string[];
            rounding: string | null;
            month_end: string | null;
            residual: string | null;
        };
    };
    policyVersions: { booking: number; installments: number };
    history: {
        version: number;
        actor_id: number;
        net: string;
        count: number;
        created_at: string;
    }[];
};

function TermsEditor(props: Props) {
    const t = useTranslate();
    const saved = props.term?.snapshot.input;
    const form = useForm({
        version: props.term?.version ?? 0,
        source_version: props.draft.version,
        policy_versions: props.policyVersions,
        discount: saved?.discount ?? '0.00',
        discount_reason: saved?.discount_reason ?? '',
        down_payment: saved?.down_payment ?? '',
        frequency: saved?.frequency ?? '',
        count: String(saved?.count ?? ''),
        first_due_date: saved?.first_due_date ?? '',
        custom: saved?.custom ?? ([] as Row[]),
    });
    useUnsavedChanges(form.isDirty);
    const recurring = !['', 'custom', 'full'].includes(form.data.frequency);
    const available = ['full', ...props.policies.installments.frequencies];
    if (form.data.frequency && !available.includes(form.data.frequency))
        available.push(form.data.frequency);
    const setupMissing =
        props.missingSetup.length > 0 ||
        !props.policyVersions.booking ||
        !props.policyVersions.installments;
    return (
        <form
            className="ef-panel overflow-hidden"
            onSubmit={(event) => {
                event.preventDefault();
                form.patch(`/sales/${props.draft.id}/terms`, {
                    preserveScroll: true,
                    onSuccess: () => form.setDefaults(),
                });
            }}
        >
            <div className="border-b p-5 sm:p-6">
                <h2 className="font-semibold">{t('Prepare proposed terms')}</h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    {t(
                        'Enter the planned amounts. The server calculates and saves the schedule when you submit.',
                    )}
                </p>
            </div>
            <fieldset
                disabled={form.processing || setupMissing}
                className="space-y-6 p-5 sm:p-6"
            >
                <div className="grid gap-5 sm:grid-cols-2">
                    <div>
                        <Label htmlFor="terms-discount">
                            {t('Admin discount (PKR)')}
                        </Label>
                        <Input
                            className="mt-2"
                            id="terms-discount"
                            inputMode="decimal"
                            required
                            readOnly={!props.canDiscount}
                            value={form.data.discount}
                            onChange={(event) =>
                                form.setData('discount', event.target.value)
                            }
                        />
                        <p className="mt-2 text-xs text-muted-foreground">
                            {props.canDiscount
                                ? t(
                                      'Enter 0 explicitly for no discount. Applies to the saved proposed gross price.',
                                  )
                                : t(
                                      'Only an authorized admin may set, remove or rebase a discount.',
                                  )}
                        </p>
                    </div>
                    <div>
                        <Label htmlFor="terms-reason">
                            {t('Discount reason')}
                        </Label>
                        <Input
                            className="mt-2"
                            id="terms-reason"
                            maxLength={500}
                            readOnly={!props.canDiscount}
                            value={form.data.discount_reason}
                            onChange={(event) =>
                                form.setData(
                                    'discount_reason',
                                    event.target.value,
                                )
                            }
                        />
                    </div>
                    <div>
                        <Label htmlFor="terms-down">
                            {t('Planned down payment (PKR) *')}
                        </Label>
                        <Input
                            className="mt-2"
                            id="terms-down"
                            inputMode="decimal"
                            required
                            value={form.data.down_payment}
                            onChange={(event) =>
                                form.setData('down_payment', event.target.value)
                            }
                        />
                        <p className="mt-2 text-xs text-muted-foreground">
                            {t(
                                'A proposal amount, not proof of received or cleared money.',
                            )}
                        </p>
                    </div>
                    <div>
                        <Label htmlFor="terms-frequency">
                            {t('Payment plan *')}
                        </Label>
                        <select
                            className="mt-2 h-11 w-full rounded-lg border bg-background px-3 text-sm"
                            id="terms-frequency"
                            required
                            value={form.data.frequency}
                            onChange={(event) =>
                                form.setData('frequency', event.target.value)
                            }
                        >
                            <option value="">
                                {t('Choose a payment plan')}
                            </option>
                            {available.map((key) => (
                                <option key={key} value={key}>
                                    {t(frequencies[key])}
                                </option>
                            ))}
                        </select>
                    </div>
                    {recurring && (
                        <>
                            <div>
                                <Label htmlFor="terms-count">
                                    {t('Number of installments *')}
                                </Label>
                                <Input
                                    className="mt-2"
                                    id="terms-count"
                                    type="number"
                                    min={1}
                                    max={360}
                                    required
                                    value={form.data.count}
                                    onChange={(event) =>
                                        form.setData(
                                            'count',
                                            event.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div>
                                <Label htmlFor="terms-first">
                                    {t('First installment date *')}
                                </Label>
                                <Input
                                    className="mt-2"
                                    id="terms-first"
                                    type="date"
                                    required
                                    value={form.data.first_due_date}
                                    onChange={(event) =>
                                        form.setData(
                                            'first_due_date',
                                            event.target.value,
                                        )
                                    }
                                />
                            </div>
                        </>
                    )}
                </div>
                {form.data.frequency === 'custom' && (
                    <section
                        aria-label={t('Custom installments')}
                        className="space-y-4"
                    >
                        <h3 className="text-sm font-semibold">
                            {t('Custom installment dates and amounts')}
                        </h3>
                        {form.data.custom.map((row, index) => (
                            <div
                                className="grid items-end gap-3 rounded-xl border p-4 sm:grid-cols-[1fr_1fr_auto]"
                                key={index}
                            >
                                <div>
                                    <Label htmlFor={`custom-date-${index}`}>
                                        {t('Due date')} {index + 1}
                                    </Label>
                                    <Input
                                        className="mt-2"
                                        id={`custom-date-${index}`}
                                        type="date"
                                        required
                                        value={row.due_date}
                                        onChange={(event) =>
                                            form.setData(
                                                'custom',
                                                form.data.custom.map(
                                                    (item, position) =>
                                                        position === index
                                                            ? {
                                                                  ...item,
                                                                  due_date:
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
                                    <Label htmlFor={`custom-amount-${index}`}>
                                        {t('Amount')} {index + 1} (PKR)
                                    </Label>
                                    <Input
                                        className="mt-2"
                                        id={`custom-amount-${index}`}
                                        inputMode="decimal"
                                        required
                                        value={row.amount}
                                        onChange={(event) =>
                                            form.setData(
                                                'custom',
                                                form.data.custom.map(
                                                    (item, position) =>
                                                        position === index
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
                                <Button
                                    type="button"
                                    variant="outline"
                                    aria-label={`Remove installment ${index + 1}`}
                                    onClick={() =>
                                        form.setData(
                                            'custom',
                                            form.data.custom.filter(
                                                (_, position) =>
                                                    position !== index,
                                            ),
                                        )
                                    }
                                >
                                    <Trash2 className="size-4" />
                                </Button>
                            </div>
                        ))}
                        <Button
                            type="button"
                            variant="outline"
                            disabled={form.data.custom.length >= 360}
                            onClick={() =>
                                form.setData('custom', [
                                    ...form.data.custom,
                                    { due_date: '', amount: '' },
                                ])
                            }
                        >
                            <Plus className="size-4" />
                            {t('Add installment')}
                        </Button>
                    </section>
                )}
                <div role="alert">
                    {Object.entries(form.errors).map(([key, message]) => (
                        <InputError key={key} message={message} />
                    ))}
                </div>
                <div className="flex flex-wrap items-center justify-between gap-4 border-t pt-5">
                    <span className="text-xs text-muted-foreground">
                        {props.term
                            ? `Terms version ${props.term.version}`
                            : t('No prepared terms yet')}
                    </span>
                    <Button disabled={form.processing || setupMissing}>
                        <Calculator className="size-4" />
                        {form.processing
                            ? 'Calculating…'
                            : t('Calculate & save proposed terms')}
                    </Button>
                </div>
            </fieldset>
        </form>
    );
}

export default function Terms(props: Props) {
    const t = useTranslate();
    const snapshot = props.term?.snapshot;
    return (
        <>
            <Head title={`Pricing · DRAFT-${props.draft.id}`} />
            <div className="ef-page">
                <PageHeading
                    eyebrow="Sales desk · Proposed terms"
                    title={t('Pricing & installments')}
                    description={`${props.draft.customer.name} · ${props.draft.plot.block.code} / ${props.draft.plot.number}`}
                >
                    <Button asChild variant="outline">
                        <Link href={`/sales/${props.draft.id}`}>
                            <ArrowLeft className="size-4" />
                            {t('Back to draft')}
                        </Link>
                    </Button>
                </PageHeading>
                <section className="rounded-2xl bg-slate-900 p-6 text-white sm:p-8">
                    <p className="text-xs font-medium tracking-widest text-blue-200">
                        {t('SAVED PROPOSED GROSS PRICE')}
                    </p>
                    <p className="mt-3 text-3xl font-bold break-words">
                        {formatPkr(props.draft.quoted_price)}
                    </p>
                    <p className="mt-3 max-w-2xl text-sm leading-6 text-slate-300">
                        {t(
                            'Prepare pricing and payment dates for review. Saving does not create a final contract, collect money or reserve the plot.',
                        )}
                    </p>
                </section>
                {props.missingSetup.length > 0 && (
                    <section
                        role="status"
                        className="rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900"
                    >
                        <h2 className="font-semibold">
                            {t('Main admin setup is incomplete')}
                        </h2>
                        <p className="mt-2 leading-6">
                            {t(
                                'Complete booking and installment settings before calculating terms. No financial or calendar defaults will be assumed.',
                            )}
                        </p>
                        <p className="mt-2 text-xs">
                            Missing: {props.missingSetup.join(', ')}
                        </p>
                        {props.canConfigure && (
                            <Link
                                className="mt-3 inline-block font-medium underline"
                                href="/business-settings/booking"
                            >
                                {t('Open Business settings')}
                            </Link>
                        )}
                    </section>
                )}
                {props.stale && (
                    <p
                        role="alert"
                        className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"
                    >
                        {t(
                            'The proposal or business settings changed. The schedule below is a saved historical calculation. Review and recalculate before using these terms.',
                        )}
                    </p>
                )}
                <TermsEditor
                    key={`terms-preparation-${props.draft.id}-${props.term?.version ?? 0}-${props.draft.version}`}
                    {...props}
                />
                {snapshot && (
                    <section
                        className="ef-panel overflow-hidden"
                        aria-label={t('Saved pricing and schedule')}
                    >
                        <div className="border-b p-5 sm:p-6">
                            <h2 className="font-semibold">
                                {t('Saved pricing & schedule')}
                            </h2>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Terms version {props.term?.version} ·{' '}
                                {props.stale
                                    ? 'Needs recalculation'
                                    : 'Current proposal and policy versions'}
                            </p>
                        </div>
                        <dl className="grid gap-px bg-border sm:grid-cols-2 xl:grid-cols-4">
                            {[
                                ['Net proposed price', snapshot.net],
                                ['Planned down payment', snapshot.down_payment],
                                [
                                    'Required minimum',
                                    snapshot.minimum_down_payment,
                                ],
                                ['Installment balance', snapshot.balance],
                            ].map(([label, value]) => (
                                <div
                                    className="min-w-0 bg-card p-5"
                                    key={label}
                                >
                                    <dt className="text-xs text-muted-foreground">
                                        {t(label)}
                                    </dt>
                                    <dd className="mt-2 text-lg font-semibold break-words">
                                        {formatPkr(value)}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                        <div className="max-h-96 overflow-auto">
                            <table className="ef-table">
                                <caption className="sr-only">
                                    {t('Saved proposed installment schedule')}
                                </caption>
                                <thead>
                                    <tr>
                                        <th>{t('Installment')}</th>
                                        <th>{t('Due date (Karachi)')}</th>
                                        <th>{t('Amount (PKR)')}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {snapshot.schedule.map((row) => (
                                        <tr key={row.sequence}>
                                            <td>{row.sequence}</td>
                                            <td className="whitespace-nowrap">
                                                {row.due_date}
                                            </td>
                                            <td className="whitespace-nowrap">
                                                {formatPkr(row.amount)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                            {snapshot.schedule.length === 0 && (
                                <p className="p-5 text-sm text-muted-foreground">
                                    {t(
                                        'Full-payment proposal. No future installments.',
                                    )}
                                </p>
                            )}
                        </div>
                    </section>
                )}
                {props.history.length > 0 && (
                    <details className="ef-panel p-5">
                        <summary className="cursor-pointer text-sm font-semibold">
                            {t('Recent terms revisions')}
                        </summary>
                        <ul className="mt-4 space-y-3 text-xs leading-5 text-muted-foreground">
                            {props.history.map((event) => (
                                <li key={event.version}>
                                    v{event.version} · {formatPkr(event.net)}{' '}
                                    net · {event.count} installment(s) · Staff #
                                    {event.actor_id} ·{' '}
                                    {event.created_at
                                        .replace('T', ' ')
                                        .slice(0, 19)}{' '}
                                    UTC
                                </li>
                            ))}
                        </ul>
                        <p className="mt-3 text-xs text-muted-foreground">
                            {t(
                                'Latest 10 revisions. Saved policy snapshots are retained with each calculation.',
                            )}
                        </p>
                    </details>
                )}
            </div>
        </>
    );
}
Terms.layout = {
    breadcrumbs: [
        { title: 'Sales desk', href: '/sales' },
        { title: 'Pricing & installments', href: '#' },
    ],
};
