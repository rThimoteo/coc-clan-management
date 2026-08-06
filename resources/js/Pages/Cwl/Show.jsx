import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

const stateLabels = {
    preparation: 'Preparação',
    inWar: 'Em andamento',
    ended: 'Encerrada',
};

const warStateLabels = {
    preparation: 'Preparação',
    inWar: 'Em andamento',
    warEnded: 'Encerrada',
    ended: 'Encerrada',
};

const stateBadge =
    'inline-flex items-center gap-1 border px-2 py-1 text-xs font-black [clip-path:polygon(0_0,calc(100%-0.45rem)_0,100%_0.45rem,100%_100%,0.45rem_100%,0_calc(100%-0.45rem))]';

export default function Show({ clan, league, memberPerformance, standings }) {
    const [activeTab, setActiveTab] = useState('standings');
    const [openRounds, setOpenRounds] = useState(
        () => new Set(getInitiallyOpenRoundIds(league.rounds)),
    );

    const setRoundOpen = (roundId, isOpen) => {
        setOpenRounds((current) => {
            const next = new Set(current);

            if (isOpen) {
                next.add(roundId);
            } else {
                next.delete(roundId);
            }

            return next;
        });
    };

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

                <div className="cwl-tabs" role="tablist" aria-label="Detalhes da CWL">
                    <TabButton activeTab={activeTab} id="standings" onSelect={setActiveTab}>
                        Classificação
                    </TabButton>
                    <TabButton activeTab={activeTab} id="rounds" onSelect={setActiveTab}>
                        Rodadas
                    </TabButton>
                    <TabButton activeTab={activeTab} id="performance" onSelect={setActiveTab}>
                        Meu clã
                    </TabButton>
                </div>

                {activeTab === 'standings' && (
                    <div id="cwl-panel-standings" role="tabpanel" aria-labelledby="cwl-tab-standings">
                        <Standings standings={standings} ownClanTag={clan.tag} />
                    </div>
                )}

                {activeTab === 'rounds' && (league.rounds.length === 0 ? (
                    <div className="cwl-detail-unavailable">
                        <strong>Resumo preservado.</strong>
                        <p>
                            As rodadas detalhadas desta temporada não foram
                            capturadas enquanto estavam disponíveis na API.
                        </p>
                    </div>
                ) : (
                    <div className="cwl-tab-panel" id="cwl-panel-rounds" role="tabpanel" aria-labelledby="cwl-tab-rounds">
                        <header className="cwl-tab-heading">
                            <div>
                                <p className="section-kicker">MAPA DE CONFRONTOS</p>
                                <h3>Rodadas da liga</h3>
                            </div>
                            <small>{league.rounds.length} rodadas registradas</small>
                        </header>
                        <div className="cwl-rounds">
                        {league.rounds.map((round) => {
                            const ownWar = round.wars.find(
                                (entry) =>
                                    entry.clan_tag === clan.tag ||
                                    entry.opponent_tag === clan.tag,
                            );
                            const summary = getRoundSummary(ownWar, clan.tag);

                            return (
                            <details
                                className={`cwl-round ${summary.tone}`}
                                key={round.id}
                                onToggle={(event) =>
                                    setRoundOpen(round.id, event.currentTarget.open)
                                }
                                open={openRounds.has(round.id)}
                            >
                                <summary>
                                    <span className="cwl-round-number">
                                        {String(round.round_number).padStart(2, '0')}
                                    </span>
                                    <span className="cwl-round-title">
                                        <strong>Rodada {round.round_number}</strong>
                                        <small>{summary.label}</small>
                                    </span>
                                    <span className="cwl-round-score">
                                        <small>Nosso resultado</small>
                                        <strong>{summary.score}</strong>
                                    </span>
                                    <span className="cwl-round-opponent">
                                        <small>{summary.opponentLabel}</small>
                                        <strong>{summary.opponent}</strong>
                                    </span>
                                    <span className="cwl-round-chevron" aria-hidden="true">⌄</span>
                                </summary>
                                <div className="cwl-round-content">
                                    {round.wars.every(
                                        (entry) => entry.is_placeholder,
                                    ) ? (
                                        <div className="cwl-match is-pending">
                                            <span>Confrontos ainda não definidos pela API</span>
                                        </div>
                                    ) : (
                                        round.wars.map((entry) => (
                                            <RoundWar
                                                entry={entry}
                                                key={entry.id}
                                                leagueId={league.id}
                                                ownClanTag={clan.tag}
                                            />
                                        ))
                                    )}
                                </div>
                            </details>
                            );
                        })}
                        </div>
                    </div>
                ))}

                {activeTab === 'performance' && (
                    <MemberPerformance
                        clanName={clan.name ?? clan.tag}
                        members={memberPerformance}
                    />
                )}
            </section>
        </AuthenticatedLayout>
    );
}

