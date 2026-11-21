import { http, HttpResponse } from 'msw'
import { ada } from '../test/fixtures'
import { API, CSRF_URL, setXsrfCookie, signedIn, TEST_XSRF_TOKEN } from '../test/handlers'
import { server } from '../test/server'
import { ApiError, createEnquiry, getProduct, login, logout, me, searchProducts } from './client'

const enquiry = { product_id: 1, name: 'Ada', email: 'ada@example.com' }

async function caught(promise: Promise<unknown>): Promise<ApiError> {
  try {
    await promise
  } catch (error) {
    if (error instanceof ApiError) return error
    throw error
  }
  throw new Error('Expected the request to fail')
}

describe('api client', () => {
  it('unwraps the data envelope', async () => {
    await expect(getProduct(1)).resolves.toMatchObject({ id: 1, price: 109.95 })
  })

  it('sends the search term as ?search=', async () => {
    let url = ''
    server.use(
      http.get(`${API}/products`, ({ request }) => {
        url = request.url
        return HttpResponse.json({ data: [] })
      }),
    )
    await searchProducts('a&b')
    expect(new URL(url).searchParams.get('search')).toBe('a&b')
  })

  it('maps 422 to an ApiError with field errors', async () => {
    server.use(
      http.post(`${API}/enquiries`, () =>
        HttpResponse.json({ message: 'Invalid', errors: { email: ['Bad email.'] } }, { status: 422 }),
      ),
    )
    const error = await caught(createEnquiry(enquiry))
    expect(error.status).toBe(422)
    expect(error.isValidation).toBe(true)
    expect(error.fieldErrors).toEqual({ email: ['Bad email.'] })
  })

  it.each([
    [404, 'isNotFound'],
    [429, 'isRateLimited'],
    [502, 'isRetryable'],
  ] as const)('maps %i to ApiError.%s', async (status, flag) => {
    server.use(http.post(`${API}/enquiries`, () => HttpResponse.json({ message: 'nope' }, { status })))
    const error = await caught(createEnquiry(enquiry))
    expect(error.status).toBe(status)
    expect(error.message).toBe('nope')
    expect(error[flag]).toBe(true)
  })

  it('maps a network failure to status 0', async () => {
    server.use(http.get(`${API}/products/:id`, () => HttpResponse.error()))
    const error = await caught(getProduct(1))
    expect(error.status).toBe(0)
    expect(error.isRetryable).toBe(true)
  })

  it('handles a non-JSON error body', async () => {
    server.use(http.get(`${API}/products/:id`, () => new HttpResponse('<html>oops</html>', { status: 500 })))
    const error = await caught(getProduct(1))
    expect(error.status).toBe(500)
    expect(error.message).toMatch(/500/)
  })

  describe('session + CSRF', () => {
    it('fetches the CSRF cookie before the first POST and sends X-XSRF-TOKEN with credentials', async () => {
      const calls: string[] = []
      let xsrf: string | null = null
      // MSW's intercepted Request doesn't carry `credentials`, so watch (not mock) the real fetch call.
      const fetchSpy = vi.spyOn(globalThis, 'fetch')
      server.use(
        http.get(CSRF_URL, () => {
          calls.push('csrf')
          setXsrfCookie()
          return new HttpResponse(null, { status: 204 })
        }),
        http.post(`${API}/enquiries`, ({ request }) => {
          calls.push('enquiry')
          xsrf = request.headers.get('X-XSRF-TOKEN')
          return HttpResponse.json({ data: { id: 1, status: 'sent' } }, { status: 201 })
        }),
      )

      await createEnquiry(enquiry)
      await createEnquiry(enquiry)

      // The cookie is reused: only one CSRF round-trip for two POSTs.
      expect(calls).toEqual(['csrf', 'enquiry', 'enquiry'])
      expect(xsrf).toBe(TEST_XSRF_TOKEN)
      for (const [, init] of fetchSpy.mock.calls) {
        expect(init?.credentials).toBe('include')
      }
      fetchSpy.mockRestore()
    })

    it('does not fetch the CSRF cookie or send the header on GET', async () => {
      let csrfCalls = 0
      let xsrf: string | null = 'unset'
      server.use(
        http.get(CSRF_URL, () => {
          csrfCalls += 1
          return new HttpResponse(null, { status: 204 })
        }),
        http.get(`${API}/products/:id`, ({ request }) => {
          xsrf = request.headers.get('X-XSRF-TOKEN')
          return HttpResponse.json({ data: { id: 1 } })
        }),
      )
      await getProduct(1)
      expect(csrfCalls).toBe(0)
      expect(xsrf).toBeNull()
    })

    it('on 419 refreshes the CSRF cookie and retries once', async () => {
      let csrfCalls = 0
      const sent: (string | null)[] = []
      server.use(
        http.get(CSRF_URL, () => {
          csrfCalls += 1
          setXsrfCookie(`token-${csrfCalls}`)
          return new HttpResponse(null, { status: 204 })
        }),
        http.post(`${API}/enquiries`, ({ request }) => {
          sent.push(request.headers.get('X-XSRF-TOKEN'))
          return sent.length === 1
            ? HttpResponse.json({ message: 'CSRF token mismatch.' }, { status: 419 })
            : HttpResponse.json({ data: { id: 2, status: 'sent' } }, { status: 201 })
        }),
      )
      await expect(createEnquiry(enquiry)).resolves.toEqual({ id: 2, status: 'sent' })
      expect(sent).toEqual(['token-1', 'token-2'])
    })

    it('gives up after one 419 retry', async () => {
      let posts = 0
      server.use(
        http.post(`${API}/enquiries`, () => {
          posts += 1
          return HttpResponse.json({ message: 'CSRF token mismatch.' }, { status: 419 })
        }),
      )
      const error = await caught(createEnquiry(enquiry))
      expect(error.isCsrfExpired).toBe(true)
      expect(posts).toBe(2)
    })

    it('me() returns null for a guest (401) and the user when signed in', async () => {
      await expect(me()).resolves.toBeNull()
      server.use(...signedIn())
      await expect(me()).resolves.toEqual(ada)
    })

    it('login() posts the credentials and returns the user; logout() accepts 204', async () => {
      let body: unknown
      server.use(
        http.post(`${API}/auth/login`, async ({ request }) => {
          body = await request.json()
          return HttpResponse.json({ data: ada })
        }),
        ...signedIn(),
      )
      await expect(login({ email: ada.email, password: 'secret123' })).resolves.toEqual(ada)
      expect(body).toEqual({ email: ada.email, password: 'secret123' })
      await expect(logout()).resolves.toBeUndefined()
    })

    it('maps 401 to ApiError.isUnauthenticated', async () => {
      const error = await caught(logout())
      expect(error.isUnauthenticated).toBe(true)
    })
  })
})
