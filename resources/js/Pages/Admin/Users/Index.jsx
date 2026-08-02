import InputError from '@/Components/InputError';
import Pagination from '@/Components/Pagination';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const roleLabels = {
    admin: 'Administrador',
    leader: 'Líder',
    co_leader: 'Colíder',
    member: 'Membro',
};

const membershipStatusLabels = {
    in: 'No clã',
    out: 'Fora do clã',
};

const cutBadge =
    'inline-flex items-center gap-1 border px-2 py-1 text-xs font-black [clip-path:polygon(0_0,calc(100%-0.45rem)_0,100%_0.45rem,100%_100%,0.45rem_100%,0_calc(100%-0.45rem))]';
const actionButton =
    'border border-white/15 bg-zinc-900 px-3 py-2 text-xs font-black uppercase tracking-[0.12em] text-zinc-300 transition hover:border-amber-400/40 hover:text-white disabled:cursor-not-allowed disabled:opacity-50 [clip-path:polygon(0_0,calc(100%-0.45rem)_0,100%_0.45rem,100%_100%,0.45rem_100%,0_calc(100%-0.45rem))]';
const cutPanel =
    '[clip-path:polygon(0_0,calc(100%-0.55rem)_0,100%_0.55rem,100%_100%,0.55rem_100%,0_calc(100%-0.55rem))]';

