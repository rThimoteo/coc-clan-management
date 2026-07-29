import { Link } from '@inertiajs/react';

export default function ActiveWarAlert({ war }) {
    if (!war) {
        return null;
    }

    const destination = war.has_details
        ? route('wars.show', war.id)
        : route('wars.index');

    return (
        <aside className="active-war-alert" role="status" aria-live="polite">
            <div className="active-war-signal" aria-hidden="true">
                <span />
            </div>
            <div className="active-war-copy">
                <p>GUERRA ATIVA</p>
                <strong>Batalha em andamento contra {war.opponent_name}</strong>
                <span>Encerra em {formatDate(war.end_time)}</span>
            </div>
            <Link href={destination}>
                Acompanhar guerra
                <span aria-hidden="true">→</span>
            </Link>
        </aside>
    );
}

function formatDate(value) {
    return new Intl.DateTimeFormat('pt-BR', {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(value));
}
