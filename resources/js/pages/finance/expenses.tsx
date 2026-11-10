import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { EmptyState, PageHeading, Pager } from '@/components/office-ui';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatPkr } from '@/lib/format-pkr';
import { useTranslate } from '@/lib/i18n';

type Expense = {
    id: number;
    number: string;
    category: string;
    amount: string;
    business_date: string;
    payee: string | null;
    reference: string | null;
    note: string;
    account_snapshot: { code: string; name: string; type: string };
    poster: { id: number; name: string };
};
type Props = {
    expenses: {
        data: Expense[];
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    total: string;
    filters: { from?: string; to?: string };
    accounts: { id: number; code: string; name: string; type: string }[];
    businessDate: string;
    commandKey: string;
    canPost: boolean;
};

export default function Expenses({
    expenses,
    total,
    filters,
    accounts,
    businessDate,
    commandKey,
    canPost,
}: Props) {
    const t = useTranslate();
    const [showForm, setShowForm] = useState(false);
    const form = useForm({
        financial_account_id: accounts[0]?.id?.toString() ?? '',
        category: '',
        amount: '',
        business_date: businessDate,
        payee: '',
        reference: '',
        note: '',
        command_key: commandKey,
        confirmed: false,
    });
    const conflictError = (form.errors as Record<string, string>).conflict;
    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post('/finance/expenses', {
            preserveScroll: true,
            onSuccess: () => setShowForm(false),
        });
    };
    return (
        <>
            <Head title={t('Office expenses')} />
            <div className="ef-page">
                <PageHeading
                    eyebrow={t('Finance · Office operations')}
                    title={t('Office expenses')}
                    description={t(
                        'Post verified office spending against a cash, bank or wallet account. Entries remain immutable audit records.',
                    )}
                >
                    {canPost && (
                        <Button
                            type="button"
                            onClick={() => setShowForm((value) => !value)}
                        >
                            <Plus className="size-4" />
                            {t('Post expense')}
                        </Button>
                    )}
                </PageHeading>
                <div className="rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm leading-6 text-blue-950 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-100">
                    {t(
                        'This operational expense register is a cashbook view. It is not a formal general ledger, profit and loss statement or balance sheet.',
                    )}
                </div>
                {showForm && (
                    <section className="ef-panel p-6">
                        <form
                            onSubmit={submit}
                            className="grid gap-5 lg:grid-cols-2"
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="expense-account">
                                    {t('Payment account')}
                                </Label>
                                <select
                                    id="expense-account"
                                    className="ef-input"
                                    value={form.data.financial_account_id}
                                    onChange={(event) =>
                                        form.setData(
                                            'financial_account_id',
                                            event.target.value,
                                        )
                                    }
                                    required
                                >
                                    <option value="">
                                        {t('Choose an account')}
                                    </option>
                                    {accounts.map((account) => (
                                        <option
                                            key={account.id}
                                            value={account.id}
                                        >
                                            {account.code} · {account.name}
                                        </option>
                                    ))}
                                </select>
                                {form.errors.financial_account_id && (
                                    <p className="text-sm text-destructive">
                                        {form.errors.financial_account_id}
                                    </p>
                                )}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="expense-date">
                                    {t('Business date')}
                                </Label>
                                <Input
                                    id="expense-date"
                                    type="date"
                                    value={form.data.business_date}
                                    readOnly
                                />
                                <p className="text-xs text-muted-foreground">
                                    {t(
                                        'Current Karachi business date. Backdating remains disabled until a close-period policy is configured.',
                                    )}
                                </p>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="expense-category">
                                    {t('Expense category')}
                                </Label>
                                <Input
                                    id="expense-category"
                                    value={form.data.category}
                                    onChange={(event) =>
                                        form.setData(
                                            'category',
                                            event.target.value,
                                        )
                                    }
                                    maxLength={100}
                                    required
                                />
                                {form.errors.category && (
                                    <p className="text-sm text-destructive">
                                        {form.errors.category}
                                    </p>
                                )}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="expense-amount">
                                    {t('Amount (PKR)')}
                                </Label>
                                <Input
                                    id="expense-amount"
                                    inputMode="decimal"
                                    value={form.data.amount}
                                    onChange={(event) =>
                                        form.setData(
                                            'amount',
                                            event.target.value,
                                        )
                                    }
                                    required
                                />
                                {form.errors.amount && (
                                    <p className="text-sm text-destructive">
                                        {form.errors.amount}
                                    </p>
                                )}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="expense-payee">
                                    {t('Paid to')}
                                </Label>
                                <Input
                                    id="expense-payee"
                                    value={form.data.payee}
                                    onChange={(event) =>
                                        form.setData(
                                            'payee',
                                            event.target.value,
                                        )
                                    }
                                    maxLength={160}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="expense-reference">
                                    {t('Reference')}
                                </Label>
                                <Input
                                    id="expense-reference"
                                    value={form.data.reference}
                                    onChange={(event) =>
                                        form.setData(
                                            'reference',
                                            event.target.value,
                                        )
                                    }
                                    maxLength={500}
                                />
                            </div>
                            <div className="grid gap-2 lg:col-span-2">
                                <Label htmlFor="expense-note">
                                    {t('Verification note')}
                                </Label>
                                <textarea
                                    id="expense-note"
                                    className="ef-input min-h-24"
                                    value={form.data.note}
                                    onChange={(event) =>
                                        form.setData('note', event.target.value)
                                    }
                                    minLength={10}
                                    required
                                />
                                {form.errors.note && (
                                    <p className="text-sm text-destructive">
                                        {form.errors.note}
                                    </p>
                                )}
                            </div>
                            <label className="flex items-start gap-3 text-sm lg:col-span-2">
                                <input
                                    className="mt-1"
                                    type="checkbox"
                                    checked={form.data.confirmed}
                                    onChange={(event) =>
                                        form.setData(
                                            'confirmed',
                                            event.target.checked,
                                        )
                                    }
                                    required
                                />
                                <span>
                                    {t(
                                        'I verified the amount, account, supporting evidence and business purpose. Post this immutable expense.',
                                    )}
                                </span>
                            </label>
                            {conflictError && (
                                <p className="text-sm text-destructive lg:col-span-2">
                                    {conflictError}
                                </p>
                            )}
                            <div className="flex gap-2 lg:col-span-2">
                                <Button
                                    disabled={
                                        form.processing || accounts.length === 0
                                    }
                                >
                                    {t('Post expense')}
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setShowForm(false)}
                                >
                                    {t('Cancel')}
                                </Button>
                            </div>
                        </form>
                    </section>
                )}
                <section className="ef-panel overflow-hidden">
                    <div className="ef-toolbar">
                        <form
                            action="/finance/expenses"
                            method="get"
                            className="flex flex-wrap gap-2"
                        >
                            <Input
                                aria-label={t('From date')}
                                name="from"
                                type="date"
                                defaultValue={filters.from ?? ''}
                            />
                            <Input
                                aria-label={t('To date')}
                                name="to"
                                type="date"
                                defaultValue={filters.to ?? ''}
                            />
                            <Button variant="outline">{t('Apply')}</Button>
                        </form>
                        <p className="font-semibold">
                            {t('Period total')}: {formatPkr(total)}
                        </p>
                    </div>
                    {expenses.data.length === 0 ? (
                        <EmptyState
                            title={t('No office expenses posted')}
                            description={t(
                                'Verified expense entries will appear here with their account and posting details.',
                            )}
                        />
                    ) : (
                        <>
                            <div className="overflow-x-auto">
                                <table className="ef-table">
                                    <thead>
                                        <tr>
                                            <th>{t('Expense')}</th>
                                            <th>{t('Date')}</th>
                                            <th>{t('Category')}</th>
                                            <th>{t('Payee and note')}</th>
                                            <th>{t('Account')}</th>
                                            <th>{t('Amount')}</th>
                                            <th>{t('Posted by')}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {expenses.data.map((expense) => (
                                            <tr key={expense.id}>
                                                <td className="font-semibold">
                                                    <a
                                                        className="ef-text-link"
                                                        href={`/finance/expenses/${expense.id}/print`}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                    >
                                                        {expense.number}
                                                    </a>
                                                </td>
                                                <td>{expense.business_date}</td>
                                                <td>{expense.category}</td>
                                                <td>
                                                    <p>
                                                        {expense.payee || '—'}
                                                    </p>
                                                    <p className="mt-1 max-w-sm text-xs text-muted-foreground">
                                                        {expense.note}
                                                    </p>
                                                    {expense.reference && (
                                                        <p className="mt-1 text-xs">
                                                            {t('Reference')}:{' '}
                                                            {expense.reference}
                                                        </p>
                                                    )}
                                                </td>
                                                <td>
                                                    {
                                                        expense.account_snapshot
                                                            .code
                                                    }
                                                    <p className="text-xs text-muted-foreground">
                                                        {
                                                            expense
                                                                .account_snapshot
                                                                .name
                                                        }
                                                    </p>
                                                </td>
                                                <td className="font-semibold whitespace-nowrap">
                                                    {formatPkr(expense.amount)}
                                                </td>
                                                <td>{expense.poster.name}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            <Pager
                                prev_page_url={expenses.prev_page_url}
                                next_page_url={expenses.next_page_url}
                            />
                        </>
                    )}
                </section>
            </div>
        </>
    );
}
