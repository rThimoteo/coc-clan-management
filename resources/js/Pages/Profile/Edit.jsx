import InputError from '@/Components/InputError';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, usePage } from '@inertiajs/react';

export default function Edit() {
    const { auth, status } = usePage().props;
    const { data, setData, patch, processing, errors, recentlySuccessful } =
        useForm({
            name: auth.user.name,
        });

    const submit = (event) => {
        event.preventDefault();
        patch(route('profile.update'), { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout header="Perfil" eyebrow="CONFIGURAÇÕES DA CONTA">
            <Head title="Perfil" />

            <section className="profile-panel">
                <div className="profile-panel-copy">
                    <p className="section-kicker">IDENTIFICAÇÃO</p>
                    <h2>Seu nome no sistema</h2>
                    <p>
                        Este nome aparece na barra superior e identifica seu
                        acesso nas áreas administrativas.
                    </p>
                </div>

                <form onSubmit={submit} className="profile-form">
                    <div>
                        <label htmlFor="name">Nome</label>
                        <input
                            id="name"
                            value={data.name}
                            onChange={(event) =>
                                setData('name', event.target.value)
                            }
                            autoComplete="name"
                            autoFocus
                            required
                        />
                        <InputError message={errors.name} className="mt-2" />
                    </div>

                    <div className="profile-form-footer">
                        <span
                            className={
                                recentlySuccessful ||
                                status === 'profile-updated'
                                    ? 'is-visible'
                                    : ''
                            }
                        >
                            Alterações salvas.
                        </span>
                        <button disabled={processing}>
                            {processing ? 'Salvando...' : 'Salvar nome'}
                        </button>
                    </div>
                </form>
            </section>
        </AuthenticatedLayout>
    );
}
