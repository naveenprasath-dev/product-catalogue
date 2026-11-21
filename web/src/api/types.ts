// Mirrors the "API contract" in PLAN.md. Change both sides together.

export type Category = {
  slug: string
  name: string
}

export type Product = {
  id: number
  title: string
  description: string
  price: number
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
