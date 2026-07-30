import InputError from '@/Components/InputError';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function Index({ clans }) {
    const { status } = usePage().props;
    const [createOpen, setCreateOpen] = useState(false);
    const [deletingClan, setDeletingClan] = useState(null);
    const [changingDefault, setChangingDefault] = useState(null);
    const [refreshingClan, setRefreshingClan] = useState(null);
    const createForm = useForm({ tag: '' });
    const deleteForm = useForm({
        acknowledge_data_loss: false,
        confirmation: '',
    });

    useEffect(() => {
        const close = (event) => {
            if (event.key === 'Escape') {
                setCreateOpen(false);
                setDeletingClan(null);
            }
        };

        document.addEventListener('keydown', close);
        return () => document.removeEventListener('keydown', close);
    }, []);

    const createClan = (event) => {
        event.preventDefault();
        createForm.post(route('admin.clans.store'), {
            preserveScroll: true,
            onSuccess: () => {
                createForm.reset();
                setCreateOpen(false);
            },
        });
    };

    const setDefault = (clan) => {
        setChangingDefault(clan.id);
        router.patch(
            route('admin.clans.default', clan.id),
            {},
            {
                preserveScroll: true,
                onFinish: () => setChangingDefault(null),
            },
        );
    };

    const refreshClan = (clan) => {
        setRefreshingClan(clan.id);
        router.patch(
            route('admin.clans.refresh', clan.id),
            {},
            {
                preserveScroll: true,
                onFinish: () => setRefreshingClan(null),
            },
        );
    };

    const openDeletion = (clan) => {
        deleteForm.reset();
        deleteForm.clearErrors();
        setDeletingClan(clan);
    };

    const deleteClan = (event) => {
        event.preventDefault();
        deleteForm.delete(route('admin.clans.destroy', deletingClan.id), {
            preserveScroll: true,
            onSuccess: () => setDeletingClan(null),
        });
    };

    return (
        <AuthenticatedLayout
            header="Clãs administrados"
            eyebrow="ESTRUTURA DA FAMÍLIA"
        >
            <Head title="Clãs administrados" />

            {status && (
                <div className="clan-admin-feedback" role="status">
                    {statusMessage(status)}
                </div>
            )}

            <section className="clan-admin-hero">
                <div>
                    <p className="section-kicker">CENTRAL MULTI-CLÃ</p>
                    <h2>Uma operação, vários campos de batalha.</h2>
                    <p>
                        O clã ativo controla dashboard, membros e guerras. Os
                        usuários e seus acessos continuam compartilhados.
                    </p>
                </div>
                <button onClick={() => setCreateOpen(true)}>
                    <PlusIcon />
                    Adicionar clã
                </button>
            </section>

            {clans.length === 0 ? (
                <section className="clan-admin-empty">
                    <span>00</span>
                    <div>
                        <h2>Nenhum clã configurado.</h2>
                        <p>
                            Adicione a primeira tag para liberar sincronizações
                            e o painel operacional.
                        </p>
                    </div>
                    <button onClick={() => setCreateOpen(true)}>
                        Configurar primeiro clã
                    </button>
                </section>
            ) : (
                <section className="clan-admin-grid">
                    {clans.map((clan, index) => (
                        <article
                            className={`clan-admin-card ${clan.is_default ? 'is-default' : ''}`}
                            key={clan.id}
                        >
                            <header>
                                <div className="clan-admin-identity">
                                    {clan.badge_url ? (
                                        <img src={clan.badge_url} alt="" />
                                    ) : (
                                        <span>{String(index + 1).padStart(2, '0')}</span>
                                    )}
                                    <div>
                                        <small>{clan.tag}</small>
                                        <h2>{clan.name ?? 'Clã sem nome'}</h2>
                                    </div>
                                </div>
                                {clan.is_default && (
                                    <div className="clan-default-badge">
                                        <DefaultIcon />
                                        PADRÃO
                                    </div>
                                )}
                            </header>

                            <div className="clan-admin-impact">
                                <div>
                                    <strong>{clan.memberships_count}</strong>
                                    <span>membros registrados</span>
                                </div>
                                <div>
                                    <strong>{clan.wars_count}</strong>
                                    <span>guerras preservadas</span>
                                </div>
                            </div>

                            <footer>
                                <button
                                    className="clan-refresh-action"
                                    disabled={refreshingClan === clan.id}
                                    onClick={() => refreshClan(clan)}
                                >
                                    {refreshingClan === clan.id
                                        ? 'Consultando API...'
                                        : 'Revalidar dados'}
                                </button>
                                {!clan.is_default && (
                                    <button
                                        className="clan-default-action"
                                        disabled={changingDefault === clan.id}
                                        onClick={() => setDefault(clan)}
                                    >
                                        {changingDefault === clan.id
                                            ? 'Alterando...'
                                            : 'Definir como padrão'}
                                    </button>
                                )}
                                <button
                                    className="clan-delete-action"
                                    onClick={() => openDeletion(clan)}
                                >
                                    Remover clã
                                </button>
                            </footer>
                        </article>
                    ))}
                </section>
            )}

            <AdminModal
                open={createOpen}
                eyebrow="NOVO CONTEXTO"
                title="Adicionar clã"
                onClose={() => setCreateOpen(false)}
            >
                <form className="clan-admin-form" onSubmit={createClan}>
                    <p>
                        A tag será validada na API oficial antes do cadastro.
                        Nome e emblema serão preenchidos automaticamente.
                    </p>
                    <label htmlFor="new-clan-tag">Tag do clã</label>
                    <input
                        id="new-clan-tag"
                        value={createForm.data.tag}
                        onChange={(event) =>
                            createForm.setData(
                                'tag',
                                event.target.value.toUpperCase(),
                            )
                        }
                        placeholder="#2Q8L9Y0JP"
                        autoComplete="off"
                        autoFocus
                        required
                    />
                    <InputError message={createForm.errors.tag} />
                    <div className="clan-modal-actions">
                        <button
                            type="button"
                            onClick={() => setCreateOpen(false)}
                        >
                            Cancelar
                        </button>
                        <button
                            className="is-primary"
                            disabled={createForm.processing}
                        >
                            {createForm.processing
                                ? 'Validando...'
                                : 'Validar e adicionar'}
                        </button>
                    </div>
                </form>
            </AdminModal>

            <AdminModal
                open={Boolean(deletingClan)}
                eyebrow="ZONA DE RISCO"
                title={`Remover ${deletingClan?.name ?? deletingClan?.tag ?? ''}`}
                dangerous
                onClose={() => setDeletingClan(null)}
            >
                {deletingClan && (
                    <form
                        className="clan-delete-form"
                        onSubmit={deleteClan}
                    >
                        <div className="clan-delete-impact">
                            <strong>Esta ação é permanente.</strong>
                            <p>
                                Serão removidos{' '}
                                <b>{deletingClan.memberships_count} membros</b>,{' '}
                                <b>{deletingClan.wars_count} guerras</b>,
                                participantes, ataques e todo o histórico
                                exclusivo deste clã.
                            </p>
                        </div>

                        <label className="clan-delete-check">
                            <input
                                type="checkbox"
                                checked={
                                    deleteForm.data.acknowledge_data_loss
                                }
                                onChange={(event) =>
                                    deleteForm.setData(
                                        'acknowledge_data_loss',
                                        event.target.checked,
                                    )
                                }
                            />
                            <span>
                                Entendo que os dados do clã não poderão ser
                                recuperados.
                            </span>
                        </label>
                        <InputError
                            message={
                                deleteForm.errors.acknowledge_data_loss
                            }
                        />

                        <label htmlFor="clan-delete-confirmation">
                            Digite <strong>{deletingClan.tag}</strong> ou{' '}
                            <strong>{deletingClan.name}</strong> para confirmar
                        </label>
                        <input
                            id="clan-delete-confirmation"
                            value={deleteForm.data.confirmation}
                            onChange={(event) =>
                                deleteForm.setData(
                                    'confirmation',
                                    event.target.value,
                                )
                            }
                            autoComplete="off"
                            required
                        />
                        <InputError
                            message={deleteForm.errors.confirmation}
                        />

                        <div className="clan-modal-actions">
                            <button
                                type="button"
                                onClick={() => setDeletingClan(null)}
                            >
                                Manter clã
                            </button>
                            <button
                                className="is-danger"
                                disabled={
                                    deleteForm.processing ||
                                    !deleteForm.data.acknowledge_data_loss ||
                                    !deleteForm.data.confirmation
                                }
                            >
                                {deleteForm.processing
                                    ? 'Removendo...'
                                    : 'Apagar clã e histórico'}
                            </button>
                        </div>
                    </form>
                )}
            </AdminModal>
        </AuthenticatedLayout>
    );
}

