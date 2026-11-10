import { Link, useForm } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useUnsavedChanges } from '@/hooks/use-unsaved-changes';
import { formatPkr } from '@/lib/format-pkr';
import { useTranslate } from '@/lib/i18n';

export type PostingSettings = {
    versions: { collections: number; receipt_posting: number };
    values: {
        collections: Record<string, string | number | string[] | null>;
        receipt_posting: Record<string, string | number | null>;
    };
    missing: { collections: string[]; receipt_posting: string[] };
};
export type PostingContext = {
    settings: PostingSettings;
    canPost: boolean;
    commandKey: string;
};

export default function ReceiptPostingPanel({
    receipt,
    posting,
    canConfigure,
    preparationDirty,
}: {
    receipt: { id: number; version: number; method: string; amount: string };
    posting: PostingContext;
    canConfigure: boolean;
    preparationDirty: boolean;
}) {
    const t = useTranslate();
    const cheque = receipt.method === 'cheque';
    const form = useForm({
        command_key: posting.commandKey,
        expected_version: receipt.version,
        posting_version: posting.settings.versions.receipt_posting,
        collection_version: posting.settings.versions.collections,
        business_date: '',
        note: '',
        confirmed: false,
        stage: cheque ? '' : 'confirmed',
    });
    useUnsavedChanges(form.isDirty);
    const missing = [
        ...posting.settings.missing.collections,
        ...posting.settings.missing.receipt_posting,
    ];
    const unallocated =
        posting.settings.values.receipt_posting.unallocated === 'allow' &&
        posting.settings.values.collections.overpayment === 'unallocated';
    const blocked = missing.length > 0 || !unallocated;
    return (
        <section
            className="ef-panel overflow-hidden"
            aria-labelledby="posting-heading"
        >
            <div className="border-b bg-blue-50/50 p-5 sm:p-6">
                <div className="flex gap-3">
                    <ShieldCheck className="mt-1 size-6 shrink-0 text-blue-600" />
                    <div>
                        <h2 id="posting-heading" className="font-semibold">
                            {t('Verify & issue receipt')}
                        </h2>
                        <p className="mt-2 text-sm leading-6 text-muted-foreground">
                            {t('Saved amount')}:{' '}
                            <strong>{formatPkr(receipt.amount)}</strong>. This
                            {t(
                                'This step freezes the preparation and assigns an official number. Recognition follows the saved payment rules.',
                            )}
                        </p>
                    </div>
                </div>
            </div>
            {blocked && (
                <div
                    role="status"
                    className="space-y-2 border-b bg-amber-50 p-5 text-sm leading-6 text-amber-900"
                >
                    <p className="font-semibold">
                        {t('Posting setup is incomplete or blocked')}
                    </p>
                    {missing.length > 0 && (
                        <p>
                            {t('Configure')}: {missing.join(', ')}.
                        </p>
                    )}
                    {!unallocated && (
                        <p>
                            {t(
                                'Unallocated customer credit must be explicitly allowed in Receiving payments and Receipt posting controls. Installment allocation is not available yet.',
                            )}
                        </p>
                    )}
                    {canConfigure && (
                        <Link
                            className="inline-block underline"
                            href="/business-settings/receipt_posting"
                        >
                            {t('Open receipt posting controls')}
                        </Link>
                    )}
                </div>
            )}
            {!posting.canPost ? (
                <p className="p-5 text-sm text-muted-foreground">
                    {t(
                        'An authorized accountant or admin must verify and issue this receipt.',
                    )}
                </p>
            ) : (
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(`/finance/receipts/${receipt.id}/issue`);
                    }}
                >
                    <fieldset
                        disabled={
                            form.processing || blocked || preparationDirty
                        }
                        className="space-y-5 p-5 sm:p-6"
                    >
                        {preparationDirty && (
                            <p role="status" className="text-sm text-amber-900">
                                {t(
                                    'Save or discard your preparation edits before verification.',
                                )}
                            </p>
                        )}
                        <div className="grid gap-5 sm:grid-cols-2">
                            <div>
                                <Label htmlFor="posting-date">
                                    {t('Posting business date *')}
                                </Label>
                                <Input
                                    className="mt-2"
                                    id="posting-date"
                                    type="date"
                                    required
                                    value={form.data.business_date}
                                    onChange={(event) =>
                                        form.setData(
                                            'business_date',
                                            event.target.value,
                                        )
                                    }
                                />
                                <p className="mt-2 text-xs leading-5 text-muted-foreground">
                                    Karachi date. First open date:{' '}
                                    {String(
                                        posting.settings.values.receipt_posting
                                            .open_from ?? 'Not configured',
                                    )}
                                    . Backdating limit:{' '}
                                    {String(
                                        posting.settings.values.receipt_posting
                                            .max_backdate_days ??
                                            'Not configured',
                                    )}{' '}
                                    days.
                                </p>
                            </div>
                            {cheque && (
                                <div>
                                    <Label htmlFor="posting-stage">
                                        {t('Verified cheque stage *')}
                                    </Label>
                                    <select
                                        id="posting-stage"
                                        className="mt-2 h-11 w-full rounded-lg border bg-background px-3 text-sm"
                                        required
                                        value={form.data.stage}
                                        onChange={(event) =>
                                            form.setData(
                                                'stage',
                                                event.target.value,
                                            )
                                        }
                                    >
                                        <option value="">
                                            {t('Choose verified stage')}
                                        </option>
                                        <option value="deposited">
                                            {t('Deposited, not bank-cleared')}
                                        </option>
                                        <option value="cleared">
                                            {t('Bank clearance verified')}
                                        </option>
                                    </select>
                                    <p className="mt-2 text-xs leading-5 text-muted-foreground">
                                        Recognition trigger:{' '}
                                        {posting.settings.values.collections
                                            .cheque_recognition === 'deposited'
                                            ? t('Verified deposit')
                                            : posting.settings.values
                                                    .collections
                                                    .cheque_recognition ===
                                                'cleared'
                                              ? t('Verified bank clearance')
                                              : t('Not configured')}
                                        .
                                    </p>
                                </div>
                            )}
                        </div>
                        <div>
                            <Label htmlFor="posting-note">
                                {t('Verification context *')}
                            </Label>
                            <textarea
                                id="posting-note"
                                className="mt-2 min-h-24 w-full rounded-lg border bg-background p-3 text-sm"
                                required
                                minLength={10}
                                maxLength={1000}
                                value={form.data.note}
                                onChange={(event) =>
                                    form.setData('note', event.target.value)
                                }
                                placeholder={t(
                                    'Describe the evidence and destination you checked. Do not enter credentials or full account details.',
                                )}
                            />
                        </div>
                        <label className="flex items-start gap-3 rounded-xl border p-4 text-sm leading-6">
                            <input
                                className="mt-1 size-4 shrink-0 accent-blue-600"
                                type="checkbox"
                                required
                                checked={form.data.confirmed}
                                onChange={(event) =>
                                    form.setData(
                                        'confirmed',
                                        event.target.checked,
                                    )
                                }
                            />
                            {t(
                                'I verified the saved amount, customer, destination, payment reference and evidence for the selected payment stage.',
                            )}
                        </label>
                        <div role="alert">
                            {Object.entries(form.errors).map(
                                ([key, message]) => (
                                    <InputError key={key} message={message} />
                                ),
                            )}
                        </div>
                        <Button
                            disabled={
                                form.processing ||
                                blocked ||
                                preparationDirty ||
                                !form.data.confirmed
                            }
                        >
                            <ShieldCheck className="size-4" />
                            {form.processing
                                ? t('Issuing…')
                                : t('Verify & issue official receipt')}
                        </Button>
                    </fieldset>
                </form>
            )}
        </section>
    );
}
