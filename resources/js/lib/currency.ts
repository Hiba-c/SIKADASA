/**
 * Format a number as Indonesian Rupiah currency.
 * Example: 1000000 → "Rp1.000.000"
 */
export function formatCurrency(amount: number): string {
    const formatted = new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(Math.abs(amount));

    const prefix = amount < 0 ? '-Rp' : 'Rp';
    return `${prefix}${formatted}`;
}

/**
 * Format currency with decimal places.
 * Example: 1000000 → "Rp1.000.000,00"
 */
export function formatCurrencyDecimal(amount: number): string {
    const formatted = new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Math.abs(amount));

    const prefix = amount < 0 ? '-Rp' : 'Rp';
    return `${prefix}${formatted}`;
}

/**
 * Parse a formatted currency string back to a number.
 * Example: "1.000.000" → 1000000
 */
export function parseCurrency(value: string): number {
    const cleaned = value.replace(/[^\d,-]/g, '').replace(',', '.');
    const num = parseFloat(cleaned);
    return isNaN(num) ? 0 : num;
}

/**
 * Format a number with Indonesian thousand separators.
 * Example: 1000000 → "1.000.000"
 */
export function formatNumber(num: number): string {
    return new Intl.NumberFormat('id-ID').format(num);
}
