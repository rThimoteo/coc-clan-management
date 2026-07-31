import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

const stateLabels = {
    preparation: 'Preparação',
    inWar: 'Em andamento',
    ended: 'Encerrada',
};

const CWL_ROUNDS = 7;
const stateBadge =
    'inline-flex items-center gap-1 border px-2 py-1 text-xs font-black [clip-path:polygon(0_0,calc(100%-0.45rem)_0,100%_0.45rem,100%_100%,0.45rem_100%,0_calc(100%-0.45rem))]';

export default function Show({ clan, league }) {
    return (
        <AuthenticatedLayout
            header={`CWL ${formatSeason(league.season)}`}
            eyebrow="DETALHES DA TEMPORADA"
        >
            <Head title={`CWL ${formatSeason(league.season)}`} />

            <Link className="text-sm font-bold text-zinc-500 hover:text-amber-300" href={route('cwl.index')}>
                ← Voltar para temporadas
            </Link>

            <section className="cwl-season cwl-season-detail">
                <header>
                    <div>
                        <p className="section-kicker">
                            TEMPORADA {formatSeason(league.season)}
                        </p>
                        <h2>{clan.name ?? clan.tag}</h2>
                    </div>
                    <div className={`${stateBadge} ${league.state === 'inWar' ? 'border-emerald-400/30 bg-emerald-400/10 text-emerald-300' : league.state === 'preparation' ? 'border-amber-400/30 bg-amber-400/10 text-amber-300' : 'border-zinc-500/30 bg-zinc-500/10 text-zinc-400'}`}>
                        {stateLabels[league.state] ?? league.state}
                    </div>
                </header>

                <div className="grid gap-px bg-white/10 text-sm text-zinc-500 sm:grid-cols-4 [&>span]:bg-zinc-900/80 [&>span]:p-4 [&_strong]:mr-1.5 [&_strong]:font-display [&_strong]:text-xl [&_strong]:font-black [&_strong]:text-amber-300">
                    <span>
                        <strong>{league.participants.length}</strong>
                        clãs
                    </span>
                    <span>
                        <strong>{league.rounds.length}</strong>
                        rodadas
                    </span>
                    <span>
                        <strong>{league.clan_stars}</strong>
                        estrelas no resumo
                    </span>
                    <span>
                        <strong>{formatAttacks(league)}</strong>
                        ataques
                    </span>
                </div>

                {league.has_summary && (
                    <div className="cwl-summary-strip">
                        <span>
                            Destruição média
                            <strong>
                                {formatPercentage(
                                    league.clan_destruction_percentage /
                                        CWL_ROUNDS,
                                )}
                            </strong>
                        </span>
                        <span>
                            Tamanho
                            <strong>
                                {league.team_size
                                    ? `${league.team_size}x${league.team_size}`
                                    : '—'}
                            </strong>
                        </span>
                        <span>
                            Encerramento
                            <strong>{formatDate(league.end_time)}</strong>
                        </span>
                    </div>
                )}

                <div className="cwl-participants">
                    {league.participants.map((participant) => (
                        <span key={participant.id}>
                            {participant.badge_url && (
                                <img src={participant.badge_url} alt="" />
                            )}
                            {participant.name}
                            <small>{participant.clan_tag}</small>
                        </span>
                    ))}
                </div>

                {league.rounds.length === 0 ? (
                    <div className="cwl-detail-unavailable">
                        <strong>Resumo preservado.</strong>
                        <p>
                            As rodadas detalhadas desta temporada não foram
                            capturadas enquanto estavam disponíveis na API.
                        </p>
                    </div>
                ) : (
                    <div className="cwl-rounds">
                        {league.rounds.map((round) => (
                            <section key={round.id}>
                                <header>
                                    <span>
                                        {String(round.round_number).padStart(
                                            2,
                                            '0',
                                        )}
                                    </span>
                                    <strong>
                                        Rodada {round.round_number}
                                    </strong>
                                </header>
                                <div>
                                    {round.wars.map((entry) => (
                                        <RoundWar
                                            entry={entry}
                                            key={entry.id}
                                        />
                                    ))}
                                </div>
                            </section>
                        ))}
                    </div>
                )}
            </section>
        </AuthenticatedLayout>
    );
}

function RoundWar({ entry }) {
    if (entry.is_placeholder) {
        return (
            <div className="cwl-match is-pending">
                <span>War tag ainda não definida pela API</span>
            </div>
        );
    }

    if (entry.status === 'unrelated') {
        return (
            <div className="cwl-match is-muted">
                <code>{entry.war_tag}</code>
                <span>Confronto entre outros clãs do grupo</span>
            </div>
        );
    }

    if (!entry.war) {
        return (
            <div className="cwl-match is-pending">
                <code>{entry.war_tag}</code>
                <span>Detalhes ainda indisponíveis</span>
            </div>
        );
    }

    return (
        <div className="cwl-match is-ready">
            <div>
                <small>CONFRONTO DO CLÃ</small>
                <strong>{entry.war.opponent_name}</strong>
                <span>{entry.war.opponent_tag}</span>
            </div>
            <div className="cwl-match-score">
                <strong>{entry.war.clan_stars}</strong>
                <span>estrelas</span>
            </div>
            <Link href={route('wars.show', entry.war.id)}>
                Ver detalhes da guerra
            </Link>
        </div>
    );
}

function formatSeason(season) {
    const [year, month] = season.split('-');

    return `${month}/${year}`;
}

function formatPercentage(value) {
    return `${new Intl.NumberFormat('pt-BR', {
        maximumFractionDigits: 2,
    }).format(value)}%`;
}

function formatDate(value) {
    return new Intl.DateTimeFormat('pt-BR', {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(value));
}

function formatAttacks(league) {
    if (league.clan_attacks === null || !league.team_size) {
        return '—';
    }

    return `${league.clan_attacks}/${league.team_size * CWL_ROUNDS}`;
}
