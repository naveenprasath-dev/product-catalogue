import { useCallback, useEffect, useRef, useState, type DependencyList } from 'react'
import { ApiError, toApiError } from '../api/client'

type Outcome<T> = { status: 'error'; error: ApiError } | { status: 'success'; data: T }

export type ApiState<T> = ({ status: 'loading'; previous?: T } | Outcome<T>) & {
  /** Runs the request again (used by the error state's "Try again" button). */
  retry: () => void
}

type Settled<T> = { key: readonly unknown[]; outcome: Outcome<T> }

export type ApiOptions = {
  /** When false nothing is requested and the state stays `loading` (e.g. until the currency is known). */
  enabled?: boolean
}

function sameKey(a: readonly unknown[], b: readonly unknown[]): boolean {
  return a.length === b.length && a.every((value, index) => Object.is(value, b[index]))
}

/**
 * Loads data for a GET call: `useApi((signal) => getProduct(id, signal), [id])`.
 * Aborts the in-flight request when `deps` change or the component unmounts.
 * "Loading" is derived (no result yet for the current deps) rather than set inside the effect; it carries
 * the last successful data as `previous` (from older deps) for pages that want to keep showing it.
 */
export function useApi<T>(
  fn: (signal: AbortSignal) => Promise<T>,
  deps: DependencyList,
  { enabled = true }: ApiOptions = {},
): ApiState<T> {
  const [attempt, setAttempt] = useState(0)
  const [settled, setSettled] = useState<Settled<T> | null>(null)

  const fnRef = useRef(fn)
  useEffect(() => {
    fnRef.current = fn
  })

  const key = [...deps, attempt, enabled]

  useEffect(() => {
    if (!enabled) return
    const controller = new AbortController()
    fnRef.current(controller.signal).then(
      (data) => {
        if (!controller.signal.aborted) {
          setSettled({ key, outcome: { status: 'success', data } })
        }
      },
      (error: unknown) => {
        if (!controller.signal.aborted) {
          setSettled({ key, outcome: { status: 'error', error: toApiError(error) } })
        }
      },
    )
    return () => controller.abort()
    // The caller's deps (checked by eslint via additionalHooks) plus the retry counter and `enabled`.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, key)

  const retry = useCallback(() => setAttempt((n) => n + 1), [])

  if (settled === null || !sameKey(settled.key, key)) {
    const previous = settled?.outcome.status === 'success' ? settled.outcome.data : undefined
    return { status: 'loading', previous, retry }
  }
  return { ...settled.outcome, retry }
}
