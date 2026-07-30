import ActiveWarAlert from '@/Components/ActiveWarAlert';
import FilterPopover from '@/Components/FilterPopover';
import Pagination from '@/Components/Pagination';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

const resultLabels = {
    win: 'Vitória',
    lose: 'Derrota',
    tie: 'Empate',
};

export default function Index({ wars, clan, activeWar, warStats, filters }) {
    const { auth, demoMode, errors, status, syncSummary } = usePage().props;
    const { post, processing } = useForm({});
    const [resultFilter, setResultFilter] = useState(filters.result ?? '');

    const sync = () => {
        post(route('wars.sync'), { preserveScroll: true });
    };

    const applyFilters = (event) => {
        event.preventDefault();
        router.get(
            route('wars.index'),
            { result: resultFilter },
            { preserveScroll: true },
        );
    };

    return (
        <AuthenticatedLayout header="Guerras" eyebrow="HISTÓRICO DE BATALHAS">
            <Head title="Guerras" />

            <ActiveWarAlert war={activeWar} />

            <section className="members-summary war-summary">
                <div>
                    <span>Guerras registradas</span>
                    <strong>{warStats.total}</strong>
                </div>
                <div>
                    <span>Vitórias</span>
                    <strong>{warStats.victories}</strong>
                </div>
                <div>
                    <span>Com detalhes</span>
                    <strong>{warStats.detailed}</strong>
                </div>
                <div className="members-sync-meta">
                    <span>Última sincronização</span>
                    <strong>
                        {clan?.wars_synced_at
                            ? formatDate(clan.wars_synced_at)
                            : 'Ainda não realizada'}
                    </strong>
                </div>
            </section>

            {(status === 'wars-synced' || errors.sync) && (
                <div
                    className={`sync-feedback ${errors.sync ? 'is-error' : 'is-success'}`}
                    role="status"
                >
                    {errors.sync ? (
                        errors.sync
                    ) : (
                        <>
                            Guerras sincronizadas.
                            <span>
                                {syncSummary?.added ?? 0} novas ·{' '}
                                {syncSummary?.updated ?? 0} atualizadas ·{' '}
                                {syncSummary?.detailed ?? 0} com detalhes
                            </span>
                        </>
                    )}
                </div>
            )}

            <section className="members-panel">
                <header className="members-panel-header">
                    <div>
                        <p className="section-kicker">REGISTRO DE GUERRA</p>
                        <h2>Histórico do clã</h2>
                        <p>
                            Resultados do war log e detalhes preservados sempre
                            que a API os disponibilizar.
                        </p>
                    </div>
                    {!demoMode &&
                        ['admin', 'leader'].includes(auth.user.role) && (
                        <button
                            className="sync-button"
                            onClick={sync}
                            disabled={processing || !clan}
                        >
                            <SyncIcon spinning={processing} />
                            {processing
                                ? 'Sincronizando...'
                                : 'Sincronizar guerras'}
                        </button>
                    )}
                </header>

                {demoMode && (
                    <div className="members-empty-warning">
                        Modo demo: o histórico foi carregado pelos seeders e a
                        sincronização com o jogo está desativada.
                    </div>
                )}

                {!clan && (
                    <div className="members-empty-warning">
                        A tag do clã precisa ser configurada antes da primeira
                        sincronização.
                    </div>
                )}

                <div className="table-toolbar is-filter-only">
                    <FilterPopover activeCount={resultFilter ? 1 : 0}>
                        <form className="popover-filter-form" onSubmit={applyFilters}>
                            <label>
                                <span>Resultado</span>
                                <select
                                    value={resultFilter}
                                    onChange={(event) => setResultFilter(event.target.value)}
                                >
                                    <option value="">Todos os resultados</option>
                                    <option value="win">Vitórias</option>
                                    <option value="lose">Derrotas</option>
                                    <option value="tie">Empates</option>
                                </select>
                            </label>
                            <div className="filter-actions">
                                <button type="button" onClick={() => router.get(route('wars.index'), {}, { preserveScroll: true })}>
                                    Limpar
                                </button>
                                <button className="is-primary">Aplicar filtro</button>
                            </div>
                        </form>
                    </FilterPopover>
                </div>

                {wars.data.length === 0 ? (
                    <div className="members-empty">
                        <div className="members-empty-mark">VS</div>
                        <h3>Nenhuma guerra sincronizada.</h3>
                        <p>
                            {demoMode
                                ? 'Execute os seeders para carregar o histórico de demonstração.'
                                : 'Importe o histórico disponível na API do jogo.'}
                        </p>
                    </div>
                ) : (
                    <div className="members-table-wrap">
                        <table className="members-table wars-table">
                            <thead>
                                <tr>
                                    <th>Resultado</th>
                                    <th>Oponente</th>
                                    <th>Estrelas</th>
                                    <th>Destruição</th>
                                    <th>Encerramento</th>
                                    <th />
                                </tr>
                            </thead>
                            <tbody>
                                {wars.data.map((war) => (
                                    <tr key={war.id}>
                                        <td>
                                            <span className={`war-result is-${war.result ?? 'pending'}`}>
                                                {resultLabels[war.result] ?? 'Pendente'}
                                            </span>
                                        </td>
                                        <td>
                                            <div className="war-opponent">
                                                {war.opponent_badge_url && (
                                                    <img src={war.opponent_badge_url} alt="" />
                                                )}
                                                <span>
                                                    <strong>{war.opponent_name}</strong>
                                                    <small>{war.opponent_tag}</small>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <span className="war-score">
                                                <strong>{war.clan_stars}</strong>
                                                <i>×</i>
                                                <span>{war.opponent_stars}</span>
                                            </span>
                                        </td>
                                        <td>
                                            {formatPercentage(war.clan_destruction_percentage)}
                                        </td>
                                        <td>{formatDate(war.end_time)}</td>
                                        <td className="war-action-cell">
                                            {war.has_details && (
                                                <Link
                                                    className="war-details-link"
                                                    href={route('wars.show', war.id)}
                                                >
                                                    Ver detalhes
                                                </Link>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <Pagination pagination={wars} />
            </section>
        </AuthenticatedLayout>
    );
}

function formatPercentage(value) {
    return `${new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 2 }).format(value)}%`;
}

function formatDate(value) {
    return new Intl.DateTimeFormat('pt-BR', {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(value));
}

function SyncIcon({ spinning }) {
    return (
        <svg className={spinning ? 'is-spinning' : ''} viewBox="0 0 24 24" aria-hidden="true">
            <path d="M20 7h-5V2M4 17h5v5M19 12a7 7 0 0 0-12-5L5 9M5 12a7 7 0 0 0 12 5l2-2" />
        </svg>
    );
}
