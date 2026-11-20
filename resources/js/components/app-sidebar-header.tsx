import { Breadcrumbs } from '@/components/breadcrumbs';
import { WorkspacePreferences } from '@/components/workspace-preferences';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { Search, CalendarDays, Command } from 'lucide-react';
import { usePage } from '@inertiajs/react';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';
import { useTranslate } from '@/lib/i18n';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const { businessDate } = usePage().props;
    const t = useTranslate();
    return (
        <header className="sticky top-0 z-30 flex min-h-19 shrink-0 flex-wrap items-center gap-3 border-b border-border/80 bg-card/90 px-4 py-3 shadow-[0_8px_30px_rgba(16,34,58,0.045)] backdrop-blur-xl md:px-7">
            <div className="flex min-w-0 items-center gap-3">
                <SidebarTrigger className="size-10 rounded-xl border bg-card shadow-xs hover:border-primary/35 hover:bg-accent" />
                <div className="min-w-0 border-s border-border ps-3">
                    <Breadcrumbs breadcrumbs={breadcrumbs} />
                </div>
            </div>
            <div className="ms-auto flex min-w-0 flex-1 basis-full items-center justify-end gap-2.5 sm:flex-initial sm:basis-auto">
                <form
                    action="/search"
                    method="get"
                    className="group relative order-last w-full sm:order-none sm:w-72 lg:w-80"
                >
                    <Search className="pointer-events-none absolute start-3.5 top-3 size-4 text-muted-foreground transition group-focus-within:text-primary" />
                    <input
                        type="search"
                        name="q"
                        aria-label={t('Search plots and customers')}
                        placeholder={t('Search plots, customers…')}
                        maxLength={160}
                        className="h-10 w-full rounded-xl border bg-muted/50 ps-10 pe-11 text-xs shadow-inner transition outline-none placeholder:text-muted-foreground/80 hover:border-primary/25 focus:border-primary/55 focus:bg-card focus:ring-3 focus:ring-ring/15"
                    />
                    <span className="pointer-events-none absolute end-2 top-2 hidden h-6 items-center gap-0.5 rounded-md border bg-card px-1.5 text-[9px] font-semibold text-muted-foreground shadow-xs lg:flex">
                        <Command className="size-3" /> K
                    </span>
                </form>
                <WorkspacePreferences />
                <span className="hidden h-10 items-center gap-2 rounded-xl border bg-card px-3 text-xs whitespace-nowrap text-muted-foreground shadow-xs xl:inline-flex">
                    <CalendarDays className="size-4" />
                    {businessDate}
                </span>
            </div>
        </header>
    );
}
