// The label of each song's service, shown on song rows and in the queue panel. YouTube Music joins it in T-10 to T-12.
export const SOURCE_LABELS: Record<string, string> = { spotify: 'Spotify' };

export function sourceLabel(source: string): string {
    return SOURCE_LABELS[source] ?? source;
}
