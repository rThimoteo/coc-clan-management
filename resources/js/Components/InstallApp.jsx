import { Download } from 'lucide-react';
import { useEffect, useState } from 'react';

export default function InstallApp() {
    const [prompt, setPrompt] = useState(null);
    const [installed, setInstalled] = useState(false);
    const [instructions, setInstructions] = useState(false);
    const [error, setError] = useState('');
    const [installing, setInstalling] = useState(false);

    useEffect(() => {
        const display = window.matchMedia('(display-mode: standalone)');
        const update = () => setInstalled(display.matches || window.navigator.standalone === true);
        const ready = (event) => {
            event.preventDefault();
            setPrompt(event);
        };
        const complete = () => { setInstalled(true); setPrompt(null); };
        update();
        window.addEventListener('beforeinstallprompt', ready);
        window.addEventListener('appinstalled', complete);
        display.addEventListener('change', update);
        return () => {
            window.removeEventListener('beforeinstallprompt', ready);
            window.removeEventListener('appinstalled', complete);
            display.removeEventListener('change', update);
        };
    }, []);

    if (installed) return null;

    const install = async () => {
        if (!prompt) { setInstructions((open) => !open); return; }
        setInstalling(true);
        setError('');
        try {
            await prompt.prompt();
            const choice = await prompt.userChoice;
            if (choice.outcome === 'accepted') setInstructions(false);
        } catch {
            setError('Não foi possível abrir a instalação. Tente pelo menu do navegador.');
            setInstructions(true);
        } finally {
            setPrompt(null);
            setInstalling(false);
        }
    };

    return (
        <div className="mx-4 my-3 text-sm text-zinc-300">
            <button type="button" onClick={install} disabled={installing}
                className="flex w-full items-center justify-center gap-2 rounded border border-amber-400/30 bg-amber-400/10 px-3 py-2 text-amber-300 hover:bg-amber-400/20 disabled:opacity-50"
                aria-expanded={instructions}>
                <Download size={16} aria-hidden="true" />
                {installing ? 'Abrindo instalação…' : 'Instalar aplicativo'}
            </button>
            {instructions && (
                <div className="mt-3 space-y-2 text-xs leading-relaxed" role="status">
                    {error && <p>{error}</p>}
                    <p>No iPhone/iPad, abra no Safari e toque em Compartilhar → Adicionar à Tela de Início.</p>
                    <p>No Android ou computador, procure “Instalar aplicativo” ou “Adicionar à tela inicial” no menu do navegador. A disponibilidade depende do navegador.</p>
                </div>
            )}
        </div>
    );
}
