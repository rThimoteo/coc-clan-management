import { SlidersHorizontal, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

export default function FilterPopover({
    children,
    activeCount = 0,
    label = 'Filtros',
}) {
    const [open, setOpen] = useState(false);
    const container = useRef(null);

    useEffect(() => {
        const close = (event) => {
            if (event.key === 'Escape') {
                setOpen(false);
            }

            if (
                event.type === 'mousedown' &&
                !container.current?.contains(event.target)
            ) {
                setOpen(false);
            }
        };

        document.addEventListener('mousedown', close);
        document.addEventListener('keydown', close);

        return () => {
            document.removeEventListener('mousedown', close);
            document.removeEventListener('keydown', close);
        };
    }, []);

    return (
        <div className="relative" ref={container}>
            <button
                type="button"
                className={`inline-flex items-center gap-2 border px-3 py-2 text-xs font-black uppercase tracking-[0.16em] transition [clip-path:polygon(0_0,calc(100%-0.45rem)_0,100%_0.45rem,100%_100%,0.45rem_100%,0_calc(100%-0.45rem))] ${
                    activeCount > 0
                        ? 'border-amber-400/50 bg-amber-400/15 text-amber-300'
                        : 'border-white/15 bg-zinc-800 text-zinc-300 hover:border-amber-400/40 hover:text-white'
                }`}
                aria-expanded={open}
                aria-haspopup="dialog"
                onClick={() => setOpen((current) => !current)}
            >
                <SlidersHorizontal className="h-4 w-4" />
                {label}
                {activeCount > 0 && (
                    <span className="grid h-5 min-w-5 place-items-center bg-amber-400 px-1 text-[0.65rem] text-zinc-950">
                        {activeCount}
                    </span>
                )}
            </button>

            {open && (
                <section
                    className="absolute right-0 z-50 mt-3 w-80 border border-white/15 bg-zinc-950 p-3 text-zinc-100 shadow-2xl shadow-black/50 [clip-path:polygon(0_0,calc(100%-0.75rem)_0,100%_0.75rem,100%_100%,0.75rem_100%,0_calc(100%-0.75rem))] sm:w-96"
                    role="dialog"
                    aria-label={label}
                >
                    <header className="mb-3 flex items-start justify-between gap-3 border-b border-white/10 pb-3">
                        <div>
                            <small className="block text-[0.66rem] font-black uppercase tracking-[0.16em] text-amber-400">
                                REFINAR LISTAGEM
                            </small>
                            <strong className="mt-1 block text-sm text-white">
                                {label}
                            </strong>
                        </div>
                        <button
                            type="button"
                            aria-label="Fechar filtros"
                            onClick={() => setOpen(false)}
                            className="grid h-8 w-8 place-items-center border border-white/10 bg-zinc-900 text-zinc-400 hover:border-amber-400/40 hover:text-white"
                        >
                            <X className="h-4 w-4" />
                        </button>
                    </header>
                    {children}
                </section>
            )}
        </div>
    );
}
