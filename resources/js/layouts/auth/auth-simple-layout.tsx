import { Link } from '@inertiajs/react';
import { MapPinned, UsersRound, CalendarCheck2 } from 'lucide-react';
import { home } from '@/routes';
import BrandHeader from '@/components/brand-header';
import { WorkspacePreferences } from '@/components/workspace-preferences';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="grid min-h-svh bg-background lg:grid-cols-[.9fr_1.1fr]">
            <aside className="relative hidden flex-col justify-between overflow-hidden bg-[#111b32] px-12 py-10 text-white lg:flex xl:px-16">
                <Link
                    href={home()}
                    className="relative z-10 flex items-center gap-3"
                >
                    <span className="rounded-xl bg-[#fffdf6] p-2">
                        <img
                            src="/branding/data-real-estate.svg"
                            alt=""
                            className="size-8 object-contain"
                        />
                    </span>
                    <span>
                        <span className="block text-2xl font-bold tracking-tight">
                            Data Real Estate
                        </span>
                        <span className="text-[9px] font-semibold tracking-[.2em] text-slate-400 uppercase">
                            Green Vista Housing Scheme
                        </span>
                    </span>
                </Link>
                <div className="relative z-10 my-12 max-w-md">
                    <p className="mb-5 text-xs font-semibold tracking-[.18em] text-blue-300 uppercase">
                        A clearer way to work
                    </p>
                    <h2 className="text-4xl leading-tight font-semibold tracking-tight xl:text-5xl">
                        Your society.
                        <br />
                        One connected office.
                    </h2>
                    <p className="mt-6 text-sm leading-7 text-slate-400">
                        Bring your inventory, customer relationships and daily
                        follow-ups into a workspace built around your team.
                    </p>
                    <div className="mt-8 flex flex-wrap gap-3">
                        {[
                            [MapPinned, 'Inventory'],
                            [UsersRound, 'Customers'],
                            [CalendarCheck2, 'Follow-ups'],
                        ].map(([Icon, label]) => {
                            const FeatureIcon = Icon as typeof MapPinned;
                            return (
                                <span
                                    className="inline-flex items-center gap-2 rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-xs text-slate-300"
                                    key={String(label)}
                                >
                                    <FeatureIcon className="size-3.5 text-blue-300" />
                                    {String(label)}
                                </span>
                            );
                        })}
                    </div>
                </div>
                <p className="relative z-10 text-xs text-slate-500">
                    Designed for the everyday work of a society office.
                </p>
                <div
                    className="pointer-events-none absolute -right-24 -bottom-20 grid rotate-[-18deg] grid-cols-6 gap-4 opacity-[.07]"
                    aria-hidden="true"
                >
                    {Array.from({ length: 36 }, (_, i) => (
                        <span
                            key={i}
                            className={`size-20 rounded-xl border border-white ${i % 4 === 0 ? 'bg-blue-400' : ''}`}
                        />
                    ))}
                </div>
            </aside>
            <main className="flex flex-col items-center justify-center px-5 py-10 sm:px-10">
                <div className="mb-6 flex w-full justify-end">
                    <WorkspacePreferences />
                </div>
                <Link
                    href={home()}
                    className="mb-8 flex items-center gap-3 lg:hidden"
                >
                    <span className="rounded-xl bg-[#fffdf6] p-2">
                        <img
                            src="/branding/data-real-estate.svg"
                            alt=""
                            className="size-7 object-contain"
                        />
                    </span>
                    <span className="text-xl font-bold tracking-tight">
                        Data Real Estate
                    </span>
                </Link>
                <div className="w-full max-w-md rounded-2xl border bg-card p-7 shadow-[0_8px_40px_rgba(16,34,58,0.04)] sm:p-9">
                    <div className="mb-7">
                        <BrandHeader />
                    </div>
                    <div className="mb-8">
                        <p className="mb-3 text-[10px] font-bold tracking-[.17em] text-blue-600 uppercase">
                            Your office workspace
                        </p>
                        <h1 className="text-2xl font-bold tracking-tight">
                            {title}
                        </h1>
                        <p className="mt-3 text-sm leading-6 text-muted-foreground">
                            {description}
                        </p>
                    </div>
                    {children}
                    <p className="mt-7 border-t pt-5 text-center text-[11px] text-muted-foreground">
                        Internal staff access · Society operations
                    </p>
                </div>
                <p className="mt-6 text-xs text-muted-foreground">
                    EstateFlow · PKR · Asia/Karachi
                </p>
            </main>
        </div>
    );
}
