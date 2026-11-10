import { Head, Link, useForm } from '@inertiajs/react';
import { Landmark, Plus, Save } from 'lucide-react';
import InputError from '@/components/input-error';
import { EmptyState, PageHeading, Pager } from '@/components/office-ui';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useUnsavedChanges } from '@/hooks/use-unsaved-changes';
import { useTranslate } from '@/lib/i18n';

export type Account = {
    id: number;
    code: string;
    name: string;
    type: string;
    active: boolean;
    version: number;
};
const types: Record<string, string> = {
    cash: 'Cash',
    bank: 'Bank',
    wallet: 'Wallet',
    other: 'Other',
};
type Props = {
    accounts: {
        data: Account[];
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    account: Account | null;
    creationKey: string;
    canManage: boolean;
    history: {
        id: number;
        version: number;
        actor_id: number;
        details: { reason: string; after: { name: string; active: boolean } };
        created_at: string;
    }[];
};

function Editor({
    account,
    creationKey,
}: Pick<Props, 'account' | 'creationKey'>) {
    const t = useTranslate();
    const form = useForm({
        code: account?.code ?? '',
        name: account?.name ?? '',
        type: account?.type ?? '',
        active: account?.active ?? true,
        reason: '',
        ...(account
            ? { version: account.version }
            : { creation_key: creationKey }),
    });
    useUnsavedChanges(form.isDirty);
    return (
        <form
            className="ef-panel overflow-hidden"
            onSubmit={(event) => {
                event.preventDefault();
                const options = { onSuccess: () => form.setDefaults() };
                if (account)
                    form.patch(`/finance/accounts/${account.id}`, options);
                else form.post('/finance/accounts', options);
            }}
        >
            <div className="border-b p-5">
                <h2 className="font-semibold">
                    {account
                        ? `${t('Edit')} ${account.code}`
                        : t('Add financial account')}
                </h2>
                <p className="mt-1 text-xs leading-5 text-muted-foreground">
                    {t(
                        'Configure a payment destination. Opening balances and ledger posting are separate.',
                    )}
                </p>
            </div>
            <fieldset className="space-y-5 p-5" disabled={form.processing}>
                <div>
                    <Label htmlFor="account-code">{t('Account code *')}</Label>
                    <Input
                        id="account-code"
                        className="mt-2"
                        required
                        maxLength={30}
                        readOnly={!!account}
                        value={form.data.code}
                        onChange={(event) =>
                            form.setData('code', event.target.value)
                        }
                        placeholder="e.g. OFFICE-CASH"
                    />
                </div>
                <div>
                    <Label htmlFor="account-name">{t('Account name *')}</Label>
                    <Input
                        id="account-name"
                        className="mt-2"
                        required
                        maxLength={100}
                        value={form.data.name}
                        onChange={(event) =>
                            form.setData('name', event.target.value)
                        }
                        placeholder="e.g. Main office cash desk"
                    />
                </div>
                <div>
                    <Label htmlFor="account-type">{t('Account type *')}</Label>
                    <select
                        id="account-type"
                        className="mt-2 h-11 w-full rounded-lg border bg-background px-3 text-sm"
                        required
                        disabled={!!account}
                        value={form.data.type}
                        onChange={(event) =>
                            form.setData('type', event.target.value)
                        }
                    >
                        <option value="">{t('Choose account type')}</option>
                        {Object.entries(types).map(([value, label]) => (
                            <option key={value} value={value}>
                                {t(label)}
                            </option>
                        ))}
                    </select>
                </div>
                <label className="flex items-center gap-3 rounded-lg border p-3 text-sm">
                    <input
                        type="checkbox"
                        className="size-4 accent-blue-600"
                        checked={form.data.active}
                        onChange={(event) =>
                            form.setData('active', event.target.checked)
                        }
                    />
                    {t('Available for new receipt preparations')}
                </label>
                {account && (
                    <div>
                        <Label htmlFor="account-reason">
                            {t('Reason for change *')}
                        </Label>
                        <Input
                            id="account-reason"
                            className="mt-2"
                            required
                            maxLength={500}
                            value={form.data.reason}
                            onChange={(event) =>
                                form.setData('reason', event.target.value)
                            }
                        />
                    </div>
                )}
                <div role="alert">
                    {Object.entries(form.errors).map(([key, message]) => (
                        <InputError key={key} message={message} />
                    ))}
                </div>
                <p className="text-xs leading-5 text-muted-foreground">
                    {t(
                        'Code and type are fixed after creation. Use a recognizable label; do not enter passwords, card details or personal bank information.',
                    )}
                </p>
                <Button className="w-full" disabled={form.processing}>
                    <Save className="size-4" />
                    {form.processing ? 'Saving…' : 'Save account setup'}
                </Button>
            </fieldset>
        </form>
    );
}

export default function Accounts(props: Props) {
    const t = useTranslate();
    return (
        <>
            <Head title={t('Financial accounts')} />
            <div className="ef-page">
                <PageHeading
                    eyebrow="Finance · Account setup"
                    title={t('Financial accounts')}
                    description={t(
                        'Configure where reported customer payments will be received. Balances are not calculated from prepared receipts.',
                    )}
                >
                    <Button asChild variant="outline">
                        <Link href="/finance/receipts">
                            {t('Receipt preparations')}
                        </Link>
                    </Button>
                    {props.canManage && props.account && (
                        <Button asChild>
                            <Link href="/finance/accounts">
                                <Plus className="size-4" />
                                {t('Add account')}
                            </Link>
                        </Button>
                    )}
                </PageHeading>
                <div className="flex items-start gap-3 rounded-xl border border-blue-100 bg-blue-50 p-4 text-sm leading-6 text-blue-900">
                    <Landmark className="mt-1 size-5 shrink-0" />
                    <p>
                        {t(
                            'Account setup is ready for preparation. No opening balance, cash movement or recognized income is created here.',
                        )}
                    </p>
                </div>
                <div
                    className={`grid items-start gap-6 ${props.canManage ? 'xl:grid-cols-[1fr_360px]' : ''}`}
                >
                    <section
                        className="ef-panel min-w-0 overflow-hidden"
                        aria-label={t('Account directory')}
                    >
                        <div className="border-b p-5">
                            <h2 className="font-semibold">
                                {t('Payment destinations')}
                            </h2>
                            <p className="mt-1 text-xs text-muted-foreground">
                                {props.accounts.total}{' '}
                                {t('configured accounts')}
                            </p>
                        </div>
                        {props.accounts.data.length ? (
                            <>
                                <ul
                                    aria-label="Account summaries"
                                    className="divide-y md:hidden"
                                >
                                    {props.accounts.data.map((account) => (
                                        <li
                                            key={account.id}
                                            className="space-y-3 p-5"
                                        >
                                            <div className="flex items-start justify-between gap-3">
                                                <div className="min-w-0">
                                                    <p className="font-semibold break-words">
                                                        {account.code}
                                                    </p>
                                                    <p className="mt-1 text-sm leading-6 text-muted-foreground">
                                                        {account.name}
                                                    </p>
                                                </div>
                                                <span
                                                    className={`shrink-0 rounded-full px-2 py-1 text-xs ${account.active ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-100 text-slate-600'}`}
                                                >
                                                    {account.active
                                                        ? t('Active')
                                                        : t('Inactive')}
                                                </span>
                                            </div>
                                            <div className="flex items-center justify-between gap-3 text-sm">
                                                <span className="text-muted-foreground">
                                                    {t(types[account.type])}
                                                </span>
                                                <Link
                                                    className="ef-text-link"
                                                    href={`/finance/accounts/${account.id}`}
                                                >
                                                    {props.canManage
                                                        ? t('Manage account')
                                                        : t('View history')}
                                                </Link>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                                <div className="hidden overflow-x-auto md:block">
                                    <table className="ef-table">
                                        <caption className="sr-only">
                                            {t('Configured financial accounts')}
                                        </caption>
                                        <thead>
                                            <tr>
                                                <th>{t('Account')}</th>
                                                <th>{t('Type')}</th>
                                                <th>{t('Availability')}</th>
                                                <th>{t('Action')}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {props.accounts.data.map(
                                                (account) => (
                                                    <tr key={account.id}>
                                                        <td>
                                                            <p className="font-semibold">
                                                                {account.code}
                                                            </p>
                                                            <p className="mt-1 text-xs text-muted-foreground">
                                                                {account.name}
                                                            </p>
                                                        </td>
                                                        <td>
                                                            {
                                                                types[
                                                                    account.type
                                                                ]
                                                            }
                                                        </td>
                                                        <td>
                                                            <span
                                                                className={`rounded-full px-2 py-1 text-xs ${account.active ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-100 text-slate-600'}`}
                                                            >
                                                                {account.active
                                                                    ? t(
                                                                          'Active',
                                                                      )
                                                                    : t(
                                                                          'Inactive',
                                                                      )}
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <Link
                                                                className="ef-text-link"
                                                                href={`/finance/accounts/${account.id}`}
                                                            >
                                                                {props.canManage
                                                                    ? t(
                                                                          'Manage',
                                                                      )
                                                                    : t(
                                                                          'History',
                                                                      )}
                                                            </Link>
                                                        </td>
                                                    </tr>
                                                ),
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </>
                        ) : (
                            <EmptyState
                                title={t('No financial accounts yet')}
                                description={t(
                                    'An authorized admin can add payment destinations using the account setup form.',
                                )}
                            />
                        )}
                        <Pager {...props.accounts} />
                    </section>
                    {props.canManage && (
                        <Editor
                            key={`account-${props.account?.id ?? 'new'}-${props.account?.version ?? props.creationKey}`}
                            account={props.account}
                            creationKey={props.creationKey}
                        />
                    )}
                </div>
                {props.account && (
                    <section
                        className="ef-panel p-5"
                        aria-label={t('Account history')}
                    >
                        <h2 className="font-semibold">
                            Recent setup changes · {props.account.code}
                        </h2>
                        <ul className="mt-4 space-y-4">
                            {props.history.map((event) => (
                                <li
                                    key={event.id}
                                    className="rounded-lg border p-4 text-sm"
                                >
                                    <p className="font-medium">
                                        v{event.version} ·{' '}
                                        {event.details.after.name} ·{' '}
                                        {event.details.after.active
                                            ? t('Active')
                                            : t('Inactive')}
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {t('Staff')} #{event.actor_id} ·{' '}
                                        {event.created_at
                                            .replace('T', ' ')
                                            .slice(0, 19)}{' '}
                                        UTC
                                    </p>
                                    {event.details.reason && (
                                        <p className="mt-2 break-words">
                                            {event.details.reason}
                                        </p>
                                    )}
                                </li>
                            ))}
                        </ul>
                        <p className="mt-4 text-xs text-muted-foreground">
                            {t(
                                'Latest 10 setup revisions. Account records are retained when made inactive.',
                            )}
                        </p>
                    </section>
                )}
            </div>
        </>
    );
}
Accounts.layout = {
    breadcrumbs: [{ title: 'Financial accounts', href: '/finance/accounts' }],
};
