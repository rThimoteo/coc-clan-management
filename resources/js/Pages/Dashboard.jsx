import ActiveWarAlert from '@/Components/ActiveWarAlert';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

const cutBadge =
    'inline-flex items-center gap-1 border px-2 py-1 text-xs font-black [clip-path:polygon(0_0,calc(100%-0.45rem)_0,100%_0.45rem,100%_100%,0.45rem_100%,0_calc(100%-0.45rem))]';
const cutPanel =
    '[clip-path:polygon(0_0,calc(100%-0.85rem)_0,100%_0.85rem,100%_100%,0.85rem_100%,0_calc(100%-0.85rem))]';

export default function Dashboard({ activeWar, clan, metrics, recentWars }) {
    const cards = [
        {
            label: 'Membros ativos',
            value: metrics.activeMembers,
            note: clan?.members_synced_at
                ? `Atualizado em ${formatDate(clan.members_synced_at)}`
                : 'Sincronização pendente',
        },
        {
            label: 'Guerras no mês',
            value: metrics.monthlyWars,
            note: 'Encerradas ou em andamento neste mês',
        },
        {
            label: 'Taxa de vitória',
            value: metrics.winRate === null ? '—' : `${formatNumber(metrics.winRate)}%`,
            note:
                metrics.winRate === null
                    ? 'Sem guerras concluídas no período'
                    : 'Sobre as guerras concluídas no mês',
        },
    ];

    return (
        <AuthenticatedLayout
            header="Visão geral"
            eyebrow="PAINEL DE COMANDO"
        >
            <Head title="Visão geral" />

            <ActiveWarAlert war={activeWar} />

            <section className="grid gap-4 md:grid-cols-3">
                {cards.map((metric, index) => (
                    <article
                        className={`border border-white/10 bg-[linear-gradient(145deg,rgba(245,158,11,0.08),transparent_45%),rgba(20,23,28,0.78)] p-5 ${cutPanel}`}
                        key={metric.label}
                    >
                        <div className="font-display text-xs font-black text-amber-400">0{index + 1}</div>
                        <p className="mt-4 text-xs uppercase tracking-[0.12em] text-zinc-500">{metric.label}</p>
                        <strong className="mt-1 block font-display text-3xl font-black text-zinc-100">{metric.value}</strong>
                        <span className="mt-3 block text-xs leading-5 text-zinc-500">{metric.note}</span>
                    </article>
                ))}
            </section>

            <section className={`mt-3 overflow-hidden border border-white/10 bg-zinc-900/80 ${cutPanel}`}>
                <header className="flex flex-col gap-4 border-b border-white/10 bg-zinc-950/25 p-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p className="section-kicker">RADAR DE BATALHAS</p>
                        <h2 className="mt-1 font-display text-xl font-black text-zinc-100">Guerras recentes</h2>
                        <p className="mt-1 text-sm leading-6 text-zinc-500">Os últimos confrontos registrados pelo Clan Hub.</p>
                    </div>
                    <Link
                        className={`inline-flex items-center justify-center border border-amber-400/30 bg-amber-400/10 px-3 py-2 text-xs font-black uppercase tracking-[0.12em] text-amber-300 transition hover:bg-amber-400 hover:text-zinc-950 ${cutPanel}`}
                        href={route('wars.index')}
                    >
                        Ver histórico completo →
                    </Link>
                </header>

                {recentWars.length === 0 ? (
                    <div className="grid gap-3 p-5 text-zinc-500 sm:grid-cols-[auto_minmax(0,1fr)] sm:items-center">
                        <span className="font-display text-4xl font-black text-zinc-800">VS</span>
                        <div>
                            <strong className="text-sm font-black text-zinc-200">Nenhuma guerra registrada.</strong>
                            <p className="mt-1 text-sm text-zinc-500">Sincronize o histórico para alimentar o painel.</p>
                        </div>
                    </div>
                ) : (
                    <div className="divide-y divide-white/10">
                        {recentWars.map((war) => (
                            <article
                                className="grid gap-3 px-4 py-3 transition hover:bg-amber-400/5 sm:grid-cols-[auto_minmax(0,1fr)_auto_minmax(5rem,auto)] sm:items-center"
                                key={war.id}
                            >
                                <span className={`${cutBadge} ${resultClass(war.result)}`}>
                                    {resultLabel(war.result, war.state)}
                                </span>
                                <div>
                                    <strong className="block text-sm font-black text-zinc-100">{war.opponent_name}</strong>
                                    <small className="mt-0.5 block text-xs text-zinc-600">{formatDate(war.end_time)}</small>
                                </div>
                                <div className="flex items-center gap-1.5 font-display">
                                    <strong className="text-amber-300">{war.clan_stars}</strong>
                                    <span className="text-xs text-zinc-600">×</span>
                                    <b className="text-zinc-400">{war.opponent_stars}</b>
                                </div>
                                {war.has_details ? (
                                    <Link
                                        className={`inline-flex justify-center border border-amber-400/30 bg-amber-400/10 px-3 py-2 text-xs font-black uppercase tracking-[0.12em] text-amber-300 transition hover:bg-amber-400 hover:text-zinc-950 sm:justify-self-end ${cutPanel}`}
                                        href={route('wars.show', war.id)}
                                    >
                                        Detalhes
                                    </Link>
                                ) : (
                                    <span className="text-xs font-black uppercase tracking-[0.12em] text-zinc-600 sm:justify-self-end">
                                        Sem detalhes
                                    </span>
                                )}
                            </article>
                        ))}
                    </div>
                )}
            </section>
        </AuthenticatedLayout>
    );
}

function resultClass(result) {
    if (result === 'win') {
        return 'border-amber-400/30 bg-amber-400/10 text-amber-300';
    }

    if (result === 'lose') {
        return 'border-rose-400/30 bg-rose-400/10 text-rose-200';
    }

    return 'border-zinc-500/30 bg-zinc-500/10 text-zinc-400';
}

function resultLabel(result, state) {
    if (state === 'preparation') {
        return 'Preparação';
    }

    return {
        win: 'Vitória',
        lose: 'Derrota',
        tie: 'Empate',
    }[result] ?? 'Em andamento';
}

function formatDate(value) {
    return new Intl.DateTimeFormat('pt-BR', {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(value));
}

function formatNumber(value) {
    return new Intl.NumberFormat('pt-BR', {
        maximumFractionDigits: 1,
    }).format(value);
}
