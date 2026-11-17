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
    ReceiptText,
    HandCoins,
    Upload,
    Banknote,
    Calculator,
    ChartNoAxesCombined,
    FileChartColumn,
    Landmark,
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
    const reports: NavItem[] = [];
    if (auth.abilities.includes('finance.receipts.view')) {
        finance.push({
            title: 'Customer receipts',
            href: '/finance/receipts',
            icon: ReceiptText,
        });
    }
    if (auth.abilities.includes('finance.expenses.view')) {
        finance.push({
            title: 'Office expenses',
            href: '/finance/expenses',
            icon: HandCoins,
        });
    }
    if (auth.abilities.includes('finance.salaries.view')) {
        finance.push({
            title: 'Salaries',
            href: '/finance/salaries',
            icon: Banknote,
        });
    }
    if (auth.abilities.includes('finance.accounts.view')) {
        finance.push({
            title: 'Payment accounts',
            href: '/finance/accounts',
            icon: Landmark,
        });
    }
    if (auth.abilities.includes('import.run')) {
        administration.push({
            title: 'Import data',
            href: '/imports',
            icon: Upload,
        });
    }
    if (auth.abilities.includes('sales.drafts')) {
        items.push({ title: 'Bookings', href: '/sales', icon: FilePenLine });
        items.push({
            title: 'Calculator',
            href: '/calculator',
            icon: Calculator,
        });
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
    if (auth.abilities.includes('business-settings.manage')) {
        administration.push({
            title: 'Business settings',
            href: '/business-settings/booking',
            icon: Settings2,
        });
    }
    if (auth.abilities.includes('reports.owners.view')) {
        reports.push({
            title: 'Owner reports',
            href: '/reports/owners',
            icon: FileChartColumn,
        });
    }
    if (auth.abilities.includes('reports.operations.view')) {
        reports.push({
            title: 'Operational reporting',
            href: '/reports/operations',
            icon: ChartNoAxesCombined,
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
                {reports.length > 0 && (
                    <NavMain label="Reports" items={reports} />
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
