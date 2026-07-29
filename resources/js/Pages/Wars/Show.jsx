import LiveWarCountdown from '@/Components/LiveWarCountdown';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

export default function Show({ war, clan, isActive }) {
    const { auth, errors, status, syncSummary } = usePage().props;
    const { post, processing } = useForm({});
    const [defenseMember, setDefenseMember] = useState(null);
    const clanMembers = war.members.filter((member) => member.side === 'clan');
    const opponentMembers = war.members.filter((member) => member.side === 'opponent');
    const opponentByTag = useMemo(
        () => Object.fromEntries(opponentMembers.map((member) => [member.player_tag, member])),
        [opponentMembers],
    );

    useEffect(() => {
        const close = (event) => event.key === 'Escape' && setDefenseMember(null);
        document.addEventListener('keydown', close);
        return () => document.removeEventListener('keydown', close);
    }, []);

    const attacksFor = (tag) =>
        war.attacks
            .filter((attack) => attack.attacker_tag === tag)
            .sort((a, b) => a.attack_order - b.attack_order);

    const defensesFor = (tag) =>
        war.attacks
            .filter((attack) => attack.defender_tag === tag)
            .sort((a, b) => a.attack_order - b.attack_order);

    const sync = () => {
        post(route('wars.sync'), { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout
            header={`Guerra contra ${war.opponent_name}`}
            eyebrow="RELATÓRIO DE BATALHA"
        >
            <Head title={`Guerra contra ${war.opponent_name}`} />

            <div className="war-details-toolbar">
                <Link href={route('wars.index')} className="war-back-link">
                    ← Voltar para guerras
                </Link>

                {['admin', 'leader'].includes(auth.user.role) && (
                    <button
                        className={`war-refresh-button ${isActive ? 'is-live' : ''}`}
                        onClick={sync}
                        disabled={processing || !clan}
                    >
                        <SyncIcon spinning={processing} />
                        {processing ? 'Atualizando...' : 'Atualizar guerra'}
                    </button>
                )}
            </div>

            {(status === 'wars-synced' || errors.sync) && (
                <div
                    className={`sync-feedback war-details-feedback ${errors.sync ? 'is-error' : 'is-success'}`}
                    role="status"
                >
                    {errors.sync ? (
                        errors.sync
                    ) : (
                        <>
                            Guerra atualizada.
                            <span>
                                {syncSummary?.updated ?? 0} registros atualizados ·{' '}
                                {syncSummary?.detailed ?? 0} com detalhes
                            </span>
                        </>
                    )}
                </div>
            )}

            <section className="war-scoreboard">
                <ClanScore
                    name={clan?.name ?? 'Nosso clã'}
                    tag={clan?.tag}
                    badge={clan?.badge_url}
                    stars={war.clan_stars}
                    destruction={war.clan_destruction_percentage}
                />
                <div className="war-scoreboard-center">
                    {isActive && (
                        <>
                            <div className="war-live-badge" role="status">
                                <span aria-hidden="true" />
                                AO VIVO
                            </div>
                            <LiveWarCountdown endTime={war.end_time} />
                        </>
                    )}
                    <span>{war.team_size} × {war.team_size}</span>
                    <strong>{war.clan_stars} <i>×</i> {war.opponent_stars}</strong>
                    <small>{resultLabel(war.result)}</small>
                </div>
                <ClanScore
                    name={war.opponent_name}
                    tag={war.opponent_tag}
                    badge={war.opponent_badge_url}
                    stars={war.opponent_stars}
                    destruction={war.opponent_destruction_percentage}
                    opponent
                />
            </section>

            <section className="members-panel war-roster-panel">
                <header className="members-panel-header">
                    <div>
                        <p className="section-kicker">DESEMPENHO INDIVIDUAL</p>
                        <h2>Ataques do nosso clã</h2>
                        <p>
                            Os ataques aparecem na ordem em que foram realizados.
                        </p>
                    </div>
                </header>

                <div className="members-table-wrap">
                    <table className="members-table war-members-table">
                        <thead>
                            <tr>
                                <th>Posição</th>
                                <th>Membro</th>
                                <th>Ataque 1</th>
                                <th>Ataque 2</th>
                                <th>Defesas</th>
                            </tr>
                        </thead>
                        <tbody>
                            {clanMembers.map((member) => {
                                const attacks = attacksFor(member.player_tag);
                                const defenses = defensesFor(member.player_tag);

                                return (
                                    <tr key={member.id}>
                                        <td>
                                            <span className="map-position">#{member.map_position}</span>
                                        </td>
                                        <td>
                                            <strong className='mr-2'>{member.name}</strong>
                                            <small>
                                                CV {member.townhall_level} · {member.player_tag}
                                            </small>
                                        </td>
                                        <td>
                                            <AttackResult
                                                attack={attacks[0]}
                                                defender={opponentByTag[attacks[0]?.defender_tag]}
                                            />
                                        </td>
                                        <td>
                                            <AttackResult
                                                attack={attacks[1]}
                                                defender={opponentByTag[attacks[1]?.defender_tag]}
                                            />
                                        </td>
                                        <td>
                                            <button
                                                className="defenses-button"
                                                onClick={() =>
                                                    setDefenseMember({
                                                        ...member,
                                                        defenses,
                                                    })
                                                }
                                            >
                                                Ver defesas
                                                <span>{defenses.length}</span>
                                            </button>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            </section>

            {defenseMember && (
                <DefenseModal
                    member={defenseMember}
                    opponents={opponentByTag}
                    onClose={() => setDefenseMember(null)}
                />
            )}
        </AuthenticatedLayout>
    );
}

function ClanScore({ name, tag, badge, stars, destruction, opponent = false }) {
    return (
        <div className={`war-clan-score ${opponent ? 'is-opponent' : ''}`}>
            {badge ? <img src={badge} alt="" /> : <div className="war-badge-fallback">CM</div>}
            <div>
                <small>{opponent ? 'OPONENTE' : 'NOSSO CLÃ'}</small>
                <h2>{name}</h2>
                <p>{tag}</p>
                <strong>★ {stars} · {formatPercentage(destruction)}</strong>
            </div>
        </div>
    );
}

function AttackResult({ attack, defender }) {
    if (!attack) {
        return <span className="attack-empty">Não realizado</span>;
    }

    return (
        <div className="attack-result">
            <span className={`attack-stars is-${attack.stars}`}>
                {Array.from({ length: 3 }, (_, index) => (
                    <i key={index}>{index < attack.stars ? '★' : ''}</i>
                ))}
            </span>
            <small>
                {formatPercentage(attack.destruction_percentage)}
                {defender ? ` · #${defender.map_position}` : ''}
            </small>
        </div>
    );
}

function DefenseModal({ member, opponents, onClose }) {
    return (
        <div className="war-modal-backdrop" onMouseDown={onClose}>
            <section
                className="war-modal"
                role="dialog"
                aria-modal="true"
                aria-labelledby="defense-modal-title"
                onMouseDown={(event) => event.stopPropagation()}
            >
                <header>
                    <div>
                        <p className="section-kicker">ATAQUES RECEBIDOS</p>
                        <h2 id="defense-modal-title">{member.name}</h2>
                    </div>
                    <button onClick={onClose} aria-label="Fechar">×</button>
                </header>

                {member.defenses.length === 0 ? (
                    <div className="war-modal-empty">
                        Nenhum ataque registrado contra esta vila.
                    </div>
                ) : (
                    <div className="defense-list">
                        {member.defenses.map((defense) => {
                            const attacker = opponents[defense.attacker_tag];
                            return (
                                <article key={defense.id}>
                                    <span className="defense-order">#{defense.attack_order}</span>
                                    <div>
                                        <strong>{attacker?.name ?? defense.attacker_tag}</strong>
                                        <small>
                                            {attacker ? `Posição #${attacker.map_position}` : defense.attacker_tag}
                                        </small>
                                    </div>
                                    <AttackResult attack={defense} />
                                </article>
                            );
                        })}
                    </div>
                )}
            </section>
        </div>
    );
}

function resultLabel(result) {
    return { win: 'VITÓRIA', lose: 'DERROTA', tie: 'EMPATE' }[result] ?? 'EM ANDAMENTO';
}

function formatPercentage(value) {
    return `${new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 2 }).format(value)}%`;
}

function SyncIcon({ spinning }) {
    return (
        <svg className={spinning ? 'is-spinning' : ''} viewBox="0 0 24 24" aria-hidden="true">
            <path d="M20 7h-5V2M4 17h5v5M19 12a7 7 0 0 0-12-5L5 9M5 12a7 7 0 0 0 12 5l2-2" />
        </svg>
    );
}
