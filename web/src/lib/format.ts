// Prices arrive already converted by the API, together with their ISO 4217 code (`product.currency`).
// The locale is fixed so output is predictable (DECISIONS.md); change it here only.
const LOCALE = 'en-US'

const formatters = new Map<string, Intl.NumberFormat>()

function formatterFor(currencyCode: string): Intl.NumberFormat | null {
  const code = currencyCode.toUpperCase()
  const cached = formatters.get(code)
  if (cached !== undefined) return cached
  try {
    const formatter = new Intl.NumberFormat(LOCALE, { style: 'currency', currency: code })
    formatters.set(code, formatter)
    return formatter
  } catch {
    // Not a well-formed currency code (RangeError): fall back to "12.34 XYZ" instead of crashing the page.
    return null
  }
}

/** "€50.51" for (50.51, 'EUR'). Never converts: `amount` must already be in `currencyCode`. */
export function formatPrice(amount: number, currencyCode: string): string {
  const formatter = formatterFor(currencyCode)
  return formatter === null ? `${amount.toFixed(2)} ${currencyCode}` : formatter.format(amount)
}

export function formatRating(rate: number): string {
  return rate.toFixed(1)
}