function AdminModal({
    open,
    eyebrow,
    title,
    dangerous = false,
    onClose,
    children,
}) {
    if (!open) {
        return null;
    }

    return (
        <div className="war-modal-backdrop" onMouseDown={onClose}>
            <section
                className={`war-modal clan-admin-modal ${dangerous ? 'is-dangerous' : ''}`}
                role="dialog"
                aria-modal="true"
                aria-labelledby="clan-admin-modal-title"
                onMouseDown={(event) => event.stopPropagation()}
            >
                <header>
                    <div>
                        <p className="section-kicker">{eyebrow}</p>
                        <h2 id="clan-admin-modal-title">{title}</h2>
                    </div>
                    <button onClick={onClose} aria-label="Fechar">
                        ×
                    </button>
                </header>
                <div className="clan-admin-modal-body">{children}</div>
            </section>
        </div>
    );
}

function statusMessage(status) {
    return {
        'clan-created': 'Clã validado e adicionado à operação.',
        'clan-refreshed': 'Identidade do clã revalidada na API.',
        'clan-default-updated': 'Clã padrão atualizado.',
        'clan-deleted': 'Clã e seu histórico foram removidos.',
    }[status];
}

function PlusIcon() {
    return (
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M12 5v14M5 12h14" />
        </svg>
    );
}

function DefaultIcon() {
    return (
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-2.9-5.6 2.9 1.1-6.2L3 9.6l6.2-.9L12 3Z" />
        </svg>
    );
}
