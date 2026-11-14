// resources/js/core/format.js
// Formatage monétaire unifié : précision 6 décimales pour les montants
// AVAXe / TST / PRANA (demande UX mobile 2026-11-13).
export const AMOUNT_DECIMALS = 6;

export function formatAmount(value, decimals = AMOUNT_DECIMALS) {
    const n = Number(value);
    if (!Number.isFinite(n)) return (0).toFixed(decimals);
    return n.toFixed(decimals);
}

export function formatToken(value, symbol, decimals = AMOUNT_DECIMALS) {
    return `${formatAmount(value, decimals)} ${symbol}`;
}
