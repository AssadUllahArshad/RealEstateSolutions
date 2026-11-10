import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Building2, Grid2X2, List, Plus, Settings2 } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useUnsavedChanges } from '@/hooks/use-unsaved-changes';
import { formatPkr } from '@/lib/format-pkr';
import { useTranslate } from '@/lib/i18n';
import { HoldBadge, StatCard } from '@/components/office-ui';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import type {
    Block,
    InventoryFilters,
    Plot,
    PlotSize,
    SocietyProfile,
    PlotOwner,
} from '@/types/inventory';

type Props = {
    owners: PlotOwner[];
    society: SocietyProfile;
    blocks: Block[];
    sizes: PlotSize[];
    filters: InventoryFilters;
    summary: { plots: number; blocks: number; sizes: number };
    plots: {
        data: Plot[];
        total: number;
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    history: {
        id: number;
        event: string;
        subject_type: string;
        subject_id: number;
        actor_id: number;
        created_at: string;
    }[];
};
const selectClass = 'min-w-36';

function PlotEditor({
    owners,
    plot,
    blocks,
    sizes,
    close,
    onDirtyChange,
}: {
    owners: PlotOwner[];
    plot: Plot | null;
    blocks: Block[];
    sizes: PlotSize[];
    close: () => void;
    onDirtyChange: (dirty: boolean) => void;
}) {
    const t = useTranslate();
    const form = useForm({
        plot_owner_id: plot?.plot_owner_id ? String(plot.plot_owner_id) : '',
        owner_percentage: plot?.owner_percentage ?? '',
        number: plot?.number ?? '',
        block_id: plot ? String(plot.block_id) : '',
        plot_size_id: plot ? String(plot.plot_size_id) : '',
        list_price: plot?.list_price ?? '',
    });
    useUnsavedChanges(form.isDirty);
    useEffect(() => onDirtyChange(form.isDirty), [form.isDirty, onDirtyChange]);
    return (
        <section className="bg-card" aria-label="Plot details">
            <p className="mt-1 text-sm text-muted-foreground">
                Catalogue details only. This does not reserve or allot the plot.
            </p>
            <form
                className="mt-5 grid gap-5 sm:grid-cols-2"
                onSubmit={(event) => {
                    event.preventDefault();
                    const options = { preserveScroll: true, onSuccess: close };
                    if (plot)
                        form.patch(`/inventory/plots/${plot.id}`, options);
                    else form.post('/inventory/plots', options);
                }}
            >
                <div>
                    <Label htmlFor="plot-number">Plot number *</Label>
                    <Input
                        className="mt-2"
                        id="plot-number"
                        value={form.data.number}
                        onChange={(event) =>
                            form.setData('number', event.target.value)
                        }
                        required
                        maxLength={64}
                        autoFocus
                    />
                    <InputError message={form.errors.number} />
                </div>
                <div>
                    <Label htmlFor="plot-block">Block *</Label>
                    <select
                        className={`${selectClass} mt-2 w-full`}
                        id="plot-block"
                        value={form.data.block_id}
                        onChange={(event) =>
                            form.setData('block_id', event.target.value)
                        }
                        required
                    >
                        <option value="">Choose a block</option>
                        {blocks.map((block) => (
                            <option key={block.id} value={block.id}>
                                {block.code} · {block.name}
                            </option>
                        ))}
                    </select>
                    <InputError message={form.errors.block_id} />
                </div>
                <div>
                    <Label htmlFor="plot-size">Plot size *</Label>
                    <select
                        className={`${selectClass} mt-2 w-full`}
                        id="plot-size"
                        value={form.data.plot_size_id}
                        onChange={(event) =>
                            form.setData('plot_size_id', event.target.value)
                        }
                        required
                    >
                        <option value="">Choose a size</option>
                        {sizes.map((size) => (
                            <option key={size.id} value={size.id}>
                                {size.label} · {size.area_marla} marla
                            </option>
                        ))}
                    </select>
                    <InputError message={form.errors.plot_size_id} />
                </div>
                <div>
                    <Label htmlFor="plot-price">
                        List price (PKR, optional)
                    </Label>
                    <Input
                        className="mt-2"
                        id="plot-price"
                        inputMode="decimal"
                        value={form.data.list_price}
                        onChange={(event) =>
                            form.setData('list_price', event.target.value)
                        }
                        maxLength={19}
                        placeholder="e.g. 3200000.00"
                    />
                    <InputError message={form.errors.list_price} />
                    <p className="mt-1 text-xs text-muted-foreground">
                        A catalogue reference price, not an agreed sale amount.
                    </p>
                </div>
                <div className="sm:col-span-2">
                    <Label htmlFor="plot-owner">Plot owner / admin</Label>
                    <select
                        id="plot-owner"
                        className={`${selectClass} mt-2 w-full`}
                        value={form.data.plot_owner_id}
                        onChange={(event) =>
                            form.setData('plot_owner_id', event.target.value)
                        }
                        required={!plot}
                    >
                        <option value="">Unassigned — choose an owner</option>
                        {owners.map((owner) => (
                            <option key={owner.id} value={owner.id}>
                                {owner.name}
                            </option>
                        ))}
                    </select>
                    <InputError message={form.errors.plot_owner_id} />
                    <Label htmlFor="plot-owner-percentage" className="mt-4">
                        {t('Owner share snapshot (%)')}
                    </Label>
                    <Input
                        id="plot-owner-percentage"
                        inputMode="decimal"
                        value={form.data.owner_percentage}
                        onChange={(event) =>
                            form.setData('owner_percentage', event.target.value)
                        }
                        className="mt-2"
                    />
                    <p className="mt-1 text-xs text-muted-foreground">
                        {t('Leave empty to copy the selected owner default.')}
                    </p>
                    <InputError message={form.errors.owner_percentage} />
                </div>
                <div className="sm:col-span-2">
                    <InputError
                        message={
                            (form.errors as Record<string, string>).society
                        }
                    />
                    <div className="flex gap-2">
                        <Button disabled={form.processing}>
                            {form.processing ? 'Saving…' : 'Save plot'}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={form.processing}
                            onClick={() => {
                                if (
                                    !form.isDirty ||
                                    window.confirm(
                                        'Discard your unsaved changes?',
                                    )
                                )
                                    close();
                            }}
                        >
                            Cancel
                        </Button>
                    </div>
                </div>
            </form>
        </section>
    );
}

export default function Inventory({
    owners,
    society,
    blocks,
    sizes,
    filters,
    plots,
    summary,
    history,
}: Props) {
    const t = useTranslate();
    const { auth } = usePage().props;
    const manage = auth.abilities.includes('inventory.manage');
    const configure = auth.abilities.includes('configuration.manage');
    const [editor, setEditor] = useState<{ plot: Plot | null } | null>(null);
    const [plotDirty, setPlotDirty] = useState(false);
    const [search, setSearch] = useState({
        owner_id: String(filters.owner_id ?? ''),
        q: filters.q ?? '',
        block_id: String(filters.block_id ?? ''),
        size_id: String(filters.size_id ?? ''),
        hold: filters.hold ?? '',
        sort: filters.sort ?? 'number',
    });
    const visit = (values: InventoryFilters) =>
        router.get(
            '/inventory',
            Object.fromEntries(
                Object.entries(values).filter(
                    ([, value]) => value !== '' && value != null,
                ),
            ),
        );
    const grid = filters.view === 'grid';
    return (
        <>
            <Head title={t('Plot inventory')} />
            <div className="ef-page">
                <header className="relative flex flex-wrap items-start justify-between gap-5 overflow-hidden rounded-2xl border bg-linear-to-br from-card via-card to-accent/35 p-5 shadow-[0_8px_30px_rgba(29,39,32,0.04)] sm:p-6">
                    <div>
                        <p className="mb-2 text-xs font-semibold tracking-widest text-muted-foreground uppercase">
                            {society.name ?? 'Your society'}
                        </p>
                        <h1 className="text-3xl font-semibold tracking-tight">
                            {t('Plot inventory')}
                        </h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            {society.office_name ??
                                t('Catalogue and block overview')}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {auth.abilities.includes('inventory.export') && (
                            <a
                                href={`/inventory/export?${new URLSearchParams(
                                    Object.entries(filters)
                                        .filter(
                                            ([key, value]) =>
                                                key !== 'page' && value != null,
                                        )
                                        .map(([key, value]) => [
                                            key,
                                            String(value),
                                        ]),
                                )}`}
                                className="inline-flex h-10 items-center rounded-xl border bg-background/90 px-4 text-sm font-semibold transition hover:border-primary/35 hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                {t('Export filtered CSV')}
                            </a>
                        )}
                        {configure && (
                            <Link
                                href="/inventory/setup"
                                className="inline-flex h-10 items-center gap-2 rounded-xl border bg-background/90 px-4 text-sm font-semibold transition hover:border-primary/35 hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                <Settings2 className="size-4" />
                                {t('Society setup')}
                            </Link>
                        )}
                        {manage && (
                            <Button
                                disabled={
                                    !!editor ||
                                    !society.name ||
                                    !blocks.length ||
                                    !sizes.length
                                }
                                onClick={() => setEditor({ plot: null })}
                            >
                                <Plus className="size-4" />
                                {t('Add plot')}
                            </Button>
                        )}
                    </div>
                </header>
                <div className="grid gap-4 sm:grid-cols-3">
                    {Object.entries(summary).map(([label, count]) => (
                        <StatCard
                            key={label}
                            title={
                                label === 'sizes'
                                    ? t('Plot sizes')
                                    : label === 'plots'
                                      ? t('Total plots')
                                      : t('Society blocks')
                            }
                            value={count}
                            detail={
                                label === 'plots'
                                    ? t('Search and manage your inventory')
                                    : label === 'sizes'
                                      ? t('Custom sizes in marla')
                                      : t('Configured for your society')
                            }
                            icon={Building2}
                        />
                    ))}
                </div>
                {!society.name && (
                    <div className="rounded-xl border border-blue-200 bg-blue-50 p-5 text-sm text-blue-950 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-100">
                        Configure your society profile, blocks and sizes to
                        begin.
                        {configure && (
                            <Link
                                href="/inventory/setup"
                                className="ms-2 font-semibold underline"
                            >
                                Start society setup
                            </Link>
                        )}
                    </div>
                )}
                <p className="text-xs text-muted-foreground">
                    {t(
                        'Manual holds remain until an authorized admin releases them. “No active hold” does not mean an allotment or sale has been approved.',
                    )}
                </p>
                {editor && (
                    <Dialog
                        open
                        onOpenChange={(open) => {
                            if (
                                !open &&
                                (!plotDirty ||
                                    window.confirm(
                                        'Close plot form? Unsaved edits will be discarded.',
                                    ))
                            )
                                setEditor(null);
                        }}
                    >
                        <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                            <DialogHeader>
                                <DialogTitle>
                                    {editor.plot ? 'Edit plot' : 'Add a plot'}
                                </DialogTitle>
                                <DialogDescription>
                                    Update catalogue information for your
                                    society.
                                </DialogDescription>
                            </DialogHeader>
                            <PlotEditor
                                owners={owners}
                                onDirtyChange={setPlotDirty}
                                key={editor.plot?.id ?? 'new'}
                                plot={editor.plot}
                                blocks={blocks}
                                sizes={sizes}
                                close={() => setEditor(null)}
                            />
                        </DialogContent>
                    </Dialog>
                )}
                <section
                    className="ef-panel overflow-hidden"
                    aria-label={t('Inventory catalogue')}
                >
                    <div className="space-y-4 border-b bg-muted/20 p-4 sm:p-5">
                        <form
                            className="flex flex-wrap gap-3"
                            onSubmit={(event) => {
                                event.preventDefault();
                                visit({ ...search, view: filters.view });
                            }}
                        >
                            <div className="min-w-40 flex-1">
                                <Label
                                    htmlFor="inventory-search"
                                    className="sr-only"
                                >
                                    {t('Search plot number')}
                                </Label>
                                <Input
                                    id="inventory-search"
                                    placeholder={t('Search plot number')}
                                    value={search.q}
                                    onChange={(event) =>
                                        setSearch({
                                            ...search,
                                            q: event.target.value,
                                        })
                                    }
                                    maxLength={64}
                                />
                            </div>
                            <select
                                aria-label={t('Filter by block')}
                                className={selectClass}
                                value={search.block_id}
                                onChange={(event) =>
                                    setSearch({
                                        ...search,
                                        block_id: event.target.value,
                                    })
                                }
                            >
                                <option value="">{t('All blocks')}</option>
                                {blocks.map((block) => (
                                    <option key={block.id} value={block.id}>
                                        {block.code}
                                    </option>
                                ))}
                            </select>
                            <select
                                aria-label={t('Filter by size')}
                                className={selectClass}
                                value={search.size_id}
                                onChange={(event) =>
                                    setSearch({
                                        ...search,
                                        size_id: event.target.value,
                                    })
                                }
                            >
                                <option value="">{t('All sizes')}</option>
                                {sizes.map((size) => (
                                    <option key={size.id} value={size.id}>
                                        {size.label}
                                    </option>
                                ))}
                            </select>
                            <select
                                aria-label={t('Sort plots')}
                                className={selectClass}
                                value={search.sort}
                                onChange={(event) =>
                                    setSearch({
                                        ...search,
                                        sort: event.target.value,
                                    })
                                }
                            >
                                <option value="number">
                                    {t('Plot number A–Z')}
                                </option>
                                <option value="number_desc">
                                    {t('Plot number Z–A')}
                                </option>
                                <option value="newest">
                                    {t('Newest first')}
                                </option>
                                <option value="price">
                                    {t('Price low to high')}
                                </option>
                                <option value="price_desc">
                                    {t('Price high to low')}
                                </option>
                            </select>
                            <select
                                aria-label={t('Filter by hold state')}
                                className={selectClass}
                                value={search.hold}
                                onChange={(event) =>
                                    setSearch({
                                        ...search,
                                        hold: event.target.value,
                                    })
                                }
                            >
                                <option value="">{t('All hold states')}</option>
                                <option value="none">
                                    {t('No active hold or block')}
                                </option>
                                <option value="reservation">{t('Held')}</option>
                                <option value="block">{t('Blocked')}</option>
                                <option value="booked">{t('Booked')}</option>
                            </select>
                            <select
                                aria-label={t('Filter by plot owner')}
                                className={selectClass}
                                value={search.owner_id}
                                onChange={(event) =>
                                    setSearch({
                                        ...search,
                                        owner_id: event.target.value,
                                    })
                                }
                            >
                                <option value="">{t('All owners')}</option>
                                <option value="0">
                                    {t('Unassigned plots')}
                                </option>
                                {owners.map((owner) => (
                                    <option key={owner.id} value={owner.id}>
                                        {owner.name}
                                    </option>
                                ))}
                            </select>
                            <Button variant="outline">
                                {t('Apply filters')}
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() => visit({ view: filters.view })}
                            >
                                {t('Clear')}
                            </Button>
                        </form>
                        <div className="flex items-center justify-between gap-3">
                            <p className="text-xs text-muted-foreground">
                                {plots.total} {t('matching plots')}
                            </p>
                            <div className="flex gap-1">
                                <Button
                                    type="button"
                                    variant={!grid ? 'secondary' : 'ghost'}
                                    size="sm"
                                    aria-label={t('Table view')}
                                    aria-pressed={!grid}
                                    onClick={() =>
                                        visit({ ...filters, view: 'table' })
                                    }
                                >
                                    <List className="size-4" />
                                </Button>
                                <Button
                                    type="button"
                                    variant={grid ? 'secondary' : 'ghost'}
                                    size="sm"
                                    aria-label={t('Block grid view')}
                                    aria-pressed={grid}
                                    onClick={() =>
                                        visit({ ...filters, view: 'grid' })
                                    }
                                >
                                    <Grid2X2 className="size-4" />
                                </Button>
                            </div>
                        </div>
                    </div>
                    {!plots.data.length ? (
                        <div className="p-12 text-center">
                            <Building2 className="mx-auto mb-4 size-8 text-muted-foreground" />
                            <h2 className="font-semibold">
                                {t('No plots to show')}
                            </h2>
                            <p className="mt-2 text-sm text-muted-foreground">
                                {summary.plots
                                    ? t(
                                          'Try a different search or clear the filters.',
                                      )
                                    : t(
                                          'Add your first plot after configuring blocks and sizes.',
                                      )}
                            </p>
                        </div>
                    ) : grid ? (
                        <>
                            <p className="px-5 pt-5 text-xs text-muted-foreground">
                                {t(
                                    'Schematic grid of this page’s plots; not a cadastral or GIS map.',
                                )}
                            </p>
                            <div className="grid grid-cols-2 gap-3 p-5 sm:grid-cols-3 xl:grid-cols-6">
                                {plots.data.map((plot) => (
                                    <article
                                        key={plot.id}
                                        className="rounded-xl border bg-card p-4 shadow-xs transition hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-md"
                                    >
                                        <p className="text-xs text-muted-foreground">
                                            Block {plot.block.code}
                                        </p>
                                        <h2 className="mt-2 font-semibold break-words">
                                            {plot.number}
                                        </h2>
                                        <p className="mt-2 text-xs text-muted-foreground">
                                            {plot.owner?.name ??
                                                t('Unassigned owner')}
                                        </p>
                                        <p className="mt-2 text-xs text-muted-foreground">
                                            {plot.size.label}
                                        </p>
                                        <p className="mt-2 text-xs break-words">
                                            {formatPkr(plot.list_price)}
                                        </p>
                                        <Link
                                            className="mt-3 block text-sm underline"
                                            href={`/inventory/plots/${plot.id}/hold`}
                                        >
                                            <HoldBadge
                                                kind={
                                                    plot.active_booking
                                                        ? 'booked'
                                                        : plot.active_hold?.kind
                                                }
                                            />
                                            <span className="mt-2 block text-xs">
                                                {t('View hold')} →
                                            </span>
                                        </Link>
                                        {manage && (
                                            <Button
                                                variant="ghost"
                                                className="mt-3 px-0"
                                                size="sm"
                                                disabled={!!editor}
                                                onClick={() =>
                                                    setEditor({ plot })
                                                }
                                            >
                                                {t('Edit')}
                                            </Button>
                                        )}
                                    </article>
                                ))}
                            </div>
                        </>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="ef-table">
                                <caption className="sr-only">
                                    Configured plots with block, size and
                                    optional catalogue price
                                </caption>
                                <thead className="border-b bg-muted/40">
                                    <tr>
                                        {[
                                            'Plot number',
                                            'Block',
                                            'Size',
                                            'List price',
                                            'Owner',
                                            'Hold',
                                            ...(manage ? ['Actions'] : []),
                                        ].map((label) => (
                                            <th
                                                key={label}
                                                className="p-4 whitespace-nowrap"
                                            >
                                                {t(label)}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {plots.data.map((plot) => (
                                        <tr
                                            key={plot.id}
                                            className="border-b last:border-0"
                                        >
                                            <td className="p-4 font-medium">
                                                {plot.number}
                                            </td>
                                            <td className="p-4">
                                                {plot.block.code}
                                            </td>
                                            <td className="p-4 whitespace-nowrap">
                                                {plot.size.label}
                                            </td>
                                            <td className="p-4 whitespace-nowrap">
                                                {formatPkr(plot.list_price)}
                                            </td>
                                            <td className="px-5 py-4">
                                                {plot.owner?.name ??
                                                    t('Unassigned owner')}
                                            </td>
                                            <td className="px-5 py-4">
                                                <Link
                                                    className="underline"
                                                    href={`/inventory/plots/${plot.id}/hold`}
                                                    aria-label={`View hold for ${plot.number}`}
                                                >
                                                    <HoldBadge
                                                        kind={
                                                            plot.active_booking
                                                                ? 'booked'
                                                                : plot
                                                                      .active_hold
                                                                      ?.kind
                                                        }
                                                    />
                                                </Link>
                                            </td>
                                            {manage && (
                                                <td className="p-4">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        disabled={!!editor}
                                                        onClick={() =>
                                                            setEditor({ plot })
                                                        }
                                                        aria-label={`Edit plot ${plot.number}`}
                                                    >
                                                        Edit
                                                    </Button>
                                                </td>
                                            )}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                    <nav
                        className="flex items-center justify-between border-t p-4 text-sm"
                        aria-label="Inventory pages"
                    >
                        {plots.prev_page_url ? (
                            <Link href={plots.prev_page_url}>Previous</Link>
                        ) : (
                            <span />
                        )}
                        <span className="text-xs text-muted-foreground">
                            Page {plots.current_page} of {plots.last_page}
                        </span>
                        {plots.next_page_url ? (
                            <Link href={plots.next_page_url}>Next</Link>
                        ) : (
                            <span />
                        )}
                    </nav>
                </section>
                <section className="rounded-xl border bg-card p-5">
                    <h2 className="font-semibold">Recent catalogue activity</h2>
                    {history.length ? (
                        <ul className="mt-3 divide-y">
                            {history.map((event) => (
                                <li
                                    key={event.id}
                                    className="flex flex-wrap justify-between gap-2 py-3 text-sm"
                                >
                                    <span>
                                        {event.event
                                            .replaceAll('_', ' ')
                                            .replace('.', ' ')}{' '}
                                        · #{event.subject_id} · Staff #
                                        {event.actor_id}
                                    </span>
                                    <time
                                        className="text-xs text-muted-foreground"
                                        dateTime={event.created_at}
                                    >
                                        {new Date(
                                            event.created_at,
                                        ).toLocaleString('en-PK', {
                                            timeZone: 'Asia/Karachi',
                                        })}
                                    </time>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p className="mt-3 text-sm text-muted-foreground">
                            No catalogue changes recorded yet.
                        </p>
                    )}
                </section>
            </div>
        </>
    );
}
Inventory.layout = {
    breadcrumbs: [{ title: 'Plot inventory', href: '/inventory' }],
};
