// Happy-path handlers for every endpoint in the PLAN.md API contract.
// Override per test with server.use(...) for error/empty cases.
import { http, HttpResponse } from 'msw'
import { API_BASE_URL, CSRF_COOKIE_URL } from '../api/client'
import type { Category, CategoryProducts, DataEnvelope, EnquiryResult, ErrorBody, Product, User } from '../api/types'
import { ada, categories, featured, mensClothingListing, products } from './fixtures'

export const API = API_BASE_URL
export const CSRF_URL = CSRF_COOKIE_URL
export const TEST_XSRF_TOKEN = 'test-xsrf-token=='

/** Like Sanctum: the CSRF endpoint sets a JS-readable XSRF-TOKEN cookie (URL-encoded) and returns 204. */
export function setXsrfCookie(token = TEST_XSRF_TOKEN) {
  document.cookie = `XSRF-TOKEN=${encodeURIComponent(token)}; path=/`
}

export function clearXsrfCookie() {
  document.cookie = 'XSRF-TOKEN=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT'
}

const unauthenticated = () => HttpResponse.json<ErrorBody>({ message: 'Unauthenticated.' }, { status: 401 })

/** Handlers for a signed-in session: `server.use(...signedIn())`. */
export function signedIn(user: User = ada) {
  return [
    http.get(`${API}/auth/me`, () => HttpResponse.json<DataEnvelope<User>>({ data: user })),
    http.post(`${API}/auth/logout`, () => new HttpResponse(null, { status: 204 })),
  ]
}

function notFound(message: string) {
  return HttpResponse.json<ErrorBody>({ message }, { status: 404 })
}

export const handlers = [
  http.get(CSRF_URL, () => {
    setXsrfCookie()
    return new HttpResponse(null, { status: 204 })
  }),

  // Default: a guest. Existing page tests don't care about auth.
  http.get(`${API}/auth/me`, unauthenticated),
  http.post(`${API}/auth/logout`, unauthenticated),
  http.post(`${API}/auth/login`, () => HttpResponse.json<DataEnvelope<User>>({ data: ada })),
  http.post(`${API}/auth/register`, () => HttpResponse.json<DataEnvelope<User>>({ data: ada }, { status: 201 })),

  http.get(`${API}/products/featured`, () => HttpResponse.json<DataEnvelope<Product[]>>({ data: featured })),

  http.get(`${API}/categories`, () => HttpResponse.json<DataEnvelope<Category[]>>({ data: categories })),

  http.get<{ slug: string }, never, DataEnvelope<CategoryProducts> | ErrorBody>(`${API}/categories/:slug/products`, ({ params }) => {
    if (params.slug !== mensClothingListing.category.slug) {
      return notFound('Category not found.')
    }
    return HttpResponse.json<DataEnvelope<CategoryProducts>>({ data: mensClothingListing })
  }),

  http.get<never, never, DataEnvelope<Product[]> | ErrorBody>(`${API}/products`, ({ request }) => {
    const term = (new URL(request.url).searchParams.get('search') ?? '').trim().toLowerCase()
    if (term === '') {
      return HttpResponse.json<ErrorBody>(
        { message: 'The search field is required.', errors: { search: ['The search field is required.'] } },
        { status: 422 },
      )
    }
    const matches = products.filter((product) =>
      [product.title, product.description, product.category.name].some((text) => text.toLowerCase().includes(term)),
    )
    return HttpResponse.json<DataEnvelope<Product[]>>({ data: matches })
  }),

  http.get<{ id: string }, never, DataEnvelope<Product> | ErrorBody>(`${API}/products/:id`, ({ params }) => {
    const product = products.find((candidate) => String(candidate.id) === params.id)
    return product ? HttpResponse.json<DataEnvelope<Product>>({ data: product }) : notFound('Product not found.')
  }),

  http.post(`${API}/enquiries`, () =>
    HttpResponse.json<DataEnvelope<EnquiryResult>>({ data: { id: 12, status: 'sent' } }, { status: 201 }),
  ),
]
