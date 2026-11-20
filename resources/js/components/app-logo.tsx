export default function AppLogo() {
    return (
        <>
            <div className="flex aspect-square size-10 items-center justify-center rounded-xl border border-white/10 bg-white text-sidebar-primary-foreground shadow-[0_5px_18px_rgba(0,0,0,0.2)] group-data-[collapsible=icon]:size-8">
                <img
                    src="/branding/data-real-estate.svg"
                    alt=""
                    className="size-9 rounded-lg bg-[#fffdf6] p-1 group-data-[collapsible=icon]:size-7"
                />
            </div>
            <div className="ms-2 grid flex-1 text-start text-sm">
                <span className="mb-0.5 truncate text-lg leading-tight font-bold tracking-[-0.025em] text-white">
                    Data Real Estate
                </span>
                <span className="text-[9px] font-bold tracking-[.22em] text-sidebar-primary uppercase">
                    Green Vista
                </span>
            </div>
        </>
    );
}