function TabButton({ activeTab, children, id, onSelect }) {
    const active = activeTab === id;

    return (
        <button
            aria-controls={`cwl-panel-${id}`}
            aria-selected={active}
            className={active ? 'is-active' : undefined}
            id={`cwl-tab-${id}`}
            onClick={() => onSelect(id)}
            onKeyDown={(event) => navigateTabs(event, id, onSelect)}
            role="tab"
            tabIndex={active ? 0 : -1}
        >
            {children}
        </button>
    );
}

function navigateTabs(event, currentId, onSelect) {
    const tabs = ['standings', 'rounds', 'performance'];
    const currentIndex = tabs.indexOf(currentId);
    const targetIndex = {
        ArrowLeft: (currentIndex - 1 + tabs.length) % tabs.length,
        ArrowRight: (currentIndex + 1) % tabs.length,
        Home: 0,
        End: tabs.length - 1,
    }[event.key];

    if (targetIndex === undefined) return;

    event.preventDefault();
    const target = tabs[targetIndex];
    onSelect(target);
    requestAnimationFrame(() => document.getElementById(`cwl-tab-${target}`)?.focus());
}

function MemberPerformance({ clanName, members }) {
    return (
        <section className="cwl-member-performance" id="cwl-panel-performance" role="tabpanel" aria-labelledby="cwl-tab-performance">
            <header className="cwl-tab-heading">
                <div>
                    <p className="section-kicker">DESEMPENHO NESTA CWL</p>
                    <h3>{clanName}</h3>
                </div>
                <small>Somente guerras desta temporada</small>
            </header>

            {members.length === 0 ? (
                <div className="cwl-detail-unavailable">
                    <strong>Nenhum desempenho disponível.</strong>
                    <p>Os membros aparecerão após a primeira guerra detalhada desta CWL.</p>
                </div>
            ) : (
                <div className="cwl-member-performance-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Membro</th>
                                <th>Estrelas</th>
                                <th>Defesa</th>
                                <th>Destruição</th>
                                <th>Ataques</th>
                            </tr>
                        </thead>
                        <tbody>
                            {members.map((member, index) => (
                                <tr key={member.player_tag}>
                                    <td><strong>{index + 1}</strong></td>
                                    <td>
                                        <strong>{member.name}</strong>
                                        <small>{member.player_tag}</small>
                                    </td>
                                    <td className="is-attack-stars">{member.stars} ★</td>
                                    <td className="is-defense-stars">{member.defensive_stars} ★</td>
                                    <td className="is-destruction">{formatNumber(member.destruction)}</td>
                                    <td>
                                        <strong>{member.attacks_made}</strong>
                                        <span> / {member.attacks_available}</span>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </section>
    );
}

function Standings({ standings, ownClanTag }) {
    return (
        <section className="cwl-standings">
            <header>
                <div>
                    <p className="section-kicker">CLASSIFICAÇÃO AO VIVO</p>
                    <h3>Tabela da liga</h3>
                </div>
                <small>Vitória vale +10 estrelas</small>
            </header>
            <div className="cwl-standings-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Clã</th>
                            <th>J</th>
                            <th>V</th>
                            <th>E</th>
                            <th>D</th>
                            <th>Estrelas</th>
                            <th>Bônus</th>
                            <th>Total</th>
                            <th>Destruição</th>
                        </tr>
                    </thead>
                    <tbody>
                        {standings.map((standing) => (
                            <tr
                                className={
                                    standing.clan_tag === ownClanTag
                                        ? 'is-own-clan'
                                        : undefined
                                }
                                key={standing.clan_tag}
                            >
                                <td><strong>{standing.position}</strong></td>
                                <td>
                                    <span className="cwl-standing-clan">
                                        {standing.badge_url && (
                                            <img src={standing.badge_url} alt="" />
                                        )}
                                        <span>
                                            <strong>{standing.name}</strong>
                                            <small>{standing.clan_tag}</small>
                                        </span>
                                    </span>
                                </td>
                                <td>{standing.played}</td>
                                <td>{standing.wins}</td>
                                <td>{standing.draws}</td>
                                <td>{standing.losses}</td>
                                <td>{standing.stars}</td>
                                <td className="is-bonus">+{standing.bonus_stars}</td>
                                <td className="is-total">{standing.score}</td>
                                <td>{formatPercentage(standing.destruction_percentage)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </section>
    );
}

function RoundWar({ entry, leagueId, ownClanTag }) {
    if (entry.is_placeholder) {
        return (
            <div className="cwl-match is-pending">
                <span>War tag ainda não definida pela API</span>
            </div>
        );
    }

    if (!entry.clan_tag || !entry.opponent_tag) {
        return (
            <div className="cwl-match is-pending">
                <code>{entry.war_tag}</code>
                <span>Detalhes ainda indisponíveis</span>
            </div>
        );
    }

    return (
        <div className={`cwl-match cwl-match-summary ${entry.war ? 'is-own-match' : ''}`}>
            <MatchSide
                badge={entry.clan_badge_url}
                destruction={entry.clan_destruction_percentage}
                isOwn={entry.clan_tag === ownClanTag}
                outcome={getSideOutcome(entry, 'clan')}
                name={entry.clan_name}
                stars={entry.clan_stars}
                tag={entry.clan_tag}
            />
            <div className="cwl-match-versus">
                <small>{warStateLabels[entry.state] ?? entry.state}</small>
                <strong>×</strong>
                <span>{entry.team_size ? `${entry.team_size} x ${entry.team_size}` : 'CWL'}</span>
                {entry.war && (
                    <Link
                        className="cwl-match-details"
                        href={route('cwl.wars.show', [leagueId, entry.war.id])}
                    >
                        Ver detalhes
                    </Link>
                )}
            </div>
            <MatchSide
                badge={entry.opponent_badge_url}
                destruction={entry.opponent_destruction_percentage}
                isOwn={entry.opponent_tag === ownClanTag}
                outcome={getSideOutcome(entry, 'opponent')}
                name={entry.opponent_name}
                stars={entry.opponent_stars}
                tag={entry.opponent_tag}
                reverse
            />
        </div>
    );
}

function MatchSide({ badge, destruction, isOwn, name, outcome, stars, tag, reverse = false }) {
    return (
        <div className={`cwl-match-side ${reverse ? 'is-reverse' : ''} ${isOwn ? 'is-own' : ''} ${outcome ? `is-${outcome}` : ''}`}>
            {badge && <img src={badge} alt="" />}
            <span>
                <strong>{name ?? tag}</strong>
                <small>{tag}</small>
                <small>{formatPercentage(destruction ?? 0)} destruição</small>
            </span>
            <b>{stars ?? 0}<small>★</small></b>
        </div>
    );
}

function getSideOutcome(entry, side) {
    if (!['warEnded', 'ended'].includes(entry.state)) return null;
    if (!entry.winner_tag) return 'draw';

    const tag = side === 'clan' ? entry.clan_tag : entry.opponent_tag;
    return entry.winner_tag === tag ? 'winner' : 'loser';
}

function getInitiallyOpenRoundIds(rounds) {
    const current = rounds.find((round) => roundHasState(round, 'inWar'))
        ?? rounds.find((round) => roundHasState(round, 'preparation'));

    return current ? [current.id] : [];
}

function roundHasState(round, state) {
    return round.wars.some(
        (entry) => entry.state === state || entry.war?.state === state,
    );
}

function getRoundSummary(entry, ownClanTag) {
    if (!entry?.clan_tag || !entry?.opponent_tag) {
        return {
            label: 'Aguardando confronto',
            opponentLabel: 'Oponente',
            opponent: 'A definir',
            score: '—',
            tone: 'is-pending',
        };
    }

    const ownIsClan = entry.clan_tag === ownClanTag;
    const ownStars = ownIsClan ? entry.clan_stars : entry.opponent_stars;
    const rivalStars = ownIsClan ? entry.opponent_stars : entry.clan_stars;
    const opponent = ownIsClan ? entry.opponent_name : entry.clan_name;
    const ended = ['warEnded', 'ended'].includes(entry.state);
    const won = ended && entry.winner_tag === ownClanTag;
    const drew = ended && !entry.winner_tag;

    return {
        label: ended ? (drew ? 'Empate' : won ? 'Vitória' : 'Derrota') : warStateLabels[entry.state] ?? 'Aguardando',
        opponentLabel: 'Oponente',
        opponent: opponent ?? 'Clã adversário',
        score: ownStars == null || rivalStars == null ? '—' : `${ownStars} × ${rivalStars}`,
        tone: ended ? (drew ? 'is-draw' : won ? 'is-win' : 'is-loss') : 'is-live',
    };
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

function formatNumber(value) {
    return new Intl.NumberFormat('pt-BR', {
        maximumFractionDigits: 2,
    }).format(value);
}
