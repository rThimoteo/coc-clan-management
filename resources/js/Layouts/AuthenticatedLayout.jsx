import { Link, router, usePage } from '@inertiajs/react';
import {
    ChevronDown,
    LayoutDashboard,
    LogOut,
    Radio,
    ShieldCog,
    Swords,
    Trophy,
    User,
    UserCog,
    Users,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

const navigation = [
    {
        code: 'OV',
        label: 'Visão geral',
        note: 'Status do clã',
        route: 'dashboard',
        icon: LayoutDashboard,
    },
    {
        code: 'SQ',
        label: 'Membros',
        note: 'Roster e vínculos',
        route: 'members.index',
        icon: Users,
    },
    {
        code: 'WR',
        label: 'Guerras',
        note: 'Histórico e ataques',
        route: 'wars.index',
        icon: Swords,
    },
    {
        code: 'LG',
        label: 'Liga de Clãs',
        note: 'CWL e rodadas',
        route: 'cwl.index',
        icon: Trophy,
    },
];

export default function AuthenticatedLayout({ header, eyebrow, children }) {
    const { auth, clanContext } = usePage().props;
    const [profileOpen, setProfileOpen] = useState(false);
    const [switchingClan, setSwitchingClan] = useState(false);
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
            <aside className="app-sidebar">
                <Link href={route('dashboard')} className="app-brand">
                    <span className="app-brand-logo">
                        <img src="/images/clan_hub.png" alt="" />
                    </span>
                    <span>
                        <strong>Clan Hub</strong>
                        <small>WAR CONSOLE</small>
                    </span>
                </Link>

                <div className="app-nav-label">Módulos</div>
                <nav className="app-nav" aria-label="Menu principal">
                    {navigation.map((item) => {
                        const Icon = item.icon;
                        const active = route().current(item.route);

                        return (
                            <Link
                                key={item.route}
                                href={route(item.route)}
                                className={`app-nav-item ${active ? 'is-active' : ''}`}
                            >
                                <span className="app-nav-icon">
                                    <Icon />
                                </span>
                                <span className="app-nav-copy">
                                    <strong>{item.label}</strong>
                                    <small>{item.note}</small>
                                </span>
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
                            >
                                <span className="app-nav-icon">
                                    <UserCog />
                                </span>
                                <span className="app-nav-copy">
                                    <strong>Usuários</strong>
                                    <small>Acessos e vínculos</small>
                                </span>
                            </Link>
                            {auth.user.role === 'admin' && (
                                <Link
                                    href={route('admin.clans.index')}
                                    className={`app-nav-item ${route().current('admin.clans.*') ? 'is-active' : ''}`}
                                >
                                    <span className="app-nav-icon">
                                        <ShieldCog />
                                    </span>
                                    <span className="app-nav-copy">
                                        <strong>Configurar clã</strong>
                                        <small>Tags e padrões</small>
                                    </span>
                                </Link>
                            )}
                        </nav>
                    </>
                )}

                <div className="app-sidebar-footer">
                    <span className="app-status-dot" />
                    <span>
                        <strong>{clanContext.active?.name ?? 'Sem clã ativo'}</strong>
                        <small>
                            {clanContext.active?.tag
                                ? `${clanContext.active.tag} · online`
                                : 'Selecione um clã'}
                        </small>
                    </span>
                </div>
            </aside>

            <div className="app-main">
                <header className="app-topbar">
                    <div className="app-topbar-context">
                        <span>WAR CONSOLE</span>
                        <i />
                        <strong>{header}</strong>
                    </div>

                    <label className="app-clan-switcher">
                        <span>Clã ativo</span>
                        <select
                            value={clanContext.active?.id ?? ''}
                            disabled={
                                switchingClan ||
                                clanContext.available.length === 0
                            }
                            onChange={(event) => {
                                setSwitchingClan(true);
                                router.put(
                                    route('clan-context.update'),
                                    { clan_id: Number(event.target.value) },
                                    {
                                        preserveScroll: false,
                                        preserveState: false,
                                        onFinish: () =>
                                            setSwitchingClan(false),
                                    },
                                );
                            }}
                            aria-label="Selecionar clã ativo"
                        >
                            {clanContext.available.length === 0 && (
                                <option value="">Nenhum clã</option>
                            )}
                            {clanContext.available.map((clan) => (
                                <option key={clan.id} value={clan.id}>
                                    {clan.name ?? clan.tag}
                                    {clan.is_default ? ' · padrão' : ''}
                                </option>
                            ))}
                        </select>
                    </label>

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
                            <ChevronDown />
                        </button>

                        {profileOpen && (
                            <div className="app-profile-dropdown">
                                <div className="app-profile-dropdown-name">
                                    <small>Conectado como</small>
                                    <strong>{auth.user.name}</strong>
                                </div>
                                <Link href={route('profile.edit')}>
                                    <User /> Perfil
                                </Link>
                                <Link
                                    href={route('logout')}
                                    method="post"
                                    as="button"
                                >
                                    <LogOut /> Sair
                                </Link>
                            </div>
                        )}
                    </div>
                </header>

                <main className="app-content">
                    <div className="app-page-heading">
                        <p>
                            <Radio />
                            <span>{eyebrow}</span>
                        </p>
                        <h1>{header}</h1>
                    </div>
                    {children}
                </main>
            </div>
        </div>
    );
}
