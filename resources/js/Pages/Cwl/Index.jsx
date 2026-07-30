import Pagination from '@/Components/Pagination';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';

const stateLabels = {
    preparation: 'Preparação',
    inWar: 'Em andamento',
    ended: 'Encerrada',
};

const CWL_ROUNDS = 7;

export default function Index({ clan, leagues, leagueStats }) {
    const { auth, demoMode, errors, status, syncSummary } = usePage().props;
    const { post, processing } = useForm({});

    return (
        <AuthenticatedLayout
            header="Liga de Guerra"
            eyebrow="HISTÓRICO DE TEMPORADAS"
        >
            <Head title="Liga de Guerra" />

            <section className="members-summary war-summary">
                <div>
                    <span>Temporadas registradas</span>
                    <strong>{leagueStats.total}</strong>
                </div>
                <div>
                    <span>Temporadas detalhadas</span>
                    <strong>{leagueStats.detailed}</strong>
                </div>
                <div className="members-sync-meta">
                    <span>Última sincronização</span>
                    <strong>
                        {leagueStats.last_synced_at
                            ? formatDate(leagueStats.last_synced_at)
                            : 'Ainda não realizada'}
                    </strong>
                </div>
            </section>

            {(status === 'cwl-synced' || errors.sync) && (
                <div
                    className={`sync-feedback ${errors.sync ? 'is-error' : 'is-success'}`}
                    role="status"
                >
                    {errors.sync ? (
                        errors.sync
                    ) : (
                        <>
                            Liga de Guerra sincronizada.
                            <span>
                                {syncSummary?.seasons ?? 0} temporadas ·{' '}
                                {syncSummary?.detailed ?? 0} guerras detalhadas ·{' '}
                                {syncSummary?.pending ?? 0} pendências
                            </span>
                        </>
                    )}
                </div>
            )}

            <section className="members-panel cwl-list-panel">
                <header className="members-panel-header">
                    <div>
                        <p className="section-kicker">ARQUIVO CWL</p>
                        <h2>Temporadas do clã</h2>
                        <p>
                            Resumos do war log permanecem disponíveis mesmo
                            quando as rodadas detalhadas não foram capturadas.
                        </p>
                    </div>
                    {!demoMode &&
                        ['admin', 'leader'].includes(auth.user.role) && (
                            <button
                                className="sync-button"
                                disabled={processing || !clan}
                                onClick={() =>
                                    post(route('cwl.sync'), {
                                        preserveScroll: true,
                                    })
                                }
                            >
                                <SyncIcon spinning={processing} />
                                {processing
                                    ? 'Sincronizando...'
                                    : 'Sincronizar CWL'}
                            </button>
                        )}
                </header>

                {demoMode && (
                    <div className="members-empty-warning">
                        Modo demo: a sincronização da Liga está desativada.
                    </div>
                )}

                {!clan && (
                    <div className="members-empty-warning">
                        Configure um clã antes de consultar temporadas da Liga.
                    </div>
                )}

                {leagues.data.length === 0 ? (
                    <div className="members-empty">
                        <div className="members-empty-mark">0/7</div>
                        <h3>Nenhuma temporada registrada.</h3>
                        <p>
                            Sincronize a CWL para importar os resumos
                            disponíveis no war log.
                        </p>
                    </div>
                ) : (
                    <div className="members-table-wrap">
                        <table className="members-table cwl-history-table">
                            <thead>
                                <tr>
                                    <th>Temporada</th>
                                    <th>Estado</th>
                                    <th>Tamanho</th>
                                    <th>Estrelas</th>
                                    <th>Ataques</th>
                                    <th>Destruição média</th>
                                    <th>Encerramento</th>
                                    <th />
                                </tr>
                            </thead>
                            <tbody>
                                {leagues.data.map((league) => (
                                    <tr key={league.id}>
                                        <td>
                                            <strong>
                                                {formatSeason(league.season)}
                                            </strong>
                                            <small>
                                                {league.rounds_count} rodadas ·{' '}
                                                {league.participants_count} clãs
                                            </small>
                                        </td>
                                        <td>
                                            <span
                                                className={`cwl-state is-${league.state}`}
                                            >
                                                {stateLabels[league.state] ??
                                                    league.state}
                                            </span>
                                        </td>
                                        <td>
                                            {formatWarSize(league.team_size)}
                                        </td>
                                        <td>
                                            <strong>{league.clan_stars}</strong>
                                        </td>
                                        <td>
                                            {formatAttacks(league)}
                                        </td>
                                        <td>
                                            {league.has_summary
                                                ? formatPercentage(
                                                      league.clan_destruction_percentage /
                                                          CWL_ROUNDS,
                                                  )
                                                : '—'}
                                        </td>
                                        <td>
                                            {league.end_time
                                                ? formatDate(league.end_time)
                                                : 'Em andamento'}
                                        </td>
                                        <td className="war-action-cell">
                                            {league.rounds_count > 0 && (
                                                <Link
                                                    className="war-details-link"
                                                    href={route(
                                                        'cwl.show',
                                                        league.id,
                                                    )}
                                                >
                                                    Ver temporada
                                                </Link>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <Pagination pagination={leagues} />
            </section>
        </AuthenticatedLayout>
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

function formatWarSize(teamSize) {
    return teamSize ? `${teamSize}x${teamSize}` : '—';
}

function formatAttacks(league) {
    if (league.clan_attacks === null || !league.team_size) {
        return '—';
    }

    return `${league.clan_attacks}/${league.team_size * CWL_ROUNDS}`;
}

function formatDate(value) {
    return new Intl.DateTimeFormat('pt-BR', {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(value));
}

function SyncIcon({ spinning }) {
    return (
        <svg
            className={spinning ? 'is-spinning' : ''}
            viewBox="0 0 24 24"
            aria-hidden="true"
        >
            <path d="M20 7h-5V2M4 17h5v5M19 12a7 7 0 0 0-12-5L5 9M5 12a7 7 0 0 0 12 5l2-2" />
        </svg>
    );
}
