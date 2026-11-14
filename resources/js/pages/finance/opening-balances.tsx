import { Head, useForm } from '@inertiajs/react';
import { WalletCards, CheckCircle2 } from 'lucide-react';
import { PageHeading, StatusBadge } from '@/components/office-ui';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatPkr } from '@/lib/format-pkr';
import { useTranslate } from '@/lib/i18n';

type CustomerBalance = {
    id: number;
    customer_id: number;
    booking_id: number | null;
    amount: string;
    effective_on: string;
    source_note: string;
    source_reference: string | null;
    customer: { id: number; name: string } | null;
};
type OwnerBalance = {
    id: number;
    plot_owner_id: number;
    amount: string;
    effective_on: string;
    source_note: string;
    source_reference: string | null;
    owner: { id: number; name: string } | null;
};
type CommissionBalance = {
    id: number;
    agent_id: number;
    amount: string;
    effective_on: string;
    source_note: string;
    source_reference: string | null;
    agent: { id: number; name: string } | null;
};
type Option = { id: number; name: string };

export default function OpeningBalances({
    cutoverDate,
    customers,
    owners,
    commissions,
    customerOptions,
    ownerOptions,
    agentOptions,
    commandKey,
}: {
    cutoverDate: string | null;
    customers: CustomerBalance[];
    owners: OwnerBalance[];
    commissions: CommissionBalance[];
    customerOptions: Option[];
    ownerOptions: Option[];
    agentOptions: Option[];
    commandKey: string;
}) {
    const t = useTranslate();
    const customerForm = useForm({
        customer_id: '',
        booking_id: '',
        amount: '',
        effective_on: cutoverDate ?? '',
        source_note: '',
        source_reference: '',
        command_key: commandKey,
        confirmed: false,
    });
    const ownerForm = useForm({
        plot_owner_id: '',
        amount: '',
        effective_on: cutoverDate ?? '',
        source_note: '',
        source_reference: '',
        command_key: commandKey,
        confirmed: false,
    });
    const commissionForm = useForm({
        agent_id: '',
        amount: '',
        effective_on: cutoverDate ?? '',
        source_note: '',
        source_reference: '',
        command_key: commandKey,
        confirmed: false,
    });

    return (
        <>
            <Head title={t('Opening balances')} />
            <div className="ef-page">
                <PageHeading
                    eyebrow={t('Administration')}
                    title={t('Opening balances')}
                    description={t(
                        'Opening balances are recorded as of the cutover date and are kept separate from live ledgers until explicitly activated.',
                    )}
                />
                {!cutoverDate && (
                    <div className="flex gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-950">
                        <WalletCards className="mt-1 size-5 shrink-0" />
                        <p>
                            {t(
                                'Set the legacy cutover date in business settings before recording opening balances.',
                            )}
                        </p>
                    </div>
                )}

                <section className="ef-panel p-6">
                    <h2 className="font-semibold">
                        {t('Opening customer balance')}
                    </h2>
                    <form
                        className="mt-4 grid gap-4 sm:grid-cols-2"
                        onSubmit={(event) => {
                            event.preventDefault();
                            customerForm.post(
                                '/finance/opening-balances/customer',
                                { preserveScroll: true },
                            );
                        }}
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="ob-customer">
                                {t('Customer')}
                            </Label>
                            <select
                                id="ob-customer"
                                className="ef-input mt-1 w-full"
                                value={customerForm.data.customer_id}
                                onChange={(event) =>
                                    customerForm.setData(
                                        'customer_id',
                                        event.target.value,
                                    )
                                }
                                required
                            >
                                <option value="">—</option>
                                {customerOptions.map((option) => (
                                    <option
                                        key={option.id}
                                        value={option.id}
                                    >
                                        {option.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="ob-customer-amount">
                                {t('Amount (PKR)')}
                            </Label>
                            <Input
                                id="ob-customer-amount"
                                type="text"
                                inputMode="decimal"
                                className="mt-1"
                                value={customerForm.data.amount}
                                onChange={(event) =>
                                    customerForm.setData(
                                        'amount',
                                        event.target.value,
                                    )
                                }
                                required
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="ob-customer-date">
                                {t('Effective on')}
                            </Label>
                            <Input
                                id="ob-customer-date"
                                type="date"
                                className="mt-1"
                                value={customerForm.data.effective_on}
                                onChange={(event) =>
                                    customerForm.setData(
                                        'effective_on',
                                        event.target.value,
                                    )
                                }
                                required
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="ob-customer-ref">
                                {t('Source reference')}
                            </Label>
                            <Input
                                id="ob-customer-ref"
                                type="text"
                                className="mt-1"
                                value={customerForm.data.source_reference}
                                onChange={(event) =>
                                    customerForm.setData(
                                        'source_reference',
                                        event.target.value,
                                    )
                                }
                            />
                        </div>
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="ob-customer-note">
                                {t('Source note')}
                            </Label>
                            <Input
                                id="ob-customer-note"
                                type="text"
                                className="mt-1"
                                value={customerForm.data.source_note}
                                onChange={(event) =>
                                    customerForm.setData(
                                        'source_note',
                                        event.target.value,
                                    )
                                }
                                required
                            />
                        </div>
                        <label className="flex items-start gap-2 text-sm sm:col-span-2">
                            <input
                                type="checkbox"
                                className="mt-1"
                                checked={customerForm.data.confirmed}
                                onChange={(event) =>
                                    customerForm.setData(
                                        'confirmed',
                                        event.target.checked,
                                    )
                                }
                            />
                            <span>
                                {t(
                                    'I verified this amount against the legacy ledger or statement.',
                                )}
                            </span>
                        </label>
                        <div className="sm:col-span-2">
                            <Button
                                type="submit"
                                disabled={
                                    customerForm.processing ||
                                    !customerForm.data.confirmed
                                }
                            >
                                {t('Record immutable opening balance')}
                            </Button>
                        </div>
                    </form>
                    {customers.length > 0 && (
                        <div className="mt-6 overflow-x-auto">
                            <table className="ef-table">
                                <thead>
                                    <tr>
                                        <th>{t('Customer')}</th>
                                        <th>{t('Amount (PKR)')}</th>
                                        <th>{t('Effective on')}</th>
                                        <th>{t('Source note')}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {customers.map((row) => (
                                        <tr key={row.id}>
                                            <td>
                                                {row.customer?.name ??
                                                    `#${row.customer_id}`}
                                            </td>
                                            <td>{formatPkr(row.amount)}</td>
                                            <td>{row.effective_on}</td>
                                            <td>{row.source_note}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>

                <section className="ef-panel p-6">
                    <h2 className="font-semibold">
                        {t('Opening owner balance')}
                    </h2>
                    <form
                        className="mt-4 grid gap-4 sm:grid-cols-2"
                        onSubmit={(event) => {
                            event.preventDefault();
                            ownerForm.post(
                                '/finance/opening-balances/owner',
                                { preserveScroll: true },
                            );
                        }}
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="ob-owner">{t('Owner')}</Label>
                            <select
                                id="ob-owner"
                                className="ef-input mt-1 w-full"
                                value={ownerForm.data.plot_owner_id}
                                onChange={(event) =>
                                    ownerForm.setData(
                                        'plot_owner_id',
                                        event.target.value,
                                    )
                                }
                                required
                            >
                                <option value="">—</option>
                                {ownerOptions.map((option) => (
                                    <option
                                        key={option.id}
                                        value={option.id}
                                    >
                                        {option.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="ob-owner-amount">
                                {t('Amount (PKR)')}
                            </Label>
                            <Input
                                id="ob-owner-amount"
                                type="text"
                                inputMode="decimal"
                                className="mt-1"
                                value={ownerForm.data.amount}
                                onChange={(event) =>
                                    ownerForm.setData(
                                        'amount',
                                        event.target.value,
                                    )
                                }
                                required
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="ob-owner-date">
                                {t('Effective on')}
                            </Label>
                            <Input
                                id="ob-owner-date"
                                type="date"
                                className="mt-1"
                                value={ownerForm.data.effective_on}
                                onChange={(event) =>
                                    ownerForm.setData(
                                        'effective_on',
                                        event.target.value,
                                    )
                                }
                                required
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="ob-owner-ref">
                                {t('Source reference')}
                            </Label>
                            <Input
                                id="ob-owner-ref"
                                type="text"
                                className="mt-1"
                                value={ownerForm.data.source_reference}
                                onChange={(event) =>
                                    ownerForm.setData(
                                        'source_reference',
                                        event.target.value,
                                    )
                                }
                            />
                        </div>
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="ob-owner-note">
                                {t('Source note')}
                            </Label>
                            <Input
                                id="ob-owner-note"
                                type="text"
                                className="mt-1"
                                value={ownerForm.data.source_note}
                                onChange={(event) =>
                                    ownerForm.setData(
                                        'source_note',
                                        event.target.value,
                                    )
                                }
                                required
                            />
                        </div>
                        <label className="flex items-start gap-2 text-sm sm:col-span-2">
                            <input
                                type="checkbox"
                                className="mt-1"
                                checked={ownerForm.data.confirmed}
                                onChange={(event) =>
                                    ownerForm.setData(
                                        'confirmed',
                                        event.target.checked,
                                    )
                                }
                            />
                            <span>
                                {t(
                                    'I verified this amount against the legacy ledger or statement.',
                                )}
                            </span>
                        </label>
                        <div className="sm:col-span-2">
                            <Button
                                type="submit"
                                disabled={
                                    ownerForm.processing ||
                                    !ownerForm.data.confirmed
                                }
                            >
                                {t('Record immutable opening balance')}
                            </Button>
                        </div>
                    </form>
                    {owners.length > 0 && (
                        <div className="mt-6 overflow-x-auto">
                            <table className="ef-table">
                                <thead>
                                    <tr>
                                        <th>{t('Owner')}</th>
                                        <th>{t('Amount (PKR)')}</th>
                                        <th>{t('Effective on')}</th>
                                        <th>{t('Source note')}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {owners.map((row) => (
                                        <tr key={row.id}>
                                            <td>
                                                {row.owner?.name ??
                                                    `#${row.plot_owner_id}`}
                                            </td>
                                            <td>{formatPkr(row.amount)}</td>
                                            <td>{row.effective_on}</td>
                                            <td>{row.source_note}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>

                <section className="ef-panel p-6">
                    <h2 className="font-semibold">
                        {t('Opening commission balance')}
                    </h2>
                    <form
                        className="mt-4 grid gap-4 sm:grid-cols-2"
                        onSubmit={(event) => {
                            event.preventDefault();
                            commissionForm.post(
                                '/finance/opening-balances/commission',
                                { preserveScroll: true },
                            );
                        }}
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="ob-agent">{t('Agent')}</Label>
                            <select
                                id="ob-agent"
                                className="ef-input mt-1 w-full"
                                value={commissionForm.data.agent_id}
                                onChange={(event) =>
                                    commissionForm.setData(
                                        'agent_id',
                                        event.target.value,
                                    )
                                }
                                required
                            >
                                <option value="">—</option>
                                {agentOptions.map((option) => (
                                    <option
                                        key={option.id}
                                        value={option.id}
                                    >
                                        {option.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="ob-agent-amount">
                                {t('Amount (PKR)')}
                            </Label>
                            <Input
                                id="ob-agent-amount"
                                type="text"
                                inputMode="decimal"
                                className="mt-1"
                                value={commissionForm.data.amount}
                                onChange={(event) =>
                                    commissionForm.setData(
                                        'amount',
                                        event.target.value,
                                    )
                                }
                                required
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="ob-agent-date">
                                {t('Effective on')}
                            </Label>
                            <Input
                                id="ob-agent-date"
                                type="date"
                                className="mt-1"
                                value={commissionForm.data.effective_on}
                                onChange={(event) =>
                                    commissionForm.setData(
                                        'effective_on',
                                        event.target.value,
                                    )
                                }
                                required
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="ob-agent-ref">
                                {t('Source reference')}
                            </Label>
                            <Input
                                id="ob-agent-ref"
                                type="text"
                                className="mt-1"
                                value={commissionForm.data.source_reference}
                                onChange={(event) =>
                                    commissionForm.setData(
                                        'source_reference',
                                        event.target.value,
                                    )
                                }
                            />
                        </div>
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="ob-agent-note">
                                {t('Source note')}
                            </Label>
                            <Input
                                id="ob-agent-note"
                                type="text"
                                className="mt-1"
                                value={commissionForm.data.source_note}
                                onChange={(event) =>
                                    commissionForm.setData(
                                        'source_note',
                                        event.target.value,
                                    )
                                }
                                required
                            />
                        </div>
                        <label className="flex items-start gap-2 text-sm sm:col-span-2">
                            <input
                                type="checkbox"
                                className="mt-1"
                                checked={commissionForm.data.confirmed}
                                onChange={(event) =>
                                    commissionForm.setData(
                                        'confirmed',
                                        event.target.checked,
                                    )
                                }
                            />
                            <span>
                                {t(
                                    'I verified this amount against the legacy ledger or statement.',
                                )}
                            </span>
                        </label>
                        <div className="sm:col-span-2">
                            <Button
                                type="submit"
                                disabled={
                                    commissionForm.processing ||
                                    !commissionForm.data.confirmed
                                }
                            >
                                {t('Record immutable opening balance')}
                            </Button>
                        </div>
                    </form>
                    {commissions.length > 0 && (
                        <div className="mt-6 overflow-x-auto">
                            <table className="ef-table">
                                <thead>
                                    <tr>
                                        <th>{t('Agent')}</th>
                                        <th>{t('Amount (PKR)')}</th>
                                        <th>{t('Effective on')}</th>
                                        <th>{t('Source note')}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {commissions.map((row) => (
                                        <tr key={row.id}>
                                            <td>
                                                {row.agent?.name ??
                                                    `#${row.agent_id}`}
                                            </td>
                                            <td>{formatPkr(row.amount)}</td>
                                            <td>{row.effective_on}</td>
                                            <td>{row.source_note}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}
