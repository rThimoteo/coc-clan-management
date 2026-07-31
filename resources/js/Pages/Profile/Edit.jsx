import InputError from '@/Components/InputError';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, usePage } from '@inertiajs/react';
import { CheckCircle2, Save, User } from 'lucide-react';

const cutPanel =
    '[clip-path:polygon(0_0,calc(100%-0.75rem)_0,100%_0.75rem,100%_100%,0.75rem_100%,0_calc(100%-0.75rem))]';
const cutControl =
    '[clip-path:polygon(0_0,calc(100%-0.45rem)_0,100%_0.45rem,100%_100%,0.45rem_100%,0_calc(100%-0.45rem))]';

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

            <section className={`overflow-hidden border border-white/10 bg-zinc-900/80 ${cutPanel}`}>
                <header className="grid gap-4 border-b border-white/10 bg-zinc-950/35 p-5 md:grid-cols-[auto_minmax(0,1fr)] md:items-center">
                    <span className={`grid h-12 w-12 place-items-center border border-amber-400/35 bg-amber-400/10 text-amber-300 ${cutControl}`}>
                        <User className="h-5 w-5" />
                    </span>
                    <div>
                        <p className="section-kicker">IDENTIFICAÇÃO</p>
                        <h2 className="mt-1 font-display text-2xl font-black text-zinc-100">
                            Seu nome no sistema
                        </h2>
                        <p className="mt-2 max-w-2xl text-sm leading-6 text-zinc-500">
                            Este nome aparece na barra superior e identifica seu
                            acesso nas áreas administrativas.
                        </p>
                    </div>
                </header>

                <form onSubmit={submit} className="grid gap-5 p-5">
                    <label className="grid gap-2" htmlFor="name">
                        <span className="text-xs font-black uppercase tracking-[0.14em] text-amber-400">
                            Nome
                        </span>
                        <input
                            className={`min-h-12 w-full border border-white/15 bg-zinc-950 px-4 py-3 text-sm font-bold text-zinc-100 placeholder:text-zinc-600 focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-400/10 ${cutControl}`}
                            id="name"
                            value={data.name}
                            onChange={(event) =>
                                setData('name', event.target.value)
                            }
                            autoComplete="name"
                            autoFocus
                            required
                        />
                        <InputError message={errors.name} className="mt-1" />
                    </label>

                    <footer className="flex min-h-12 flex-col gap-3 border-t border-white/10 pt-5 sm:flex-row sm:items-center sm:justify-end">
                        <span
                            className={
                                recentlySuccessful ||
                                status === 'profile-updated'
                                    ? 'inline-flex items-center gap-2 text-sm font-bold text-emerald-300'
                                    : 'hidden'
                            }
                        >
                            <CheckCircle2 className="h-4 w-4" />
                            Alterações salvas.
                        </span>
                        <button
                            className={`inline-flex items-center justify-center gap-2 border border-amber-400/40 bg-amber-500 px-4 py-3 text-xs font-black uppercase tracking-[0.14em] text-zinc-950 transition hover:bg-amber-300 disabled:cursor-not-allowed disabled:opacity-60 ${cutControl}`}
                            disabled={processing}
                        >
                            <Save className="h-4 w-4" />
                            {processing ? 'Salvando...' : 'Salvar nome'}
                        </button>
                    </footer>
                </form>
            </section>
        </AuthenticatedLayout>
    );
}
