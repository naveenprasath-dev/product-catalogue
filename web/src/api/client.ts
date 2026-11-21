// The only module that knows the API base URL and calls fetch (CLAUDE.md).
// Every function returns our own contract types (types.ts) and throws ApiError on failure.
import type {
  Category,
  CategoryProducts,
  DataEnvelope,
  EnquiryInput,
  EnquiryResult,
  ErrorBody,
  FieldErrors,
  LoginInput,
  Product,
  RegisterInput,
  User,
} from './types'

// Default matches .env.example / docker-compose, so `npm run dev` works without extra setup.
const DEFAULT_API_URL = 'http://localhost:8000/api/v1'

export const API_BASE_URL = (import.meta.env.VITE_API_URL || DEFAULT_API_URL).replace(/\/+$/, '')

// Sanctum's CSRF endpoint lives at the API origin, outside /api/v1, so derive it here.
export const CSRF_COOKIE_URL = `${new URL(API_BASE_URL).origin}/sanctum/csrf-cookie`

const XSRF_COOKIE = 'XSRF-TOKEN'
const SAFE_METHODS = new Set(['GET', 'HEAD', 'OPTIONS'])

/** Thrown for every non-2xx response; `status: 0` means the request never got a response. */
export class ApiError extends Error {
  readonly status: number
  readonly fieldErrors: FieldErrors

  constructor(status: number, message: string, fieldErrors: FieldErrors = {}) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.fieldErrors = fieldErrors
  }

  get isNotFound(): boolean {
    return this.status === 404
  }

  get isValidation(): boolean {
    return this.status === 422
  }

  get isRateLimited(): boolean {
    return this.status === 429
  }

  /** No (or an expired) session: the caller is a guest. */
  get isUnauthenticated(): boolean {
    return this.status === 401
  }

  /** CSRF token missing/expired (Laravel's 419); request() refreshes and retries once itself. */
  get isCsrfExpired(): boolean {
    return this.status === 419
  }

  /** Worth retrying as-is: network failure, upstream/mail failure (502) or other 5xx. */
  get isRetryable(): boolean {
    return this.status === 0 || this.status >= 500
  }
}

/** Normalises anything thrown during a request into an ApiError. */
export function toApiError(error: unknown): ApiError {
  if (error instanceof ApiError) {
    return error
  }
  return new ApiError(0, 'Could not reach the server.')
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value)
}

function isErrorBody(value: unknown): value is ErrorBody {
  return isRecord(value) && typeof value.message === 'string'
}

function readFieldErrors(value: unknown): FieldErrors {
  if (!isRecord(value)) {
    return {}
  }
  const result: FieldErrors = {}
  for (const [field, messages] of Object.entries(value)) {
    if (Array.isArray(messages)) {
      result[field] = messages.filter((m): m is string => typeof m === 'string')
    }
  }
  return result
}

async function readJson(response: Response): Promise<unknown> {
  try {
    const body: unknown = await response.json()
    return body
  } catch {
    return null
  }
}

/** Reads the XSRF-TOKEN cookie Sanctum sets (readable by JS by design; the session cookie is HttpOnly). */
function readXsrfToken(): string | null {
  for (const part of document.cookie.split(';')) {
    const [name, ...rest] = part.trim().split('=')
    if (name === XSRF_COOKIE) {
      return decodeURIComponent(rest.join('='))
    }
  }
  return null
}

let csrfRequest: Promise<void> | null = null

/**
 * Makes sure an XSRF-TOKEN cookie exists before a state-changing request.
 * `force` re-fetches it (after a 419). Concurrent callers share one request.
 */
async function ensureCsrf(force = false): Promise<void> {
  if (!force && readXsrfToken() !== null) {
    return
  }
  csrfRequest ??= fetch(CSRF_COOKIE_URL, { credentials: 'include', headers: { Accept: 'application/json' } })
    .then((response) => {
      if (!response.ok) {
        throw new ApiError(response.status, 'Could not start a secure session.')
      }
    })
    .catch((error: unknown) => {
      throw toApiError(error)
    })
    .finally(() => {
      csrfRequest = null
    })
  await csrfRequest
}

