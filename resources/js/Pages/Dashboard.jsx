import ActiveWarAlert from '@/Components/ActiveWarAlert';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

const metrics = [
    { label: 'Membros ativos', value: '—', note: 'Aguardando sincronização' },
    { label: 'Guerras no mês', value: '—', note: 'Nenhum dado registrado' },
    { label: 'Taxa de vitória', value: '—', note: 'Histórico ainda vazio' },
];

export default function Dashboard({ activeWar }) {
    return (
        <AuthenticatedLayout
            header="Visão geral"
            eyebrow="PAINEL DE COMANDO"
        >
            <Head title="Visão geral" />

            <ActiveWarAlert war={activeWar} />

            <section className="dashboard-grid">
                {metrics.map((metric, index) => (
                    <article className="metric-card" key={metric.label}>
                        <div className="metric-index">0{index + 1}</div>
                        <p>{metric.label}</p>
                        <strong>{metric.value}</strong>
                        <span>{metric.note}</span>
                    </article>
                ))}
            </section>

            <section className="empty-command-card">
                <div className="empty-command-symbol">
                    <span />
                    <span />
                    <span />
                </div>
                <div>
                    <p className="section-kicker">PRÓXIMA MISSÃO</p>
                    <h2>Sua central está pronta.</h2>
                    <p>
                        Os módulos operacionais aparecerão aqui conforme o
                        gerenciamento do clã evoluir.
                    </p>
                </div>
                <span className="empty-command-code">HQ·001</span>
            </section>
        </AuthenticatedLayout>
    );
}
