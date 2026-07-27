import InputError from '@/Components/InputError';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';

export default function Login({ status }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        access_code: '',
    });

    const submit = (event) => {
        event.preventDefault();

        post(route('login'), {
            onFinish: () => reset('access_code'),
        });
    };

    return (
        <GuestLayout
            eyebrow="ÁREA DO CLÃ"
            title="A guerra começa com organização."
            description="Use seu código de acesso para entrar na central de gerenciamento."
        >
            <Head title="Entrar" />

            {status && (
                <div className="mb-5 rounded-xl border border-emerald-400/20 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-200">
                    {status}
                </div>
            )}

            <form onSubmit={submit} className="space-y-5">
                <div>
                    <label className="auth-label" htmlFor="access_code">
                        Código de acesso
                    </label>
                    <input
                        id="access_code"
                        name="access_code"
                        type="password"
                        value={data.access_code}
                        className="auth-input"
                        autoComplete="off"
                        autoFocus
                        onChange={(event) =>
                            setData('access_code', event.target.value)
                        }
                        required
                    />
                    <InputError
                        message={errors.access_code}
                        className="mt-2"
                    />
                </div>

                <button className="auth-button" disabled={processing}>
                    {processing ? 'Entrando...' : 'Entrar na central'}
                </button>
            </form>
        </GuestLayout>
    );
}
