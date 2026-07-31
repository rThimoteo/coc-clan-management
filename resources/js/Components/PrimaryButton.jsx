export default function PrimaryButton({
    className = '',
    disabled,
    children,
    ...props
}) {
    return (
        <button
            {...props}
            className={
                `inline-flex items-center justify-center border border-amber-400/40 bg-amber-500 px-4 py-2 text-xs font-black uppercase tracking-[0.16em] text-zinc-950 transition hover:bg-amber-300 focus:outline-none focus:ring-2 focus:ring-amber-400/60 disabled:cursor-not-allowed disabled:opacity-50 [clip-path:polygon(0_0,calc(100%-0.45rem)_0,100%_0.45rem,100%_100%,0.45rem_100%,0_calc(100%-0.45rem))] ${
                    disabled && 'opacity-25'
                } ` + className
            }
            disabled={disabled}
        >
            {children}
        </button>
    );
}
