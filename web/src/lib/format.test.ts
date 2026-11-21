import { formatPrice } from './format'

describe('formatPrice', () => {
  it('formats the amount in the given currency without converting it', () => {
    expect(formatPrice(55.99, 'USD')).toBe('$55.99')
    expect(formatPrice(51.51, 'EUR')).toBe('€51.51')
    expect(formatPrice(44.23, 'GBP')).toBe('£44.23')
    expect(formatPrice(1234.5, 'EUR')).toBe('€1,234.50')
  })

  it('falls back to "amount CODE" for a malformed code instead of throwing', () => {
    expect(formatPrice(10, 'NOT-A-CODE')).toBe('10.00 NOT-A-CODE')
  })
})
