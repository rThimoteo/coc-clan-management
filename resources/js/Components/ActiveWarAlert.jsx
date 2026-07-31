import LiveWarCountdown from '@/Components/LiveWarCountdown';
import { Link } from '@inertiajs/react';

const cutPanel =
    '[clip-path:polygon(0_0,calc(100%-0.85rem)_0,100%_0.85rem,100%_100%,0.85rem_100%,0_calc(100%-0.85rem))]';

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
            className={`relative grid gap-4 overflow-hidden border border-rose-400/40 bg-[linear-gradient(90deg,rgba(251,113,133,0.16),transparent_70%),rgba(20,23,28,0.86)] p-4 sm:grid-cols-[auto_minmax(0,1fr)_auto] sm:px-5 ${cutPanel}`}
            role="status"
            aria-live="polite"
        >
            <div
                className="grid h-11 w-11 place-items-center self-center border border-rose-400/45 bg-rose-400/15 [clip-path:polygon(50%_0,100%_50%,50%_100%,0_50%)]"
                aria-hidden="true"
            >
                <span className="block h-3 w-3 animate-pulse bg-rose-400 shadow-[0_0_0_0.25rem_rgba(251,113,133,0.15)]" />
            </div>
            <div>
                <p className="text-xs font-black uppercase tracking-[0.16em] text-rose-200">
                    {isPreparation ? 'GUERRA EM PREPARAÇÃO' : 'GUERRA ATIVA'}
                </p>
                <strong className="mt-1 block font-display text-xl font-black text-zinc-100">
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
            <Link
                className={`inline-flex items-center justify-center gap-2 self-center border border-amber-400/35 bg-amber-400/10 px-4 py-3 text-xs font-black uppercase tracking-[0.14em] text-amber-300 transition hover:bg-amber-400 hover:text-zinc-950 sm:justify-self-end ${cutPanel}`}
                href={destination}
            >
                {isPreparation ? 'Ver preparação' : 'Acompanhar guerra'}
                <span aria-hidden="true">→</span>
            </Link>
        </aside>
    );
}
