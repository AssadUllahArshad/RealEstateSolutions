import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Save, ArrowLeft, ShieldCheck } from 'lucide-react';
import { PageHeading, HoldBadge, Pager } from '@/components/office-ui';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useUnsavedChanges } from '@/hooks/use-unsaved-changes';
import { formatPkr } from '@/lib/format-pkr';
import { useTranslate } from '@/lib/i18n';
import type { DraftRecord } from './index';
import BookingPreparation, {
    type Preparation,
} from '@/components/booking-preparation';
import DraftPurchasers, { type Purchaser } from '@/components/draft-purchasers';

type CustomerOption = { id: number; name: string };
type PlotOption = DraftRecord['plot'] & { list_price?: string | null };
type Props = {
    purchaserHistory: {
        version: number;
        actor_id: number;
        count: number;
        created_at: string;
    }[];
    purchasers: Purchaser[];
    preparation: Preparation | null;
    draft: (DraftRecord & { purchasers_version: number }) | null;
    creationKey: string;
    customers: CustomerOption[];
    plots: PlotOption[];
    lookup: { customer_q: string; plot_q: string };
    history: {
        data: {
            id: number;
            version: number;
            actor_id: number;
            details: {
                quoted_price: string;
                catalogue_price_snapshot: string | null;
                notes_changed: boolean;
            };
            created_at: string;
        }[];
        prev_page_url: string | null;
        next_page_url: string | null;
    } | null;
};

