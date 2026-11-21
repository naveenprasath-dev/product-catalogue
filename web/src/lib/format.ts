// Fake Store prices carry no currency; we assume USD (DECISIONS.md). Change it here only.
const CURRENCY = 'USD'
const LOCALE = 'en-US'

const priceFormatter = new Intl.NumberFormat(LOCALE, { style: 'currency', currency: CURRENCY })

export function formatPrice(price: number): string {
  return priceFormatter.format(price)
}

export function formatRating(rate: number): string {
  return rate.toFixed(1)
}
