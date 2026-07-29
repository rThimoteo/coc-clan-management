import ActiveWarAlert from '@/Components/ActiveWarAlert';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

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

            <section className="dashboard-grid">
                {cards.map((metric, index) => (
                    <article className="metric-card" key={metric.label}>
                        <div className="metric-index">0{index + 1}</div>
                        <p>{metric.label}</p>
                        <strong>{metric.value}</strong>
                        <span>{metric.note}</span>
                    </article>
                ))}
            </section>

            <section className="dashboard-activity">
                <header>
                    <div>
                        <p className="section-kicker">RADAR DE BATALHAS</p>
                        <h2>Guerras recentes</h2>
                        <p>Os últimos confrontos registrados pelo Clan Hub.</p>
                    </div>
                    <Link href={route('wars.index')}>Ver histórico completo →</Link>
                </header>

                {recentWars.length === 0 ? (
                    <div className="dashboard-activity-empty">
                        <span>VS</span>
                        <div>
                            <strong>Nenhuma guerra registrada.</strong>
                            <p>Sincronize o histórico para alimentar o painel.</p>
                        </div>
                    </div>
                ) : (
                    <div className="dashboard-war-list">
                        {recentWars.map((war) => (
                            <article key={war.id}>
                                <span className={`war-result is-${war.result ?? 'pending'}`}>
                                    {resultLabel(war.result)}
                                </span>
                                <div>
                                    <strong>{war.opponent_name}</strong>
                                    <small>{formatDate(war.end_time)}</small>
                                </div>
                                <div className="dashboard-war-score">
                                    <strong>{war.clan_stars}</strong>
                                    <span>×</span>
                                    <b>{war.opponent_stars}</b>
                                </div>
                                {war.has_details ? (
                                    <Link href={route('wars.show', war.id)}>
                                        Detalhes
                                    </Link>
                                ) : (
                                    <span className="dashboard-war-unavailable">
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

function resultLabel(result) {
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
