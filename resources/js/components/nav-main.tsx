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
            <SidebarGroupLabel className="mt-5 mb-2 text-[10px] font-semibold tracking-[.16em] uppercase">
                {t(label)}
            </SidebarGroupLabel>
            <SidebarMenu>
                {items.map((item) => (
                    <SidebarMenuItem key={item.title}>
                        <SidebarMenuButton
                            asChild
                            isActive={
                                isCurrentOrParentUrl(item.href) &&
                                !(
                                    item.href === '/inventory' &&
                                    currentUrl.startsWith('/inventory/setup')
                                )
                            }
                            tooltip={{ children: item.title }}
                        >
                            <Link href={item.href} prefetch>
                                {item.icon && <item.icon />}
                                <span>{t(item.title)}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                ))}
            </SidebarMenu>
        </SidebarGroup>
    );
}
