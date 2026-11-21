import { createContext } from 'react'
import type { Currency } from '../api/types'

export type CurrencyValue = {
  /** Enabled currencies (default first); empty while loading or when `/currencies` failed. */
  currencies: Currency[]
  /** The dropdown is shown only when the list loaded. */
  status: 'loading' | 'ready' | 'unavailable'
  /** Code shown as selected in the dropdown (null until the list loaded / when unavailable). */
  selected: string | null
  /**
   * What product requests send as `?currency=`. `undefined` means "server default" (omitted), which is
   * also what we send when the selection *is* the default, so learning the default never refetches.
   */
  requestCurrency: string | undefined
  /** False only while a saved preference waits to be checked against `/currencies` (no flash of a wrong currency). */
  ready: boolean
  setCurrency: (code: string) => void
  /** A product request answered 422 for `code` (disabled meanwhile): fall back to the default and reload the list. */
  rejectCurrency: (code: string) => void
}

export const CurrencyContext = createContext<CurrencyValue | null>(null)
