import { Head, useForm } from '@inertiajs/react';
import {
    EmptyState,
    PageHeading,
    Pager,
    StatusBadge,
} from '@/components/office-ui';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatPkr } from '@/lib/format-pkr';
import { useTranslate } from '@/lib/i18n';

type Owner = { id: number; name: string };
type Account = { id: number; code: string; name: string; type: string };
type Payout = {
    id: number;
    number: string;
    amount: string;
    business_date: string;
    state: 'prepared' | 'approved' | 'rejected';
    reference: string | null;
    verification_note: string;
    decision_note: string | null;
    owner: Owner;
    account_snapshot: Account;
    preparer: { name: string };
    decider: { name: string } | null;
};
type Props = {
    payouts: {
        data: Payout[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    owners: Owner[];
    balances: Record<string, string>;
    accounts: Account[];
    businessDate: string;
    commandKey: string;
    decisionKey: string;
    canPrepare: boolean;
    canApprove: boolean;
};

export default function OwnerPayouts({
    payouts,
    owners,
    balances,
    accounts,
    businessDate,
    commandKey,
    decisionKey,
    canPrepare,
    canApprove,
}: Props) {
    const t = useTranslate();
    const form = useForm({
        plot_owner_id: '',
        financial_account_id: '',
        amount: '',
        business_date: businessDate,
        reference: '',
        verification_note: '',
        command_key: commandKey,
        confirmed: false,
    });
    const decision = useForm({
        decision: 'approve',
        decision_note: '',
        decision_key: decisionKey,
        confirmed: false,
    });
    return (
        <>
            <Head title={t('Owner payouts')} />
            <div className="ef-page">
                <PageHeading
                    eyebrow={t('Finance · Owner settlements')}
                    title={t('Owner payouts')}
                    description={t(
                        'Accountants prepare partial payouts from earned owner entitlement. An admin must approve before money is posted.',
                    )}
                />
                <div className="grid gap-4 sm:grid-cols-2">
                    {owners.map((o) => {
                        const balance = balances[String(o.id)] ?? '0.00';
                        const recoverable = balance.startsWith('-');

                        return (
                            <div key={o.id} className="ef-panel p-5">
                                <p className="text-sm text-muted-foreground">
                                    {o.name}
                                </p>
                                <p className="mt-2 text-2xl font-bold">
                                    {formatPkr(balance)}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {t(
                                        recoverable
                                            ? 'Recoverable from owner'
                                            : 'Available entitlement',
                                    )}
                                </p>
                                {recoverable && (
                                    <p className="mt-2 text-xs text-muted-foreground">
                                        {t(
                                            'Future entitlements will offset this balance before payouts become available.',
                                        )}
                                    </p>
                                )}
                            </div>
                        );
                    })}
                </div>
                {canPrepare && (
                    <section className="ef-panel p-6">
                        <h2 className="text-lg font-semibold">
                            {t('Prepare owner payout')}
                        </h2>
                        <form
                            className="mt-5 grid gap-4 md:grid-cols-2"
                            onSubmit={(e) => {
                                e.preventDefault();
                                form.post('/finance/owner-payouts');
                            }}
                        >
                            <div>
                                <Label htmlFor="payout-owner">
                                    {t('Plot owner')}
                                </Label>
                                <select
                                    id="payout-owner"
                                    className="ef-input mt-2"
                                    value={form.data.plot_owner_id}
                                    onChange={(e) =>
                                        form.setData(
                                            'plot_owner_id',
                                            e.target.value,
                                        )
                                    }
                                    required
                                >
                                    <option value="">
                                        {t('Choose an owner')}
                                    </option>
                                    {owners.map((o) => (
                                        <option key={o.id} value={o.id}>
                                            {o.name} ·{' '}
                                            {formatPkr(
                                                balances[String(o.id)] ??
                                                    '0.00',
                                            )}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <Label htmlFor="payout-account">
                                    {t('Payment account')}
                                </Label>
                                <select
                                    id="payout-account"
                                    className="ef-input mt-2"
                                    value={form.data.financial_account_id}
                                    onChange={(e) =>
                                        form.setData(
                                            'financial_account_id',
                                            e.target.value,
                                        )
                                    }
                                    required
                                >
                                    <option value="">
                                        {t('Choose an account')}
                                    </option>
                                    {accounts.map((a) => (
                                        <option key={a.id} value={a.id}>
                                            {a.code} · {a.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <Label htmlFor="payout-amount">
                                    {t('Amount (PKR)')}
                                </Label>
                                <Input
                                    id="payout-amount"
                                    className="mt-2"
                                    inputMode="decimal"
                                    value={form.data.amount}
                                    onChange={(e) =>
                                        form.setData('amount', e.target.value)
                                    }
                                    required
                                />
                            </div>
                            <div>
                                <Label htmlFor="payout-reference">
                                    {t('Reference')}
                                </Label>
                                <Input
                                    id="payout-reference"
                                    className="mt-2"
                                    value={form.data.reference}
                                    onChange={(e) =>
                                        form.setData(
                                            'reference',
                                            e.target.value,
                                        )
                                    }
                                />
                                <p className="mt-1 text-xs text-muted-foreground">
                                    {t('Required for bank and wallet payouts.')}
                                </p>
                            </div>
                            <div className="md:col-span-2">
                                <Label htmlFor="payout-note">
                                    {t('Verification note')}
                                </Label>
                                <textarea
                                    id="payout-note"
                                    className="ef-input mt-2 min-h-24"
                                    minLength={10}
                                    value={form.data.verification_note}
                                    onChange={(e) =>
                                        form.setData(
                                            'verification_note',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                            </div>
                            <label className="flex gap-3 text-sm md:col-span-2">
                                <input
                                    type="checkbox"
                                    checked={form.data.confirmed}
                                    onChange={(e) =>
                                        form.setData(
                                            'confirmed',
                                            e.target.checked,
                                        )
                                    }
                                    required
                                />
                                {t(
                                    'I verified the owner, available entitlement, amount, account and evidence.',
                                )}
                            </label>
                            <div>
                                <Button disabled={form.processing}>
                                    {t('Submit for admin approval')}
                                </Button>
                            </div>
                        </form>
                    </section>
                )}
                <section className="ef-panel overflow-hidden">
                    {payouts.data.length === 0 ? (
                        <EmptyState
                            title={t('No owner payouts')}
                            description={t(
                                'Prepared and decided owner payouts will appear here.',
                            )}
                        />
                    ) : (
                        <>
                            <div className="divide-y">
                                {payouts.data.map((p) => (
                                    <article key={p.id} className="p-5">
                                        <div className="flex flex-wrap justify-between gap-4">
                                            <div>
                                                <div className="flex gap-2">
                                                    <strong>{p.number}</strong>
                                                    <StatusBadge
                                                        label={t(p.state)}
                                                        tone={
                                                            p.state ===
                                                            'approved'
                                                                ? 'success'
                                                                : p.state ===
                                                                    'rejected'
                                                                  ? 'danger'
                                                                  : 'warning'
                                                        }
                                                    />
                                                </div>
                                                <p className="mt-2">
                                                    {p.owner.name} ·{' '}
                                                    {p.account_snapshot.code}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {p.business_date} ·{' '}
                                                    {p.preparer.name}
                                                </p>
                                            </div>
                                            <strong>
                                                {formatPkr(p.amount)}
                                            </strong>
                                        </div>
                                        {canApprove &&
                                            p.state === 'prepared' && (
                                                <form
                                                    className="mt-4 grid gap-3 sm:grid-cols-[1fr_auto_auto]"
                                                    onSubmit={(e) => {
                                                        e.preventDefault();
                                                        decision.post(
                                                            `/finance/owner-payouts/${p.id}/decision`,
                                                        );
                                                    }}
                                                >
                                                    <Input
                                                        aria-label={t(
                                                            'Admin decision note',
                                                        )}
                                                        value={
                                                            decision.data
                                                                .decision_note
                                                        }
                                                        onChange={(e) =>
                                                            decision.setData(
                                                                'decision_note',
                                                                e.target.value,
                                                            )
                                                        }
                                                        required
                                                        minLength={5}
                                                    />
                                                    <Button
                                                        type="submit"
                                                        onClick={() => {
                                                            decision.setData(
                                                                'decision',
                                                                'approve',
                                                            );
                                                            decision.setData(
                                                                'confirmed',
                                                                true,
                                                            );
                                                        }}
                                                    >
                                                        {t('Approve')}
                                                    </Button>
                                                    <Button
                                                        type="submit"
                                                        variant="destructive"
                                                        onClick={() => {
                                                            decision.setData(
                                                                'decision',
                                                                'reject',
                                                            );
                                                            decision.setData(
                                                                'confirmed',
                                                                true,
                                                            );
                                                        }}
                                                    >
                                                        {t('Reject')}
                                                    </Button>
                                                </form>
                                            )}
                                        {p.decision_note && (
                                            <p className="mt-3 text-xs text-muted-foreground">
                                                {p.decider?.name}:{' '}
                                                {p.decision_note}
                                            </p>
                                        )}
                                    </article>
                                ))}
                            </div>
                            <Pager
                                prev_page_url={payouts.prev_page_url}
                                next_page_url={payouts.next_page_url}
                            />
                        </>
                    )}
                </section>
            </div>
        </>
    );
}
OwnerPayouts.layout = {
    breadcrumbs: [{ title: 'Owner payouts', href: '/finance/owner-payouts' }],
};
