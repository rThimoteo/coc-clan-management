import Pagination from '@/Components/Pagination';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';

const roleLabels = {
    leader: 'Líder',
    coLeader: 'Colíder',
    admin: 'Ancião',
    member: 'Membro',
};

const statusLabels = {
    in: 'No clã',
    out: 'Fora do clã',
};

export default function Show({
    membership,
    metrics,
    series,
    attacks,
    defenses,
    filters,
}) {
    const player = membership.player;
    const applyFilter = (key, value) => {
        router.get(
            route('members.show', membership.id),
            { ...filters, [key]: value },
            { preserveScroll: true },
        );
    };

    return (
        <AuthenticatedLayout
            header={player.name}
            eyebrow="DESEMPENHO DO JOGADOR"
        >
            <Head title={`Desempenho · ${player.name}`} />

            <Link className="war-back-link" href={route('members.index')}>
                ← Voltar para membros
            </Link>

            <section className="player-performance-hero">
                <div className="player-performance-identity">
                    <span>CV {player.town_hall_level ?? '—'}</span>
                    <div>
                        <small>{player.player_tag}</small>
                        <h2>{player.name}</h2>
                        <p>
                            {roleLabels[membership.role] ??
                                membership.role ??
                                'Sem cargo'}{' '}
                            · {statusLabels[membership.status.slug]}
                        </p>
                    </div>
                </div>
                <div className="player-performance-filters">
                    <label>
                        <span>Tipo</span>
                        <select
                            value={filters.type}
                            onChange={(event) =>
                                applyFilter('type', event.target.value)
                            }
                        >
                            <option value="all">Todas</option>
                            <option value="regular">Guerras regulares</option>
                            <option value="cwl">CWL</option>
                        </select>
                    </label>
                    <label>
                        <span>Janela</span>
                        <select
                            value={filters.window}
                            onChange={(event) =>
                                applyFilter('window', event.target.value)
                            }
                        >
                            <option value="5">5 guerras</option>
                            <option value="10">10 guerras</option>
                            <option value="20">20 guerras</option>
                            <option value="all">Todas</option>
                        </select>
                    </label>
                </div>
            </section>

            <section className="player-metric-grid">
                <MetricCard
                    index="01"
                    label="Ataques utilizados"
                    value={`${metrics.attacks_used}/${metrics.attacks_available}`}
                    note={`${metrics.wars} guerras na amostra`}
                />
                <MetricCard
                    index="02"
                    label="Média de estrelas"
                    value={formatNumber(metrics.average_stars)}
                    note="Somente ataques realizados"
                />
                <MetricCard
                    index="03"
                    label="Destruição média"
                    value={formatPercentage(metrics.average_destruction)}
                    note={`${metrics.attacks_used} ataques contabilizados`}
                />
                <MetricCard
                    index="04"
                    label="Defesas sofridas"
                    value={metrics.defenses}
                    note={`${formatNumber(metrics.average_stars_conceded)} estrelas cedidas em média`}
                    defensive
                />
                <MetricCard
                    index="05"
                    label="Destruição cedida"
                    value={formatPercentage(
                        metrics.average_destruction_conceded,
                    )}
                    note="Cada ataque defensivo conta separadamente"
                    defensive
                />
            </section>

            <section className="player-chart-panel">
                <header>
                    <div>
                        <p className="section-kicker">TRAJETÓRIA</p>
                        <h2>Destruição por guerra</h2>
                    </div>
                    <div className="player-chart-legend">
                        <span className="is-offense">Ataques</span>
                        <span className="is-defense">Defesas</span>
                    </div>
                </header>
                {series.length === 0 ? (
                    <PerformanceEmpty>
                        Ainda não há guerras concluídas com detalhes para esta
                        seleção.
                    </PerformanceEmpty>
                ) : (
                    <PerformanceChart series={series} />
                )}
            </section>

            <HistoryTable
                title="Ataques realizados"
                eyebrow="OFENSIVA"
                pagination={attacks}
                empty="Nenhum ataque realizado nesta amostra."
                targetLabel="Alvo"
                targetField="defender_tag"
            />

            <HistoryTable
                title="Defesas sofridas"
                eyebrow="DEFENSIVA"
                pagination={defenses}
                empty="Nenhuma defesa registrada nesta amostra."
                targetLabel="Atacante"
                targetField="attacker_tag"
                defensive
            />
        </AuthenticatedLayout>
    );
}

function MetricCard({ index, label, value, note, defensive = false }) {
    return (
        <article
            className={`player-performance-metric ${defensive ? 'is-defensive' : ''}`}
        >
            <span>{index}</span>
            <p>{label}</p>
            <strong>{value}</strong>
            <small>{note}</small>
        </article>
    );
}

