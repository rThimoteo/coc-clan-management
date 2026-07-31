export default function Checkbox({ className = '', ...props }) {
    return (
        <input
            {...props}
            type="checkbox"
            className={
                'border-white/20 bg-zinc-950 text-amber-400 shadow-none focus:ring-amber-400/30 ' +
                className
            }
        />
    );
}
