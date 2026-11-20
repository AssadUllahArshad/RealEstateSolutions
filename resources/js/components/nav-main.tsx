import { Link } from '@inertiajs/react';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavItem } from '@/types';
import { useTranslate } from '@/lib/i18n';
import { ChevronRight } from 'lucide-react';

export function NavMain({
    items,
    label = 'Operations',
}: {
    items: NavItem[];
    label?: string;
}) {
    const t = useTranslate();
    const { isCurrentOrParentUrl, currentUrl } = useCurrentUrl();

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel className="mt-4 mb-2 gap-2 text-[10px] font-bold tracking-[.18em] uppercase">
                <span>{t(label)}</span>
                <span className="h-px flex-1 bg-sidebar-border/70" />
            </SidebarGroupLabel>
            <SidebarMenu className="gap-1.5">
                {items.map((item) => {
                    const active =
                        isCurrentOrParentUrl(item.href) &&
                        !(
                            item.href === '/inventory' &&
                            currentUrl.startsWith('/inventory/setup')
                        );

                    return (
                        <SidebarMenuItem key={item.title}>
                            <SidebarMenuButton
                                asChild
                                isActive={active}
                                tooltip={{ children: t(item.title) }}
                            >
                                <Link href={item.href} prefetch>
                                    {item.icon && (
                                        <span className="ef-nav-icon flex size-7 shrink-0 items-center justify-center rounded-lg transition-colors group-data-[collapsible=icon]:size-4">
                                            <item.icon className="size-4" />
                                        </span>
                                    )}
                                    <span className="font-medium">
                                        {t(item.title)}
                                    </span>
                                    <ChevronRight
                                        className={`ef-nav-chevron ms-auto size-3.5 transition ${active ? 'opacity-80' : 'opacity-0'}`}
                                    />
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    );
                })}
            </SidebarMenu>
        </SidebarGroup>
    );
}
