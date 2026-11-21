// The selected currency is a display preference, not auth data, so localStorage is fine (DECISIONS.md).
// Storage can be unavailable (private mode, blocked site data, quota): every access is guarded and
// the shop simply falls back to the server's default currency.
export const CURRENCY_STORAGE_KEY = 'catalogue.currency'

export function readSavedCurrency(): string | null {
  try {
    const value = window.localStorage.getItem(CURRENCY_STORAGE_KEY)
    return value !== null && /^[A-Za-z]{3}$/.test(value) ? value.toUpperCase() : null
  } catch {
    return null
  }
}

export function saveCurrency(code: string | null): void {
  try {
    if (code === null) {
      window.localStorage.removeItem(CURRENCY_STORAGE_KEY)
    } else {
      window.localStorage.setItem(CURRENCY_STORAGE_KEY, code)
    }
  } catch {
    // Not persisted; the choice still applies for this visit.
  }
}
