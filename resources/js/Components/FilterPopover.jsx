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
        <div className="filter-popover" ref={container}>
            <button
                type="button"
                className={`filter-trigger ${activeCount > 0 ? 'has-filters' : ''}`}
                aria-expanded={open}
                aria-haspopup="dialog"
                onClick={() => setOpen((current) => !current)}
            >
                <FilterIcon />
                {label}
                {activeCount > 0 && <span>{activeCount}</span>}
            </button>

            {open && (
                <section
                    className="filter-popover-panel"
                    role="dialog"
                    aria-label={label}
                >
                    <header>
                        <div>
                            <small>REFINAR LISTAGEM</small>
                            <strong>{label}</strong>
                        </div>
                        <button
                            type="button"
                            aria-label="Fechar filtros"
                            onClick={() => setOpen(false)}
                        >
                            ×
                        </button>
                    </header>
                    {children}
                </section>
            )}
        </div>
    );
}

function FilterIcon() {
    return (
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M4 6h16M7 12h10M10 18h4" />
        </svg>
    );
}
