import { useEffect, type DependencyList } from 'react'
import { useCurrency } from '../currency/useCurrency'
import { useApi, type ApiState } from './useApi'

/**
 * `useApi` for product endpoints: passes the selected currency to `fn`, re-fetches when it changes,
 * waits while a saved currency is still being checked, and handles a 422 on `currency` (disabled
 * since the list loaded) by falling back to the default currency, which re-fetches.
 *
 *   useProductApi((signal, currency) => getProduct(id, { currency, signal }), [id])
 */
export function useProductApi<T>(
  fn: (signal: AbortSignal, currency: string | undefined) => Promise<T>,
  deps: DependencyList,
): ApiState<T> {
  const { requestCurrency, ready, rejectCurrency } = useCurrency()
  const state = useApi(
    (signal) => fn(signal, requestCurrency),
    // The caller's deps are checked by eslint at the call site (additionalHooks); the currency is ours.
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [...deps, requestCurrency],
    { enabled: ready },
  )

  const rejected =
    state.status === 'error' &&
    state.error.isValidation &&
    'currency' in state.error.fieldErrors &&
    requestCurrency !== undefined
      ? requestCurrency
      : null

  useEffect(() => {
    if (rejected !== null) rejectCurrency(rejected)
  }, [rejected, rejectCurrency])

  // Not an error the user should see: the default-currency request follows immediately.
  if (rejected !== null) return { status: 'loading', retry: state.retry }
  return state
}
