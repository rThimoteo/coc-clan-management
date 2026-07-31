const cutPanel =
    '[clip-path:polygon(0_0,calc(100%-0.85rem)_0,100%_0.85rem,100%_100%,0.85rem_100%,0_calc(100%-0.85rem))]';

export default function GuestLayout({ children }) {
    return (
        <main className="relative grid min-h-screen place-items-center overflow-hidden bg-[#08090b] px-5 py-8 text-zinc-100">
            <div
                className="pointer-events-none absolute inset-0 opacity-35 [background-image:linear-gradient(rgba(255,255,255,0.045)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.045)_1px,transparent_1px)] [background-size:4rem_4rem]"
                aria-hidden="true"
            />
            <div
                className="pointer-events-none absolute left-0 top-0 h-80 w-80 bg-amber-500/10 blur-3xl"
                aria-hidden="true"
            />

            <section className={`relative z-10 w-full max-w-md overflow-hidden border border-white/10 bg-zinc-900/80 shadow-2xl shadow-black/30 ${cutPanel}`}>
                {children}
            </section>
        </main>
    );
}
