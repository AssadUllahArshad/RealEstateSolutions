import { Head, Link, useForm } from '@inertiajs/react';
import BrandHeader from '@/components/brand-header';
import BookingPreparation, {
    type Preparation,
} from '@/components/booking-preparation';
import { PageHeading } from '@/components/office-ui';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatPkr } from '@/lib/format-pkr';
import { useUnsavedChanges } from '@/hooks/use-unsaved-changes';
import { useTranslate } from '@/lib/i18n';

type Props = {
    draft: {
        id: number;
        version: number;
        purchasers_version: number;
        customer: { name: string };
        plot: { number: string; block: { code: string } };
    };
    terms: {
        term: null | {
            version: number;
            snapshot: { net: string; down_payment: string; balance: string };
        };
        stale: boolean;
    };
    purchasers: { customer_id: number; name: string; share: string }[];
    credits: {
        id: number;
        number: string;
        available: string;
        recognized_on: string;
    }[];
    preparation: Preparation;
    canConfirm: boolean;
    commandKey: string;
};

export default function ConfirmBooking(props: Props) {
    const t = useTranslate();
    const term = props.terms.term;
    const form = useForm({
        command_key: props.commandKey,
        source_version: props.draft.version,
        purchasers_version: props.draft.purchasers_version,
        terms_version: term?.version ?? 0,
        allocations: [] as { receipt_id: number; amount: string }[],
        confirmed: false,
    });
    useUnsavedChanges(form.isDirty);
    const blocked =
        !props.canConfirm ||
        !term ||
        props.terms.stale ||
        props.preparation.checks.some(
            (check) => check.key !== 'payment' && check.state !== 'recorded',
        );
    return (
        <>
            <Head title={t('Confirm booking')} />
            <div className="ef-page">
                <BrandHeader />
                <PageHeading
                    eyebrow={t('Sales · Final confirmation')}
                    title={t('Review booking')}
                    description={`${props.draft.customer.name} · Block ${props.draft.plot.block.code} / Plot ${props.draft.plot.number}`}
                >
                    <Button asChild variant="outline">
                        <Link href={`/sales/${props.draft.id}`}>
                            {t('Back to proposal')}
                        </Link>
                    </Button>
                </PageHeading>
                <BookingPreparation preparation={props.preparation} />
                {term && (
                    <section className="ef-panel grid gap-5 p-6 sm:grid-cols-3">
                        {[
                            ['Agreed price', term.snapshot.net],
                            [
                                'Down payment to allocate',
                                term.snapshot.down_payment,
                            ],
                            ['Scheduled balance', term.snapshot.balance],
                        ].map(([label, value]) => (
                            <div key={label}>
                                <p className="text-xs text-muted-foreground">
                                    {t(label)}
                                </p>
                                <p className="mt-2 text-xl font-semibold">
                                    {formatPkr(value)}
                                </p>
                            </div>
                        ))}
                    </section>
                )}
                <form
                    className="ef-panel space-y-6 p-6"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(`/sales/${props.draft.id}/confirm`);
                    }}
                >
                    <div>
                        <h2 className="text-lg font-semibold">
                            {t('Allocate the down payment')}
                        </h2>
                        <p className="mt-2 text-sm leading-6 text-muted-foreground">
                            {t(
                                'Choose recognized receipts for this sales contact and enter the exact amount from each. The total must equal the saved planned down payment. Unused funds remain customer credit. This confirms the booking and freezes its proposal, purchasers and terms.',
                            )}
                        </p>
                    </div>
                    {props.credits.length === 0 && (
                        <p className="rounded-lg bg-amber-50 p-4 text-sm text-amber-900">
                            {t(
                                'No recognized unallocated credit is available. Prepare and verify the customer payment first.',
                            )}
                        </p>
                    )}
                    <fieldset
                        disabled={form.processing || blocked}
                        className="space-y-4"
                    >
                        {props.credits.map((credit) => {
                            const row = form.data.allocations.find(
                                (item) => item.receipt_id === credit.id,
                            );
                            return (
                                <div
                                    key={credit.id}
                                    className="rounded-xl border p-4"
                                >
                                    <label className="flex items-start gap-3">
                                        <input
                                            type="checkbox"
                                            checked={Boolean(row)}
                                            className="mt-1 size-4"
                                            onChange={(event) =>
                                                form.setData(
                                                    'allocations',
                                                    event.target.checked
                                                        ? [
                                                              ...form.data
                                                                  .allocations,
                                                              {
                                                                  receipt_id:
                                                                      credit.id,
                                                                  amount: '',
                                                              },
                                                          ]
                                                        : form.data.allocations.filter(
                                                              (item) =>
                                                                  item.receipt_id !==
                                                                  credit.id,
                                                          ),
                                                )
                                            }
                                        />
                                        <span className="min-w-0">
                                            <span className="block font-semibold break-words">
                                                {credit.number}
                                            </span>
                                            <span className="text-sm text-muted-foreground">
                                                {t('Available')}{' '}
                                                {formatPkr(credit.available)} ·
                                                {t('Recognized')}{' '}
                                                {credit.recognized_on}
                                            </span>
                                        </span>
                                    </label>
                                    {row && (
                                        <div className="mt-4">
                                            <Label
                                                htmlFor={`allocate-${credit.id}`}
                                            >
                                                Amount from {credit.number}{' '}
                                                (PKR)
                                            </Label>
                                            <Input
                                                id={`allocate-${credit.id}`}
                                                className="mt-2 max-w-sm"
                                                inputMode="decimal"
                                                value={row.amount}
                                                onChange={(event) =>
                                                    form.setData(
                                                        'allocations',
                                                        form.data.allocations.map(
                                                            (item) =>
                                                                item.receipt_id ===
                                                                credit.id
                                                                    ? {
                                                                          ...item,
                                                                          amount: event
                                                                              .target
                                                                              .value,
                                                                      }
                                                                    : item,
                                                        ),
                                                    )
                                                }
                                            />
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                        <label className="flex items-start gap-3 rounded-xl border bg-muted/30 p-4 text-sm leading-6">
                            <input
                                type="checkbox"
                                className="mt-1 size-4 shrink-0"
                                checked={form.data.confirmed}
                                onChange={(event) =>
                                    form.setData(
                                        'confirmed',
                                        event.target.checked,
                                    )
                                }
                            />
                            <span>
                                {t(
                                    'I verified the purchaser identities and consent, required signed documents, agreed pricing and payment schedule, and actual payment evidence. I authorize final booking and the selected down payment allocation.',
                                )}
                            </span>
                        </label>
                        <Button
                            disabled={
                                props.credits.length === 0 ||
                                !form.data.confirmed ||
                                form.data.allocations.length === 0
                            }
                        >
                            {t('Confirm booking & allocate payment')}
                        </Button>
                    </fieldset>
                    {blocked && (
                        <p className="text-sm text-amber-700">
                            {t(
                                'Resolve the checklist and current terms before confirmation. Only authorized staff may confirm.',
                            )}
                        </p>
                    )}
                    {Object.entries(form.errors).map(([key, message]) => (
                        <p
                            role="alert"
                            key={key}
                            className="text-sm text-destructive"
                        >
                            {message}
                        </p>
                    ))}
                </form>
            </div>
        </>
    );
}
