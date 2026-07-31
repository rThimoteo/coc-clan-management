import { Link } from '@inertiajs/react';

export default function NavLink({
    active = false,
    className = '',
    children,
    ...props
}) {
    return (
        <Link
            {...props}
            className={
                'inline-flex items-center border-b-2 px-1 pt-1 text-sm font-bold leading-5 transition focus:outline-none ' +
                (active
                    ? 'border-amber-400 text-amber-300 focus:border-amber-300'
                    : 'border-transparent text-zinc-500 hover:border-zinc-600 hover:text-zinc-200 focus:border-zinc-600 focus:text-zinc-200') +
                className
            }
        >
            {children}
        </Link>
    );
}
