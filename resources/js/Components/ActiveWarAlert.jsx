import LiveWarCountdown from '@/Components/LiveWarCountdown';
import { Link } from '@inertiajs/react';

export default function ActiveWarAlert({ war }) {
    if (!war) {
        return null;
    }

    const destination = war.has_details
        ? route('wars.show', war.id)
        : route('wars.index');
    const isPreparation = war.state === 'preparation';

    return (
        <aside
            className={`active-war-alert ${isPreparation ? 'is-preparation' : ''}`}
            role="status"
            aria-live="polite"
        >
            <div className="active-war-signal" aria-hidden="true">
                <span />
            </div>
            <div className="active-war-copy">
                <p>{isPreparation ? 'GUERRA EM PREPARAÇÃO' : 'GUERRA ATIVA'}</p>
                <strong>
                    {isPreparation
                        ? `Preparação contra ${war.opponent_name}`
                        : `Batalha em andamento contra ${war.opponent_name}`}
                </strong>
                <LiveWarCountdown
                    endTime={isPreparation ? war.start_time : war.end_time}
                    expiredLabel={isPreparation ? 'Começando...' : 'Encerrando...'}
                    compact
                />
            </div>
            <Link href={destination}>
                {isPreparation ? 'Ver preparação' : 'Acompanhar guerra'}
                <span aria-hidden="true">→</span>
            </Link>
        </aside>
    );
}
