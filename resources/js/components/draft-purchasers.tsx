import { Link, useForm } from '@inertiajs/react';
import { Plus, Save, Trash2, Users } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useUnsavedChanges } from '@/hooks/use-unsaved-changes';
import { useTranslate } from '@/lib/i18n';

export type Purchaser = {
    customer_id: number | null;
    name: string;
    share: string;
    identity_complete: boolean;
};

export default function DraftPurchasers({
    draftId,
    version,
    purchasers,
    customers,
    history,
}: {
    draftId: number;
    version: number;
    purchasers: Purchaser[];
    customers: { id: number; name: string }[];
    history: {
        version: number;
        actor_id: number;
        count: number;
        created_at: string;
    }[];
}) {
    const t = useTranslate();
    const form = useForm({
        version,
        purchasers: purchasers.map((row) => ({
            customer_id: String(row.customer_id ?? ''),
            share: row.share,
        })),
    });
    useUnsavedChanges(form.isDirty);
    const options = [...customers];
    for (const row of purchasers) {
        if (
            row.customer_id &&
            !options.some((option) => option.id === row.customer_id)
        )
            options.push({ id: row.customer_id, name: row.name });
    }
    const update = (
        index: number,
        field: 'customer_id' | 'share',
        value: string,
    ) =>
        form.setData(
            'purchasers',
            form.data.purchasers.map((row, position) =>
                position === index ? { ...row, [field]: value } : row,
            ),
        );
    // Fixed-point integers avoid float rounding and keep incomplete input visibly invalid.
    const valid = form.data.purchasers.every((row) =>
        /^\d{1,3}(?:\.\d{1,4})?$/.test(row.share),
    );
    const total = valid
        ? form.data.purchasers.reduce((sum, row) => {
              const [whole, fraction = ''] = row.share.split('.');
              return (
                  sum + Number(whole) * 10000 + Number(fraction.padEnd(4, '0'))
              );
          }, 0)
        : null;
    const totalLabel =
        total === null
            ? t('Check share values')
            : `${Math.floor(total / 10000)}.${String(total % 10000).padStart(4, '0')}%`;

    return (
        <section
            id="purchasers"
            className="ef-panel overflow-hidden"
            aria-labelledby="purchaser-title"
        >
            <div className="flex flex-wrap items-start justify-between gap-4 border-b p-5 sm:p-6">
                <div className="flex gap-3">
                    <span className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                        <Users className="size-5" />
                    </span>
                    <div>
                        <h2 id="purchaser-title" className="font-semibold">
                            {t('Proposed purchasers')}
                        </h2>
                        <p className="mt-1 max-w-xl text-sm leading-6 text-muted-foreground">
                            {t(
                                'Choose one purchaser or split ownership between joint purchasers. Shares must total exactly 100%.',
                            )}
                        </p>
                    </div>
                </div>
                <span className="rounded-full border px-3 py-1 text-xs text-muted-foreground">
                    {t('Purchaser version')} {version}
                </span>
            </div>
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    form.patch(`/sales/${draftId}/purchasers`, {
                        preserveScroll: true,
                        onSuccess: () => form.setDefaults(),
                    });
                }}
            >
                <fieldset
                    disabled={form.processing}
                    className="space-y-4 p-5 sm:p-6"
                >
                    {purchasers.some((row) => !row.identity_complete) && (
                        <p
                            role="status"
                            className="rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs leading-5 text-amber-900"
                        >
                            {t(
                                'A saved purchaser has missing identity details or changed customer access. Review the customer profile or select an accessible purchaser before saving.',
                            )}
                        </p>
                    )}
                    {form.data.purchasers.length === 0 && (
                        <div className="rounded-xl border border-dashed p-6 text-center">
                            <Users className="mx-auto mb-3 size-7 text-muted-foreground" />
                            <p className="text-sm font-medium">
                                {t('Who will purchase this plot?')}
                            </p>
                            <p className="mt-2 text-xs leading-5 text-muted-foreground">
                                {t(
                                    'The sales contact is separate from the proposed legal purchasers. Add each purchaser explicitly.',
                                )}
                            </p>
                        </div>
                    )}
                    {form.data.purchasers.map((row, index) => (
                        <div
                            key={index}
                            className="grid items-end gap-4 rounded-xl border bg-muted/20 p-4 sm:grid-cols-[1fr_160px_auto]"
                        >
                            <div className="min-w-0">
                                <Label htmlFor={`purchaser-${index}`}>
                                    {t('Purchaser')} {index + 1}
                                </Label>
                                <select
                                    id={`purchaser-${index}`}
                                    required
                                    className="mt-2 h-11 w-full rounded-lg border bg-background px-3 text-sm"
                                    value={row.customer_id}
                                    onChange={(event) =>
                                        update(
                                            index,
                                            'customer_id',
                                            event.target.value,
                                        )
                                    }
                                >
                                    <option value="">
                                        {t('Select customer')}
                                    </option>
                                    {options.map((customer) => (
                                        <option
                                            key={customer.id}
                                            value={customer.id}
                                        >
                                            {customer.name} (#{customer.id})
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <Label htmlFor={`share-${index}`}>
                                    {t('Ownership share (%)')}
                                </Label>
                                <Input
                                    id={`share-${index}`}
                                    className="mt-2"
                                    required
                                    inputMode="decimal"
                                    maxLength={8}
                                    value={row.share}
                                    onChange={(event) =>
                                        update(
                                            index,
                                            'share',
                                            event.target.value,
                                        )
                                    }
                                />
                            </div>
                            <Button
                                variant="outline"
                                type="button"
                                aria-label={`Remove purchaser ${index + 1}`}
                                onClick={() =>
                                    form.setData(
                                        'purchasers',
                                        form.data.purchasers.filter(
                                            (_, position) => position !== index,
                                        ),
                                    )
                                }
                            >
                                <Trash2 className="size-4" />
                                <span className="sm:sr-only">
                                    {t('Remove purchaser')}
                                </span>
                            </Button>
                        </div>
                    ))}
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <Button
                            type="button"
                            variant="outline"
                            disabled={form.data.purchasers.length >= 20}
                            onClick={() =>
                                form.setData('purchasers', [
                                    ...form.data.purchasers,
                                    { customer_id: '', share: '' },
                                ])
                            }
                        >
                            <Plus className="size-4" />
                            {t('Add purchaser')}
                        </Button>
                        <p
                            aria-live="polite"
                            className={`text-sm font-semibold ${total === 1000000 ? 'text-emerald-700' : 'text-amber-800'}`}
                        >
                            {t('Total')}: {totalLabel}
                        </p>
                    </div>
                    <div role="alert">
                        {Object.entries(form.errors).map(([key, message]) => (
                            <InputError key={key} message={message} />
                        ))}
                    </div>
                    <p className="text-xs leading-6 text-muted-foreground">
                        {t(
                            'Each purchaser needs a CNIC and address in their private customer profile.',
                        )}{' '}
                        <Link className="text-blue-600" href="/customers">
                            {t('Manage customers')}
                        </Link>
                        .{' '}
                        {t(
                            'Use the customer search above to find additional records. Changing this list does not transfer ownership or allocate a plot.',
                        )}
                    </p>
                    <div className="flex justify-end border-t pt-4">
                        <Button
                            disabled={
                                form.processing ||
                                form.data.purchasers.length === 0
                            }
                        >
                            <Save className="size-4" />
                            {form.processing
                                ? t('Saving…')
                                : t('Save purchasers')}
                        </Button>
                    </div>
                </fieldset>
            </form>
            {history.length > 0 && (
                <details className="border-t p-5">
                    <summary className="cursor-pointer text-sm font-semibold">
                        {t('Recent purchaser changes')}
                    </summary>
                    <ul className="mt-4 space-y-3 text-xs text-muted-foreground">
                        {history.map((event) => (
                            <li key={event.version}>
                                v{event.version} · {event.count} purchaser(s) ·
                                Staff #{event.actor_id} ·{' '}
                                {event.created_at
                                    .replace('T', ' ')
                                    .slice(0, 19)}{' '}
                                UTC
                            </li>
                        ))}
                    </ul>
                    <p className="mt-3 text-xs text-muted-foreground">
                        {t(
                            'Latest 10 revisions. Historical customer identities remain private.',
                        )}
                    </p>
                </details>
            )}
        </section>
    );
}