function DraftEditor({ draft, creationKey, customers, plots }: Props) {
    const t = useTranslate();
    const form = useForm({
        customer_id: String(draft?.customer_id ?? ''),
        plot_id: String(draft?.plot_id ?? ''),
        quoted_price: draft?.quoted_price ?? '',
        notes: draft?.notes ?? '',
        ...(!draft
            ? { creation_key: creationKey }
            : { version: draft.version }),
    });
    useUnsavedChanges(form.isDirty);
    const customerOptions = [...customers];
    if (draft && !customerOptions.some((c) => c.id === draft.customer_id))
        customerOptions.unshift(draft.customer);
    const plotOptions = [...plots];
    if (draft && !plotOptions.some((p) => p.id === draft.plot_id))
        plotOptions.unshift(draft.plot);
    const selected = plotOptions.find(
        (p) => String(p.id) === form.data.plot_id,
    );
    return (
        <form
            className="grid items-start gap-6 lg:grid-cols-[1fr_320px]"
            onSubmit={(e) => {
                e.preventDefault();
                const options = { onSuccess: () => form.setDefaults() };
                if (draft) form.patch(`/sales/${draft.id}`, options);
                else form.post('/sales', options);
            }}
        >
            <fieldset
                disabled={form.processing}
                className="min-w-0 border-0 p-0"
            >
                <section
                    className="ef-panel space-y-6 p-6"
                    aria-label={t('Draft details')}
                >
                    <div className="border-b pb-4">
                        <h2 className="font-semibold">{t('Proposed terms')}</h2>
                        <p className="mt-1 text-xs leading-5 text-muted-foreground">
                            {t(
                                'A customer contact is not a declaration of legal ownership.',
                            )}
                        </p>
                    </div>
                    <div>
                        <Label htmlFor="draft-customer">
                            {t('Customer contact *')}
                        </Label>
                        <select
                            id="draft-customer"
                            required
                            className="mt-2 h-11 w-full rounded-lg border bg-background px-3 text-sm"
                            value={form.data.customer_id}
                            onChange={(e) =>
                                form.setData('customer_id', e.target.value)
                            }
                        >
                            <option value="">{t('Select a customer')}</option>
                            {customerOptions.map((c) => (
                                <option value={c.id} key={c.id}>
                                    {c.name} (#{c.id})
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.customer_id} />
                        <Link
                            className="mt-2 inline-block text-xs text-blue-600"
                            href="/customers"
                        >
                            {t('Manage customer contacts')}
                        </Link>
                    </div>
                    <div>
                        <Label htmlFor="draft-plot">
                            {t('Proposed plot *')}
                        </Label>
                        <select
                            id="draft-plot"
                            required
                            className="mt-2 h-11 w-full rounded-lg border bg-background px-3 text-sm"
                            value={form.data.plot_id}
                            onChange={(e) =>
                                form.setData('plot_id', e.target.value)
                            }
                        >
                            <option value="">{t('Select a plot')}</option>
                            {plotOptions.map((p) => (
                                <option value={p.id} key={p.id}>
                                    {p.block.code} / {p.number}
                                    {p.active_hold
                                        ? ` (${p.active_hold.kind === 'block' ? t('Blocked') : t('On hold')})`
                                        : ''}
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.plot_id} />
                    </div>
                    <div>
                        <Label htmlFor="draft-price">
                            {t('Quoted price (PKR) *')}
                        </Label>
                        <Input
                            className="mt-2"
                            id="draft-price"
                            inputMode="decimal"
                            placeholder="e.g. 4500000.00"
                            required
                            value={form.data.quoted_price}
                            onChange={(e) =>
                                form.setData('quoted_price', e.target.value)
                            }
                        />
                        <InputError message={form.errors.quoted_price} />
                        <p className="mt-2 text-xs text-muted-foreground">
                            {t(
                                'Enter the full proposed price, with up to two decimal places. This is not an approved sale price.',
                            )}
                        </p>
                    </div>
                    <div>
                        <Label htmlFor="draft-notes">
                            {t('Conversation notes')}
                        </Label>
                        <textarea
                            id="draft-notes"
                            className="mt-2 min-h-28 w-full rounded-lg border bg-background p-3 text-sm"
                            maxLength={2000}
                            value={form.data.notes}
                            onChange={(e) =>
                                form.setData('notes', e.target.value)
                            }
                            placeholder={t(
                                'Site visit preferences, questions or proposed terms. Avoid identity documents and bank details.',
                            )}
                        />
                        <InputError message={form.errors.notes} />
                    </div>
                    <div role="alert">
                        {Object.entries(form.errors)
                            .filter(
                                ([key]) =>
                                    ![
                                        'customer_id',
                                        'plot_id',
                                        'quoted_price',
                                        'notes',
                                    ].includes(key),
                            )
                            .map(([key, message]) => (
                                <InputError key={key} message={message} />
                            ))}
                    </div>
                    <div className="flex flex-wrap items-center justify-between gap-3 border-t pt-5">
                        <span className="text-xs text-muted-foreground">
                            {draft
                                ? `${t('Saved version')} ${draft.version}`
                                : t('Not saved yet')}
                        </span>
                        <Button disabled={form.processing}>
                            <Save className="size-4" />
                            {form.processing ? t('Saving…') : t('Save draft')}
                        </Button>
                    </div>
                </section>
            </fieldset>
            <aside className="space-y-4">
                <div className="ef-panel p-5">
                    <h2 className="mb-4 font-semibold">{t('Plot context')}</h2>
                    {selected ? (
                        <div className="space-y-4 text-sm">
                            <p className="text-lg font-bold">
                                {selected.block.code} / {selected.number}
                            </p>
                            <HoldBadge
                                kind={
                                    selected.active_hold?.kind as
                                        | 'reservation'
                                        | 'block'
                                        | undefined
                                }
                            />
                            <p className="text-xs leading-5 text-muted-foreground">
                                {t(
                                    'Current holds are shown for context. Multiple drafts can refer to the same plot.',
                                )}
                            </p>
                            <Link
                                className="ef-text-link"
                                href={`/inventory/plots/${selected.id}/hold`}
                            >
                                {t('Review plot holds')}
                            </Link>
                        </div>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            {t(
                                'Select a plot to review its current hold status.',
                            )}
                        </p>
                    )}
                </div>
                <div className="ef-panel p-5">
                    <ShieldCheck className="mb-3 size-6 text-blue-600" />
                    <h2 className="font-semibold">
                        {t('Recorded separately')}
                    </h2>
                    <p className="mt-2 text-xs leading-6 text-muted-foreground">
                        {t(
                            'The catalogue price is captured on save when a plot is first selected or changed. Later catalogue changes will not silently alter this snapshot.',
                        )}
                    </p>
                    {draft && (
                        <p className="mt-4 text-sm font-semibold">
                            {t('Snapshot')}:{' '}
                            {draft.catalogue_price_snapshot
                                ? formatPkr(draft.catalogue_price_snapshot)
                                : t('No list price recorded')}
                        </p>
                    )}
                </div>
            </aside>
        </form>
    );
}

export default function Edit(props: Props) {
    const { errors } = usePage().props;
    const t = useTranslate();
    return (
        <>
            <Head
                title={
                    props.draft
                        ? `Draft ${props.draft.id}`
                        : t('New booking draft')
                }
            />
            <div className="ef-page">
                <PageHeading
                    eyebrow={t('Sales desk · Non-binding proposal')}
                    title={
                        props.draft
                            ? `DRAFT-${props.draft.id}`
                            : t('New booking draft')
                    }
                    description={t(
                        'Save the conversation now. Plot allocation, legal purchasers and payments are handled separately.',
                    )}
                >
                    <Button asChild variant="outline">
                        <Link href="/sales">
                            <ArrowLeft />
                            {t('All drafts')}
                        </Link>
                    </Button>
                    {props.draft && (
                        <Button asChild>
                            <Link href={`/sales/${props.draft.id}/terms`}>
                                {t('Pricing & installments')}
                            </Link>
                        </Button>
                    )}
                    {props.draft && (
                        <Button asChild variant="outline">
                            <Link href={`/sales/${props.draft.id}/confirm`}>
                                {t('Review & confirm booking')}
                            </Link>
                        </Button>
                    )}
                </PageHeading>
                <details className="ef-panel p-5">
                    <summary className="cursor-pointer text-sm font-semibold">
                        {t('Find a different customer or plot')}
                    </summary>
                    <p className="mt-3 text-xs text-muted-foreground">
                        {t(
                            'Lists show up to 30 matches. Search before editing; changing this search reloads the form.',
                        )}
                    </p>
                    <form method="get" className="mt-4 flex flex-wrap gap-3">
                        <Input
                            className="min-w-40 flex-1"
                            name="customer_q"
                            aria-label={t('Find customer')}
                            placeholder={t('Customer name')}
                            defaultValue={props.lookup.customer_q}
                            maxLength={160}
                        />
                        <Input
                            className="min-w-40 flex-1"
                            name="plot_q"
                            aria-label={t('Find plot')}
                            placeholder={t('Plot number')}
                            defaultValue={props.lookup.plot_q}
                            maxLength={160}
                        />
                        <Button variant="outline">{t('Find matches')}</Button>
                    </form>
                </details>
                {errors.conflict && (
                    <div
                        role="alert"
                        className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"
                    >
                        {errors.conflict}
                    </div>
                )}
                <DraftEditor
                    key={`terms-${props.draft?.id ?? 'new'}-${props.draft?.version ?? props.creationKey}`}
                    {...props}
                />
                {props.preparation && (
                    <BookingPreparation preparation={props.preparation} />
                )}
                {props.draft && (
                    <DraftPurchasers
                        key={`purchasers-${props.draft.id}-${props.draft.purchasers_version}`}
                        draftId={props.draft.id}
                        version={props.draft.purchasers_version}
                        purchasers={props.purchasers}
                        customers={props.customers}
                        history={props.purchaserHistory}
                    />
                )}
                {props.history && (
                    <section
                        className="ef-panel overflow-hidden"
                        aria-label="Draft version history"
                    >
                        <div className="border-b p-5">
                            <h2 className="font-semibold">Version history</h2>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Exact quoted amounts retained for each saved
                                change. Notes are not copied into this history.
                            </p>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="ef-table">
                                <caption className="sr-only">
                                    Saved draft versions
                                </caption>
                                <thead>
                                    <tr>
                                        <th>Version</th>
                                        <th>Quoted price</th>
                                        <th>Catalogue snapshot</th>
                                        <th>Staff reference</th>
                                        <th>Saved (UTC)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {props.history.data.map((event) => (
                                        <tr key={event.id}>
                                            <td>v{event.version}</td>
                                            <td className="whitespace-nowrap">
                                                {formatPkr(
                                                    event.details.quoted_price,
                                                )}
                                            </td>
                                            <td className="whitespace-nowrap">
                                                {formatPkr(
                                                    event.details
                                                        .catalogue_price_snapshot,
                                                )}
                                            </td>
                                            <td>#{event.actor_id}</td>
                                            <td className="whitespace-nowrap">
                                                {event.created_at
                                                    .replace('T', ' ')
                                                    .slice(0, 19)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <Pager {...props.history} />
                    </section>
                )}
            </div>
        </>
    );
}
Edit.layout = {
    breadcrumbs: [
        { title: 'Sales desk', href: '/sales' },
        { title: 'Booking draft', href: '#' },
    ],
};
