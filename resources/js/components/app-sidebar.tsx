import { Link, router, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import {
    LayoutGrid,
    UsersRound,
    Building2,
    Settings2,
    CalendarCheck2,
    ContactRound,
    FilePenLine,
    SlidersHorizontal,
    ReceiptText,
    Landmark,
    WalletCards,
    ChartNoAxesColumnIncreasing,
    HandCoins,
    CircleDollarSign,
    CalendarClock,
    BadgeDollarSign,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';
import { useTranslate } from '@/lib/i18n';

const mainNavItems: NavItem[] = [
    {
        title: 'Overview',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

export function AppSidebar() {
    const t = useTranslate();
    const { auth, locale } = usePage().props;
    const { setOpenMobile } = useSidebar();
    useEffect(
        () => router.on('navigate', () => setOpenMobile(false)),
        [setOpenMobile],
    );
    const items = [...mainNavItems];
    const administration: NavItem[] = [];
    const finance: NavItem[] = [];
    if (auth.abilities.includes('finance.receipts.view')) {
        finance.push({
            title: 'Customer receipts',
            href: '/finance/receipts',
            icon: ReceiptText,
        });
        finance.push({
            title: 'Receivables',
            href: '/finance/receivables',
            icon: CalendarClock,
        });
    }
    if (auth.abilities.includes('finance.accounts.view')) {
        finance.push({
            title: 'Finance overview',
            href: '/finance',
            icon: CircleDollarSign,
        });
        finance.push({
            title: 'Cashbook',
            href: '/finance/movements',
            icon: Landmark,
        });
        finance.push({
            title: 'Financial accounts',
            href: '/finance/accounts',
            icon: WalletCards,
        });
    }
    if (auth.abilities.includes('finance.expenses.view')) {
        finance.push({
            title: 'Office expenses',
            href: '/finance/expenses',
            icon: HandCoins,
        });
    }
    if (auth.abilities.includes('finance.owner-payouts.view')) {
        finance.push({
            title: 'Owner payouts',
            href: '/finance/owner-payouts',
            icon: HandCoins,
        });
    }
    if (auth.abilities.includes('finance.refunds.view')) {
        finance.push({
            title: 'Customer refunds',
            href: '/finance/refunds',
            icon: HandCoins,
        });
    }
    if (auth.abilities.includes('finance.commissions.view')) {
        finance.push({
            title: 'Agent commissions',
            href: '/finance/commissions',
            icon: HandCoins,
        });
    }
    if (auth.abilities.includes('finance.charges.view')) {
        finance.push({
            title: 'Manual charges',
            href: '/finance/charges',
            icon: BadgeDollarSign,
        });
    }
    if (auth.abilities.includes('business-settings.manage')) {
        administration.push({
            title: 'Business settings',
            href: '/business-settings',
            icon: SlidersHorizontal,
        });
    }
    if (auth.abilities.includes('reports.owners.view')) {
        items.push({
            title: 'Owner reports',
            href: '/reports/owners',
            icon: ChartNoAxesColumnIncreasing,
        });
    }
    if (auth.abilities.includes('sales.drafts')) {
        items.push({ title: 'Sales desk', href: '/sales', icon: FilePenLine });
    }
    if (auth.abilities.includes('customers.view')) {
        items.push(
            { title: 'Customers', href: '/customers', icon: ContactRound },
            { title: 'Follow-ups', href: '/follow-ups', icon: CalendarCheck2 },
        );
    }
    if (auth.abilities.includes('inventory.view')) {
        items.splice(1, 0, {
            title: 'Plot inventory',
            href: '/inventory',
            icon: Building2,
        });
    }
    if (auth.abilities.includes('configuration.manage')) {
        administration.push({
            title: 'Society setup',
            href: '/inventory/setup',
            icon: Settings2,
        });
    }
    if (auth.abilities.includes('users.view')) {
        administration.push({
            title: 'Team & access',
            href: '/team',
            icon: UsersRound,
        });
    }
    return (
        <Sidebar
            collapsible="icon"
            variant="sidebar"
            side={locale === 'ur' ? 'right' : 'left'}
        >
            <SidebarHeader className="px-4 pt-6 pb-4">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={items} />
                {finance.length > 0 && (
                    <NavMain label="Finance" items={finance} />
                )}
                {administration.length > 0 && (
                    <NavMain label="Administration" items={administration} />
                )}
            </SidebarContent>

            <SidebarFooter>
                <p className="px-3 py-2 text-xs text-muted-foreground group-data-[collapsible=icon]:hidden">
                    {t('Society operations')} · PKR
                </p>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
