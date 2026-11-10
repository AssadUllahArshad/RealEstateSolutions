import { Head, Link, useForm, usePage } from '@inertiajs/react';
import {
    Check,
    ChevronRight,
    Save,
    Settings2,
    ShieldCheck,
} from 'lucide-react';
import { PageHeading, Pager, StatusBadge } from '@/components/office-ui';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useUnsavedChanges } from '@/hooks/use-unsaved-changes';
import { useTranslate } from '@/lib/i18n';

type Value = string | number | string[] | null;
type Field = {
    label: string;
    type:
        | 'select'
        | 'multi'
        | 'integer'
        | 'percentage'
        | 'money'
        | 'decimal'
        | 'prefix'
        | 'counter'
        | 'width'
        | 'date';
    help: string;
    options?: Record<string, string>;
};
type Definition = {
    title: string;
    description: string;
    fields: Record<string, Field>;
};
type PolicyEvent = {
    id: number;
    version: number;
    actor_id: number;
    before: Record<string, Value> | null;
    after: Record<string, Value>;
    created_at: string;
};
type Props = {
    section: string;
    definition: Definition;
    sections: {
        key: string;
        title: string;
        saved: boolean;
        missing: string[];
    }[];
    values: Record<string, Value>;
    version: number;
    missing: string[];
    history: {
        data: PolicyEvent[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
};

function displayValue(value: Value | undefined, field?: Field) {
    if (value === null || value === undefined || value === '') return 'Not set';
    if (Array.isArray(value))
        return (
            value.map((v) => field?.options?.[v] ?? v).join(', ') ||
            'None selected'
        );
    return field?.options?.[String(value)] ?? String(value);
}

function PolicyForm({ section, definition, values, version, missing }: Props) {
    const initial = Object.fromEntries(
        Object.entries(values).map(([key, value]) => [
            key,
            Array.isArray(value) ? value : String(value ?? ''),
        ]),
    );
    const form = useForm({
        values: initial as Record<string, string | string[]>,
        version,
    });
    useUnsavedChanges(form.isDirty);
    const errors: Record<string, string | undefined> = form.errors;
    function change(name: string, value: string | string[]) {
        const next = { ...form.data.values, [name]: value };
        if (
            section === 'charges' &&
            name === 'late_fee_mode' &&
            ['disabled', 'manual'].includes(String(value))
        ) {
            for (const key of [
                'late_fee_value',
                'grace_days',
                'basis',
                'frequency',
                'cap_mode',
                'cap_value',
            ])
                next[key] = '';
        }
        if (section === 'charges' && name === 'cap_mode' && value === 'none')
            next.cap_value = '';
        if (section === 'commissions' && name === 'type' && value === 'fixed')
            next.basis = '';
        form.setData('values', next);
    }
    return (
        <form
            className="ef-panel overflow-hidden"
            onSubmit={(event) => {
                event.preventDefault();
                form.patch(`/business-settings/${section}`, {
                    preserveScroll: true,
                    onSuccess: () => form.setDefaults(),
                });
            }}
        >
            <div className="border-b p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="flex items-center gap-3">
                        <span className="rounded-xl bg-blue-50 p-3 text-blue-600">
                            <Settings2 className="size-5" />
                        </span>
                        <div>
                            <h2 className="text-lg font-bold">
                                {definition.title}
                            </h2>
                            <p className="mt-1 text-xs text-muted-foreground">
                                {version
                                    ? `Saved version ${version}`
                                    : 'Not configured yet'}
                            </p>
                        </div>
                    </div>
                    <StatusBadge
                        tone={missing.length ? 'warning' : 'success'}
                        label={
                            missing.length
                                ? `${missing.length} fields to complete`
                                : 'Fields complete'
                        }
                    />
                </div>
                <p className="mt-4 text-sm leading-6 text-muted-foreground">
                    {definition.description}
                </p>
            </div>
            <fieldset
                disabled={form.processing}
                className="min-w-0 space-y-6 p-6"
            >
                {Object.entries(definition.fields).map(([name, field]) => {
                    if (
                        section === 'charges' &&
                        name !== 'late_fee_mode' &&
                        ['disabled', 'manual'].includes(
                            String(form.data.values.late_fee_mode),
                        )
                    )
                        return null;
                    const value = form.data.values[name];
                    const id = `policy-${name}`;
                    return field.type === 'multi' ? (
                        <fieldset key={name}>
                            <legend className="text-sm font-semibold">
                                {field.label}
                            </legend>
                            <div className="mt-3 grid gap-3 sm:grid-cols-2">
                                {Object.entries(field.options ?? {}).map(
                                    ([option, label]) => (
                                        <label
                                            key={option}
                                            className="flex min-h-12 cursor-pointer items-center gap-3 rounded-xl border bg-background p-3 text-sm transition hover:border-primary/30 has-checked:border-primary/40 has-checked:bg-primary/8"
                                        >
                                            <input
                                                type="checkbox"
                                                className="size-4 accent-blue-600"
                                                checked={
                                                    Array.isArray(value) &&
                                                    value.includes(option)
                                                }
                                                onChange={(event) => {
                                                    const selected =
                                                        Array.isArray(value)
                                                            ? value
                                                            : [];
                                                    change(
                                                        name,
                                                        event.target.checked
                                                            ? [
                                                                  ...selected,
                                                                  option,
                                                              ]
                                                            : selected.filter(
                                                                  (v) =>
                                                                      v !==
                                                                      option,
                                                              ),
                                                    );
                                                }}
                                            />
                                            {label}
                                        </label>
                                    ),
                                )}
                            </div>
                            <InputError message={errors[`values.${name}`]} />
                        </fieldset>
                    ) : (
                        <div key={name}>
                            <Label htmlFor={id}>{field.label}</Label>
                            {field.type === 'select' ? (
                                <select
                                    id={id}
                                    className="mt-2 w-full"
                                    value={String(value)}
                                    onChange={(event) =>
                                        change(name, event.target.value)
                                    }
                                >
                                    <option value="">Choose a rule…</option>
                                    {Object.entries(field.options ?? {}).map(
                                        ([option, label]) => (
                                            <option key={option} value={option}>
                                                {label}
                                            </option>
                                        ),
                                    )}
                                </select>
                            ) : (
                                <Input
                                    id={id}
                                    className="mt-2"
                                    type={
                                        field.type === 'date' ? 'date' : 'text'
                                    }
                                    inputMode={
                                        [
                                            'integer',
                                            'counter',
                                            'width',
                                        ].includes(field.type)
                                            ? 'numeric'
                                            : field.type === 'prefix' ||
                                                field.type === 'date'
                                              ? 'text'
                                              : 'decimal'
                                    }
                                    value={String(value)}
                                    onChange={(event) =>
                                        change(name, event.target.value)
                                    }
                                    placeholder="Not set"
                                    maxLength={32}
                                />
                            )}
                            {field.help && (
                                <p className="mt-2 text-xs leading-5 text-muted-foreground">
                                    {field.help}
                                </p>
                            )}
                            <InputError message={errors[`values.${name}`]} />
                        </div>
                    );
                })}
                <div role="alert">
                    {Object.entries(errors)
                        .filter(([key]) => !key.startsWith('values.'))
                        .map(([key, message]) => (
                            <InputError key={key} message={message} />
                        ))}
                </div>
            </fieldset>
            <div className="flex flex-wrap items-center justify-between gap-4 border-t bg-muted/20 px-6 py-5">
                <p className="max-w-sm text-xs leading-5 text-muted-foreground">
                    You can save an incomplete setup. Unset rules stay
                    unconfigured; no amounts or dates are inferred.
                </p>
                <Button disabled={form.processing}>
                    <Save className="size-4" />
                    {form.processing ? 'Saving…' : 'Save settings'}
                </Button>
            </div>
        </form>
    );
}

function PolicyHistory({
    history,
    definition,
}: Pick<Props, 'history' | 'definition'>) {
    return (
        <section
            className="ef-panel overflow-hidden"
            aria-label="Settings history"
        >
            <div className="border-b p-5">
                <h2 className="font-semibold">Settings history</h2>
                <p className="mt-1 text-xs text-muted-foreground">
                    Every saved change retains its version, staff reference and
                    previous values.
                </p>
            </div>
            {history.data.length ? (
                <div className="divide-y">
                    {history.data.map((event) => (
                        <details className="px-5 py-4" key={event.id}>
                            <summary className="cursor-pointer text-sm font-medium">
                                Version {event.version}
                                <span className="ms-3 text-xs font-normal text-muted-foreground">
                                    Staff #{event.actor_id} ·{' '}
                                    {event.created_at
                                        .replace('T', ' ')
                                        .slice(0, 19)}{' '}
                                    UTC
                                </span>
                            </summary>
                            <dl className="mt-4 space-y-4">
                                {Object.entries(event.after)
                                    .filter(
                                        ([key, value]) =>
                                            JSON.stringify(
                                                event.before?.[key],
                                            ) !== JSON.stringify(value),
                                    )
                                    .map(([key, value]) => (
                                        <div
                                            className="grid gap-1 rounded-lg bg-muted/40 p-3 text-xs"
                                            key={key}
                                        >
                                            <dt className="font-semibold">
                                                {definition.fields[key]
                                                    ?.label ?? key}
                                            </dt>
                                            <dd className="leading-5 text-muted-foreground">
                                                Before:{' '}
                                                {displayValue(
                                                    event.before?.[key],
                                                    definition.fields[key],
                                                )}
                                            </dd>
                                            <dd className="leading-5 break-words">
                                                After:{' '}
                                                {displayValue(
                                                    value,
                                                    definition.fields[key],
                                                )}
                                            </dd>
                                        </div>
                                    ))}
                            </dl>
                        </details>
                    ))}
                </div>
            ) : (
                <p className="p-6 text-sm text-muted-foreground">
                    No settings have been saved for this section yet.
                </p>
            )}
            <Pager {...history} />
        </section>
    );
}

export default function BusinessSettings(props: Props) {
    const t = useTranslate();
    const { errors } = usePage().props;
    return (
        <>
            <Head title={t('Business settings')} />
            <div className="ef-page">
                <PageHeading
                    eyebrow={t('Main administration')}
                    title={t('Business settings')}
                    description={t(
                        "One place to configure your office's booking, collection, owner and commission rules.",
                    )}
                />
                <div className="flex gap-3 rounded-xl border border-blue-100 bg-blue-50 p-4 text-sm leading-6 text-blue-900">
                    <ShieldCheck className="mt-1 size-5 shrink-0" />
                    <p>
                        {t(
                            'Only the main admin can edit these rules. Saving settings does not post payments, levy fees or create bookings.',
                        )}
                    </p>
                </div>
                <div className="grid items-start gap-6 lg:grid-cols-[240px_minmax(0,1fr)]">
                    <nav
                        className="ef-panel flex gap-1 overflow-x-auto p-2 lg:block"
                        aria-label={t('Business settings sections')}
                    >
                        {props.sections.map((section) => (
                            <Link
                                key={section.key}
                                href={`/business-settings/${section.key}`}
                                className={`flex min-w-48 items-center justify-between gap-3 rounded-xl px-4 py-3 text-sm lg:min-w-0 ${props.section === section.key ? 'bg-primary/10 font-semibold text-primary' : 'text-muted-foreground hover:bg-muted/60'}`}
                                aria-current={
                                    props.section === section.key
                                        ? 'page'
                                        : undefined
                                }
                            >
                                <span>
                                    {t(section.title)}
                                    <span className="mt-1 block text-[10px] font-normal">
                                        {!section.saved
                                            ? t('Not saved')
                                            : section.missing.length
                                              ? t('Setup incomplete')
                                              : t('Fields complete')}
                                    </span>
                                </span>
                                {section.saved && !section.missing.length ? (
                                    <Check className="size-4 shrink-0 text-emerald-600" />
                                ) : (
                                    <ChevronRight className="size-4 shrink-0" />
                                )}
                            </Link>
                        ))}
                        <Link
                            className="m-2 hidden border-t px-2 pt-4 pb-2 text-xs text-primary lg:block"
                            href="/inventory/setup"
                        >
                            {t('Society, blocks & plot sizes')} →
                        </Link>
                    </nav>
                    <div className="min-w-0 space-y-6">
                        {errors.conflict && (
                            <div
                                role="alert"
                                className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"
                            >
                                {errors.conflict}
                                <Link
                                    className="ms-2 underline"
                                    href={`/business-settings/${props.section}`}
                                >
                                    {t('Reload section')}
                                </Link>
                            </div>
                        )}
                        <PolicyForm
                            key={`${props.section}-${props.version}`}
                            {...props}
                        />
                        <PolicyHistory
                            history={props.history}
                            definition={props.definition}
                        />
                    </div>
                </div>
            </div>
        </>
    );
}
BusinessSettings.layout = {
    breadcrumbs: [{ title: 'Business settings', href: '/business-settings' }],
};
