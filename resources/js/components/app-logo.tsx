export default function AppLogo() {
    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                <img
                    src="/branding/data-real-estate.svg"
                    alt=""
                    className="size-8 rounded bg-[#fffdf6] p-1"
                />
            </div>
            <div className="ms-1 grid flex-1 text-start text-sm">
                <span className="mb-0.5 truncate text-xl leading-tight font-bold tracking-tight">
                    Data Real Estate
                </span>
                <span className="text-[9px] font-semibold tracking-[.18em] text-sidebar-foreground/60 uppercase">
                    Green Vista
                </span>
            </div>
        </>
    );
}
