export default function InputLabel({
    value,
    className = '',
    children,
    ...props
}) {
    return (
        <label
            {...props}
            className={
                `block text-xs font-black uppercase tracking-[0.14em] text-amber-400 ` +
                className
            }
        >
            {value ? value : children}
        </label>
    );
}
