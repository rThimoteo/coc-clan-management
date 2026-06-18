import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Login({ status }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        username: '',
        password: '',
        remember: false,
    });

    const submit = (event) => {
        event.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout
            eyebrow="ÁREA DO CLÃ"
            title="A guerra começa com organização."
            description="Entre com suas credenciais para acessar a central de gerenciamento."
        >
            <Head title="Entrar" />

            {status && (
                <div className="mb-5 rounded-xl border border-emerald-400/20 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-200">
                    {status}
                </div>
            )}

            <form onSubmit={submit} className="space-y-5">
                <div>
                    <label className="auth-label" htmlFor="username">
                        Usuário
                    </label>
                    <input
                        id="username"
                        name="username"
                        value={data.username}
                        className="auth-input"
                        autoComplete="username"
                        autoFocus
                        onChange={(event) =>
                            setData('username', event.target.value)
                        }
                        required
                    />
                    <InputError message={errors.username} className="mt-2" />
                </div>

                <div>
                    <label className="auth-label" htmlFor="password">
                        Senha
                    </label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        value={data.password}
                        className="auth-input"
                        autoComplete="current-password"
                        onChange={(event) =>
                            setData('password', event.target.value)
                        }
                        required
                    />
                    <InputError message={errors.password} className="mt-2" />
                </div>

                <label className="flex cursor-pointer items-center gap-3 text-sm text-stone-400">
                    <Checkbox
                        name="remember"
                        checked={data.remember}
                        onChange={(event) =>
                            setData('remember', event.target.checked)
                        }
                    />
                    Manter conectado
                </label>

                <button className="auth-button" disabled={processing}>
                    {processing ? 'Entrando...' : 'Entrar na central'}
                </button>

                <p className="text-center text-sm text-stone-400">
                    Ainda não tem acesso?{' '}
                    <Link className="auth-link" href={route('register')}>
                        Criar conta
                    </Link>
                </p>
            </form>
        </GuestLayout>
    );
}
