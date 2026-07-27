import InputError from '@/Components/InputError';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, usePage } from '@inertiajs/react';

export default function Edit({ clan }) {
    const { status } = usePage().props;
    const { data, setData, patch, processing, errors, recentlySuccessful } =
        useForm({
            tag: clan?.tag ?? '',
        });

    const submit = (event) => {
        event.preventDefault();
        patch(route('admin.clan.update'), { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout
            header="Configurar clã"
            eyebrow="ADMINISTRAÇÃO"
        >
            <Head title="Configurar clã" />

            <section className="profile-panel clan-settings-panel">
                <div className="profile-panel-copy">
                    <p className="section-kicker">IDENTIDADE DO CLÃ</p>
                    <h2>Tag oficial</h2>
                    <p>
                        Antes de salvar, a tag é consultada na API oficial para
                        confirmar que o clã existe.
                    </p>
                </div>

                <form onSubmit={submit} className="profile-form">
                    <div>
                        <label htmlFor="tag">Tag do clã</label>
                        <input
                            id="tag"
                            value={data.tag}
                            onChange={(event) =>
                                setData('tag', event.target.value.toUpperCase())
                            }
                            placeholder="#QGRJ2"
                            autoComplete="off"
                            autoFocus
                            required
                        />
                        <InputError message={errors.tag} className="mt-2" />
                        <p className="field-hint">
                            Você pode informar a tag com ou sem o caractere #.
                        </p>
                    </div>

                    <div className="profile-form-footer">
                        <span
                            className={
                                recentlySuccessful || status === 'clan-updated'
                                    ? 'is-visible'
                                    : ''
                            }
                        >
                            Configuração salva.
                        </span>
                        <button disabled={processing}>
                            {processing ? 'Validando clã...' : 'Validar e salvar'}
                        </button>
                    </div>
                </form>
            </section>
        </AuthenticatedLayout>
    );
}
