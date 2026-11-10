import { Link } from '@inertiajs/react';
import {
    ArrowUpRight,
    Check,
    CircleDashed,
    ClipboardCheck,
} from 'lucide-react';

export type Preparation = {
    checks: {
        key: string;
        title: string;
        state: 'recorded' | 'action' | 'configuration' | 'pending';
        detail: string;
        href: string | null;
    }[];
    observed_at: string;
};

const states = {
    recorded: {
        label: 'Recorded',
        color: 'bg-emerald-50 text-emerald-800 border-emerald-200',
    },
    action: {
        label: 'Needs attention',
        color: 'bg-amber-50 text-amber-900 border-amber-200',
    },
    configuration: {
        label: 'Admin setup',
        color: 'bg-blue-50 text-blue-800 border-blue-200',
    },
    pending: {
        label: 'Coming next',
        color: 'bg-slate-100 text-slate-600 border-slate-200',
    },
};

export default function BookingPreparation({
    preparation,
}: {
    preparation: Preparation;
}) {
    return (
        <section
            className="ef-panel overflow-hidden"
            aria-labelledby="preparation-title"
        >
            <div className="flex flex-wrap items-start justify-between gap-4 border-b bg-slate-50/70 p-5 sm:p-6">
                <div className="flex gap-3">
                    <span className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-blue-600 text-white">
                        <ClipboardCheck className="size-5" />
                    </span>
                    <div>
                        <h2 id="preparation-title" className="font-semibold">
                            Booking preparation
                        </h2>
                        <p className="mt-1 max-w-xl text-sm leading-6 text-muted-foreground">
                            Work through the customer, documents and plot checks
                            before preparing a final contract.
                        </p>
                    </div>
                </div>
                <span className="rounded-full border bg-white px-3 py-1 text-xs font-medium text-slate-600">
                    Proposal stage
                </span>
            </div>
            <ol className="grid gap-px bg-border md:grid-cols-2 xl:grid-cols-3">
                {preparation.checks.map((check, index) => {
                    const state = states[check.state];
                    return (
                        <li
                            key={check.key}
                            className="flex flex-col bg-card p-5"
                        >
                            <div className="mb-4 flex items-center justify-between gap-2">
                                <span className="text-xs font-semibold tracking-widest text-muted-foreground">
                                    STEP {String(index + 1).padStart(2, '0')}
                                </span>
                                <span
                                    className={`inline-flex items-center gap-1.5 rounded-full border px-2 py-1 text-[11px] font-medium ${state.color}`}
                                >
                                    {check.state === 'recorded' ? (
                                        <Check className="size-3" />
                                    ) : (
                                        <CircleDashed className="size-3" />
                                    )}
                                    {state.label}
                                </span>
                            </div>
                            <h3 className="text-sm font-semibold">
                                {check.title}
                            </h3>
                            <p className="mt-2 mb-4 flex-1 text-xs leading-6 text-muted-foreground">
                                {check.detail}
                            </p>
                            {check.href && (
                                <Link
                                    href={check.href}
                                    className="inline-flex items-center gap-1 text-xs font-medium text-blue-600"
                                >
                                    Review{' '}
                                    {check.key === 'payment'
                                        ? 'receipts'
                                        : check.key === 'plot'
                                          ? 'plot'
                                          : check.key === 'contract'
                                            ? 'pricing & plan'
                                            : 'customer'}
                                    <ArrowUpRight className="size-3.5" />
                                    <span className="sr-only">
                                        : {check.title}
                                    </span>
                                </Link>
                            )}
                        </li>
                    );
                })}
            </ol>
            <p className="border-t px-5 py-3 text-xs leading-5 text-muted-foreground">
                Based on the saved proposal at{' '}
                {preparation.observed_at.replace('T', ' ').slice(0, 19)} UTC.
                Recorded items still require staff verification. This checklist
                does not authorize or finalize a booking.
            </p>
        </section>
    );
}
