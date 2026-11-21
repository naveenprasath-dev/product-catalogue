const AUTH_PAGES = ['/login', '/register']

/**
 * Where to go after logging in. Only same-app paths are allowed (no `//evil.example` or
 * absolute URLs), so `?next=` can't be used as an open redirect.
 */
export function safeNext(next: string | null): string {
  if (next === null || !next.startsWith('/') || next.startsWith('//') || next.startsWith('/\\')) return '/'
  if (AUTH_PAGES.some((page) => next === page || next.startsWith(`${page}?`))) return '/'
  return next
}

/** `/login?next=…` (or `/register?next=…`) that returns to `here` afterwards. */
export function authPath(page: '/login' | '/register', here: string): string {
  const next = safeNext(here)
  return next === '/' ? page : `${page}?${new URLSearchParams({ next }).toString()}`
}
