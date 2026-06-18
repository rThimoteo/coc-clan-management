import { Head, Link } from '@inertiajs/react';

export default function Dashboard() {
    return (
        <>
            <Head title="Dashboard" />
            <main className="flex min-h-screen items-center justify-center bg-stone-950 text-stone-100">
                <div className="text-center">
                    <h1 className="font-display text-5xl font-black tracking-tight">
                        Dashboard
                    </h1>
                    <Link
                        href={route('logout')}
                        method="post"
                        as="button"
                        className="mt-6 text-sm text-stone-500 transition hover:text-amber-400"
                    >
                        Sair
                    </Link>
                </div>
            </main>
        </>
    );
}
