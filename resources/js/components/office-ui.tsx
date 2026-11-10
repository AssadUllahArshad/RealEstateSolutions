import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import type { LucideIcon } from 'lucide-react';
import { ArrowUpRight, Inbox } from 'lucide-react';
import { useTranslate } from '@/lib/i18n';
export function PageHeading({
    eyebrow,
    title,
    description,
    children,
}: {
    eyebrow: string;
    title: string;
    description: string;
    children?: ReactNode;
}) {
    return (
        <header className="relative overflow-hidden rounded-2xl border bg-linear-to-br from-card via-card to-accent/35 p-5 shadow-[0_8px_30px_rgba(29,39,32,0.04)] sm:p-6">
            <span
                aria-hidden="true"
                className="absolute -end-12 -top-16 size-40 rounded-full bg-primary/8 blur-2xl"
            />
            <div className="relative flex flex-wrap items-start justify-between gap-5">
                <div className="min-w-0 flex-1 basis-64">
                    <p className="mb-2 text-[11px] font-bold tracking-[.16em] text-primary uppercase">
                        {eyebrow}
                    </p>
                    <h1 className="text-3xl font-bold tracking-tight break-words text-foreground">
                        {title}
                    </h1>
                    <p className="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground">
                        {description}
                    </p>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    {children}
                </div>
            </div>
        </header>
    );
}
export function StatCard({
    title,
    value,
    detail,
    icon: Icon,
    href,
    tone = 'blue',
}: {
    title: string;
    value: number | string;
    detail: string;
    icon: LucideIcon;
    href?: string;
    tone?: 'blue' | 'amber' | 'green' | 'rose';
}) {
    const colors = {
        blue: 'bg-primary/10 text-primary',
        amber: 'bg-amber-500/10 text-amber-700 dark:text-amber-300',
        green: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
        rose: 'bg-rose-500/10 text-rose-700 dark:text-rose-300',
    };
    const content = (
        <>
            <div className="flex items-start justify-between gap-4">
                <span className="text-xs font-semibold tracking-wide text-muted-foreground">
                    {title}
                </span>
                <span
                    className={`rounded-xl p-3 ring-1 ring-current/10 ${colors[tone]}`}
                >
                    <Icon className="size-5" />
                </span>
            </div>
            <p className="mt-3 text-3xl font-extrabold tracking-tight tabular-nums">
                {value}
            </p>
            <div className="mt-3 flex min-h-8 items-end justify-between gap-3 text-xs leading-5 text-muted-foreground">
                <span>{detail}</span>
                {href && <ArrowUpRight className="size-4" />}
            </div>
        </>
    );
    return href ? (
        <Link
            href={href}
            className="ef-panel group block border-t-2 border-t-primary/35 p-5 transition duration-200 hover:-translate-y-0.5 hover:border-primary/35 hover:shadow-lg focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        >
            {content}
        </Link>
    ) : (
        <div className="ef-panel p-5">{content}</div>
    );
}
export function EmptyState({
    title,
    description,
    children,
}: {
    title: string;
    description: string;
    children?: ReactNode;
}) {
    return (
        <div className="flex flex-col items-center px-6 py-14 text-center">
            <span className="mb-4 rounded-2xl bg-blue-50 p-4 text-blue-500 dark:bg-blue-950">
                <Inbox className="size-7" />
            </span>
            <h3 className="font-semibold">{title}</h3>
            <p className="mt-2 max-w-sm text-sm leading-6 text-muted-foreground">
                {description}
            </p>
            {children && <div className="mt-5">{children}</div>}
        </div>
    );
}
export function StatusBadge({
    label,
    tone = 'neutral',
}: {
    label: string;
    tone?: 'success' | 'warning' | 'danger' | 'info' | 'neutral';
}) {
    const tones = {
        success:
            'bg-emerald-500/10 text-emerald-700 ring-emerald-600/20 dark:text-emerald-300',
        warning:
            'bg-amber-500/10 text-amber-800 ring-amber-600/20 dark:text-amber-300',
        danger: 'bg-rose-500/10 text-rose-700 ring-rose-600/20 dark:text-rose-300',
        info: 'bg-blue-500/10 text-blue-700 ring-blue-600/20 dark:text-blue-300',
        neutral: 'bg-muted text-muted-foreground ring-border',
    };

    return (
        <span
            className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold whitespace-nowrap ring-1 ring-inset ${tones[tone]}`}
        >
            <span className="size-1.5 rounded-full bg-current" />
            {label}
        </span>
    );
}
export function Pager({
    prev_page_url,
    next_page_url,
}: {
    prev_page_url: string | null;
    next_page_url: string | null;
}) {
    const t = useTranslate();
    return (
        <nav
            aria-label={t('Results pages')}
            className="flex justify-between border-t px-5 py-4 text-sm"
        >
            {prev_page_url ? (
                <Link
                    className="font-medium text-blue-600"
                    href={prev_page_url}
                >
                    {t('Previous page')}
                </Link>
            ) : (
                <span />
            )}
            {next_page_url ? (
                <Link
                    className="font-medium text-blue-600"
                    href={next_page_url}
                >
                    {t('Next page')}
                </Link>
            ) : (
                <span />
            )}
        </nav>
    );
}
export function HoldBadge({
    kind,
}: {
    kind?: 'reservation' | 'block' | 'booked' | null;
}) {
    const t = useTranslate();
    return (
        <span
            className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold ${kind === 'block' ? 'bg-amber-50 text-amber-700 dark:bg-amber-950' : kind === 'reservation' ? 'bg-blue-50 text-blue-700 dark:bg-blue-950' : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950'}`}
        >
            <span className="size-1.5 rounded-full bg-current" />
            {kind === 'booked'
                ? t('Booked')
                : kind === 'block'
                  ? t('Blocked')
                  : kind === 'reservation'
                    ? t('Held')
                    : t('No active hold')}
        </span>
    );
}
