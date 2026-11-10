import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { ArrowLeft, Building2, Pencil, Plus } from 'lucide-react';
import InputError from '@/components/input-error';
import { PlotOwnerSetup } from '@/components/plot-owner-setup';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useUnsavedChanges } from '@/hooks/use-unsaved-changes';
import { useTranslate } from '@/lib/i18n';
import type { Block, PlotSize, SocietyProfile } from '@/types/inventory';

function SocietyForm({ society }: { society: SocietyProfile }) {
    const t = useTranslate();
    const form = useForm({
        name: society.name ?? '',
        office_name: society.office_name ?? '',
        phone: society.phone ?? '',
        email: society.email ?? '',
        address: society.address ?? '',
    });
    useUnsavedChanges(form.isDirty);
    return (
        <section
            className="rounded-xl border bg-card p-6"
            aria-labelledby="society-heading"
        >
            <div className="mb-6 flex items-start gap-3">
                <span className="rounded-lg bg-blue-50 p-2 text-blue-600 dark:bg-blue-950">
                    <Building2 className="size-5" />
                </span>
                <div>
                    <h2 id="society-heading" className="text-lg font-semibold">
                        {t('Society & office')}
                    </h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {t(
                            'One society per installation. Use your own names and contact details.',
                        )}
                    </p>
                </div>
            </div>
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    form.patch('/inventory/society', {
                        preserveScroll: true,
                        onSuccess: () => form.setDefaults(),
                    });
                }}
                className="grid gap-5 sm:grid-cols-2"
            >
                {(
                    [
                        'name',
                        'office_name',
                        'phone',
                        'email',
                        'address',
                    ] as const
                ).map((field) => (
                    <div
                        key={field}
                        className={field === 'address' ? 'sm:col-span-2' : ''}
                    >
                        <Label htmlFor={`society-${field}`}>
                            {t(
                                {
                                    name: 'Society name',
                                    office_name: 'Office name',
                                    phone: 'Office phone',
                                    email: 'Office email',
                                    address: 'Office address',
                                }[field],
                            )}
                            {field === 'name' ? ' *' : ''}
                        </Label>
                        <Input
                            id={`society-${field}`}
                            className="mt-2"
                            name={field}
                            type={field === 'email' ? 'email' : 'text'}
                            value={form.data[field]}
                            onChange={(event) =>
                                form.setData(field, event.target.value)
                            }
                            required={field === 'name'}
                            maxLength={
                                field === 'address'
                                    ? 500
                                    : field === 'phone'
                                      ? 40
                                      : field === 'office_name'
                                        ? 120
                                        : 160
                            }
                        />
                        <InputError
                            className="mt-1"
                            message={form.errors[field]}
                        />
                    </div>
                ))}
                <div className="sm:col-span-2">
                    <Button disabled={form.processing}>
                        {form.processing
                            ? t('Saving…')
                            : t('Save society profile')}
                    </Button>
                </div>
            </form>
        </section>
    );
}

