import type { Category, CategoryProducts, Currency, CurrencyList, Product, User } from '../api/types'

export const ada: User = { id: 7, name: 'Ada Lovelace', email: 'ada@example.com' }

export const mensClothing: Category = { slug: 'mens-clothing', name: "men's clothing" }
export const electronics: Category = { slug: 'electronics', name: 'electronics' }
export const categories: Category[] = [electronics, mensClothing]

let nextId = 1

export function makeProduct(overrides: Partial<Product> = {}): Product {
  const id = overrides.id ?? nextId++
  return {
    id,
    title: `Product ${id}`,
    description: `Description of product ${id}`,
    price: 19.99,
    currency: 'USD',
    image_url: `https://images.example.test/${id}.jpg`,
    category: mensClothing,
    rating: { rate: 4.1, count: 120 },
    ...overrides,
  }
}

export const backpack = makeProduct({
  id: 1,
  title: 'Fjallraven Foldsack No. 1 Backpack',
  description: 'Your perfect pack for everyday use and walks in the forest.',
  price: 109.95,
  rating: { rate: 3.9, count: 120 },
})
export const jacket = makeProduct({
  id: 3,
  title: 'Mens Cotton Jacket',
  description: 'Great outerwear jackets for Spring/Autumn/Winter.',
  price: 55.99,
  rating: { rate: 4.7, count: 500 },
})
export const tshirt = makeProduct({ id: 2, title: 'Mens Casual Premium Slim Fit T-Shirts', rating: { rate: 4.1, count: 259 } })
export const shirt = makeProduct({ id: 4, title: 'Mens Casual Slim Fit', rating: { rate: 2.1, count: 430 } })
export const harddrive = makeProduct({
  id: 9,
  title: 'WD 2TB Elements Portable External Hard Drive',
  category: electronics,
  price: 64,
  rating: { rate: 3.3, count: 203 },
})

export const products: Product[] = [backpack, tshirt, jacket, shirt, harddrive]
export const featured: Product[] = [jacket, tshirt, backpack, harddrive, shirt]

export const mensClothingListing: CategoryProducts = {
  category: mensClothing,
  top_rated: [jacket, tshirt, backpack],
  others: [shirt],
}

// Currencies (GET /currencies): enabled only, default first. Rates are relative to USD.
export const usd: Currency = { code: 'USD', name: 'US Dollar', symbol: '$', rate: 1, is_default: true }
export const eur: Currency = { code: 'EUR', name: 'Euro', symbol: '€', rate: 0.92, is_default: false }
export const gbp: Currency = { code: 'GBP', name: 'Pound Sterling', symbol: '£', rate: 0.79, is_default: false }
export const currencies: Currency[] = [usd, eur, gbp]

export const currencyList: CurrencyList = {
  data: currencies,
  meta: { base: 'USD', rates_updated_at: '2026-11-20T06:00:00+00:00' },
}

/** What the API returns for `?currency=code`: the price converted on the server (2 decimals) + the code. */
export function inCurrency(product: Product, currency: Currency): Product {
  return { ...product, price: Math.round(product.price * currency.rate * 100) / 100, currency: currency.code }
}
