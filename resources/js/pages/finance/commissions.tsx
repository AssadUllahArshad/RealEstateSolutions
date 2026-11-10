import { Head, useForm } from '@inertiajs/react';
import { PageHeading, Pager, StatusBadge } from '@/components/office-ui';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatPkr } from '@/lib/format-pkr';
import { useTranslate } from '@/lib/i18n';
type Props = {
    agents: { id: number; name: string; payable: string }[];
    events: {
        id: number;
        booking_id: number;
        source_kind: string;
        business_date: string;
        percentage: string;
        commission_amount: string;
    }[];
    payments: {
        data: any[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    accounts: { id: number; code: string; name: string; type: string }[];
    businessDate: string;
    commandKey: string;
    decisionKey: string;
    canPrepare: boolean;
    canApprove: boolean;
    canConfigure: boolean;
    canCorrect: boolean;
    legacyBookings: { id: number; label: string; confirmed_on: string }[];
    activeLegacyAssignments: {
        booking_id: number;
        label: string;
        agent_name: string;
        percentage: string;
        effective_from: string;
    }[];
    filters: { agent_id: string; from: string; to: string };
    summary: {
        earned: string;
        paid: string;
        payable: string;
        recoverable: string;
    };
    corrections: {
        id: number;
        booking_id: number;
        agent_name: string;
        percentage: string;
        effective_from: string;
        reason: string;
        created_at: string;
    }[];
};
export default function Commissions({
    agents,
    events,
    payments,
    accounts,
    businessDate,
    commandKey,
    decisionKey,
    canPrepare,
    canApprove,
    canConfigure,
    canCorrect,
    legacyBookings,
    activeLegacyAssignments,
    filters,
    summary,
    corrections,
}: Props) {
    const t = useTranslate();
    const form = useForm({
        agent_id: '',
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
    const legacy = useForm({
        booking_id: '',
        agent_id: '',
        percentage: '',
        effective_from: '',
        reason: '',
        confirmed: false,
    });
    const correction = useForm({
        booking_id: '',
        agent_id: '',
        percentage: '',
        effective_from: '',
        reason: '',
        confirmed: false,
    });
    return (
        <>
            <Head title={t('Agent commissions')} />
            <div className="ef-page">
                <PageHeading
                    eyebrow={t('Finance · Agent earnings')}
                    title={t('Agent commissions')}
                    description={t(
                        'Commission accrues only from recognized principal allocated to bookings at the saved booking rate.',
                    )}
                />
                <section className="ef-panel p-5">
                    <form
                        action="/finance/commissions"
                        className="grid gap-4 md:grid-cols-[1.3fr_1fr_1fr_auto]"
                        method="get"
                    >
                        <div>
                            <Label>{t('Sales agent')}</Label>
                            <select
                                className="ef-input mt-2"
                                defaultValue={filters.agent_id}
                                name="agent_id"
                            >
                                <option value="">{t('All agents')}</option>
                                {agents.map((agent) => (
                                    <option key={agent.id} value={agent.id}>
                                        {agent.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <Label>{t('From date')}</Label>
                            <Input
                                className="mt-2"
                                defaultValue={filters.from}
                                name="from"
                                type="date"
                            />
                        </div>
                        <div>
                            <Label>{t('To date')}</Label>
                            <Input
                                className="mt-2"
                                defaultValue={filters.to}
                                name="to"
                                type="date"
                            />
                        </div>
                        <Button className="self-end">
                            {t('Apply filters')}
                        </Button>
                    </form>
                    <div className="mt-4 flex flex-wrap gap-4 text-sm">
                        <a
                            className="ef-text-link"
                            href={`/finance/commissions/export?${new URLSearchParams(filters).toString()}`}
                        >
                            {t('Export reconciliation CSV')}
                        </a>
                        {filters.agent_id && (
                            <a
                                className="ef-text-link"
                                href={`/reports/agents/${filters.agent_id}/commission-statement/print?from=${filters.from}&to=${filters.to}`}
                                rel="noreferrer"
                                target="_blank"
                            >
                                {t('Print filtered agent statement')}
                            </a>
                        )}
                    </div>
                </section>
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {[
                        ['Lifetime earned', summary.earned],
                        ['Approved payments', summary.paid],
                        ['Total payable', summary.payable],
                        ['Total recoverable', summary.recoverable],
                    ].map(([label, value]) => (
                        <div className="ef-panel p-5" key={label}>
                            <p className="text-xs text-muted-foreground">
                                {t(label)}
                            </p>
                            <strong className="mt-2 block text-xl">
                                {formatPkr(value)}
                            </strong>
                        </div>
                    ))}
                </div>
                <div className="grid gap-4 sm:grid-cols-2">
                    {agents.map((a) => (
                        <div className="ef-panel p-5" key={a.id}>
                            <p>{a.name}</p>
                            <strong className="mt-2 block text-2xl">
                                {formatPkr(a.payable)}
                            </strong>
                            <span className="text-xs text-muted-foreground">
                                {t(
                                    Number(a.payable) < 0
                                        ? 'Recoverable from agent'
                                        : 'Available commission',
                                )}
                            </span>
                            <a
                                className="ef-text-link mt-3 inline-flex text-xs"
                                href={`/reports/agents/${a.id}/commission-statement/print`}
                                target="_blank"
                                rel="noreferrer"
                            >
                                {t('Print statement')}
                            </a>
                        </div>
                    ))}
                </div>
                {canConfigure && legacyBookings.length > 0 && (
                    <section className="ef-panel p-6">
                        <h2 className="font-semibold">
                            {t('Assign a legacy booking')}
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {t(
                                'Enter the exact approved booking rate and Karachi collection start date. Earlier allocations remain excluded.',
                            )}
                        </p>
                        <form
                            className="mt-5 grid gap-4 md:grid-cols-2"
                            onSubmit={(e) => {
                                e.preventDefault();
                                legacy.post(
                                    `/finance/commissions/legacy/${legacy.data.booking_id}`,
                                );
                            }}
                        >
                            <div>
                                <Label>{t('Legacy booking')}</Label>
                                <select
                                    className="ef-input mt-2"
                                    required
                                    value={legacy.data.booking_id}
                                    onChange={(e) =>
                                        legacy.setData(
                                            'booking_id',
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="">
                                        {t('Choose a booking')}
                                    </option>
                                    {legacyBookings.map((b) => (
                                        <option key={b.id} value={b.id}>
                                            {b.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <Label>{t('Responsible sales agent')}</Label>
                                <select
                                    className="ef-input mt-2"
                                    required
                                    value={legacy.data.agent_id}
                                    onChange={(e) =>
                                        legacy.setData(
                                            'agent_id',
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="">
                                        {t('Choose an agent')}
                                    </option>
                                    {agents.map((a) => (
                                        <option key={a.id} value={a.id}>
                                            {a.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <Label>{t('Exact commission rate (%)')}</Label>
                                <Input
                                    className="mt-2"
                                    required
                                    inputMode="decimal"
                                    value={legacy.data.percentage}
                                    onChange={(e) =>
                                        legacy.setData(
                                            'percentage',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div>
                                <Label>{t('Eligible collections from')}</Label>
                                <Input
                                    className="mt-2"
                                    required
                                    type="date"
                                    value={legacy.data.effective_from}
                                    onChange={(e) =>
                                        legacy.setData(
                                            'effective_from',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div className="md:col-span-2">
                                <Label>
                                    {t('Approval reason and evidence')}
                                </Label>
                                <textarea
                                    className="ef-input mt-2 min-h-20"
                                    required
                                    minLength={10}
                                    value={legacy.data.reason}
                                    onChange={(e) =>
                                        legacy.setData('reason', e.target.value)
                                    }
                                />
                            </div>
                            <label className="flex gap-3 text-sm md:col-span-2">
                                <input
                                    type="checkbox"
                                    required
                                    checked={legacy.data.confirmed}
                                    onChange={(e) =>
                                        legacy.setData(
                                            'confirmed',
                                            e.target.checked,
                                        )
                                    }
                                />
                                {t(
                                    'I verified the booking, responsible agent, exact rate, start date and supporting approval.',
                                )}
                            </label>
                            {Object.values(legacy.errors).map(
                                (error, index) => (
                                    <p
                                        className="text-sm text-destructive"
                                        key={index}
                                    >
                                        {error}
                                    </p>
                                ),
                            )}
                            <Button
                                className="w-fit"
                                disabled={legacy.processing}
                            >
                                {t('Record legacy assignment')}
                            </Button>
                        </form>
                    </section>
                )}
                {canCorrect && activeLegacyAssignments.length > 0 && (
                    <section className="ef-panel p-6">
                        <h2 className="font-semibold">
                            {t('Correct a legacy commission')}
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {t(
                                'Main-admin correction creates compensating entries. Existing events and payments remain in the audit trail.',
                            )}
                        </p>
                        <form
                            className="mt-5 grid gap-4 md:grid-cols-2"
                            onSubmit={(e) => {
                                e.preventDefault();
                                correction.post(
                                    `/finance/commissions/legacy/${correction.data.booking_id}/correct`,
                                );
                            }}
                        >
                            <div>
                                <Label>{t('Active legacy assignment')}</Label>
                                <select
                                    className="ef-input mt-2"
                                    required
                                    value={correction.data.booking_id}
                                    onChange={(e) =>
                                        correction.setData(
                                            'booking_id',
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="">
                                        {t('Choose a booking')}
                                    </option>
                                    {activeLegacyAssignments.map((a) => (
                                        <option
                                            key={a.booking_id}
                                            value={a.booking_id}
                                        >
                                            {a.label} · {a.agent_name} ·{' '}
                                            {a.percentage}%
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <Label>{t('Correct responsible agent')}</Label>
                                <select
                                    className="ef-input mt-2"
                                    required
                                    value={correction.data.agent_id}
                                    onChange={(e) =>
                                        correction.setData(
                                            'agent_id',
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="">
                                        {t('Choose an agent')}
                                    </option>
                                    {agents.map((a) => (
                                        <option key={a.id} value={a.id}>
                                            {a.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <Label>
                                    {t('Correct commission rate (%)')}
                                </Label>
                                <Input
                                    className="mt-2"
                                    required
                                    inputMode="decimal"
                                    value={correction.data.percentage}
                                    onChange={(e) =>
                                        correction.setData(
                                            'percentage',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div>
                                <Label>{t('Correct eligible date')}</Label>
                                <Input
                                    className="mt-2"
                                    required
                                    type="date"
                                    value={correction.data.effective_from}
                                    onChange={(e) =>
                                        correction.setData(
                                            'effective_from',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div className="md:col-span-2">
                                <Label>
                                    {t('Correction reason and evidence')}
                                </Label>
                                <textarea
                                    className="ef-input mt-2 min-h-20"
                                    required
                                    minLength={10}
                                    value={correction.data.reason}
                                    onChange={(e) =>
                                        correction.setData(
                                            'reason',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <label className="flex gap-3 text-sm md:col-span-2">
                                <input
                                    type="checkbox"
                                    required
                                    checked={correction.data.confirmed}
                                    onChange={(e) =>
                                        correction.setData(
                                            'confirmed',
                                            e.target.checked,
                                        )
                                    }
                                />
                                {t(
                                    'I authorize the compensating correction and verified the replacement agent, rate and effective date.',
                                )}
                            </label>
                            {Object.values(correction.errors).map(
                                (error, index) => (
                                    <p
                                        className="text-sm text-destructive"
                                        key={index}
                                    >
                                        {error}
                                    </p>
                                ),
                            )}
                            <Button
                                className="w-fit"
                                disabled={correction.processing}
                            >
                                {t('Post commission correction')}
                            </Button>
                        </form>
                    </section>
                )}
                {canPrepare && (
                    <section className="ef-panel p-6">
                        <h2 className="font-semibold">
                            {t('Prepare commission payment')}
                        </h2>
                        <form
                            className="mt-5 grid gap-4 md:grid-cols-2"
                            onSubmit={(e) => {
                                e.preventDefault();
                                form.post('/finance/commissions');
                            }}
                        >
                            <div>
                                <Label>{t('Sales agent')}</Label>
                                <select
                                    className="ef-input mt-2"
                                    required
                                    value={form.data.agent_id}
                                    onChange={(e) =>
                                        form.setData('agent_id', e.target.value)
                                    }
                                >
                                    <option value="">
                                        {t('Choose an agent')}
                                    </option>
                                    {agents.map((a) => (
                                        <option value={a.id} key={a.id}>
                                            {a.name} · {formatPkr(a.payable)}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <Label>{t('Payment account')}</Label>
                                <select
                                    className="ef-input mt-2"
                                    required
                                    value={form.data.financial_account_id}
                                    onChange={(e) =>
                                        form.setData(
                                            'financial_account_id',
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="">
                                        {t('Choose an account')}
                                    </option>
                                    {accounts.map((a) => (
                                        <option value={a.id} key={a.id}>
                                            {a.code} · {a.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <Label>{t('Amount (PKR)')}</Label>
                                <Input
                                    className="mt-2"
                                    required
                                    inputMode="decimal"
                                    value={form.data.amount}
                                    onChange={(e) =>
                                        form.setData('amount', e.target.value)
                                    }
                                />
                            </div>
                            <div>
                                <Label>{t('Reference')}</Label>
                                <Input
                                    className="mt-2"
                                    value={form.data.reference}
                                    onChange={(e) =>
                                        form.setData(
                                            'reference',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div className="md:col-span-2">
                                <Label>{t('Verification note')}</Label>
                                <textarea
                                    className="ef-input mt-2 min-h-20"
                                    required
                                    minLength={10}
                                    value={form.data.verification_note}
                                    onChange={(e) =>
                                        form.setData(
                                            'verification_note',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <label className="flex gap-3 text-sm md:col-span-2">
                                <input
                                    type="checkbox"
                                    required
                                    checked={form.data.confirmed}
                                    onChange={(e) =>
                                        form.setData(
                                            'confirmed',
                                            e.target.checked,
                                        )
                                    }
                                />
                                {t(
                                    'I verified the agent, earned commission, amount, account and evidence.',
                                )}
                            </label>
                            <Button
                                className="w-fit"
                                disabled={form.processing}
                            >
                                {t('Submit for admin approval')}
                            </Button>
                        </form>
                    </section>
                )}
                <section className="ef-panel overflow-hidden">
                    <div className="ef-panel-heading">
                        <h2 className="font-semibold">
                            {t('Commission payments')}
                        </h2>
                    </div>
                    <div className="divide-y">
                        {payments.data.length === 0 ? (
                            <p className="p-6 text-sm text-muted-foreground">
                                {t('No commission payments recorded.')}
                            </p>
                        ) : (
                            payments.data.map((p) => (
                                <article className="p-5" key={p.id}>
                                    <div className="flex justify-between gap-3">
                                        <div>
                                            <strong>{p.number}</strong>{' '}
                                            <StatusBadge
                                                label={t(p.state)}
                                                tone={
                                                    p.state === 'approved'
                                                        ? 'success'
                                                        : p.state === 'rejected'
                                                          ? 'danger'
                                                          : 'warning'
                                                }
                                            />
                                            <p>
                                                {p.agent_snapshot.name} ·{' '}
                                                {p.account_snapshot.code}
                                            </p>
                                        </div>
                                        <strong>{formatPkr(p.amount)}</strong>
                                    </div>
                                    {p.state === 'approved' && (
                                        <a
                                            className="ef-text-link mt-3 inline-flex text-sm"
                                            href={`/finance/commissions/${p.id}/print`}
                                            target="_blank"
                                            rel="noreferrer"
                                        >
                                            {t('Print / Save PDF')}
                                        </a>
                                    )}
                                    {canApprove && p.state === 'prepared' && (
                                        <form
                                            className="mt-4 grid gap-3 sm:grid-cols-[1fr_auto_auto]"
                                            onSubmit={(e) => {
                                                e.preventDefault();
                                                decision.post(
                                                    `/finance/commissions/${p.id}/decision`,
                                                );
                                            }}
                                        >
                                            <Input
                                                required
                                                minLength={5}
                                                value={
                                                    decision.data.decision_note
                                                }
                                                onChange={(e) =>
                                                    decision.setData(
                                                        'decision_note',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                            <Button
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
                                </article>
                            ))
                        )}
                    </div>
                    <Pager {...payments} />
                </section>
                {corrections.length > 0 && (
                    <section className="ef-panel overflow-hidden">
                        <div className="ef-panel-heading">
                            <h2 className="font-semibold">
                                {t('Commission correction history')}
                            </h2>
                        </div>
                        <div className="divide-y">
                            {corrections.map((correction) => (
                                <article className="p-5" key={correction.id}>
                                    <div className="flex flex-wrap justify-between gap-3">
                                        <strong>
                                            #{correction.booking_id} ·{' '}
                                            {correction.agent_name}
                                        </strong>
                                        <span>
                                            {correction.percentage}% ·{' '}
                                            {correction.effective_from}
                                        </span>
                                    </div>
                                    <p className="mt-2 text-sm text-muted-foreground">
                                        {correction.reason}
                                    </p>
                                </article>
                            ))}
                        </div>
                    </section>
                )}
                <section className="ef-panel overflow-hidden">
                    <div className="ef-panel-heading">
                        <h2 className="font-semibold">
                            {t('Immutable earning events')}
                        </h2>
                    </div>
                    <div className="divide-y">
                        {events.length === 0 ? (
                            <p className="p-6 text-sm text-muted-foreground">
                                {t('No commission activity recorded.')}
                            </p>
                        ) : (
                            events.map((e) => (
                                <div
                                    className="flex flex-wrap justify-between gap-3 p-4 text-sm"
                                    key={e.id}
                                >
                                    <span>
                                        #{e.booking_id} · {t(e.source_kind)}
                                    </span>
                                    <span className="flex flex-wrap gap-x-3 gap-y-1">
                                        <time dateTime={e.business_date}>
                                            {e.business_date}
                                        </time>
                                        <span>
                                            {formatPkr(e.commission_amount)} ·{' '}
                                            {e.percentage}%
                                        </span>
                                    </span>
                                </div>
                            ))
                        )}
                    </div>
                </section>
            </div>
        </>
    );
}
Commissions.layout = {
    breadcrumbs: [{ title: 'Agent commissions', href: '/finance/commissions' }],
};
