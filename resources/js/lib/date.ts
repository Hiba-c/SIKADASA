/**
 * Format a date string to Indonesian locale.
 * Example: "2026-10-31" → "31 Oktober 2026"
 */
export function formatDate(dateString: string): string {
    const date = new Date(dateString);
    return new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(date);
}

/**
 * Format a date to short Indonesian format.
 * Example: "2026-10-31" → "31 Okt 2026"
 */
export function formatDateShort(dateString: string): string {
    const date = new Date(dateString);
    return new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(date);
}

/**
 * Format a datetime string to Indonesian locale with time.
 * Example: "2026-10-31T10:30:00" → "31 Oktober 2026, 10:30"
 */
export function formatDateTime(dateString: string): string {
    const date = new Date(dateString);
    return new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
}

/**
 * Format a date to relative time.
 * Example: "2 jam yang lalu", "3 hari yang lalu"
 */
export function formatRelative(dateString: string): string {
    const date = new Date(dateString);
    const now = new Date();
    const diffInSeconds = Math.floor((now.getTime() - date.getTime()) / 1000);

    if (diffInSeconds < 60) return 'Baru saja';
    if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)} menit lalu`;
    if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)} jam lalu`;
    if (diffInSeconds < 2592000) return `${Math.floor(diffInSeconds / 86400)} hari lalu`;

    return formatDateShort(dateString);
}

/**
 * Get current date as YYYY-MM-DD string.
 */
export function today(): string {
    return new Date().toISOString().split('T')[0];
}

/**
 * Get current month period as YYYYMM string.
 */
export function currentPeriod(): string {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    return `${year}${month}`;
}
