import { Head, Link, useForm, usePage } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useUnsavedChanges } from '@/hooks/use-unsaved-changes';
import { useTranslate } from '@/lib/i18n';
import type { Plot } from '@/types/inventory';

type Hold = {
    id: number;
    kind: 'reservation' | 'block';
    state: 'active' | 'released';
    purpose: string;
    created_at: string;
    released_at: string | null;
    release_reason: string | null;
    creator: { name: string };
    releaser?: { name: string } | null;
};

function HeldAction({
    plot,
    active,
    requestKey,
    canAdminister,
}: {
    plot: Plot;
    active: Hold | null;
    requestKey: string;
    canAdminister: boolean;
}) {
    const t = useTranslate();
    const abilities = usePage().props.auth.abilities;
    const form = useForm({
        key: requestKey,
        reason: '',
        kind: abilities.includes('inventory.reservation')
            ? 'reservation'
            : 'block',
    });
    useUnsavedChanges(form.isDirty && form.data.reason !== '');
    const canRelease = canAdminister && abilities.includes('inventory.release');
    const canBlock = canAdminister && abilities.includes('inventory.block');
    const canReserve = abilities.includes('inventory.reservation');
    if (active && !canRelease)
        return (
            <p className="text-sm text-muted-foreground">
                {t(
                    active.kind === 'block'
                        ? 'Only an authorized admin can release this block.'
                        : 'Only an authorized admin can release this hold.',
                )}
            </p>
        );
    if (!active && !canReserve && !canBlock) return null;
    return (
        <form
            className="space-y-4"
            onSubmit={(event) => {
                event.preventDefault();
                form.post(
                    active
                        ? `/inventory/holds/${active.id}/release`
                        : `/inventory/plots/${plot.id}/holds`,
                    { preserveScroll: true },
                );
            }}
        >
            {!active && canBlock && (
                <div>
                    <Label htmlFor="hold-kind">{t('Action')}</Label>
                    <select
                        id="hold-kind"
                        className="mt-2 block h-10 w-full rounded-md border bg-background px-3"
                        value={form.data.kind}
                        onChange={(event) =>
                            form.setData('kind', event.target.value)
                        }
                    >
                        {canReserve && (
                            <option value="reservation">
                                {t('Place a hold')}
                            </option>
                        )}
                        <option value="block">
                            {t('Block plot (admin only)')}
                        </option>
                    </select>
                </div>
            )}
            <div>
                <Label htmlFor="hold-reason">
                    {active ? t('Release reason') : t('Hold or block purpose')}
                </Label>
                <textarea
                    id="hold-reason"
                    className="mt-2 block min-h-24 w-full rounded-md border bg-background p-3"
                    required
                    maxLength={500}
                    value={form.data.reason}
                    onChange={(event) =>
                        form.setData('reason', event.target.value)
                    }
                />
                <p className="mt-2 text-xs text-muted-foreground">
                    {t(
                        'Use an internal purpose. Do not enter CNIC, contact details or private customer information.',
                    )}
                </p>
                <InputError message={form.errors.reason} />
                <InputError message={form.errors.kind} />
                <InputError message={form.errors.key} />
                <InputError
                    message={(form.errors as Record<string, string>).conflict}
                />
            </div>
            <Button disabled={form.processing}>
                {active
                    ? t('Release hold or block')
                    : form.data.kind === 'block'
                      ? t('Block plot')
                      : t('Confirm hold')}
            </Button>
        </form>
    );
}

