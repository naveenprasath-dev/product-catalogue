import { render } from '@testing-library/react'
import type { ReactElement } from 'react'
import { MemoryRouter } from 'react-router'
import { App } from '../App'
import { AuthProvider } from '../auth/AuthContext'

type Options = { route?: string }

/** Renders `ui` (default: the whole app with its routes) inside a MemoryRouter at `route`, with auth. */
export function renderWithRouter(ui: ReactElement = <App />, { route = '/' }: Options = {}) {
  return render(
    <MemoryRouter initialEntries={[route]}>
      <AuthProvider>{ui}</AuthProvider>
    </MemoryRouter>,
  )
}
