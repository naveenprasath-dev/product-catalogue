// Mirrors the "API contract" in PLAN.md. Change both sides together.

export type Category = {
  slug: string
  name: string
}

export type Product = {
  id: number
  title: string
  description: string
  /** Already converted by the server into `currency`; the client never converts. */
  price: number
  /** ISO 4217 code of `price`, e.g. "EUR". */
  currency: string
  image_url: string
  category: Category
  rating: {
    rate: number
    count: number
  }
}

export type CategoryProducts = {
  category: Category
  top_rated: Product[]
  others: Product[]
}

export type EnquiryInput = {
  product_id: number
  name: string
  email: string
  phone?: string
  message?: string
  /** Honeypot: real users leave it empty. */
  website?: string
}

export type EnquiryResult = {
  id: number
  status: string
}

/** An enabled display currency (`GET /currencies`). `rate` is relative to `CurrencyMeta.base`. */
export type Currency = {
  code: string
  name: string
  symbol: string
  rate: number
  is_default: boolean
}

export type CurrencyMeta = {
  base: string
  /** ISO-8601, or null when rates were never refreshed. */
  rates_updated_at: string | null
}

/** `GET /currencies`: enabled currencies, default first. */
export type CurrencyList = {
  data: Currency[]
  meta: CurrencyMeta
}

/** `{ "data": … }` envelope used by every 2xx response. */
export type DataEnvelope<T> = {
  data: T
}

/** 404/429/502 → `{ message }`; 422 → `{ message, errors }` (Laravel). */
export type ErrorBody = {
  message: string
  errors?: Record<string, string[]>
}

export type FieldErrors = Record<string, string[]>

/** Signed-in shop customer (UserResource). `is_admin` is never exposed. */
export type User = {
  id: number
  name: string
  email: string
}

export type LoginInput = {
  email: string
  password: string
  remember?: boolean
}

export type RegisterInput = {
  name: string
  email: string
  password: string
  password_confirmation: string
}