export default function PlotHold({
    booked,
    plot,
    active,
    history,
    requestKey,
    canAdminister,
}: {
    booked: boolean;
    plot: Plot;
    active: Hold | null;
    history: {
        data: Hold[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    requestKey: string;
    canAdminister: boolean;
}) {
    const t = useTranslate();
    return (
        <>
            <Head title={`${t('Plot hold')} ${plot.number}`} />
            <div className="mx-auto w-full max-w-3xl space-y-6 p-5 md:p-8">
                <Link href="/inventory" className="text-sm underline">
                    {t('Back to inventory')}
                </Link>
                <header>
                    <p className="text-sm text-muted-foreground">
                        Block {plot.block.code} · {plot.size.label}
                    </p>
                    <h1 className="mt-2 text-2xl font-semibold break-words">
                        {t('Plot hold')} {plot.number}
                    </h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        {t(
                            'Holds take effect immediately and remain until an authorized admin releases them. No automatic expiry or approval queue.',
                        )}
                    </p>
                </header>
                <section
                    className="space-y-4 rounded-xl border bg-card p-5"
                    aria-labelledby="current-hold"
                >
                    <h2 id="current-hold" className="font-semibold">
                        {booked
                            ? t('Plot booked')
                            : active
                              ? active.kind === 'block'
                                  ? t('Plot blocked')
                                  : t('Active hold')
                              : t('No active hold or block')}
                    </h2>
                    {active && (
                        <div className="space-y-2 text-sm">
                            <p>
                                {t('Placed by')} {active.creator.name}
                            </p>
                            <p className="break-words whitespace-pre-wrap">
                                {active.purpose}
                            </p>
                        </div>
                    )}
                    {!booked && (
                        <HeldAction
                            key={`${active?.id ?? 'new'}-${active?.state ?? ''}`}
                            plot={plot}
                            active={active}
                            requestKey={requestKey}
                            canAdminister={canAdminister}
                        />
                    )}
                    {booked && (
                        <p className="text-sm text-muted-foreground">
                            {t(
                                'This plot has a confirmed booking. Holds and blocks are unavailable.',
                            )}
                        </p>
                    )}
                </section>
                <p className="text-sm text-muted-foreground">
                    {t(
                        'This is internal inventory control. It does not create a customer booking, allotment, sale or payment obligation.',
                    )}
                </p>
                <section className="rounded-xl border bg-card p-5">
                    <h2 className="font-semibold">{t('Hold history')}</h2>
                    {!history.data.length && (
                        <p className="mt-3 text-sm text-muted-foreground">
                            {t('No holds or blocks recorded.')}
                        </p>
                    )}
                    <ul className="mt-3 divide-y">
                        {history.data.map((hold) => (
                            <li
                                key={hold.id}
                                className="space-y-2 py-4 text-sm"
                            >
                                <p className="font-medium">
                                    {hold.kind === 'block'
                                        ? t('Block')
                                        : t('Hold')}{' '}
                                    #{hold.id} · {hold.state}
                                </p>
                                <p className="break-words whitespace-pre-wrap">
                                    {hold.purpose}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {hold.creator.name} ·{' '}
                                    {new Date(hold.created_at).toLocaleString(
                                        'en-PK',
                                        { timeZone: 'Asia/Karachi' },
                                    )}
                                </p>
                                {hold.released_at && (
                                    <p className="break-words whitespace-pre-wrap">
                                        {t('Released by')} {hold.releaser?.name}{' '}
                                        ·{' '}
                                        {new Date(
                                            hold.released_at,
                                        ).toLocaleString('en-PK', {
                                            timeZone: 'Asia/Karachi',
                                        })}
                                        <br />
                                        {t('Reason')}: {hold.release_reason}
                                    </p>
                                )}
                            </li>
                        ))}
                    </ul>
                    <nav
                        className="mt-3 flex justify-between text-sm"
                        aria-label="Hold history pages"
                    >
                        {history.prev_page_url ? (
                            <Link href={history.prev_page_url}>
                                {t('Previous')}
                            </Link>
                        ) : (
                            <span />
                        )}
                        {history.next_page_url && (
                            <Link href={history.next_page_url}>
                                {t('Next')}
                            </Link>
                        )}
                    </nav>
                </section>
            </div>
        </>
    );
}
PlotHold.layout = {
    breadcrumbs: [
        { title: 'Plot inventory', href: '/inventory' },
        { title: 'Plot hold', href: '#' },
    ],
};
