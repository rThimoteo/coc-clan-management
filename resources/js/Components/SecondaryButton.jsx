export default function SecondaryButton({
    type = 'button',
    className = '',
    disabled,
    children,
    ...props
}) {
    return (
        <button
            {...props}
            type={type}
            className={
                `inline-flex items-center justify-center border border-white/15 bg-zinc-800 px-4 py-2 text-xs font-black uppercase tracking-[0.16em] text-zinc-200 transition hover:border-amber-400/40 hover:bg-zinc-700 hover:text-white focus:outline-none focus:ring-2 focus:ring-amber-400/50 disabled:cursor-not-allowed disabled:opacity-50 [clip-path:polygon(0_0,calc(100%-0.45rem)_0,100%_0.45rem,100%_100%,0.45rem_100%,0_calc(100%-0.45rem))] ${
                    disabled && 'opacity-25'
                } ` + className
            }
            disabled={disabled}
        >
            {children}
        </button>
    );
}
