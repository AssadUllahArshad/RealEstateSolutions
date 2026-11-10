import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import {
    ArrowLeft,
    CalendarPlus,
    Check,
    Mail,
    Pencil,
    Phone,
    UserRound,
    Clock3,
} from 'lucide-react';
import CustomerEditor from '@/components/customer-editor';
import CustomerDocuments from '@/components/customer-documents';
import type { CustomerDocumentsPage } from '@/components/customer-documents';
import type { AgentOption, CustomerRecord } from '@/components/customer-editor';
import {
    EmptyState,
    PageHeading,
    Pager,
    StatusBadge,
} from '@/components/office-ui';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/input-error';
import {
    Dialog,
    DialogContent,
    DialogTitle,
    DialogDescription,
    DialogHeader,
} from '@/components/ui/dialog';
import { useUnsavedChanges } from '@/hooks/use-unsaved-changes';
import { useTranslate } from '@/lib/i18n';
export type FollowUpRecord = {
    id: number;
    note: string;
    due_date: string;
    completed_at: string | null;
    customer?: { id: number; name: string; phone?: string | null };
};
export default function CustomerProfile({
    customer,
    agents,
    followUps,
    activity,
    identity,
    documents,
}: {
    customer: CustomerRecord;
    agents: AgentOption[];
    identity: { cnic: string | null; address: string | null };
    documents: CustomerDocumentsPage;
    followUps: {
        data: FollowUpRecord[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    activity: {
        id: number;
        event: string;
        actor: string;
        created_at: string;
    }[];
}) {
    const t = useTranslate();
    const [edit, setEdit] = useState(false);
    const [dirty, setDirty] = useState(false);
    const [pending, setPending] = useState<number | null>(null);
    const { auth } = usePage().props;
    const manage = auth.abilities.includes('customers.manage');
    const form = useForm({ note: '', due_date: '' });
    useUnsavedChanges(form.isDirty);
    return (
        <>
            <Head title={customer.name} />
            <div className="ef-page">
                <Link href="/customers" className="ef-text-link">
                    <ArrowLeft className="size-4" />
                    {t('Back to customers')}
                </Link>
                <PageHeading
                    eyebrow={`${t('Customer')} · CUS-${String(customer.id).padStart(4, '0')}`}
                    title={customer.name}
                    description={t(
                        'Contact information, follow-ups and relationship history.',
                    )}
                >
                    {manage && (
                        <Button variant="outline" onClick={() => setEdit(true)}>
                            <Pencil className="size-4" />
                            {t('Edit customer')}
                        </Button>
                    )}
                </PageHeading>
                <div className="grid items-start gap-6 lg:grid-cols-[320px_1fr]">
                    <aside className="contents lg:block lg:space-y-5">
                        <section className="ef-panel p-6">
                            <div className="mb-5 flex size-16 items-center justify-center rounded-2xl bg-blue-50 text-xl font-bold text-blue-600 dark:bg-blue-950">
                                {customer.name.slice(0, 2).toUpperCase()}
                            </div>
                            <h2 className="font-semibold">
                                {t('Contact details')}
                            </h2>
                            <dl className="mt-5 space-y-5 text-sm">
                                <div>
                                    <dt className="flex items-center gap-2 text-xs text-muted-foreground">
                                        <Phone className="size-3.5" />
                                        {t('Phone number')}
                                    </dt>
                                    <dd className="mt-1.5 break-words">
                                        {customer.phone ? (
                                            <a
                                                className="text-blue-600"
                                                href={`tel:${customer.phone}`}
                                            >
                                                {customer.phone}
                                            </a>
                                        ) : (
                                            t('Not added')
                                        )}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="flex items-center gap-2 text-xs text-muted-foreground">
                                        <Mail className="size-3.5" />
                                        {t('Email address')}
                                    </dt>
                                    <dd className="mt-1.5 break-all">
                                        {customer.email ? (
                                            <a
                                                className="text-blue-600"
                                                href={`mailto:${customer.email}`}
                                            >
                                                {customer.email}
                                            </a>
                                        ) : (
                                            t('Not added')
                                        )}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="flex items-center gap-2 text-xs text-muted-foreground">
                                        <UserRound className="size-3.5" />
                                        {t('Assigned staff member')}
                                    </dt>
                                    <dd className="mt-1.5 font-medium">
                                        {customer.agent.name}
                                    </dd>
                                </div>
                            </dl>
                        </section>
                        <section className="ef-panel order-1 p-6 lg:order-none">
                            <h2 className="font-semibold">
                                {t('Activity timeline')}
                            </h2>
                            <ol className="mt-5 space-y-5 border-s border-blue-100 ps-4">
                                {activity.map((event) => (
                                    <li key={event.id}>
                                        <p className="text-xs font-medium capitalize">
                                            {event.event
                                                .replaceAll('_', ' ')
                                                .replaceAll('.', ' ')}
                                        </p>
                                        <p className="mt-1 text-[11px] text-muted-foreground">
                                            {event.actor} ·{' '}
                                            {new Date(
                                                event.created_at,
                                            ).toLocaleDateString('en-PK', {
                                                timeZone: 'Asia/Karachi',
                                            })}
                                        </p>
                                    </li>
                                ))}
                            </ol>
                        </section>
                    </aside>
                    <div className="space-y-6">
                        <section
                            className="ef-panel p-6"
                            aria-label={t('Customer identity')}
                        >
                            <h2 className="font-semibold">
                                {t('Customer identity')}
                            </h2>
                            <dl className="mt-4 grid gap-5 sm:grid-cols-2">
                                <div>
                                    <dt className="text-xs text-muted-foreground">
                                        CNIC
                                    </dt>
                                    <dd className="mt-2 text-sm font-medium">
                                        {identity.cnic
                                            ? `${identity.cnic.slice(0, 5)}-${identity.cnic.slice(5, 12)}-${identity.cnic.slice(12)}`
                                            : 'Not recorded'}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-xs text-muted-foreground">
                                        {t('Address')}
                                    </dt>
                                    <dd className="mt-2 text-sm leading-6 break-words whitespace-pre-wrap">
                                        {identity.address || 'Not recorded'}
                                    </dd>
                                </div>
                            </dl>
                        </section>
                        <CustomerDocuments
                            customerId={customer.id}
                            documents={documents}
                            manage={manage}
                        />
                        {manage && (
                            <section className="ef-panel">
                                <div className="ef-panel-heading">
                                    <div className="flex items-center gap-3">
                                        <span className="rounded-lg bg-blue-50 p-2 text-blue-600 dark:bg-blue-950">
                                            <CalendarPlus className="size-5" />
                                        </span>
                                        <div>
                                            <h2 className="font-semibold">
                                                {t('Schedule a follow-up')}
                                            </h2>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {t(
                                                    'Keep the next conversation moving.',
                                                )}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <form
                                    className="space-y-4 p-6"
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        form.post(
                                            `/customers/${customer.id}/follow-ups`,
                                            {
                                                preserveScroll: true,
                                                onSuccess: () => form.reset(),
                                            },
                                        );
                                    }}
                                >
                                    <div>
                                        <Label htmlFor="follow-note">
                                            {t('Follow-up note *')}
                                        </Label>
                                        <textarea
                                            id="follow-note"
                                            className="mt-2 min-h-24 w-full rounded-lg border bg-background p-3 text-sm"
                                            placeholder={t(
                                                'What would you like to follow up on?',
                                            )}
                                            required
                                            maxLength={1000}
                                            value={form.data.note}
                                            onChange={(e) =>
                                                form.setData(
                                                    'note',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={form.errors.note}
                                        />
                                    </div>
                                    <div className="flex flex-wrap items-end justify-between gap-4">
                                        <div>
                                            <Label htmlFor="follow-due">
                                                {t('Due date (Karachi) *')}
                                            </Label>
                                            <Input
                                                id="follow-due"
                                                type="date"
                                                required
                                                className="mt-2"
                                                value={form.data.due_date}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'due_date',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                            <InputError
                                                message={form.errors.due_date}
                                            />
                                        </div>
                                        <Button disabled={form.processing}>
                                            <CalendarPlus className="size-4" />
                                            {t('Save follow-up')}
                                        </Button>
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        {t(
                                            'Internal task only. No SMS, WhatsApp or email is sent.',
                                        )}
                                    </p>
                                </form>
                            </section>
                        )}
                        <section className="ef-panel overflow-hidden">
                            <div className="ef-panel-heading">
                                <h2 className="font-semibold">
                                    {t('Follow-up history')}
                                </h2>
                                <Clock3 className="size-5 text-muted-foreground" />
                            </div>
                            {followUps.data.length ? (
                                <ul className="divide-y">
                                    {followUps.data.map((follow) => (
                                        <li
                                            className="flex items-start justify-between gap-4 p-6"
                                            key={follow.id}
                                        >
                                            <div className="min-w-0">
                                                <StatusBadge
                                                    tone={
                                                        follow.completed_at
                                                            ? 'success'
                                                            : 'info'
                                                    }
                                                    label={t(
                                                        follow.completed_at
                                                            ? 'Completed'
                                                            : 'Open',
                                                    )}
                                                />
                                                <p className="mt-3 text-sm leading-6 break-words whitespace-pre-wrap">
                                                    {follow.note}
                                                </p>
                                                <p className="mt-2 text-xs text-muted-foreground">
                                                    {t('Due')} {follow.due_date}
                                                </p>
                                            </div>
                                            {manage && !follow.completed_at && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    disabled={pending !== null}
                                                    onClick={() => {
                                                        setPending(follow.id);
                                                        router.post(
                                                            `/follow-ups/${follow.id}/complete`,
                                                            {},
                                                            {
                                                                preserveScroll: true,
                                                                onFinish: () =>
                                                                    setPending(
                                                                        null,
                                                                    ),
                                                            },
                                                        );
                                                    }}
                                                >
                                                    <Check className="size-4" />
                                                    {t('Complete')}
                                                </Button>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <EmptyState
                                    title={t('No follow-ups yet')}
                                    description={t(
                                        'Schedule a call, meeting or site-visit follow-up using the form above.',
                                    )}
                                />
                            )}
                            <Pager {...followUps} />
                        </section>
                    </div>
                </div>
                <Dialog
                    open={edit}
                    onOpenChange={(value) => {
                        if (
                            value ||
                            !dirty ||
                            window.confirm(
                                'Close the edit form? Unsaved edits will be discarded.',
                            )
                        )
                            setEdit(value);
                    }}
                >
                    <DialogContent className="max-h-[90vh] overflow-y-auto">
                        <DialogHeader>
                            <DialogTitle>{t('Edit customer')}</DialogTitle>
                            <DialogDescription>
                                {t(
                                    'Update contact information or staff assignment.',
                                )}
                            </DialogDescription>
                        </DialogHeader>
                        <CustomerEditor
                            onDirtyChange={setDirty}
                            customer={{ ...customer, ...identity }}
                            agents={agents}
                            close={() => setEdit(false)}
                        />
                    </DialogContent>
                </Dialog>
            </div>
        </>
    );
}
CustomerProfile.layout = {
    breadcrumbs: [
        { title: 'Customers', href: '/customers' },
        { title: 'Customer profile', href: '#' },
    ],
};
