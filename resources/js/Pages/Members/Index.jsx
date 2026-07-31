import FilterPopover from '@/Components/FilterPopover';
import Pagination from '@/Components/Pagination';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

const statusLabels = {
    in: 'No clã',
    out: 'Fora do clã',
};

const roleLabels = {
    leader: 'Líder',
    coLeader: 'Colíder',
    admin: 'Ancião',
    member: 'Membro',
};

const cutBadge =
    'inline-flex items-center gap-1 border px-2 py-1 text-xs font-black [clip-path:polygon(0_0,calc(100%-0.45rem)_0,100%_0.45rem,100%_100%,0.45rem_100%,0_calc(100%-0.45rem))]';
const actionLink =
    'inline-flex border border-white/15 bg-zinc-900 px-3 py-2 text-xs font-black uppercase tracking-[0.12em] text-zinc-300 transition hover:border-amber-400/40 hover:text-white [clip-path:polygon(0_0,calc(100%-0.45rem)_0,100%_0.45rem,100%_100%,0.45rem_100%,0_calc(100%-0.45rem))]';

export default function Index({
    members,
    clan,
    memberStats,
    filters,
    filterOptions,
}) {
    const { auth, demoMode, errors, status, syncSummary } = usePage().props;
    const { post, processing } = useForm({});
    const [filterForm, setFilterForm] = useState({
        search: filters.search ?? '',
        town_hall: filters.townHall ?? '',
        role: filters.role ?? '',
        status: filters.status ?? 'in',
        sort: filters.sort ?? 'name',
        direction: filters.direction ?? 'asc',
    });

    const sync = () => {
        post(route('members.sync'), { preserveScroll: true });
    };

    const applyFilters = (event) => {
        event.preventDefault();
        router.get(route('members.index'), filterForm, {
            preserveScroll: true,
        });
    };

    const clearFilters = () => {
        router.get(
            route('members.index'),
            { status: 'in' },
            { preserveScroll: true },
        );
    };
    const sortMembers = (column) => {
        const nextFilters = {
            ...filterForm,
            sort: column,
            direction:
                filterForm.sort === column && filterForm.direction === 'asc'
                    ? 'desc'
                    : 'asc',
        };

        setFilterForm(nextFilters);
        router.get(route('members.index'), nextFilters, {
            preserveScroll: true,
        });
    };
    const activeFilterCount = [
        filterForm.status !== 'in',
        Boolean(filterForm.town_hall),
        Boolean(filterForm.role),
    ].filter(Boolean).length;

    return (
        <AuthenticatedLayout header="Membros" eyebrow="ROSTER DO CLÃ">
            <Head title="Membros" />

            <section className="members-summary">
                <div>
                    <span>Total registrado</span>
                    <strong>{memberStats.total}</strong>
                </div>
                <div>
                    <span>No clã</span>
                    <strong>{memberStats.inClan}</strong>
                </div>
                <div>
                    <span>Fora do clã</span>
                    <strong>{memberStats.outClan}</strong>
                </div>
                <div className="members-sync-meta">
                    <span>Última sincronização</span>
                    <strong>
                        {clan?.members_synced_at
                            ? formatDate(clan.members_synced_at)
                            : 'Ainda não realizada'}
                    </strong>
                </div>
            </section>

            {(status === 'members-synced' || errors.sync) && (
                <div
                    className={`sync-feedback ${errors.sync ? 'is-error' : 'is-success'}`}
                    role="status"
                >
                    {errors.sync ? (
                        errors.sync
                    ) : (
                        <>
                            Sincronização concluída.
                            <span>
                                {syncSummary?.added ?? 0} novos ·{' '}
                                {syncSummary?.moved_in ?? 0} retornaram ·{' '}
                                {syncSummary?.moved_out ?? 0} saíram
                            </span>
                        </>
                    )}
                </div>
            )}

            <section className="members-panel">
                <header className="members-panel-header">
                    <div>
                        <p className="section-kicker">LISTAGEM LOCAL</p>
                        <h2>Membros registrados</h2>
                        <p>
                            A sincronização adiciona novos jogadores e preserva
                            o histórico de quem deixou o clã.
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
                                : 'Sincronizar com o jogo'}
                        </button>
                    )}
                </header>

                {demoMode && (
                    <div className="members-empty-warning">
                        Modo demo: os dados desta tela são demonstrativos e a
                        sincronização com o jogo está desativada.
                    </div>
                )}

                {!clan && (
                    <div className="members-empty-warning">
                        A tag do clã precisa ser configurada antes da primeira
                        sincronização.
                    </div>
                )}

                <div className="table-toolbar">
                    <form className="table-search" onSubmit={applyFilters}>
                        <input
                            type="search"
                            value={filterForm.search}
                            placeholder="Nome do jogador"
                            onChange={(event) =>
                                setFilterForm({
                                    ...filterForm,
                                    search: event.target.value,
                                })
                            }
                        />
                        <button aria-label="Buscar">Buscar</button>
                    </form>

                    <FilterPopover activeCount={activeFilterCount}>
                        <form className="popover-filter-form" onSubmit={applyFilters}>
                            <label>
                                <span>Status</span>
                                <select value={filterForm.status} onChange={(event) => setFilterForm({ ...filterForm, status: event.target.value })}>
                                    <option value="in">No clã</option>
                                    <option value="out">Fora do clã</option>
                                    <option value="all">Todos</option>
                                </select>
                            </label>
                            <label>
                                <span>Centro de Vila</span>
                                <select value={filterForm.town_hall} onChange={(event) => setFilterForm({ ...filterForm, town_hall: event.target.value })}>
                                    <option value="">Todos os CVs</option>
                                    {filterOptions.townHalls.map((level) => (
                                        <option key={level} value={level}>CV {level}</option>
                                    ))}
                                </select>
                            </label>
                            <label>
                                <span>Cargo</span>
                                <select value={filterForm.role} onChange={(event) => setFilterForm({ ...filterForm, role: event.target.value })}>
                                    <option value="">Todos os cargos</option>
                                    {filterOptions.roles.map((role) => (
                                        <option key={role} value={role}>{roleLabels[role] ?? role}</option>
                                    ))}
                                </select>
                            </label>
                            <div className="filter-actions">
                                <button type="button" onClick={clearFilters}>Limpar</button>
                                <button className="is-primary">Aplicar filtros</button>
                            </div>
                        </form>
                    </FilterPopover>
                </div>

                {members.data.length === 0 ? (
                    <div className="members-empty">
                        <div className="members-empty-mark">00</div>
                        <h3>Nenhum membro sincronizado.</h3>
                        <p>
                            {demoMode
                                ? 'Execute os seeders para carregar os dados de demonstração.'
                                : 'Use o botão acima para importar a formação atual do clã.'}
                        </p>
                    </div>
                ) : (
                    <div className="members-table-wrap">
                        <table className="members-table">
                            <thead>
                                <tr>
                                    <SortableHeader
                                        column="name"
                                        label="Jogador"
                                        filters={filterForm}
                                        onSort={sortMembers}
                                    />
                                    <SortableHeader
                                        column="town_hall"
                                        label="CV"
                                        filters={filterForm}
                                        onSort={sortMembers}
                                    />
                                    <SortableHeader
                                        column="role"
                                        label="Cargo"
                                        filters={filterForm}
                                        onSort={sortMembers}
                                    />
                                    <th>Status</th>
                                    <th />
                                </tr>
                            </thead>
                            <tbody>
                                {members.data.map((member) => (
                                    <tr key={member.id}>
                                        <td>
                                            <strong>{member.name}</strong>
                                            <small>{member.player_tag}</small>
                                        </td>
                                        <td>
                                            <span className={`${cutBadge} border-amber-400/30 bg-amber-400/10 text-amber-300`}>
                                                {member.town_hall_level
                                                    ? member.town_hall_level
                                                    : '—'}
                                            </span>
                                        </td>
                                        <td>{roleLabels[member.role] ?? member.role ?? '—'}</td>
                                        <td>
                                            <span className={`${cutBadge} ${
                                                member.status.slug === 'in'
                                                    ? 'border-amber-400/30 bg-amber-400/10 text-amber-300'
                                                    : 'border-zinc-500/30 bg-zinc-500/10 text-zinc-400'
                                            }`}>
                                                <i className="h-1.5 w-1.5 bg-current" />
                                                {statusLabels[member.status.slug]}
                                            </span>
                                        </td>
                                        <td className="war-action-cell">
                                            <Link
                                                className={actionLink}
                                                href={route(
                                                    'members.show',
                                                    member.id,
                                                )}
                                            >
                                                Ver desempenho
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
                <Pagination pagination={members} />
            </section>
        </AuthenticatedLayout>
    );
}

function SortableHeader({ column, label, filters, onSort }) {
    const active = filters.sort === column;

    return (
        <th
            aria-sort={
                active
                    ? filters.direction === 'asc'
                        ? 'ascending'
                        : 'descending'
                    : 'none'
            }
        >
            <button
                className={`inline-flex items-center gap-1 uppercase text-inherit transition hover:text-amber-300 ${active ? 'text-amber-300' : ''}`}
                style={{ font: 'inherit', letterSpacing: 'inherit' }}
                onClick={() => onSort(column)}
            >
                {label}
                <span aria-hidden="true">
                    {active
                        ? filters.direction === 'asc'
                            ? '↑'
                            : '↓'
                        : '↕'}
                </span>
            </button>
        </th>
    );
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
