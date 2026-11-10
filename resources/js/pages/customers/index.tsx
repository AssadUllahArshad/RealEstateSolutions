import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Plus, Search, UsersRound, ArrowUpRight } from 'lucide-react';
import CustomerEditor from '@/components/customer-editor';
import type { CustomerRecord, AgentOption } from '@/components/customer-editor';
import { EmptyState, PageHeading, Pager } from '@/components/office-ui';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslate } from '@/lib/i18n';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';

export default function Customers({
    customers,
    agents,
    filters,
    creationKey,
    totalCustomers,
}: {
    customers: {
        data: CustomerRecord[];
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    agents: AgentOption[];
    filters: { q: string; agent: string };
    creationKey: string;
    totalCustomers: number;
}) {
    const t = useTranslate();
    const { auth, url } = { auth: usePage().props.auth, url: usePage().url };
    const [open, setOpen] = useState(
        new URLSearchParams(url.split('?')[1]).get('new') === '1',
    );
    const [q, setQ] = useState(filters.q);
    const [dirty, setDirty] = useState(false);
    const [agent, setAgent] = useState(String(filters.agent));
    return (
        <>
            <Head title={t('Customers')} />
            <div className="ef-page">
                <PageHeading
                    eyebrow={t('Customer relationships')}
                    title={t('Customers')}
                    description={t(
                        'Your people, conversations and next steps — together in one place.',
                    )}
                >
                    {auth.abilities.includes('customers.manage') && (
                        <Button onClick={() => setOpen(true)}>
                            <Plus className="size-4" />
                            {t('Add customer')}
                        </Button>
                    )}
                </PageHeading>
                <div className="flex items-center gap-3 text-sm text-muted-foreground">
                    <UsersRound className="size-4 text-blue-600" />
                    <span>
                        <b className="text-foreground">{totalCustomers}</b>{' '}
                        {auth.abilities.includes('customers.view_all')
                            ? t('customers in your office')
                            : t('customers assigned to you')}
                    </span>
                </div>
                <section className="ef-panel overflow-hidden">
                    <form
                        className="ef-toolbar"
                        onSubmit={(e) => {
                            e.preventDefault();
                            router.get('/customers', { q, agent });
                        }}
                    >
                        <div className="relative min-w-48 flex-1">
                            <Search className="absolute start-3 top-3 size-4 text-muted-foreground" />
                            <Input
                                aria-label={t('Search customers')}
                                placeholder={t('Search name, phone or email…')}
                                className="ps-9"
                                value={q}
                                onChange={(e) => setQ(e.target.value)}
                            />
                        </div>
                        {agents.length > 0 && (
                            <select
                                aria-label={t('Filter customers by staff')}
                                className="min-w-40"
                                value={agent}
                                onChange={(e) => setAgent(e.target.value)}
                            >
                                <option value="">{t('All staff')}</option>
                                {agents.map((a) => (
                                    <option key={a.id} value={a.id}>
                                        {a.name}
                                    </option>
                                ))}
                            </select>
                        )}
                        <Button variant="outline">{t('Search')}</Button>
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => router.get('/customers')}
                        >
                            {t('Clear')}
                        </Button>
                    </form>
                    {customers.data.length ? (
                        <>
                            <ul
                                className="divide-y md:hidden"
                                aria-label={t('Customer directory')}
                            >
                                {customers.data.map((customer) => (
                                    <li
                                        key={customer.id}
                                        className="space-y-4 p-5"
                                    >
                                        <div className="flex items-start gap-3">
                                            <span className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-sm font-bold text-primary">
                                                {customer.name
                                                    .slice(0, 2)
                                                    .toUpperCase()}
                                            </span>
                                            <div className="min-w-0 flex-1">
                                                <Link
                                                    href={`/customers/${customer.id}`}
                                                    className="font-semibold break-words hover:text-primary"
                                                >
                                                    {customer.name}
                                                </Link>
                                                <p className="mt-1 text-[11px] text-muted-foreground">
                                                    CUS-
                                                    {String(
                                                        customer.id,
                                                    ).padStart(4, '0')}
                                                </p>
                                            </div>
                                        </div>
                                        <dl className="grid grid-cols-2 gap-3 rounded-xl bg-muted/35 p-3 text-sm">
                                            <div className="min-w-0">
                                                <dt className="text-xs text-muted-foreground">
                                                    {t('Contact details')}
                                                </dt>
                                                <dd className="mt-1 truncate font-medium">
                                                    {customer.phone ??
                                                        t('No phone added')}
                                                </dd>
                                            </div>
                                            <div className="min-w-0">
                                                <dt className="text-xs text-muted-foreground">
                                                    {t('Assigned to')}
                                                </dt>
                                                <dd className="mt-1 truncate font-medium">
                                                    {customer.agent.name}
                                                </dd>
                                            </div>
                                        </dl>
                                        <Link
                                            className="ef-text-link"
                                            href={`/customers/${customer.id}`}
                                        >
                                            {t('View profile')}
                                            <ArrowUpRight className="size-4" />
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                            <div className="hidden overflow-x-auto md:block">
                                <table className="ef-table">
                                    <caption className="sr-only">
                                        Customer contact directory
                                    </caption>
                                    <thead>
                                        <tr>
                                            {[
                                                'Customer',
                                                'Contact details',
                                                'Assigned to',
                                                'Added',
                                                '',
                                            ].map((label, i) => (
                                                <th key={i}>
                                                    {label ? t(label) : ''}
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {customers.data.map((customer) => (
                                            <tr key={customer.id}>
                                                <td>
                                                    <Link
                                                        href={`/customers/${customer.id}`}
                                                        className="flex items-center gap-3"
                                                    >
                                                        <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-blue-50 text-sm font-bold text-blue-600 dark:bg-blue-950">
                                                            {customer.name
                                                                .slice(0, 2)
                                                                .toUpperCase()}
                                                        </span>
                                                        <div>
                                                            <p className="font-semibold">
                                                                {customer.name}
                                                            </p>
                                                            <p className="mt-1 text-[11px] text-muted-foreground">
                                                                CUS-
                                                                {String(
                                                                    customer.id,
                                                                ).padStart(
                                                                    4,
                                                                    '0',
                                                                )}
                                                            </p>
                                                        </div>
                                                    </Link>
                                                </td>
                                                <td>
                                                    <p className="whitespace-nowrap">
                                                        {customer.phone ??
                                                            t('No phone added')}
                                                    </p>
                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                        {customer.email ??
                                                            t('No email added')}
                                                    </p>
                                                </td>
                                                <td className="whitespace-nowrap">
                                                    {customer.agent.name}
                                                </td>
                                                <td className="text-xs whitespace-nowrap text-muted-foreground">
                                                    {new Date(
                                                        customer.created_at,
                                                    ).toLocaleDateString(
                                                        'en-PK',
                                                        {
                                                            timeZone:
                                                                'Asia/Karachi',
                                                        },
                                                    )}
                                                </td>
                                                <td>
                                                    <Link
                                                        aria-label={`Open ${customer.name}`}
                                                        href={`/customers/${customer.id}`}
                                                        className="ef-text-link"
                                                    >
                                                        {t('View profile')}
                                                        <ArrowUpRight className="size-4" />
                                                    </Link>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </>
                    ) : (
                        <EmptyState
                            title={t('Build your customer directory')}
                            description={
                                filters.q
                                    ? t(
                                          'No customers match this search. Try a different name, phone or email.',
                                      )
                                    : t(
                                          'Add your first customer to record contact details and schedule a follow-up.',
                                      )
                            }
                        >
                            {auth.abilities.includes('customers.manage') && (
                                <Button onClick={() => setOpen(true)}>
                                    <Plus className="size-4" />
                                    {t('Add customer')}
                                </Button>
                            )}
                        </EmptyState>
                    )}
                    <Pager {...customers} />
                </section>
                <Dialog
                    open={open}
                    onOpenChange={(value) => {
                        if (
                            value ||
                            !dirty ||
                            window.confirm(
                                'Close this customer form? Unsaved edits will be discarded.',
                            )
                        )
                            setOpen(value);
                    }}
                >
                    <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                        <DialogHeader>
                            <DialogTitle>{t('Add customer')}</DialogTitle>
                            <DialogDescription>
                                {t(
                                    'Create a contact record and choose who will look after them.',
                                )}
                            </DialogDescription>
                        </DialogHeader>
                        <CustomerEditor
                            onDirtyChange={setDirty}
                            agents={agents}
                            creationKey={creationKey}
                            close={() => setOpen(false)}
                        />
                    </DialogContent>
                </Dialog>
            </div>
        </>
    );
}
Customers.layout = {
    breadcrumbs: [{ title: 'Customers', href: '/customers' }],
};
