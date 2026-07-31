import { Link } from '@inertiajs/react';

export default function Pagination({ pagination }) {
    if (pagination.last_page <= 1) {
        return null;
    }

    return (
        <nav
            className="mt-4 flex flex-col gap-3 border border-white/10 bg-zinc-900/60 p-3 text-xs text-zinc-400 [clip-path:polygon(0_0,calc(100%-0.65rem)_0,100%_0.65rem,100%_100%,0.65rem_100%,0_calc(100%-0.65rem))] sm:flex-row sm:items-center sm:justify-between"
            aria-label="Paginação"
        >
            <p className="font-bold">
                Exibindo <strong>{pagination.from}–{pagination.to}</strong> de{' '}
                <strong>{pagination.total}</strong>
            </p>

            <div className="flex flex-wrap gap-1.5">
                {pagination.links.map((link, index) => {
                    const label =
                        index === 0
                            ? 'Anterior'
                            : index === pagination.links.length - 1
                              ? 'Próxima'
                              : link.label;

                    if (!link.url) {
                        return (
                            <span
                                key={`${label}-${index}`}
                                aria-disabled="true"
                                className="border border-white/5 bg-zinc-950/40 px-3 py-2 text-zinc-600"
                            >
                                {label}
                            </span>
                        );
                    }

                    return (
                        <Link
                            key={`${label}-${index}`}
                            href={link.url}
                            className={`border px-3 py-2 font-black transition ${
                                link.active
                                    ? 'border-amber-400 bg-amber-400 text-zinc-950'
                                    : 'border-white/10 bg-zinc-950/60 text-zinc-300 hover:border-amber-400/50 hover:text-white'
                            }`}
                            aria-current={link.active ? 'page' : undefined}
                            preserveScroll
                        >
                            {label}
                        </Link>
                    );
                })}
            </div>
        </nav>
    );
}
