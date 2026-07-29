import { Link } from '@inertiajs/react';

export default function Pagination({ pagination }) {
    if (pagination.last_page <= 1) {
        return null;
    }

    return (
        <nav className="pagination" aria-label="Paginação">
            <p>
                Exibindo <strong>{pagination.from}–{pagination.to}</strong> de{' '}
                <strong>{pagination.total}</strong>
            </p>

            <div className="pagination-links">
                {pagination.links.map((link, index) => {
                    const label =
                        index === 0
                            ? 'Anterior'
                            : index === pagination.links.length - 1
                              ? 'Próxima'
                              : link.label;

                    if (!link.url) {
                        return (
                            <span key={`${label}-${index}`} aria-disabled="true">
                                {label}
                            </span>
                        );
                    }

                    return (
                        <Link
                            key={`${label}-${index}`}
                            href={link.url}
                            className={link.active ? 'is-active' : ''}
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
