import InputError from '@/Components/InputError';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

const roleLabels = {
    admin: 'Administrador',
    leader: 'Líder',
    co_leader: 'Colíder',
    member: 'Membro',
};

const memberStatusLabels = {
    in: 'No clã',
    out: 'Fora do clã',
};

export default function Index({ users, roles, members }) {
    const { generatedAccess } = usePage().props;
    const [createOpen, setCreateOpen] = useState(false);
    const [linkingUser, setLinkingUser] = useState(null);
    const [memberSearch, setMemberSearch] = useState('');
    const [codeOpen, setCodeOpen] = useState(Boolean(generatedAccess));
    const [copied, setCopied] = useState(false);

    const createForm = useForm({
        name: '',
        role_id: roles.find((role) => role.slug === 'leader')?.id ?? roles[0]?.id,
    });
    const linkForm = useForm({ member_ids: [] });
    const filteredMembers = members.filter((member) => {
        const search = normalizeSearch(memberSearch);

        return (
            normalizeSearch(member.name).includes(search) ||
            normalizeSearch(member.player_tag).includes(search)
        );
    });

    const createUser = (event) => {
        event.preventDefault();
        createForm.post(route('admin.users.store'), {
            preserveScroll: true,
            onSuccess: () => {
                createForm.reset('name');
                setCreateOpen(false);
                setCodeOpen(true);
            },
        });
    };

    const openMemberLink = (user) => {
        setLinkingUser(user);
        setMemberSearch('');
        linkForm.setData(
            'member_ids',
            user.members.map((member) => member.id),
        );
        linkForm.clearErrors();
    };

    const saveMemberLinks = (event) => {
        event.preventDefault();
        linkForm.put(route('admin.users.members.update', linkingUser.id), {
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

    return (
        <AuthenticatedLayout header="Usuários" eyebrow="ADMINISTRAÇÃO DE ACESSOS">
            <Head title="Usuários" />

            <section className="admin-users-summary">
                <div>
                    <span>Acessos cadastrados</span>
                    <strong>{users.length}</strong>
                </div>
                <div>
                    <span>Contas do jogo vinculadas</span>
                    <strong>
                        {members.filter((member) => member.user_id).length}
                    </strong>
                </div>
                <button onClick={() => setCreateOpen(true)}>
                    <PlusIcon />
                    Criar novo usuário
                </button>
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

                <div className="members-table-wrap">
                    <table className="members-table admin-users-table">
                        <thead>
                            <tr>
                                <th>Usuário</th>
                                <th>Papel</th>
                                <th>Contas vinculadas</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            {users.map((user) => (
                                <tr key={user.id}>
                                    <td>
                                        <strong>{user.name}</strong>
                                        <small>Acesso #{user.id}</small>
                                    </td>
                                    <td>
                                        <span className={`user-role is-${user.role.slug}`}>
                                            {roleLabels[user.role.slug] ?? user.role.name}
                                        </span>
                                    </td>
                                    <td>
                                        {user.members.length ? (
                                            <div className="linked-members">
                                                {user.members.map((member) => (
                                                    <span key={member.id}>
                                                        {member.name}
                                                        <small>{member.player_tag}</small>
                                                    </span>
                                                ))}
                                            </div>
                                        ) : (
                                            <span className="no-linked-members">
                                                Nenhuma conta
                                            </span>
                                        )}
                                    </td>
                                    <td>
                                        <div className="admin-user-actions">
                                            <button onClick={() => openMemberLink(user)}>
                                                Vincular membros
                                            </button>
                                            <button
                                                className="is-warning"
                                                onClick={() => regenerate(user)}
                                            >
                                                Gerar código de acesso
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
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
                            <label htmlFor="user-role">Papel</label>
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
                                value={memberSearch}
                                onChange={(event) =>
                                    setMemberSearch(event.target.value)
                                }
                                placeholder="Buscar por nome ou player tag"
                                autoFocus
                            />
                            <span>
                                {filteredMembers.length}{' '}
                                {filteredMembers.length === 1
                                    ? 'resultado'
                                    : 'resultados'}
                            </span>
                        </div>
                        <div className="link-members-list">
                            {members.length === 0 ? (
                                <div className="link-members-empty">
                                    Sincronize os membros do clã primeiro.
                                </div>
                            ) : filteredMembers.length === 0 ? (
                                <div className="link-members-empty">
                                    Nenhum membro encontrado para essa busca.
                                </div>
                            ) : (
                                filteredMembers.map((member) => (
                                    <label key={member.id}>
                                        <input
                                            type="checkbox"
                                            checked={linkForm.data.member_ids.includes(member.id)}
                                            onChange={() =>
                                                linkForm.setData(
                                                    'member_ids',
                                                    toggleId(
                                                        linkForm.data.member_ids,
                                                        member.id,
                                                    ),
                                                )
                                            }
                                        />
                                        <span>
                                            <strong>{member.name}</strong>
                                            <small>
                                                {member.player_tag} ·{' '}
                                                {memberStatusLabels[member.status.slug]}
                                                {member.user &&
                                                member.user.id !== linkingUser.id
                                                    ? ` · atualmente com ${member.user.name}`
                                                    : ''}
                                            </small>
                                        </span>
                                    </label>
                                ))
                            )}
                        </div>
                        <InputError message={linkForm.errors.member_ids} className="mt-2" />
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
