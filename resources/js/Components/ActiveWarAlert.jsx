import LiveWarCountdown from '@/Components/LiveWarCountdown';
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
                <LiveWarCountdown endTime={war.end_time} compact />
            </div>
            <Link href={destination}>
                Acompanhar guerra
                <span aria-hidden="true">→</span>
            </Link>
        </aside>
    );
}
