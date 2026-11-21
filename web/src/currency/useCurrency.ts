import { useContext } from 'react'
import { CurrencyContext, type CurrencyValue } from './context'

/** Selected display currency + the enabled list. Must be used inside <CurrencyProvider>. */
export function useCurrency(): CurrencyValue {
  const value = useContext(CurrencyContext)
  if (value === null) {
    throw new Error('useCurrency must be used inside <CurrencyProvider>')
  }
  return value
}
