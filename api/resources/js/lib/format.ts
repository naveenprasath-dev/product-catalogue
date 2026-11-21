const dateTime = new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
});

/** ISO 8601 → local date and time; null → an em dash. */
export function formatDateTime(iso: string | null): string {
    return iso === null ? '—' : dateTime.format(new Date(iso));
}
