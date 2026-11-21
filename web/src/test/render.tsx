import { render } from '@testing-library/react'
import type { ReactElement } from 'react'
import { MemoryRouter } from 'react-router'
import { App } from '../App'
import { AuthProvider } from '../auth/AuthContext'
import { CurrencyProvider } from '../currency/CurrencyProvider'

type Options = { route?: string }

/** Renders `ui` (default: the whole app with its routes) inside a MemoryRouter at `route`, with auth and currency. */
export function renderWithRouter(ui: ReactElement = <App />, { route = '/' }: Options = {}) {
  return render(
    <MemoryRouter initialEntries={[route]}>
      <AuthProvider>
        <CurrencyProvider>{ui}</CurrencyProvider>
      </AuthProvider>
    </MemoryRouter>,
  )
}
