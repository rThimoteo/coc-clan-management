import { useEffect, useState } from 'react';

export default function LiveWarCountdown({
    endTime,
    compact = false,
    label = 'TERMINA EM',
    expiredLabel = 'Encerrando...',
}) {
    const classes = compact
        ? 'mt-1 inline-flex text-sm font-black uppercase tracking-[0.12em] text-zinc-400'
        : 'inline-grid gap-1 font-display text-amber-300';

    const [remaining, setRemaining] = useState(() =>
        remainingSeconds(endTime),
    );

    useEffect(() => {
        setRemaining(remainingSeconds(endTime));

        const timer = window.setInterval(() => {
            setRemaining(remainingSeconds(endTime));
        }, 1000);

        return () => window.clearInterval(timer);
    }, [endTime]);

    if (remaining <= 0) {
        return (
            <span className={classes}>
                {expiredLabel}
            </span>
        );
    }

    const hours = Math.floor(remaining / 3600);
    const minutes = Math.floor((remaining % 3600) / 60);
    const seconds = remaining % 60;
    const formatted = [hours, minutes, seconds]
        .map((value) => String(value).padStart(2, '0'))
        .join(':');

    return (
        <time
            className={classes}
            dateTime={new Date(endTime).toISOString()}
            aria-label={`${hours} horas, ${minutes} minutos e ${seconds} segundos restantes`}
        >
            {!compact && (
                <small className="font-sans text-[0.66rem] font-black uppercase tracking-[0.16em] text-zinc-500">
                    {label}
                </small>
            )}
            <strong className={compact ? undefined : 'text-2xl font-black'}>{formatted}</strong>
        </time>
    );
}

function remainingSeconds(endTime) {
    return Math.max(
        0,
        Math.ceil((new Date(endTime).getTime() - Date.now()) / 1000),
    );
}
