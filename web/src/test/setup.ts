import '@testing-library/jest-dom/vitest'
import { clearXsrfCookie } from './handlers'
import { server } from './server'

// Fail on any request without a handler. MSW v3 renamed v2's `onUnhandledRequest` to `onUnhandledFrame`.
beforeAll(() => server.listen({ onUnhandledFrame: 'error' }))
afterEach(() => {
  server.resetHandlers()
  clearXsrfCookie()
  localStorage.clear()
})
afterAll(() => server.close())