function CatalogSection({
    kind,
    rows,
    configured,
}: {
    kind: 'blocks' | 'sizes';
    rows: (Block | PlotSize)[];
    configured: boolean;
}) {
    const t = useTranslate();
    const isBlock = kind === 'blocks';
    const [editing, setEditing] = useState<number | null>(null);
    const empty: Record<string, string> = isBlock
        ? { code: '', name: '' }
        : { label: '', area_marla: '' };
    const form = useForm<Record<string, string>>(empty);
    useUnsavedChanges(form.isDirty);
    const choose = (row: Block | PlotSize | null) => {
        if (form.isDirty && !window.confirm('Discard your unsaved changes?'))
            return;
        const data = row
            ? 'code' in row
                ? { code: row.code, name: row.name }
                : { label: row.label, area_marla: row.area_marla }
            : empty;
        setEditing(row?.id ?? null);
        form.setDefaults(data);
        form.setData(data);
        form.clearErrors();
    };
    const fields = isBlock
        ? [
              { key: 'code', label: 'Block code', max: 32 },
              { key: 'name', label: 'Block name', max: 120 },
          ]
        : [
              { key: 'label', label: 'Size label', max: 80 },
              { key: 'area_marla', label: 'Area in marla', max: 9 },
          ];
    return (
        <section
            className="rounded-xl border bg-card p-6"
            aria-labelledby={`${kind}-heading`}
        >
            <h2 id={`${kind}-heading`} className="text-lg font-semibold">
                {isBlock ? t('Blocks') : t('Plot sizes')}
            </h2>
            <p className="mt-1 text-sm text-muted-foreground">
                {isBlock
                    ? t('Choose codes and names that match your society.')
                    : t(
                          'Define any positive size, including fractional marla.',
                      )}
            </p>
            <ul className="mt-5 max-h-64 divide-y overflow-y-auto">
                {rows.length ? (
                    rows.map((row) => (
                        <li
                            key={row.id}
                            className="flex items-center justify-between gap-3 py-3 text-sm"
                        >
                            <div>
                                {'code' in row ? (
                                    <>
                                        <span className="font-medium">
                                            {row.code}
                                        </span>{' '}
                                        · {row.name}
                                    </>
                                ) : (
                                    <>
                                        <span className="font-medium">
                                            {row.label}
                                        </span>{' '}
                                        · {row.area_marla} marla
                                    </>
                                )}
                            </div>
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => choose(row)}
                                aria-label={`Edit ${'code' in row ? row.code : row.label}`}
                            >
                                <Pencil className="size-3.5" />
                                {t('Edit')}
                            </Button>
                        </li>
                    ))
                ) : (
                    <li className="py-4 text-sm text-muted-foreground">
                        {t(
                            isBlock
                                ? 'No blocks configured yet.'
                                : 'No plot sizes configured yet.',
                        )}
                    </li>
                )}
            </ul>
            <form
                className="mt-5 space-y-4 border-t pt-5"
                onSubmit={(event) => {
                    event.preventDefault();
                    const options = {
                        preserveScroll: true,
                        onSuccess: () => {
                            if (editing) form.setDefaults();
                            else form.reset();
                        },
                    };
                    if (editing)
                        form.patch(`/inventory/${kind}/${editing}`, options);
                    else form.post(`/inventory/${kind}`, options);
                }}
            >
                <p className="text-sm font-medium">
                    {editing
                        ? t('Edit selected entry')
                        : t(isBlock ? 'Add a block' : 'Add a plot size')}
                </p>
                {fields.map((field) => (
                    <div key={field.key}>
                        <Label htmlFor={`${kind}-${field.key}`}>
                            {t(field.label)} *
                        </Label>
                        <Input
                            className="mt-2"
                            id={`${kind}-${field.key}`}
                            value={form.data[field.key] ?? ''}
                            onChange={(event) =>
                                form.setData(field.key, event.target.value)
                            }
                            inputMode={
                                field.key === 'area_marla'
                                    ? 'decimal'
                                    : undefined
                            }
                            required
                            maxLength={field.max}
                            disabled={!configured}
                        />
                        <InputError message={form.errors[field.key]} />
                    </div>
                ))}
                <InputError message={form.errors.society} />
                <div className="flex gap-2">
                    <Button disabled={form.processing || !configured}>
                        {editing ? (
                            t('Save changes')
                        ) : (
                            <>
                                <Plus className="size-4" />
                                {isBlock ? t('Add block') : t('Add size')}
                            </>
                        )}
                    </Button>
                    {editing && (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => choose(null)}
                        >
                            {t('New entry')}
                        </Button>
                    )}
                </div>
                {!configured && (
                    <p className="text-xs text-muted-foreground">
                        {t('Save your society profile first.')}
                    </p>
                )}
            </form>
        </section>
    );
}

export default function InventorySetup({
    owners,
    admins,
    society,
    blocks,
    sizes,
}: {
    owners: import('@/types/inventory').PlotOwner[];
    admins: { id: number; name: string }[];
    society: SocietyProfile;
    blocks: Block[];
    sizes: PlotSize[];
}) {
    const t = useTranslate();
    return (
        <>
            <Head title={t('Society setup')} />
            <div className="mx-auto w-full max-w-6xl space-y-6 p-5 md:p-8">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p className="mb-2 text-xs font-semibold tracking-widest text-muted-foreground uppercase">
                            {t('Configuration')}
                        </p>
                        <h1 className="text-3xl font-semibold tracking-tight">
                            {t('Society setup')}
                        </h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            {t(
                                'Make EstateFlow fit your society, without fixed blocks or sizes.',
                            )}
                        </p>
                    </div>
                    <Link
                        href="/inventory"
                        className="inline-flex items-center gap-2 text-sm font-medium"
                    >
                        <ArrowLeft className="size-4" />
                        {t('Plot inventory')}
                    </Link>
                </header>
                <SocietyForm society={society} />
                <PlotOwnerSetup owners={owners} admins={admins} />
                <div className="grid gap-6 lg:grid-cols-2">
                    <CatalogSection
                        kind="blocks"
                        rows={blocks}
                        configured={!!society.name}
                    />
                    <CatalogSection
                        kind="sizes"
                        rows={sizes}
                        configured={!!society.name}
                    />
                </div>
            </div>
        </>
    );
}
InventorySetup.layout = {
    breadcrumbs: [{ title: 'Society setup', href: '/inventory/setup' }],
};
