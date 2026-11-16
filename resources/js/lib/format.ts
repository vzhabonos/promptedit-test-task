const numberFormat = new Intl.NumberFormat();
const relativeFormat = new Intl.RelativeTimeFormat(undefined, {
    numeric: 'auto',
});

export function formatNumber(value: number): string {
    return numberFormat.format(value);
}

/** "2,000" or "200–2,000" */
export function formatRange(min: number, max: number): string {
    return min === max
        ? formatNumber(max)
        : `${formatNumber(min)}–${formatNumber(max)}`;
}

/** "$0.05" or "$0.05–$0.09" */
export function formatMoneyRange(min: string, max: string): string {
    return min === max ? max : `${min}–${max}`;
}

export function formatRelativeTime(iso: string): string {
    const seconds = Math.round((new Date(iso).getTime() - Date.now()) / 1000);
    const units: [Intl.RelativeTimeFormatUnit, number][] = [
        ['day', 86_400],
        ['hour', 3_600],
        ['minute', 60],
    ];

    for (const [unit, size] of units) {
        if (Math.abs(seconds) >= size) {
            return relativeFormat.format(Math.round(seconds / size), unit);
        }
    }

    return 'just now';
}
