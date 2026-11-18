import { Link } from '@inertiajs/react';
import { Home, Swords, Trophy, User, Users } from 'lucide-react';

export default function MobileNavigation() {
    const battles = route().current('wars.*') || route().current('cwl.*');
    const account = route().current('profile.*') || route().current('admin.*');
    const items = [
        { label: 'Início', icon: Home, href: route('dashboard'), active: route().current('dashboard') },
        { label: 'Guerras', icon: Swords, href: route(route().current('cwl.*') ? 'cwl.index' : 'wars.index'), active: battles },
        { label: 'Membros', icon: Users, href: route('members.index'), active: route().current('members.*') },
        { label: 'Perfil', icon: User, href: route('profile.edit'), active: account },
    ];

    return (
        <nav className="mobile-bottom-nav" aria-label="Navegação principal mobile">
            {items.map(({ label, icon: Icon, href, active }) => (
                <Link key={label} href={href} className={active ? 'is-active' : ''} aria-current={active ? 'page' : undefined}>
                    <Icon aria-hidden="true" /><span>{label}</span>
                </Link>
            ))}
        </nav>
    );
}

export function MobileBattleNavigation() {
    if (!route().current('wars.*') && !route().current('cwl.*')) return null;
    return <nav className="mobile-battle-nav" aria-label="Modalidade de guerra">
        {[['wars.index', 'wars.*', 'Guerras', Swords], ['cwl.index', 'cwl.*', 'Liga de Clãs', Trophy]].map(([name, pattern, label, Icon]) => (
            <Link key={name} href={route(name)} className={route().current(pattern) ? 'is-active' : ''}
                aria-current={route().current(pattern) ? 'page' : undefined}>
                <Icon aria-hidden="true" />{label}
            </Link>
        ))}
    </nav>;
}
