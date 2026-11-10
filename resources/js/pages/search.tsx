import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Search as SearchIcon, ArrowUpRight } from 'lucide-react';
import { EmptyState, PageHeading, HoldBadge } from '@/components/office-ui';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import type { Plot } from '@/types/inventory';
export default function Search({
    q,
    plots,
    customers,
}: {
    q: string;
    plots: Plot[];
    customers: { id: number; name: string; phone: string | null }[];
}) {
    const [query, setQuery] = useState(q);
    return (
        <>
            <Head title="Search" />
            <div className="ef-page">
                <PageHeading
                    eyebrow="Workspace search"
                    title="Find it faster"
                    description="Search plot numbers and customer names across records you are authorized to see."
                />
                <form
                    className="ef-panel flex gap-3 p-5"
                    onSubmit={(e) => {
                        e.preventDefault();
                        router.get('/search', { q: query });
                    }}
                >
                    <Input
                        aria-label="Search workspace"
                        placeholder="Plot number or customer name…"
                        value={query}
                        maxLength={160}
                        onChange={(e) => setQuery(e.target.value)}
                    />
                    <Button>
                        <SearchIcon className="size-4" />
                        Search
                    </Button>
                </form>
                <div className="grid items-start gap-6 lg:grid-cols-2">
                    <section className="ef-panel overflow-hidden">
                        <div className="ef-panel-heading">
                            <h2 className="font-semibold">Plots</h2>
                            <span className="text-xs text-muted-foreground">
                                {plots.length} results · maximum 20
                            </span>
                        </div>
                        {plots.length ? (
                            <ul className="divide-y">
                                {plots.map((plot) => (
                                    <li key={plot.id}>
                                        <Link
                                            className="flex items-center justify-between gap-3 p-5 hover:bg-muted/40"
                                            href={`/inventory/plots/${plot.id}/hold`}
                                        >
                                            <div>
                                                <p className="text-sm font-semibold">
                                                    {plot.number}
                                                </p>
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    Block {plot.block.code} ·{' '}
                                                    {plot.size.label}
                                                </p>
                                            </div>
                                            <HoldBadge
                                                kind={plot.active_hold?.kind}
                                            />
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <EmptyState
                                title={
                                    q
                                        ? 'No matching plots'
                                        : 'Search your workspace'
                                }
                                description="Enter a plot number in the search above."
                            />
                        )}
                    </section>
                    <section className="ef-panel overflow-hidden">
                        <div className="ef-panel-heading">
                            <h2 className="font-semibold">Customers</h2>
                            <span className="text-xs text-muted-foreground">
                                {customers.length} results · maximum 20
                            </span>
                        </div>
                        {customers.length ? (
                            <ul className="divide-y">
                                {customers.map((customer) => (
                                    <li key={customer.id}>
                                        <Link
                                            className="flex items-center justify-between p-5 hover:bg-muted/40"
                                            href={`/customers/${customer.id}`}
                                        >
                                            <div>
                                                <p className="text-sm font-semibold">
                                                    {customer.name}
                                                </p>
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    {customer.phone ??
                                                        'No phone added'}
                                                </p>
                                            </div>
                                            <ArrowUpRight className="size-4 text-blue-600" />
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <EmptyState
                                title={
                                    q
                                        ? 'No matching customers'
                                        : 'Find a customer'
                                }
                                description="Results include only the customer records you can access."
                            />
                        )}
                    </section>
                </div>
            </div>
        </>
    );
}
Search.layout = { breadcrumbs: [{ title: 'Search', href: '/search' }] };
