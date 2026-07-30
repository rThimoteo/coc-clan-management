import { useEffect, useState } from 'react';

export default function LiveWarCountdown({
    endTime,
    compact = false,
    label = 'TERMINA EM',
    expiredLabel = 'Encerrando...',
}) {
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
            <span className={`live-war-countdown ${compact ? 'is-compact' : ''}`}>
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
            className={`live-war-countdown ${compact ? 'is-compact' : ''}`}
            dateTime={new Date(endTime).toISOString()}
            aria-label={`${hours} horas, ${minutes} minutos e ${seconds} segundos restantes`}
        >
            {!compact && <small>{label}</small>}
            <strong>{formatted}</strong>
        </time>
    );
}

function remainingSeconds(endTime) {
    return Math.max(
        0,
        Math.ceil((new Date(endTime).getTime() - Date.now()) / 1000),
    );
}
