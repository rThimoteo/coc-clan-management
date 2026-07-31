import InputError from '@/Components/InputError';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';
import { KeyRound, LogIn } from 'lucide-react';

const cutControl =
    '[clip-path:polygon(0_0,calc(100%-0.45rem)_0,100%_0.45rem,100%_100%,0.45rem_100%,0_calc(100%-0.45rem))]';

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
        <GuestLayout>
            <Head title="Entrar" />

            {status && (
                <div className="m-5 mb-0 border border-emerald-400/20 bg-emerald-400/10 px-4 py-3 text-sm font-bold text-emerald-200 [clip-path:polygon(0_0,calc(100%-0.55rem)_0,100%_0.55rem,100%_100%,0.55rem_100%,0_calc(100%-0.55rem))]">
                    {status}
                </div>
            )}

            <form onSubmit={submit}>
                <header className="grid justify-items-center gap-5 border-b border-white/10 bg-zinc-950/35 p-7 text-center">
                    <img
                        className="h-32 w-auto object-contain sm:h-36"
                        src="/images/clan_hub.png"
                        alt="Clan Hub"
                    />
                    <div>
                        <p className="text-xs font-black uppercase tracking-[0.18em] text-amber-400">
                            Acesso restrito
                        </p>
                        <h1 className="mt-1 font-display text-2xl font-black text-zinc-100">
                            Entrar
                        </h1>
                    </div>
                </header>

                <div className="grid gap-5 p-5">
                    <div className="grid gap-2">
                        <label htmlFor="access_code">
                            <span className="text-xs font-black uppercase tracking-[0.14em] text-amber-400">
                                Código de acesso
                            </span>
                        </label>
                        <div className="relative">
                            <KeyRound className="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-600" />
                            <input
                                id="access_code"
                                name="access_code"
                                type="password"
                                value={data.access_code}
                                className={`min-h-12 w-full border border-white/15 bg-zinc-950 py-3 pl-11 pr-4 text-sm font-bold text-zinc-100 placeholder:text-zinc-600 focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-400/10 ${cutControl}`}
                                autoComplete="off"
                                autoFocus
                                onChange={(event) =>
                                    setData('access_code', event.target.value)
                                }
                                required
                            />
                        </div>
                    </div>
                    <InputError
                        message={errors.access_code}
                        className="mt-[-0.75rem]"
                    />

                    <button
                        className={`inline-flex min-h-12 w-full items-center justify-center gap-2 border border-amber-400/40 bg-amber-500 px-4 py-3 text-xs font-black uppercase tracking-[0.14em] text-zinc-950 transition hover:bg-amber-300 disabled:cursor-not-allowed disabled:opacity-60 ${cutControl}`}
                        disabled={processing}
                    >
                        <LogIn className="h-4 w-4" />
                        {processing ? 'Entrando...' : 'Entrar na central'}
                    </button>
                </div>
            </form>
        </GuestLayout>
    );
}
