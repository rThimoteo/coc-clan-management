import Pagination from '@/Components/Pagination';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

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

const cutBadge =
    'inline-flex items-center gap-1 border px-2 py-1 text-xs font-black [clip-path:polygon(0_0,calc(100%-0.45rem)_0,100%_0.45rem,100%_100%,0.45rem_100%,0_calc(100%-0.45rem))]';
const cutPanel =
    'border border-white/10 bg-zinc-900/80 [clip-path:polygon(0_0,calc(100%-0.55rem)_0,100%_0.55rem,100%_100%,0.55rem_100%,0_calc(100%-0.55rem))]';
const cutInput =
    'min-h-10 w-full border border-white/15 bg-zinc-950 px-3 py-2 text-sm text-zinc-100 focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-400/10 [clip-path:polygon(0_0,calc(100%-0.45rem)_0,100%_0.45rem,100%_100%,0.45rem_100%,0_calc(100%-0.45rem))]';

export default function Show({
    membership,
    metrics,
    series,
    attacks,
    defenses,
    filters,
}) {
    const player = membership.player;
    const [chartMetric, setChartMetric] = useState('destruction');
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

            <Link className="text-sm font-bold text-zinc-500 hover:text-amber-300" href={route('members.index')}>
                ← Voltar para membros
            </Link>

            <section className={`${cutPanel} mt-3 flex flex-col gap-5 p-5 min-[900px]:flex-row min-[900px]:items-center min-[900px]:justify-between`}>
                <div className="flex items-center gap-4">
                    <span className="grid h-14 w-14 shrink-0 place-items-center border border-amber-400/30 bg-amber-400/10 text-xs font-black text-amber-300 [clip-path:polygon(0_0,calc(100%-0.45rem)_0,100%_0.45rem,100%_100%,0.45rem_100%,0_calc(100%-0.45rem))]">CV {player.town_hall_level ?? '—'}</span>
                    <div>
                        <small className="text-xs tracking-[0.08em] text-zinc-500">{player.player_tag}</small>
                        <h2 className="mt-0.5 font-display text-2xl font-black text-zinc-100">{player.name}</h2>
                        <p className="mt-0.5 text-sm leading-6 text-zinc-500">
                            {roleLabels[membership.role] ??
                                membership.role ??
                                'Sem cargo'}{' '}
                            · {statusLabels[membership.status.slug]}
                        </p>
                    </div>
                </div>
                <div className="grid gap-2 sm:grid-cols-2 min-[900px]:w-[min(26rem,45%)]">
                    <label>
                        <span className="mb-1 block text-[0.66rem] font-black uppercase tracking-[0.14em] text-zinc-500">Tipo</span>
                        <select
                            className={cutInput}
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
                        <span className="mb-1 block text-[0.66rem] font-black uppercase tracking-[0.14em] text-zinc-500">Janela</span>
                        <select
                            className={cutInput}
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

            <section className="mt-3 grid gap-3 sm:grid-cols-2 min-[900px]:grid-cols-5">
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
                        <h2>
                            {chartMetric === 'destruction'
                                ? 'Destruição por guerra'
                                : 'Média de estrelas por guerra'}
                        </h2>
                    </div>
                    <div className="flex flex-wrap items-center justify-end gap-3">
                        <div
                            className="chart-metric-toggle"
                            role="group"
                            aria-label="Métrica do gráfico"
                        >
                            <button
                                type="button"
                                className={chartMetric === 'destruction' ? 'is-active' : ''}
                                aria-pressed={chartMetric === 'destruction'}
                                onClick={() => setChartMetric('destruction')}
                            >
                                Destruição
                            </button>
                            <button
                                type="button"
                                className={chartMetric === 'stars' ? 'is-active' : ''}
                                aria-pressed={chartMetric === 'stars'}
                                onClick={() => setChartMetric('stars')}
                            >
                                Estrelas
                            </button>
                        </div>
                        <div className="flex gap-3 text-xs text-zinc-500">
                            <span className="before:mr-1.5 before:inline-block before:h-2 before:w-2 before:bg-amber-500">Ataques</span>
                            <span className="before:mr-1.5 before:inline-block before:h-2 before:w-2 before:bg-red-500">Defesas</span>
                        </div>
                    </div>
                </header>
                {series.length === 0 ? (
                    <PerformanceEmpty>
                        Ainda não há guerras concluídas com detalhes para esta
                        seleção.
                    </PerformanceEmpty>
                ) : (
                    <PerformanceChart series={series} metric={chartMetric} />
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
            className={`${cutPanel} relative overflow-hidden p-4`}
        >
            <span className="absolute right-3 top-2 font-mono text-xs text-zinc-700">{index}</span>
            <p className="text-xs text-zinc-500">{label}</p>
            <strong className={`mt-1 block font-display text-2xl font-black ${defensive ? 'text-rose-300' : 'text-amber-300'}`}>{value}</strong>
            <small className="mt-1 block text-xs text-zinc-600">{note}</small>
        </article>
    );
}

function PerformanceChart({ series, metric }) {
    const [activePoint, setActivePoint] = useState(null);
    const width = 900;
    const height = 260;
    const padding = 32;
    const isDestruction = metric === 'destruction';
    const maximum = isDestruction ? 100 : 3;
    const offenseField = isDestruction
        ? 'average_destruction'
        : 'average_stars';
    const defenseField = isDestruction
        ? 'average_destruction_conceded'
        : 'average_stars_conceded';
    const ticks = isDestruction ? [0, 25, 50, 75, 100] : [0, 1, 2, 3];
    const formatMetric = isDestruction ? formatPercentage : formatNumber;
    const x = (index) =>
        series.length === 1
            ? width / 2
            : padding +
              (index * (width - padding * 2)) / (series.length - 1);
    const y = (value) =>
        height -
        padding -
        (Math.min(maximum, value) / maximum) * (height - padding * 2);
    const offenseSeries = series
        .map((item, index) => ({ item, index }))
        .filter(({ item }) => item.attacks > 0);
    const defenseSeries = series
        .map((item, index) => ({ item, index }))
        .filter(({ item }) => item.defenses > 0);
    const offensePoints = offenseSeries
        .map(
            ({ item, index }) =>
                `${x(index)},${y(item[offenseField])}`,
        )
        .join(' ');
    const defensePoints = defenseSeries
        .map(
            ({ item, index }) =>
                `${x(index)},${y(item[defenseField])}`,
        )
        .join(' ');
    const tooltip = activePoint
        ? {
              ...activePoint,
              x: x(activePoint.index),
              y: y(activePoint.item[activePoint.field]),
          }
        : null;

    return (
        <div className="player-chart">
            <svg
                viewBox={`0 0 ${width} ${height}`}
                role="img"
                aria-label={`${isDestruction ? 'Destruição média' : 'Média de estrelas'} ofensiva e defensiva por guerra`}
            >
                {ticks.map((value) => (
                    <g key={value}>
                        <line
                            x1={padding}
                            x2={width - padding}
                            y1={y(value)}
                            y2={y(value)}
                        />
                        <text x="0" y={y(value) + 4}>
                            {isDestruction ? `${value}%` : value}
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
                        cy={y(item[offenseField])}
                        r="4"
                        key={`offense-${item.war_id}`}
                        tabIndex="0"
                        role="button"
                        aria-label={`${item.opponent_name}: ataque ${formatMetric(item[offenseField])}`}
                        onMouseEnter={() =>
                            setActivePoint({
                                item,
                                index,
                                field: offenseField,
                                label: 'Ataques',
                                kind: 'offense',
                            })
                        }
                        onMouseLeave={() => setActivePoint(null)}
                        onFocus={() =>
                            setActivePoint({
                                item,
                                index,
                                field: offenseField,
                                label: 'Ataques',
                                kind: 'offense',
                            })
                        }
                        onBlur={() => setActivePoint(null)}
                    />
                ))}
                {defenseSeries.map(({ item, index }) => (
                    <circle
                        className="is-defense"
                        cx={x(index)}
                        cy={y(item[defenseField])}
                        r="4"
                        key={`defense-${item.war_id}`}
                        tabIndex="0"
                        role="button"
                        aria-label={`${item.opponent_name}: defesa ${formatMetric(item[defenseField])}`}
                        onMouseEnter={() =>
                            setActivePoint({
                                item,
                                index,
                                field: defenseField,
                                label: 'Defesas',
                                kind: 'defense',
                            })
                        }
                        onMouseLeave={() => setActivePoint(null)}
                        onFocus={() =>
                            setActivePoint({
                                item,
                                index,
                                field: defenseField,
                                label: 'Defesas',
                                kind: 'defense',
                            })
                        }
                        onBlur={() => setActivePoint(null)}
                    />
                ))}
                {tooltip && (
                    <ChartTooltip
                        point={tooltip}
                        formatMetric={formatMetric}
                        chartWidth={width}
                        chartHeight={height}
                    />
                )}
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

function ChartTooltip({ point, formatMetric, chartWidth, chartHeight }) {
    const boxWidth = 190;
    const boxHeight = 62;
    const boxX = Math.min(
        chartWidth - boxWidth - 8,
        Math.max(8, point.x - boxWidth / 2),
    );
    const boxY =
        point.y > boxHeight + 18
            ? point.y - boxHeight - 14
            : Math.min(chartHeight - boxHeight - 8, point.y + 14);
    const accent = point.kind === 'offense' ? '#f59e0b' : '#ef4444';

    return (
        <g className="player-chart-tooltip" aria-hidden="true">
            <line
                className="player-chart-guide"
                x1={point.x}
                x2={point.x}
                y1="12"
                y2={chartHeight - 32}
            />
            <rect
                x={boxX}
                y={boxY}
                width={boxWidth}
                height={boxHeight}
                rx="7"
            />
            <rect
                className="player-chart-tooltip-accent"
                x={boxX}
                y={boxY}
                width="4"
                height={boxHeight}
                rx="2"
                fill={accent}
            />
            <text className="player-chart-tooltip-name" x={boxX + 13} y={boxY + 20}>
                {point.item.opponent_name}
            </text>
            <text className="player-chart-tooltip-meta" x={boxX + 13} y={boxY + 40}>
                {point.label} · {formatShortDate(point.item.end_time)}
            </text>
            <text
                className="player-chart-tooltip-value"
                x={boxX + boxWidth - 12}
                y={boxY + 40}
                textAnchor="end"
                fill={accent}
            >
                {formatMetric(point.item[point.field])}
            </text>
        </g>
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
                                            className={`${cutBadge} ${
                                                attack.war.type === 'cwl'
                                                    ? 'border-amber-400/30 bg-amber-400/10 text-amber-300'
                                                    : 'border-white/15 bg-zinc-900 text-zinc-300'
                                            }`}
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
                                        <span className="font-black text-amber-300">
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
        <div className="flex items-center gap-3 px-4 py-5 text-sm leading-6 text-zinc-500">
            <span className="text-2xl text-zinc-800">—</span>
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