export default function Index({
    users,
    roles,
    players,
    permissions,
    filters,
}) {
    const { auth, generatedAccess } = usePage().props;
    const [createOpen, setCreateOpen] = useState(false);
    const [linkingUser, setLinkingUser] = useState(null);
    const [playerSearch, setPlayerSearch] = useState('');
    const [codeOpen, setCodeOpen] = useState(Boolean(generatedAccess));
    const [copied, setCopied] = useState(false);
    const [editingRole, setEditingRole] = useState(null);
    const [adminConfirmed, setAdminConfirmed] = useState(false);
    const [deletingUser, setDeletingUser] = useState(null);
    const [userSearch, setUserSearch] = useState(filters.search ?? '');

    useEffect(() => {
        setCopied(false);
    }, [generatedAccess?.code]);

    const createForm = useForm({
        name: '',
        role_id: roles.find((role) => role.slug === 'leader')?.id ?? roles[0]?.id,
    });
    const linkForm = useForm({ player_ids: [] });
    const roleForm = useForm({ role_id: null, confirm_admin: false });
    const filteredPlayers = players.filter((player) => {
        const search = normalizeSearch(playerSearch);
        const clanIdentity = player.memberships
            .map((membership) => `${membership.clan.name} ${membership.clan.tag}`)
            .join(' ');

        return (
            normalizeSearch(player.name).includes(search) ||
            normalizeSearch(player.player_tag).includes(search) ||
            normalizeSearch(clanIdentity).includes(search)
        );
    });

    const createUser = (event) => {
        event.preventDefault();
        createForm.post(route('admin.users.store'), {
            preserveScroll: true,
            onSuccess: () => {
                createForm.reset('name');
                setCopied(false);
                setCreateOpen(false);
                setCodeOpen(true);
            },
        });
    };

    const openMemberLink = (user) => {
        setLinkingUser(user);
        setPlayerSearch('');
        linkForm.setData(
            'player_ids',
            user.players.map((player) => player.id),
        );
        linkForm.clearErrors();
    };

    const saveMemberLinks = (event) => {
        event.preventDefault();
        linkForm.put(route('admin.users.players.update', linkingUser.id), {
            preserveScroll: true,
            onSuccess: () => setLinkingUser(null),
        });
    };

    const regenerate = (user) => {
        if (
            !window.confirm(
                `Gerar um novo código para ${user.name}? O código atual deixará de funcionar.`,
            )
        ) {
            return;
        }

        router.post(
            route('admin.users.access-code.regenerate', user.id),
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    setCopied(false);
                    setCodeOpen(true);
                },
            },
        );
    };

    const copyCode = async () => {
        await navigator.clipboard.writeText(generatedAccess.code);
        setCopied(true);
    };

    const openRoleEditor = (user) => {
        setEditingRole(user);
        setAdminConfirmed(false);
        roleForm.setData({
            role_id: user.role.id,
            confirm_admin: false,
        });
        roleForm.clearErrors();
    };

    const selectedRole = roles.find(
        (role) => role.id === Number(roleForm.data.role_id),
    );
    const promotingToAdmin =
        auth.user.role === 'admin' &&
        selectedRole?.slug === 'admin' &&
        editingRole?.role.slug !== 'admin';
    const editableRoles =
        auth.user.role === 'admin'
            ? roles
            : roles.filter((role) =>
                  ['co_leader', 'member'].includes(role.slug),
              );

    const saveRole = (event) => {
        event.preventDefault();
        roleForm.transform((data) => ({
            ...data,
            confirm_admin: promotingToAdmin && adminConfirmed,
        }));
        roleForm.patch(route('admin.users.role.update', editingRole.id), {
            preserveScroll: true,
            onSuccess: () => setEditingRole(null),
            onFinish: () => roleForm.transform((data) => data),
        });
    };

    const canEditRole = (user) => {
        if (user.id === auth.user.id) {
            return false;
        }

        return (
            (auth.user.role === 'admin' && user.role.slug !== 'admin') ||
            ['co_leader', 'member'].includes(user.role.slug)
        );
    };

    const deleteUser = () => {
        router.delete(route('admin.users.destroy', deletingUser.id), {
            preserveScroll: true,
            onSuccess: () => setDeletingUser(null),
        });
    };

    return (
        <AuthenticatedLayout header="Usuários" eyebrow="ADMINISTRAÇÃO DE ACESSOS">
            <Head title="Usuários" />

            <section className={`grid gap-px overflow-hidden border border-white/10 bg-white/10 sm:grid-cols-[repeat(2,minmax(0,1fr))_auto] ${cutPanel}`}>
                <div className="bg-zinc-900/80 px-4 py-3">
                    <span className="text-xs text-zinc-500">Acessos cadastrados</span>
                    <strong className="mt-1 block font-display text-2xl font-black text-zinc-100">{users.total}</strong>
                </div>
                <div className="bg-zinc-900/80 px-4 py-3">
                    <span className="text-xs text-zinc-500">Contas do jogo vinculadas</span>
                    <strong className="mt-1 block font-display text-2xl font-black text-zinc-100">
                        {players.filter((player) => player.user_id).length}
                    </strong>
                </div>
                {permissions.createUsers && (
                    <button
                        className={`inline-flex items-center justify-center gap-2 border border-amber-400/40 bg-amber-500 px-4 py-3 text-xs font-black uppercase tracking-[0.14em] text-zinc-950 transition hover:bg-amber-300 disabled:cursor-not-allowed disabled:opacity-50 [&>svg]:h-4 [&>svg]:w-4 [&>svg]:fill-none [&>svg]:stroke-current ${cutPanel}`}
                        onClick={() => setCreateOpen(true)}
                    >
                        <PlusIcon />
                        Criar novo usuário
                    </button>
                )}
            </section>

            <section className="members-panel admin-users-panel">
                <header className="members-panel-header">
                    <div>
                        <p className="section-kicker">CREDENCIAIS</p>
                        <h2>Usuários autorizados</h2>
                        <p>
                            Os códigos permanecem protegidos por hash e só são
                            exibidos no momento da geração.
                        </p>
                    </div>
                </header>

                <div className="table-toolbar">
                <form
                    className="table-search"
                    onSubmit={(event) => {
                        event.preventDefault();
                        router.get(
                            route('admin.users.index'),
                            { search: userSearch },
                            { preserveScroll: true },
                        );
                    }}
                >
                        <input
                            type="search"
                            value={userSearch}
                            placeholder="Nome do usuário"
                            onChange={(event) => setUserSearch(event.target.value)}
                        />
                        <button
                            type="button"
                            onClick={() =>
                                router.get(
                                    route('admin.users.index'),
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            Limpar
                        </button>
                        <button>Buscar</button>
                </form>
                </div>

                <div className="members-table-wrap">
                    <table className="members-table admin-users-table">
                        <thead>
                            <tr>
                                <th>Usuário</th>
                                <th>Função</th>
                                <th>Contas vinculadas</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            {users.data.map((user) => (
                                <tr key={user.id}>
                                    <td>
                                        <strong>{user.name}</strong>
                                    </td>
                                    <td>
                                        <div className="flex flex-col items-start gap-1.5">
                                            <span className={`${cutBadge} ${
                                                user.role.slug === 'admin'
                                                    ? 'border-amber-400/30 bg-amber-400/10 text-amber-300'
                                                    : 'border-white/15 bg-zinc-900 text-zinc-300'
                                            }`}>
                                                {roleLabels[user.role.slug] ?? user.role.name}
                                            </span>
                                            {canEditRole(user) && (
                                                <button
                                                    className="text-xs font-bold text-zinc-400 underline decoration-zinc-700 underline-offset-4 hover:text-amber-300 hover:decoration-amber-500"
                                                    onClick={() => openRoleEditor(user)}
                                                >
                                                    Alterar função
                                                </button>
                                            )}
                                        </div>
                                    </td>
                                    <td>
                                        {user.players.length ? (
                                            <div className="flex max-w-sm flex-wrap gap-1.5">
                                                {user.players.map((player) => (
                                                    <span
                                                        className={`${cutBadge} border-white/15 bg-zinc-900 text-zinc-300`}
                                                        key={player.id}
                                                    >
                                                        {player.name}
                                                        <small className="mt-0.5 block text-[0.68rem] text-zinc-600">
                                                            {player.player_tag}
                                                            {player.memberships.length > 0 &&
                                                                ` · ${player.memberships.map((membership) => membership.clan.name ?? membership.clan.tag).join(', ')}`}
                                                        </small>
                                                    </span>
                                                ))}
                                            </div>
                                        ) : (
                                            <span className="text-sm text-zinc-600">
                                                Nenhuma conta
                                            </span>
                                        )}
                                    </td>
                                    <td>
                                        <div className="flex flex-wrap gap-1.5">
                                            {permissions.linkPlayers && (
                                                <button className={actionButton} onClick={() => openMemberLink(user)}>
                                                    Vincular contas
                                                </button>
                                            )}
                                            {permissions.generateCodes &&
                                                user.id !== auth.user.id && (
                                                    <button
                                                        className={`${actionButton} border-amber-400/35 text-amber-300`}
                                                        onClick={() => regenerate(user)}
                                                    >
                                                        Gerar código de acesso
                                                    </button>
                                                )}
                                            {permissions.deleteUsers &&
                                                user.id !== auth.user.id &&
                                                user.role.slug !== 'admin' && (
                                                    <button
                                                        className={`${actionButton} border-rose-400/40 bg-rose-600 text-white hover:bg-rose-500`}
                                                        onClick={() => setDeletingUser(user)}
                                                    >
                                                        Excluir conta
                                                    </button>
                                                )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <Pagination pagination={users} />
            </section>

            {createOpen && (
                <Modal title="Criar novo usuário" onClose={() => setCreateOpen(false)}>
                    <form onSubmit={createUser} className="profile-form admin-user-form">
                        <div>
                            <label htmlFor="user-name">Nome</label>
                            <input
                                id="user-name"
                                value={createForm.data.name}
                                onChange={(event) =>
                                    createForm.setData('name', event.target.value)
                                }
                                autoFocus
                                required
                            />
                            <InputError message={createForm.errors.name} className="mt-2" />
                        </div>
                        <div>
                            <label htmlFor="user-role">Função</label>
                            <select
                                id="user-role"
                                value={createForm.data.role_id}
                                onChange={(event) =>
                                    createForm.setData('role_id', Number(event.target.value))
                                }
                            >
                                {roles.map((role) => (
                                    <option key={role.id} value={role.id}>
                                        {roleLabels[role.slug] ?? role.name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={createForm.errors.role_id} className="mt-2" />
                        </div>
                        <div className="modal-form-actions">
                            <button type="button" onClick={() => setCreateOpen(false)}>
                                Cancelar
                            </button>
                            <button className="is-primary" disabled={createForm.processing}>
                                {createForm.processing ? 'Criando...' : 'Criar e gerar código'}
                            </button>
                        </div>
                    </form>
                </Modal>
            )}

            {editingRole && (
                <Modal
                    title={`Alterar função de ${editingRole.name}`}
                    onClose={() => setEditingRole(null)}
                    important={promotingToAdmin}
                >
                    <form onSubmit={saveRole} className="profile-form admin-user-form">
                        <div>
                            <label htmlFor="edit-user-role">Nova função</label>
                            <select
                                id="edit-user-role"
                                value={roleForm.data.role_id}
                                onChange={(event) => {
                                    roleForm.setData(
                                        'role_id',
                                        Number(event.target.value),
                                    );
                                    setAdminConfirmed(false);
                                }}
                                autoFocus
                            >
                                {editableRoles.map((role) => (
                                    <option key={role.id} value={role.id}>
                                        {roleLabels[role.slug] ?? role.name}
                                    </option>
                                ))}
                            </select>
                            <InputError
                                message={roleForm.errors.role_id}
                                className="mt-2"
                            />
                        </div>

                        {promotingToAdmin && (
                            <label className="admin-promotion-check">
                                <input
                                    type="checkbox"
                                    checked={adminConfirmed}
                                    onChange={(event) =>
                                        setAdminConfirmed(event.target.checked)
                                    }
                                />
                                <span>
                                    <strong>Confirmar acesso administrativo</strong>
                                    <small>
                                        Este usuário poderá gerenciar acessos,
                                        configurações e outros administradores.
                                    </small>
                                </span>
                            </label>
                        )}

                        <div className="modal-form-actions">
                            <button type="button" onClick={() => setEditingRole(null)}>
                                Cancelar
                            </button>
                            <button
                                className="is-primary"
                                disabled={
                                    roleForm.processing ||
                                    (promotingToAdmin && !adminConfirmed)
                                }
                            >
                                {roleForm.processing ? 'Salvando...' : 'Salvar função'}
                            </button>
                        </div>
                    </form>
                </Modal>
            )}

            {deletingUser && (
                <Modal
                    title={`Excluir conta de ${deletingUser.name}?`}
                    onClose={() => setDeletingUser(null)}
                    important
                >
                    <div className="delete-user-confirmation">
                        <p>
                            Esta ação remove permanentemente o acesso ao Clan Hub.
                            As contas do jogo vinculadas serão preservadas e ficarão
                            disponíveis para um novo vínculo.
                        </p>
                        <div className="delete-user-impact">
                            <strong>{deletingUser.players.length}</strong>
                            <span>
                                {deletingUser.players.length === 1
                                    ? 'conta vinculada será liberada'
                                    : 'contas vinculadas serão liberadas'}
                            </span>
                        </div>
                        <div className="modal-form-actions">
                            <button type="button" onClick={() => setDeletingUser(null)}>
                                Cancelar
                            </button>
                            <button className="is-danger" onClick={deleteUser}>
                                Excluir permanentemente
                            </button>
                        </div>
                    </div>
                </Modal>
            )}

            {linkingUser && (
                <Modal
                    title={`Vincular contas a ${linkingUser.name}`}
                    onClose={() => setLinkingUser(null)}
                >
                    <form onSubmit={saveMemberLinks}>
                        <p className="link-members-help">
                            Selecione todas as contas do jogo pertencentes a
                            este usuário.
                        </p>
                        <div className="link-members-search">
                            <SearchIcon />
                            <input
                                type="search"
                                value={playerSearch}
                                onChange={(event) =>
                                    setPlayerSearch(event.target.value)
                                }
                                placeholder="Buscar por nome ou player tag"
                                autoFocus
                            />
                            <span>
                                {filteredPlayers.length}{' '}
                                {filteredPlayers.length === 1
                                    ? 'resultado'
                                    : 'resultados'}
                            </span>
                        </div>
                        <div className="link-members-list">
                            {players.length === 0 ? (
                                <div className="link-members-empty">
                                    Sincronize os membros do clã primeiro.
                                </div>
                            ) : filteredPlayers.length === 0 ? (
                                <div className="link-members-empty">
                                    Nenhum membro encontrado para essa busca.
                                </div>
                            ) : (
                                filteredPlayers.map((player) => (
                                    <label
                                        key={player.id}
                                        className={
                                            linkForm.data.player_ids.includes(
                                                player.id,
                                            )
                                                ? 'is-selected'
                                                : undefined
                                        }
                                    >
                                        <input
                                            type="checkbox"
                                            checked={linkForm.data.player_ids.includes(player.id)}
                                            onChange={() =>
                                                linkForm.setData(
                                                    'player_ids',
                                                    toggleId(
                                                        linkForm.data.player_ids,
                                                        player.id,
                                                    ),
                                                )
                                            }
                                        />
                                        <span>
                                            <strong>{player.name}</strong>
                                            <small>
                                                {player.player_tag}
                                                {player.town_hall_level
                                                    ? ` · CV ${player.town_hall_level}`
                                                    : ''}
                                                {player.user &&
                                                player.user.id !== linkingUser.id
                                                    ? ` · atualmente com ${player.user.name}`
                                                    : ''}
                                            </small>
                                            <span className="player-clan-memberships">
                                                {player.memberships.length > 0
                                                    ? player.memberships.map((membership) => (
                                                          <small
                                                              key={membership.id}
                                                              className={`is-${membership.status.slug}`}
                                                          >
                                                              {membership.clan.name ??
                                                                  membership.clan.tag}
                                                              {' · '}
                                                              {membershipStatusLabels[
                                                                  membership.status.slug
                                                              ]}
                                                          </small>
                                                      ))
                                                    : (
                                                          <small>Sem clã atual</small>
                                                      )}
                                            </span>
                                        </span>
                                    </label>
                                ))
                            )}
                        </div>
                        <InputError message={linkForm.errors.player_ids} className="mt-2" />
                        <div className="modal-form-actions">
                            <button type="button" onClick={() => setLinkingUser(null)}>
                                Cancelar
                            </button>
                            <button className="is-primary" disabled={linkForm.processing}>
                                {linkForm.processing ? 'Salvando...' : 'Salvar vínculos'}
                            </button>
                        </div>
                    </form>
                </Modal>
            )}

            {codeOpen && generatedAccess && (
                <Modal
                    title="Código de acesso gerado"
                    onClose={() => setCodeOpen(false)}
                    important
                >
                    <div className="generated-code-content">
                        <p>
                            Código para <strong>{generatedAccess.user_name}</strong>.
                            Ele não poderá ser consultado novamente.
                        </p>
                        <button className="generated-code" onClick={copyCode}>
                            <code>{generatedAccess.code}</code>
                            <span>{copied ? 'Copiado!' : 'Copiar código'}</span>
                        </button>
                        <div className="generated-code-warning">
                            Envie este código por um canal seguro antes de fechar.
                        </div>
                    </div>
                </Modal>
            )}
        </AuthenticatedLayout>
    );
}

function Modal({ title, children, onClose, important = false }) {
    return (
        <div className="war-modal-backdrop" onMouseDown={onClose}>
            <section
                className={`war-modal credential-modal ${important ? 'is-important' : ''}`}
                role="dialog"
                aria-modal="true"
                onMouseDown={(event) => event.stopPropagation()}
            >
                <header>
                    <div>
                        <p className="section-kicker">
                            {important ? 'EXIBIÇÃO ÚNICA' : 'GERENCIAR ACESSO'}
                        </p>
                        <h2>{title}</h2>
                    </div>
                    <button onClick={onClose} aria-label="Fechar">
                        ×
                    </button>
                </header>
                <div className="credential-modal-body">{children}</div>
            </section>
        </div>
    );
}

function toggleId(ids, id) {
    return ids.includes(id)
        ? ids.filter((currentId) => currentId !== id)
        : [...ids, id];
}

function normalizeSearch(value) {
    return value
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();
}

function SearchIcon() {
    return (
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="m21 21-4.35-4.35M19 11a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z" />
        </svg>
    );
}

function PlusIcon() {
    return (
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M12 5v14M5 12h14" />
        </svg>
    );
}
