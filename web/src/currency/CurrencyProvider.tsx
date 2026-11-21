import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react'
import { getCurrencies } from '../api/client'
import type { Currency } from '../api/types'
import { CurrencyContext, type CurrencyValue } from './context'
import { readSavedCurrency, saveCurrency } from './storage'

type CurrencyProviderProps = { children: ReactNode }

type ListState = { status: 'loading' } | { status: 'ready'; currencies: Currency[] } | { status: 'unavailable' }

/**
 * Loads `/currencies` once and holds the selected display currency.
 * Selected = the saved preference if it is still enabled, otherwise the server's default.
 * The client never converts prices: it only tells the API which currency to return.
 */
export function CurrencyProvider({ children }: CurrencyProviderProps) {
  const [list, setList] = useState<ListState>({ status: 'loading' })
  const [preference, setPreference] = useState<string | null>(readSavedCurrency)
  // Bumped to re-fetch the list (after the API rejected a currency that was disabled meanwhile).
  const [reload, setReload] = useState(0)

  useEffect(() => {
    const controller = new AbortController()
    getCurrencies(controller.signal).then(
      ({ data }) => {
        if (controller.signal.aborted) return
        if (data.length === 0) {
          setList({ status: 'unavailable' })
          return
        }
        // A saved code that is no longer enabled is dropped, so the default applies from now on.
        const saved = readSavedCurrency()
        if (saved !== null && !data.some((currency) => currency.code === saved)) {
          saveCurrency(null)
          setPreference(null)
        }
        setList({ status: 'ready', currencies: data })
      },
      () => {
        // No list (network/5xx/bad shape): hide the dropdown; prices show in the server default.
        if (!controller.signal.aborted) setList({ status: 'unavailable' })
      },
    )
    return () => controller.abort()
  }, [reload])

  const setCurrency = useCallback((code: string) => {
    setPreference(code)
    saveCurrency(code)
  }, [])

  const rejectCurrency = useCallback((code: string) => {
    setPreference((current) => (current === code ? null : current))
    if (readSavedCurrency() === code) saveCurrency(null)
    setReload((n) => n + 1)
  }, [])

  const value = useMemo<CurrencyValue>(() => {
    if (list.status !== 'ready') {
      return {
        currencies: [],
        status: list.status,
        selected: null,
        requestCurrency: undefined,
        // Without a saved preference the server default is right, so products load in parallel with the list.
        ready: list.status === 'unavailable' || preference === null,
        setCurrency,
        rejectCurrency,
      }
    }
    const { currencies } = list
    const defaultCode = (currencies.find((currency) => currency.is_default) ?? currencies[0])?.code ?? null
    const selected = currencies.some((currency) => currency.code === preference) ? preference : defaultCode
    return {
      currencies,
      status: 'ready',
      selected,
      requestCurrency: selected === null || selected === defaultCode ? undefined : selected,
      ready: true,
      setCurrency,
      rejectCurrency,
    }
  }, [list, preference, setCurrency, rejectCurrency])

  return <CurrencyContext.Provider value={value}>{children}</CurrencyContext.Provider>
}
