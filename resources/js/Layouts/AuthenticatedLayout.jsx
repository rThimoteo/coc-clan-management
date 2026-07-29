import { Link, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

const navigation = [
    { label: 'Visão geral', route: 'dashboard', icon: GridIcon },
    { label: 'Membros', route: 'members.index', icon: MembersIcon },
    { label: 'Guerras', route: 'wars.index', icon: WarsIcon },
];

export default function AuthenticatedLayout({ header, eyebrow, children }) {
    const { auth } = usePage().props;
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [profileOpen, setProfileOpen] = useState(false);
    const profileMenu = useRef(null);

    useEffect(() => {
        const closeMenu = (event) => {
            if (!profileMenu.current?.contains(event.target)) {
                setProfileOpen(false);
            }
        };

        document.addEventListener('mousedown', closeMenu);
        return () => document.removeEventListener('mousedown', closeMenu);
    }, []);

    return (
        <div className="app-shell">
            {sidebarOpen && (
                <button
                    className="app-sidebar-backdrop"
                    aria-label="Fechar menu"
                    onClick={() => setSidebarOpen(false)}
                />
            )}

            <aside className={`app-sidebar ${sidebarOpen ? 'is-open' : ''}`}>
                <Link href={route('dashboard')} className="app-brand">
                    <span className="app-brand-logo">
                        <img src="/images/clan_hub.png" alt="" />
                    </span>
                    <span>
                        <strong>Clan Hub</strong>
                        <small>CENTRAL DO CLÃ</small>
                    </span>
                </Link>

                <div className="app-nav-label">Central</div>
                <nav className="app-nav" aria-label="Menu principal">
                    {navigation.map((item) => {
                        const Icon = item.icon;
                        const active = route().current(item.route);

                        return (
                            <Link
                                key={item.route}
                                href={route(item.route)}
                                className={`app-nav-item ${active ? 'is-active' : ''}`}
                                onClick={() => setSidebarOpen(false)}
                            >
                                <Icon />
                                <span>{item.label}</span>
                                {active && <span className="app-nav-pip" />}
                            </Link>
                        );
                    })}
                </nav>

                {['admin', 'leader'].includes(auth.user.role) && (
                    <>
                        <div className="app-nav-label app-nav-section">
                            Administração
                        </div>
                        <nav className="app-nav" aria-label="Administração">
                            <Link
                                href={route('admin.users.index')}
                                className={`app-nav-item ${route().current('admin.users.*') ? 'is-active' : ''}`}
                                onClick={() => setSidebarOpen(false)}
                            >
                                <UsersIcon />
                                <span>Usuários</span>
                                {route().current('admin.users.*') && (
                                    <span className="app-nav-pip" />
                                )}
                            </Link>
                            {auth.user.role === 'admin' && (
                                <Link
                                    href={route('admin.clan.edit')}
                                    className={`app-nav-item ${route().current('admin.clan.*') ? 'is-active' : ''}`}
                                    onClick={() => setSidebarOpen(false)}
                                >
                                    <ShieldIcon />
                                    <span>Configurar clã</span>
                                    {route().current('admin.clan.*') && (
                                        <span className="app-nav-pip" />
                                    )}
                                </Link>
                            )}
                        </nav>
                    </>
                )}

                <div className="app-sidebar-footer">
                    <span className="app-status-dot" />
                    <span>
                        <strong>Sistema operacional</strong>
                        <small>Ambiente conectado</small>
                    </span>
                </div>
            </aside>

            <div className="app-main">
                <header className="app-topbar">
                    <button
                        className="app-menu-button"
                        aria-label="Abrir menu"
                        onClick={() => setSidebarOpen(true)}
                    >
                        <MenuIcon />
                    </button>

                    <div className="app-topbar-context">
                        <span>CLAN HUB</span>
                        <i />
                        <strong>{header}</strong>
                    </div>

                    <div className="app-profile-menu" ref={profileMenu}>
                        <button
                            className="app-profile-trigger"
                            aria-expanded={profileOpen}
                            onClick={() => setProfileOpen((open) => !open)}
                        >
                            <span>
                                <small>Acesso ativo</small>
                                <strong>{auth.user.name}</strong>
                            </span>
                            <ChevronIcon />
                        </button>

                        {profileOpen && (
                            <div className="app-profile-dropdown">
                                <div className="app-profile-dropdown-name">
                                    <small>Conectado como</small>
                                    <strong>{auth.user.name}</strong>
                                </div>
                                <Link href={route('profile.edit')}>
                                    <UserIcon /> Perfil
                                </Link>
                                <Link
                                    href={route('logout')}
                                    method="post"
                                    as="button"
                                >
                                    <ExitIcon /> Sair
                                </Link>
                            </div>
                        )}
                    </div>
                </header>

                <main className="app-content">
                    <div className="app-page-heading">
                        <p>{eyebrow}</p>
                        <h1>{header}</h1>
                    </div>
                    {children}
                </main>
            </div>
        </div>
    );
}

function GridIcon() {
    return <IconPath d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z" />;
}

function UserIcon() {
    return <IconPath d="M20 21a8 8 0 0 0-16 0M12 13a5 5 0 1 0 0-10 5 5 0 0 0 0 10Z" />;
}

function MembersIcon() {
    return <IconPath d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />;
}

function UsersIcon() {
    return <IconPath d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM19 8v6M22 11h-6" />;
}

function WarsIcon() {
    return <IconPath d="m14.5 4.5 5 5M16 3l5 5-8.5 8.5-5-5L16 3ZM9.5 14.5 4 20M4 15v5h5M9.5 4.5l-5 5M8 3 3 8l4.5 4.5" />;
}

function ShieldIcon() {
    return <IconPath d="M12 3 5 6v5c0 4.6 2.9 8.1 7 10 4.1-1.9 7-5.4 7-10V6l-7-3Zm0 5v8M8 12h8" />;
}

function ExitIcon() {
    return <IconPath d="M10 17l5-5-5-5M15 12H3M15 4h4a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-4" />;
}

function MenuIcon() {
    return <IconPath d="M4 7h16M4 12h16M4 17h16" />;
}

function ChevronIcon() {
    return <IconPath d="m8 10 4 4 4-4" />;
}

function IconPath({ d }) {
    return (
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d={d} fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    );
}
