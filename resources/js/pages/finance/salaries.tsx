import { Head, router, useForm } from '@inertiajs/react';
import { PageHeading } from '@/components/office-ui';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatPkr } from '@/lib/format-pkr';
import { useTranslate } from '@/lib/i18n';

type Payment = {
    id: number;
    employee_name: string;
    salary_month: string;
    amount: string;
    paid_on: string;
    method: string;
    reference: string | null;
    note: string | null;
    recorder: { name: string };
};
type Props = {
    payments: Payment[];
    month: string;
    total: string;
    businessDate: string;
    canManage: boolean;
};

export default function Salaries({
    payments,
    month,
    total,
    businessDate,
    canManage,
}: Props) {
    const t = useTranslate();
    const form = useForm({
        employee_name: '',
        salary_month: month,
        amount: '',
        paid_on: businessDate,
        method: 'cash',
        reference: '',
        note: '',
    });
    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post('/finance/salaries', {
            preserveScroll: true,
            onSuccess: () =>
                form.reset('employee_name', 'amount', 'reference', 'note'),
        });
    };
    return (
        <>
            <Head title={t('Salaries')} />
            <div className="ef-page">
                <PageHeading
                    eyebrow={t('Office money')}
                    title={t('Salaries')}
                    description={t(
                        'Record staff salary payments in one simple register.',
                    )}
                />
                <section className="ef-panel p-5">
                    <div className="flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <p className="text-sm text-muted-foreground">
                                {t('Total paid this month')}
                            </p>
                            <p className="text-2xl font-semibold">
                                {formatPkr(total)}
                            </p>
                        </div>
                        <Input
                            aria-label={t('Salary month filter')}
                            type="month"
                            value={month}
                            onChange={(e) =>
                                router.get(
                                    '/finance/salaries',
                                    { month: e.target.value },
                                    { preserveState: true },
                                )
                            }
                            className="w-44"
                        />
                    </div>
                </section>
                {canManage && (
                    <section className="ef-panel p-5">
                        <h2 className="mb-4 font-semibold">
                            {t('Record salary payment')}
                        </h2>
                        <form
                            onSubmit={submit}
                            className="grid gap-4 md:grid-cols-2 lg:grid-cols-3"
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="salary-name">
                                    {t('Employee name')}
                                </Label>
                                <Input
                                    id="salary-name"
                                    value={form.data.employee_name}
                                    onChange={(e) =>
                                        form.setData(
                                            'employee_name',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="salary-month">
                                    {t('Salary month')}
                                </Label>
                                <Input
                                    id="salary-month"
                                    type="month"
                                    value={form.data.salary_month}
                                    onChange={(e) =>
                                        form.setData(
                                            'salary_month',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="salary-amount">
                                    {t('Amount (PKR)')}
                                </Label>
                                <Input
                                    id="salary-amount"
                                    type="number"
                                    min="0.01"
                                    step="0.01"
                                    value={form.data.amount}
                                    onChange={(e) =>
                                        form.setData('amount', e.target.value)
                                    }
                                    required
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="salary-date">
                                    {t('Paid on')}
                                </Label>
                                <Input
                                    id="salary-date"
                                    type="date"
                                    value={form.data.paid_on}
                                    onChange={(e) =>
                                        form.setData('paid_on', e.target.value)
                                    }
                                    required
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="salary-method">
                                    {t('Payment method')}
                                </Label>
                                <select
                                    id="salary-method"
                                    className="ef-input"
                                    value={form.data.method}
                                    onChange={(e) =>
                                        form.setData('method', e.target.value)
                                    }
                                >
                                    <option value="cash">{t('Cash')}</option>
                                    <option value="bank_transfer">
                                        {t('Bank transfer')}
                                    </option>
                                    <option value="easypaisa">Easypaisa</option>
                                    <option value="jazzcash">JazzCash</option>
                                    <option value="other">{t('Other')}</option>
                                </select>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="salary-ref">
                                    {t('Reference (optional)')}
                                </Label>
                                <Input
                                    id="salary-ref"
                                    value={form.data.reference}
                                    onChange={(e) =>
                                        form.setData(
                                            'reference',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div className="grid gap-2 md:col-span-2">
                                <Label htmlFor="salary-note">
                                    {t('Note (optional)')}
                                </Label>
                                <Input
                                    id="salary-note"
                                    value={form.data.note}
                                    onChange={(e) =>
                                        form.setData('note', e.target.value)
                                    }
                                />
                            </div>
                            <div className="flex items-end">
                                <Button disabled={form.processing}>
                                    {t('Save salary payment')}
                                </Button>
                            </div>
                        </form>
                    </section>
                )}
                <section className="ef-panel overflow-hidden">
                    <h2 className="ef-panel-heading font-semibold">
                        {t('Salary payments')}
                    </h2>
                    {payments.length === 0 ? (
                        <p className="p-6 text-sm text-muted-foreground">
                            {t('No salary payments for this month.')}
                        </p>
                    ) : (
                        <div className="divide-y">
                            {payments.map((payment) => (
                                <article
                                    key={payment.id}
                                    className="flex flex-wrap items-center justify-between gap-3 p-5"
                                >
                                    <div>
                                        <p className="font-medium">
                                            {payment.employee_name}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {payment.paid_on} ·{' '}
                                            {t(
                                                payment.method.replace(
                                                    '_',
                                                    ' ',
                                                ),
                                            )}{' '}
                                            · {payment.recorder.name}
                                        </p>
                                    </div>
                                    <p className="font-semibold">
                                        {formatPkr(payment.amount)}
                                    </p>
                                </article>
                            ))}
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}
