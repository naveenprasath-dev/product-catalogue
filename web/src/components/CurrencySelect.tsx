import { useId } from 'react'
import { useCurrency } from '../currency/useCurrency'

/** Header dropdown for the display currency. Hidden while loading or when `/currencies` failed. */
export function CurrencySelect() {
  const { status, currencies, selected, setCurrency } = useCurrency()
  const id = useId()

  if (status !== 'ready' || selected === null) return null

  return (
    <div className="currency-select">
      <label htmlFor={id} className="visually-hidden">
        Currency
      </label>
      <select
        id={id}
        className="currency-select__input"
        value={selected}
        onChange={(event) => setCurrency(event.target.value)}
      >
        {currencies.map((currency) => (
          <option key={currency.code} value={currency.code} title={currency.name}>
            {currency.symbol} {currency.code}
          </option>
        ))}
      </select>
    </div>
  )
}
