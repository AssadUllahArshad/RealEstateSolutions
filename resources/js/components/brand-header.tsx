export default function BrandHeader() {
    return (
        <div
            className="brand-header flex items-center justify-between gap-6 rounded-xl border border-[#d7c594] bg-[#fffdf6] px-5 py-4 dark:border-blue-800 dark:bg-[#101c31]"
            aria-label="Data Real Estate · Green Vista Housing Scheme"
        >
            <img
                src="/branding/data-real-estate.svg"
                alt="Data Real Estate"
                className="h-20 w-24 object-contain"
                width="96"
                height="80"
            />
            <div className="text-center text-[#243b30] dark:text-blue-100">
                <p className="text-sm font-semibold sm:text-lg">
                    Data Real Estate
                </p>
                <p className="mt-1 text-xs">Green Vista Housing Scheme</p>
            </div>
            <img
                src="/branding/green-vista.svg"
                alt="Green Vista Housing Scheme"
                className="h-20 w-28 object-contain"
                width="112"
                height="80"
            />
        </div>
    );
}
