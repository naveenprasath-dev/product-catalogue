import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { ada } from '../test/fixtures'
import { API, signedIn } from '../test/handlers'
import { renderWithRouter } from '../test/render'
import { server } from '../test/server'

function header() {
  return within(screen.getByRole('banner'))
}

describe('Header user menu', () => {
  it('shows Log in / Register for a guest, linking back to the current page', async () => {
    renderWithRouter(undefined, { route: '/search?q=bag' })

    expect(await header().findByRole('link', { name: /log in/i })).toHaveAttribute(
      'href',
      `/login?next=${encodeURIComponent('/search?q=bag')}`,
    )
    expect(header().getByRole('link', { name: /register/i })).toBeInTheDocument()
    expect(header().queryByRole('button', { name: /log out/i })).not.toBeInTheDocument()
  })

  it('shows the name and logs out back to the guest header', async () => {
    let loggedOut = false
    server.use(
      http.post(`${API}/auth/logout`, () => {
        loggedOut = true
        return new HttpResponse(null, { status: 204 })
      }),
      ...signedIn(),
    )
    const user = userEvent.setup()
    renderWithRouter()

    expect(await header().findByText(`Hi, ${ada.name}`)).toBeInTheDocument()
    expect(header().queryByRole('link', { name: /log in/i })).not.toBeInTheDocument()

    await user.click(header().getByRole('button', { name: /log out/i }))

    expect(await header().findByRole('link', { name: /log in/i })).toBeInTheDocument()
    expect(header().queryByText(`Hi, ${ada.name}`)).not.toBeInTheDocument()
    expect(loggedOut).toBe(true)
  })

  it('keeps the user signed in and says so when logout fails', async () => {
    server.use(
      http.post(`${API}/auth/logout`, () => HttpResponse.json({ message: 'Server Error' }, { status: 500 })),
      ...signedIn(),
    )
    const user = userEvent.setup()
    renderWithRouter()

    await user.click(await header().findByRole('button', { name: /log out/i }))

    expect(await header().findByRole('alert')).toHaveTextContent(/could not log out/i)
    expect(header().getByText(`Hi, ${ada.name}`)).toBeInTheDocument()
  })
})