function PerformanceChart({ series }) {
    const width = 900;
    const height = 260;
    const padding = 32;
    const x = (index) =>
        series.length === 1
            ? width / 2
            : padding +
              (index * (width - padding * 2)) / (series.length - 1);
    const y = (value) =>
        height - padding - (Math.min(100, value) / 100) * (height - padding * 2);
    const offenseSeries = series
        .map((item, index) => ({ item, index }))
        .filter(({ item }) => item.attacks > 0);
    const defenseSeries = series
        .map((item, index) => ({ item, index }))
        .filter(({ item }) => item.defenses > 0);
    const offensePoints = offenseSeries
        .map(
            ({ item, index }) =>
                `${x(index)},${y(item.average_destruction)}`,
        )
        .join(' ');
    const defensePoints = defenseSeries
        .map(
            ({ item, index }) =>
                `${x(index)},${y(item.average_destruction_conceded)}`,
        )
        .join(' ');

    return (
        <div className="player-chart">
            <svg
                viewBox={`0 0 ${width} ${height}`}
                role="img"
                aria-label="Destruição média ofensiva e defensiva por guerra"
            >
                {[0, 25, 50, 75, 100].map((value) => (
                    <g key={value}>
                        <line
                            x1={padding}
                            x2={width - padding}
                            y1={y(value)}
                            y2={y(value)}
                        />
                        <text x="0" y={y(value) + 4}>
                            {value}%
                        </text>
                    </g>
                ))}
                <polyline
                    className="is-offense"
                    points={offensePoints}
                />
                <polyline
                    className="is-defense"
                    points={defensePoints}
                />
                {offenseSeries.map(({ item, index }) => (
                    <circle
                        className="is-offense"
                        cx={x(index)}
                        cy={y(item.average_destruction)}
                        r="4"
                        key={`offense-${item.war_id}`}
                    >
                        <title>
                            {item.opponent_name}: ataque{' '}
                            {formatPercentage(item.average_destruction)}
                        </title>
                    </circle>
                ))}
                {defenseSeries.map(({ item, index }) => (
                    <circle
                        className="is-defense"
                        cx={x(index)}
                        cy={y(item.average_destruction_conceded)}
                        r="4"
                        key={`defense-${item.war_id}`}
                    >
                        <title>
                            {item.opponent_name}: defesa{' '}
                            {formatPercentage(
                                item.average_destruction_conceded,
                            )}
                        </title>
                    </circle>
                ))}
            </svg>
            <div
                className="player-chart-labels"
                style={{
                    gridTemplateColumns: `repeat(${series.length}, minmax(3rem, 1fr))`,
                }}
            >
                {series.map((item) => (
                    <span key={item.war_id}>
                        {item.opponent_name}
                        <small>{formatShortDate(item.end_time)}</small>
                    </span>
                ))}
            </div>
        </div>
    );
}

function HistoryTable({
    title,
    eyebrow,
    pagination,
    empty,
    targetLabel,
    targetField,
    defensive = false,
}) {
    return (
        <section
            className={`members-panel player-history-panel ${defensive ? 'is-defensive' : ''}`}
        >
            <header className="members-panel-header">
                <div>
                    <p className="section-kicker">{eyebrow}</p>
                    <h2>{title}</h2>
                    <p>
                        {pagination.total}{' '}
                        {pagination.total === 1 ? 'registro' : 'registros'} na
                        amostra selecionada.
                    </p>
                </div>
            </header>

            {pagination.data.length === 0 ? (
                <PerformanceEmpty>{empty}</PerformanceEmpty>
            ) : (
                <div className="members-table-wrap">
                    <table className="members-table player-history-table">
                        <thead>
                            <tr>
                                <th>Guerra</th>
                                <th>Tipo</th>
                                <th>{targetLabel}</th>
                                <th>Estrelas</th>
                                <th>Destruição</th>
                                <th>Data</th>
                            </tr>
                        </thead>
                        <tbody>
                            {pagination.data.map((attack) => (
                                <tr key={attack.id}>
                                    <td>
                                        <strong>
                                            {attack.war.opponent_name}
                                        </strong>
                                        <small>
                                            {attack.war.opponent_tag}
                                        </small>
                                    </td>
                                    <td>
                                        <span
                                            className={`player-war-type is-${attack.war.type}`}
                                        >
                                            {attack.war.type === 'cwl'
                                                ? 'CWL'
                                                : 'Regular'}
                                        </span>
                                    </td>
                                    <td>
                                        <code>{attack[targetField]}</code>
                                    </td>
                                    <td>
                                        <span className="player-stars">
                                            {attack.stars} ★
                                        </span>
                                    </td>
                                    <td>
                                        {formatPercentage(
                                            attack.destruction_percentage,
                                        )}
                                    </td>
                                    <td>
                                        {formatDate(attack.war.end_time)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
            <Pagination pagination={pagination} />
        </section>
    );
}

function PerformanceEmpty({ children }) {
    return (
        <div className="player-performance-empty">
            <span>—</span>
            <p>{children}</p>
        </div>
    );
}

function formatNumber(value) {
    return new Intl.NumberFormat('pt-BR', {
        minimumFractionDigits: 1,
        maximumFractionDigits: 2,
    }).format(value);
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

function formatShortDate(value) {
    return new Intl.DateTimeFormat('pt-BR', {
        day: '2-digit',
        month: '2-digit',
    }).format(new Date(value));
}
