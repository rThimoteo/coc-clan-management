import { Link } from '@inertiajs/react';

export default function ResponsiveNavLink({
    active = false,
    className = '',
    children,
    ...props
}) {
    return (
        <Link
            {...props}
            className={`flex w-full items-start border-l-4 py-2 pe-4 ps-3 ${
                active
                    ? 'border-amber-400 bg-amber-400/10 text-amber-300 focus:border-amber-300 focus:bg-amber-400/15'
                    : 'border-transparent text-zinc-400 hover:border-zinc-600 hover:bg-zinc-900 hover:text-zinc-100 focus:border-zinc-600 focus:bg-zinc-900'
            } text-base font-bold transition focus:outline-none ${className}`}
        >
            {children}
        </Link>
    );
}
