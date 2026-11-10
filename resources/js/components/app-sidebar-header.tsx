import { Breadcrumbs } from '@/components/breadcrumbs';
import { WorkspacePreferences } from '@/components/workspace-preferences';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { Search, CalendarDays } from 'lucide-react';
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
        <header className="sticky top-0 z-30 flex min-h-18 shrink-0 flex-wrap items-center justify-between gap-3 border-b border-border bg-card/92 px-5 py-3 shadow-[0_1px_0_rgba(0,0,0,0.02)] backdrop-blur-xl md:px-8">
            <div className="flex items-center gap-2">
                <SidebarTrigger className="-ms-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            <div className="flex min-w-0 flex-1 basis-full items-center justify-end gap-4 sm:flex-initial sm:basis-auto">
                <WorkspacePreferences />
                <form
                    action="/search"
                    method="get"
                    className="relative w-full sm:max-w-xs"
                >
                    <Search className="pointer-events-none absolute start-3 top-3 size-4 text-muted-foreground" />
                    <input
                        type="search"
                        name="q"
                        aria-label={t('Search plots and customers')}
                        placeholder={t('Search plots, customers…')}
                        maxLength={160}
                        className="h-10 w-full rounded-xl border bg-muted/45 ps-9 pe-3 text-xs transition outline-none focus:border-primary/60 focus:bg-background focus:ring-2 focus:ring-ring/20"
                    />
                </form>
                <span className="hidden items-center gap-2 rounded-lg border bg-muted/35 px-3 py-2 text-xs whitespace-nowrap text-muted-foreground lg:inline-flex">
                    <CalendarDays className="size-4" />
                    {businessDate}
                </span>
            </div>
        </header>
    );
}
