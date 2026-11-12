import { Head, useForm } from '@inertiajs/react';
import { Calculator, Plus, Trash2 } from 'lucide-react';
import InputError from '@/components/input-error';
import { PageHeading } from '@/components/office-ui';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
type Result = {
    gross: string;
    discount: string;
    net: string;
    minimum_down_payment: string;
    down_payment: string;
    balance: string;
    schedule: (Row & { sequence: number })[];
    discount_type: 'fixed' | 'percentage';
    discount_value: string;
    commission: { amount: string | null; formula: string; note: string };
    policy_versions: { booking: number; installments: number; commissions: number };
};
type Props = {
    policies: {
        booking: { down_payment_type: string | null; down_payment_value: string | null };
        installments: {
            frequencies: string[];
            rounding: string | null;
            month_end: string | null;
            residual: string | null;
        };
        commissions: {
            type: string | null;
            value: string | null;
            basis: string | null;
            trigger: string | null;
        };
    };
    missingSetup: string[];
    commissionMissing: boolean;
    result: Result | null;
    input: {
        gross: string;
        discount_type: 'fixed' | 'percentage';
        discount_value: string;
        down_payment: string;
        frequency: string;
        count: number | null;
        first_due_date: string | null;
        custom: Row[];
    } | null;
};

export default function CalculatorPage({
    policies,
    missingSetup,
    commissionMissing,
    result,
    input,
}: Props) {
    const t = useTranslate();
    const form = useForm({
        gross: input?.gross ?? '',
        discount_type: input?.discount_type ?? 'fixed',
        discount_value: input?.discount_value ?? '0.00',
        down_payment: input?.down_payment ?? '',
        frequency: input?.frequency ?? '',
        count: String(input?.count ?? ''),
        first_due_date: input?.first_due_date ?? '',
        custom: input?.custom ?? ([] as Row[]),
    });
    const recurring = !['', 'custom', 'full'].includes(form.data.frequency);
    const available = ['full', ...policies.installments.frequencies];
    if (form.data.frequency && !available.includes(form.data.frequency))
        available.push(form.data.frequency);
    const setupMissing = missingSetup.length > 0;
    return (
        <>
            <Head title="Payment plan calculator" />
            <div className="ef-page">
                <PageHeading
                    eyebrow="Sales desk · Preview only"
                    title={t('Payment plan calculator')}
                    description={t(
                        'Preview a plot price, discount, down payment and installment schedule. Nothing is saved, booked or collected.',
                    )}
                />
                {setupMissing && (
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
                            Missing: {missingSetup.join(', ')}
                        </p>
                    </section>
                )}
                <form
                    className="ef-panel overflow-hidden"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post('/calculator', { preserveScroll: true });
                    }}
                >
                    <div className="border-b p-5 sm:p-6">
                        <h2 className="font-semibold">{t('Preview inputs')}</h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {t(
                                'Enter the proposed amounts. The server calculates the schedule; nothing is saved.',
                            )}
                        </p>
                    </div>
                    <fieldset
                        disabled={form.processing || setupMissing}
                        className="space-y-6 p-5 sm:p-6"
                    >
                        <div className="grid gap-5 sm:grid-cols-2">
                            <div>
                                <Label htmlFor="calc-gross">
                                    {t('Plot price (PKR) *')}
                                </Label>
                                <Input
                                    className="mt-2"
                                    id="calc-gross"
                                    inputMode="decimal"
                                    required
                                    value={form.data.gross}
                                    onChange={(event) =>
                                        form.setData('gross', event.target.value)
                                    }
                                />
                            </div>
                            <div>
                                <Label htmlFor="calc-discount-type">
                                    {t('Discount type')}
                                </Label>
                                <select
                                    className="mt-2 h-11 w-full rounded-lg border bg-background px-3 text-sm"
                                    id="calc-discount-type"
                                    value={form.data.discount_type}
                                    onChange={(event) =>
                                        form.setData(
                                            'discount_type',
                                            event.target.value as 'fixed' | 'percentage',
                                        )
                                    }
                                >
                                    <option value="fixed">{t('Fixed PKR')}</option>
                                    <option value="percentage">
                                        {t('Percentage of price')}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <Label htmlFor="calc-discount-value">
                                    {form.data.discount_type === 'percentage'
                                        ? t('Discount (%)')
                                        : t('Discount (PKR)')}
                                </Label>
                                <Input
                                    className="mt-2"
                                    id="calc-discount-value"
                                    inputMode="decimal"
                                    required
                                    value={form.data.discount_value}
                                    onChange={(event) =>
                                        form.setData(
                                            'discount_value',
                                            event.target.value,
                                        )
                                    }
                                />
                                <p className="mt-2 text-xs text-muted-foreground">
                                    {t(
                                        'Enter 0 explicitly for no discount. A preview cannot approve a discount.',
                                    )}
                                </p>
                            </div>
                            <div>
                                <Label htmlFor="calc-down">
                                    {t('Planned down payment (PKR) *')}
                                </Label>
                                <Input
                                    className="mt-2"
                                    id="calc-down"
                                    inputMode="decimal"
                                    required
                                    value={form.data.down_payment}
                                    onChange={(event) =>
                                        form.setData(
                                            'down_payment',
                                            event.target.value,
                                        )
                                    }
                                />
                                <p className="mt-2 text-xs text-muted-foreground">
                                    {t(
                                        'A preview amount, not proof of received or cleared money.',
                                    )}
                                </p>
                            </div>
                            <div>
                                <Label htmlFor="calc-frequency">
                                    {t('Payment plan *')}
                                </Label>
                                <select
                                    className="mt-2 h-11 w-full rounded-lg border bg-background px-3 text-sm"
                                    id="calc-frequency"
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
                                        <Label htmlFor="calc-count">
                                            {t('Number of installments *')}
                                        </Label>
                                        <Input
                                            className="mt-2"
                                            id="calc-count"
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
                                        <Label htmlFor="calc-first">
                                            {t('First installment date *')}
                                        </Label>
                                        <Input
                                            className="mt-2"
                                            id="calc-first"
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
                                            <Label htmlFor={`calc-custom-date-${index}`}>
                                                {t('Due date')} {index + 1}
                                            </Label>
                                            <Input
                                                className="mt-2"
                                                id={`calc-custom-date-${index}`}
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
                                            <Label htmlFor={`calc-custom-amount-${index}`}>
                                                {t('Amount')} {index + 1} (PKR)
                                            </Label>
                                            <Input
                                                className="mt-2"
                                                id={`calc-custom-amount-${index}`}
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
                                {t(
                                    'Preview only. Nothing is saved, booked or collected.',
                                )}
                            </span>
                            <Button disabled={form.processing || setupMissing}>
                                <Calculator className="size-4" />
                                {form.processing
                                    ? 'Calculating…'
                                    : t('Calculate preview')}
                            </Button>
                        </div>
                    </fieldset>
                </form>
                {result && (
                    <section
                        className="ef-panel overflow-hidden"
                        aria-label={t('Preview result')}
                    >
                        <div className="border-b p-5 sm:p-6">
                            <h2 className="font-semibold">{t('Preview result')}</h2>
                            <p className="mt-1 text-xs text-muted-foreground">
                                {t(
                                    'Estimate only. Not a contract, receipt or financial transaction.',
                                )}
                            </p>
                        </div>
                        <dl className="grid gap-px bg-border sm:grid-cols-2 xl:grid-cols-4">
                            {[
                                ['Net price', result.net],
                                ['Discount', result.discount],
                                ['Planned down payment', result.down_payment],
                                ['Required minimum', result.minimum_down_payment],
                                ['Installment balance', result.balance],
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
                        <div className="border-t p-5 sm:p-6">
                            <h3 className="text-sm font-semibold">
                                {t('Referral commission preview')}
                            </h3>
                            <p className="mt-2 text-sm">
                                {result.commission.amount !== null
                                    ? formatPkr(result.commission.amount)
                                    : t('No single preview amount')}
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                {result.commission.formula} ·{' '}
                                {result.commission.note}
                            </p>
                            {commissionMissing && (
                                <p className="mt-2 text-xs text-amber-800">
                                    {t(
                                        'Agent commission settings are incomplete; the preview is unavailable.',
                                    )}
                                </p>
                            )}
                        </div>
                        <div className="max-h-96 overflow-auto border-t">
                            <table className="ef-table">
                                <caption className="sr-only">
                                    {t('Preview installment schedule')}
                                </caption>
                                <thead>
                                    <tr>
                                        <th>{t('Installment')}</th>
                                        <th>{t('Due date (Karachi)')}</th>
                                        <th>{t('Amount (PKR)')}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {result.schedule.map((row) => (
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
                            {result.schedule.length === 0 && (
                                <p className="p-5 text-sm text-muted-foreground">
                                    {t(
                                        'Full-payment preview. No future installments.',
                                    )}
                                </p>
                            )}
                        </div>
                        <p className="border-t p-5 text-xs text-muted-foreground">
                            {t(
                                'Formula: net = price − discount; balance = net − down payment; installments split the balance with the configured rounding and residual rule.',
                            )}
                        </p>
                    </section>
                )}
            </div>
        </>
    );
}
CalculatorPage.layout = {
    breadcrumbs: [
        { title: 'Sales desk', href: '/sales' },
        { title: 'Payment plan calculator', href: '#' },
    ],
};
