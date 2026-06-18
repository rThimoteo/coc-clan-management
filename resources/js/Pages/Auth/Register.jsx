import InputError from '@/Components/InputError';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Register({ demoMode = false }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        username: '',
        player_tag: '',
        password: '',
        password_confirmation: '',
    });

    const submit = (event) => {
        event.preventDefault();

        post(route('register'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <GuestLayout
            eyebrow="NOVO RECRUTA"
            title="Entre para a central do clã."
            description="Seu acesso será liberado depois que confirmarmos sua player tag no clã."
        >
            <Head title="Criar conta" />

            {demoMode && (
                <div className="mb-5 rounded-xl border border-amber-400/25 bg-amber-400/10 px-4 py-3 text-sm leading-relaxed text-amber-100">
                    <strong className="block text-amber-300">
                        Modo de demonstração
                    </strong>
                    A API externa está desativada. Use uma tag válida de
                    exemplo, como <code>#PQLG2</code>.
                </div>
            )}

            <form onSubmit={submit} className="space-y-5">
                <Field
                    id="username"
                    label="Usuário"
                    value={data.username}
                    onChange={(value) => setData('username', value)}
                    error={errors.username}
                    autoComplete="username"
                    autoFocus
                    placeholder="seu_usuario"
                />

                <Field
                    id="player_tag"
                    label="Player tag"
                    value={data.player_tag}
                    onChange={(value) => setData('player_tag', value.toUpperCase())}
                    error={errors.player_tag}
                    autoComplete="off"
                    placeholder="#2PP"
                />

                <Field
                    id="password"
                    label="Senha"
                    type="password"
                    value={data.password}
                    onChange={(value) => setData('password', value)}
                    error={errors.password}
                    autoComplete="new-password"
                    placeholder="Mínimo de 8 caracteres"
                />

                <Field
                    id="password_confirmation"
                    label="Confirmar senha"
                    type="password"
                    value={data.password_confirmation}
                    onChange={(value) => setData('password_confirmation', value)}
                    error={errors.password_confirmation}
                    autoComplete="new-password"
                    placeholder="Repita sua senha"
                />

                <button className="auth-button" disabled={processing}>
                    {processing ? 'Validando jogador...' : 'Criar acesso'}
                </button>

                <p className="text-center text-sm text-stone-400">
                    Já possui acesso?{' '}
                    <Link className="auth-link" href={route('login')}>
                        Entrar
                    </Link>
                </p>
            </form>
        </GuestLayout>
    );
}

function Field({
    id,
    label,
    type = 'text',
    value,
    onChange,
    error,
    ...props
}) {
    return (
        <div>
            <label className="auth-label" htmlFor={id}>
                {label}
            </label>
            <input
                id={id}
                name={id}
                type={type}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className="auth-input"
                required
                {...props}
            />
            <InputError message={error} className="mt-2" />
        </div>
    );
}
