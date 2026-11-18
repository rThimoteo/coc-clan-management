import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import InputError from './InputError';

export default function ClanSwitcher() {
    const { clanContext, errors } = usePage().props;
    const [switching, setSwitching] = useState(false);

    return (
        <div>
            <label className="app-clan-switcher">
                <span>Clã ativo</span>
                <select
                    value={clanContext.active?.id ?? ''}
                    disabled={switching || clanContext.available.length === 0}
                    aria-label="Selecionar clã ativo"
                    aria-busy={switching}
                    onChange={(event) => {
                        setSwitching(true);
                        router.put(
                            route('clan-context.update'),
                            { clan_id: Number(event.target.value) },
                            {
                                preserveScroll: true,
                                preserveState: false,
                                onFinish: () => setSwitching(false),
                            },
                        );
                    }}
                >
                    {clanContext.available.length === 0 && <option value="">Nenhum clã</option>}
                    {clanContext.available.map((clan) => (
                        <option key={clan.id} value={clan.id}>
                            {clan.name ?? clan.tag}{clan.is_default ? ' · padrão' : ''}
                        </option>
                    ))}
                </select>
            </label>
            <InputError message={errors?.clan_id} className="mt-2" />
        </div>
    );
}