async function send(path: string, init: RequestInit = {}, retried = false): Promise<unknown> {
  const method = (init.method ?? 'GET').toUpperCase()
  const mutating = !SAFE_METHODS.has(method)
  if (mutating) {
    await ensureCsrf()
  }
  const xsrf = mutating ? readXsrfToken() : null

  let response: Response
  try {
    response = await fetch(`${API_BASE_URL}${path}`, {
      ...init,
      // Session cookie auth (Sanctum SPA): the browser sends/stores the HttpOnly cookies; no tokens in JS storage.
      credentials: 'include',
      headers: {
        Accept: 'application/json',
        ...(init.body !== undefined ? { 'Content-Type': 'application/json' } : {}),
        ...(xsrf !== null ? { 'X-XSRF-TOKEN': xsrf } : {}),
        ...init.headers,
      },
    })
  } catch (error) {
    // Let aborts propagate untouched so callers can ignore them.
    if (error instanceof DOMException && error.name === 'AbortError') {
      throw error
    }
    throw new ApiError(0, 'Could not reach the server.')
  }

  // Expired CSRF token (e.g. session timed out): get a fresh one and retry once.
  if (response.status === 419 && mutating && !retried) {
    await ensureCsrf(true)
    return send(path, init, true)
  }

  const body = await readJson(response)

  if (!response.ok) {
    const message = isErrorBody(body) ? body.message : `Request failed (${response.status}).`
    const fieldErrors = isErrorBody(body) ? readFieldErrors(body.errors) : {}
    throw new ApiError(response.status, message, fieldErrors)
  }

  return body
}

async function request<T>(path: string, init: RequestInit = {}): Promise<T> {
  const body = await send(path, init)
  if (!isRecord(body) || !('data' in body)) {
    throw new ApiError(200, 'Unexpected response from the server.')
  }
  // The shape is guaranteed by our own API contract (ProductResource/UserResource), not by upstream data.
  return (body as DataEnvelope<T>).data
}

export function getFeatured(signal?: AbortSignal): Promise<Product[]> {
  return request<Product[]>('/products/featured', { signal })
}

export function getCategories(signal?: AbortSignal): Promise<Category[]> {
  return request<Category[]>('/categories', { signal })
}

export function getCategoryProducts(slug: string, signal?: AbortSignal): Promise<CategoryProducts> {
  return request<CategoryProducts>(`/categories/${encodeURIComponent(slug)}/products`, { signal })
}

export function searchProducts(term: string, signal?: AbortSignal): Promise<Product[]> {
  const query = new URLSearchParams({ search: term })
  return request<Product[]>(`/products?${query.toString()}`, { signal })
}

export function getProduct(id: string | number, signal?: AbortSignal): Promise<Product> {
  return request<Product>(`/products/${encodeURIComponent(String(id))}`, { signal })
}

export function createEnquiry(input: EnquiryInput): Promise<EnquiryResult> {
  return request<EnquiryResult>('/enquiries', {
    method: 'POST',
    body: JSON.stringify(input),
  })
}

export function register(input: RegisterInput): Promise<User> {
  return request<User>('/auth/register', { method: 'POST', body: JSON.stringify(input) })
}

export function login(input: LoginInput): Promise<User> {
  return request<User>('/auth/login', { method: 'POST', body: JSON.stringify(input) })
}

export async function logout(): Promise<void> {
  await send('/auth/logout', { method: 'POST' })
}

/** The signed-in user, or `null` for a guest (401 is the normal guest answer, not an error). */
export async function me(signal?: AbortSignal): Promise<User | null> {
  try {
    return await request<User>('/auth/me', { signal })
  } catch (error) {
    if (error instanceof ApiError && error.isUnauthenticated) {
      return null
    }
    throw error
  }
}
