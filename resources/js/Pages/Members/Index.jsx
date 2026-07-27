import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, usePage } from '@inertiajs/react';

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

export default function Index({ members, clan }) {
    const { auth, errors, status, syncSummary } = usePage().props;
    const { post, processing } = useForm({});
    const inClan = members.filter((member) => member.status.slug === 'in').length;
    const outClan = members.length - inClan;

    const sync = () => {
        post(route('members.sync'), { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout header="Membros" eyebrow="ROSTER DO CLÃ">
            <Head title="Membros" />

            <section className="members-summary">
                <div>
                    <span>Total registrado</span>
                    <strong>{members.length}</strong>
                </div>
                <div>
                    <span>No clã</span>
                    <strong>{inClan}</strong>
                </div>
                <div>
                    <span>Fora do clã</span>
                    <strong>{outClan}</strong>
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
                    {auth.user.role !== 'member' && (
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

                {!clan && (
                    <div className="members-empty-warning">
                        A tag do clã precisa ser configurada antes da primeira
                        sincronização.
                    </div>
                )}

                {members.length === 0 ? (
                    <div className="members-empty">
                        <div className="members-empty-mark">00</div>
                        <h3>Nenhum membro sincronizado.</h3>
                        <p>
                            Use o botão acima para importar a formação atual do
                            clã.
                        </p>
                    </div>
                ) : (
                    <div className="members-table-wrap">
                        <table className="members-table">
                            <thead>
                                <tr>
                                    <th>Jogador</th>
                                    <th>Cargo</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                {members.map((member) => (
                                    <tr key={member.id}>
                                        <td>
                                            <strong>{member.name}</strong>
                                            <small>{member.player_tag}</small>
                                        </td>
                                        <td>{roleLabels[member.role] ?? member.role ?? '—'}</td>
                                        <td>
                                            <span className={`member-status is-${member.status.slug}`}>
                                                <i />
                                                {statusLabels[member.status.slug]}
                                            </span>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </section>
        </AuthenticatedLayout>
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
