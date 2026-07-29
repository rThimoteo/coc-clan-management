export default function GuestLayout({
    children,
    eyebrow,
    title,
    description,
}) {
    return (
        <main className="auth-shell">
            <div className="auth-grid" aria-hidden="true" />
            <section className="auth-intro">
                <div className="auth-logo">
                    <img src="/images/clan_hub.png" alt="Clan Hub" />
                </div>
                <div>
                    <p className="auth-eyebrow">{eyebrow}</p>
                    <h1>{title}</h1>
                    <p>{description}</p>
                </div>
                <div className="auth-coordinate">CLAN HUB · CENTRAL DO CLÃ</div>
            </section>

            <section className="auth-panel">
                <div className="auth-card">{children}</div>
            </section>
        </main>
    );
}
