import LiveWarCountdown from '@/Components/LiveWarCountdown';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

const cutButton =
    'inline-flex items-center gap-2 border border-white/15 bg-zinc-900 px-3 py-2 text-xs font-black uppercase tracking-[0.12em] text-zinc-300 transition hover:border-amber-400/40 hover:text-white [clip-path:polygon(0_0,calc(100%-0.45rem)_0,100%_0.45rem,100%_100%,0.45rem_100%,0_calc(100%-0.45rem))]';
const cutPanel =
    '[clip-path:polygon(0_0,calc(100%-0.55rem)_0,100%_0.55rem,100%_100%,0.55rem_100%,0_calc(100%-0.55rem))]';

export default function Show({ war, clan, isActive, isPreparation }) {
    const { auth, demoMode, errors, status, syncSummary } = usePage().props;
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
                <Link href={route('wars.index')} className="text-sm font-bold text-zinc-500 hover:text-amber-300">
                    ← Voltar para guerras
                </Link>

                {!demoMode && ['admin', 'leader'].includes(auth.user.role) && (
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

            <section className={`${cutPanel} grid gap-px overflow-hidden border border-white/10 bg-white/10 lg:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)]`}>
                <ClanScore
                    name={clan?.name ?? 'Nosso clã'}
                    tag={clan?.tag}
                    badge={clan?.badge_url}
                    stars={war.clan_stars}
                    destruction={war.clan_destruction_percentage}
                />
                <div className="grid min-w-40 content-center bg-zinc-900/90 p-5 text-center">
                    {isActive && (
                        <>
                            <div
                                className={`mx-auto mb-3 inline-flex items-center gap-2 border px-3 py-1 text-[0.66rem] font-black uppercase tracking-[0.16em] ${isPreparation ? 'border-amber-400/35 bg-amber-400/10 text-amber-200' : 'border-rose-400/35 bg-rose-400/10 text-rose-200'} ${cutPanel}`}
                                role="status"
                            >
                                <span className={`h-1.5 w-1.5 ${isPreparation ? 'bg-amber-300' : 'bg-rose-400'}`} aria-hidden="true" />
                                {isPreparation ? 'PREPARAÇÃO' : 'AO VIVO'}
                            </div>
                            <LiveWarCountdown
                                endTime={
                                    isPreparation
                                        ? war.start_time
                                        : war.end_time
                                }
                                label={
                                    isPreparation
                                        ? 'COMEÇA EM'
                                        : 'TERMINA EM'
                                }
                                expiredLabel={
                                    isPreparation
                                        ? 'Começando...'
                                        : 'Encerrando...'
                                }
                            />
                        </>
                    )}
                    <span className="block text-xs font-black uppercase tracking-[0.12em] text-zinc-600">{war.team_size} × {war.team_size}</span>
                    <strong className="my-2 block font-display text-4xl font-black text-zinc-100">{war.clan_stars} <i className="text-sm not-italic text-zinc-600">×</i> {war.opponent_stars}</strong>
                    <small className="block text-xs font-black uppercase tracking-[0.12em] text-zinc-600">{resultLabel(war.result, war.state)}</small>
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
                                            <span className="grid h-8 w-8 place-items-center border border-white/15 bg-zinc-950 font-display text-xs text-zinc-300 [clip-path:polygon(0_0,calc(100%-0.35rem)_0,100%_0.35rem,100%_100%,0.35rem_100%,0_calc(100%-0.35rem))]">#{member.map_position}</span>
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
                                                className={cutButton}
                                                onClick={() =>
                                                    setDefenseMember({
                                                        ...member,
                                                        defenses,
                                                    })
                                                }
                                            >
                                                Ver defesas
                                                <span className="grid h-5 min-w-5 place-items-center border border-white/15 bg-zinc-950 px-1 text-[0.66rem] text-zinc-400">
                                                    {defenses.length}
                                                </span>
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
        <div className={`flex items-center gap-4 bg-zinc-900/90 p-5 ${opponent ? 'text-right lg:[&>img]:order-2 lg:[&>.war-badge-fallback]:order-2' : ''}`}>
            {badge ? <img className="h-14 w-14 shrink-0 object-contain" src={badge} alt="" /> : <div className={`war-badge-fallback grid h-14 w-14 shrink-0 place-items-center border border-white/15 bg-zinc-950 text-xs font-black text-zinc-500 ${cutPanel}`}>CM</div>}
            <div>
                <small className="text-[0.68rem] font-black uppercase tracking-[0.14em] text-zinc-600">{opponent ? 'OPONENTE' : 'NOSSO CLÃ'}</small>
                <h2 className="mt-1 font-display text-xl font-black text-zinc-100">{name}</h2>
                <p className="text-xs text-zinc-600">{tag}</p>
                <strong className="mt-2 block text-xs font-black text-amber-300">★ {stars} · {formatPercentage(destruction)}</strong>
            </div>
        </div>
    );
}

function AttackResult({ attack, defender }) {
    if (!attack) {
        return <span className="text-xs text-zinc-700">Não realizado</span>;
    }

    return (
        <div className="min-w-28">
            <span className="flex gap-0.5 text-lg leading-none text-amber-300">
                {Array.from({ length: 3 }, (_, index) => (
                    <i className="not-italic" key={index}>{index < attack.stars ? '★' : ''}</i>
                ))}
            </span>
            <small className="mt-1 block text-xs text-zinc-500">
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
                    <div className="grid gap-px bg-white/10">
                        {member.defenses.map((defense) => {
                            const attacker = opponents[defense.attacker_tag];
                            return (
                                <article className="grid grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-3 bg-zinc-900 p-4" key={defense.id}>
                                    <span className="font-display text-xs font-black text-amber-300">#{defense.attack_order}</span>
                                    <div>
                                        <strong className="block text-sm font-black text-zinc-100">{attacker?.name ?? defense.attacker_tag}</strong>
                                        <small className="mt-0.5 block text-xs text-zinc-600">
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

function resultLabel(result, state) {
    if (state === 'preparation') {
        return 'Em preparação';
    }

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
