import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import type { LoginInput } from '../api/types'
import { ada, backpack } from '../test/fixtures'
import { API } from '../test/handlers'
import { renderWithRouter } from '../test/render'
import { server } from '../test/server'

async function fillLogin(user: ReturnType<typeof userEvent.setup>, password = 'Secret123') {
  await user.type(screen.getByLabelText(/^email/i), ada.email)
  await user.type(screen.getByLabelText(/^password/i), password)
  await user.click(screen.getByRole('button', { name: /^log in$/i }))
}

function header() {
  return within(screen.getByRole('banner'))
}

describe('LoginPage', () => {
  it('logs in, updates the header and returns to ?next=', async () => {
    let body: LoginInput | undefined
    server.use(
      http.post(`${API}/auth/login`, async ({ request }) => {
        body = (await request.json()) as LoginInput
        return HttpResponse.json({ data: ada })
      }),
    )
    const user = userEvent.setup()
    renderWithRouter(undefined, { route: `/login?next=${encodeURIComponent(`/products/${backpack.id}`)}` })

    expect(await screen.findByRole('heading', { level: 1, name: /log in/i })).toBeInTheDocument()
    await fillLogin(user)

    expect(await screen.findByRole('heading', { level: 1, name: backpack.title })).toBeInTheDocument()
    expect(header().getByText(`Hi, ${ada.name}`)).toBeInTheDocument()
    expect(header().getByRole('button', { name: /log out/i })).toBeInTheDocument()
    expect(body).toEqual({ email: ada.email, password: 'Secret123', remember: false })
  })

  it('shows the server 422 error under the email field and stays a guest', async () => {
    const message = 'These credentials do not match our records.'
    server.use(
      http.post(`${API}/auth/login`, () =>
        HttpResponse.json({ message, errors: { email: [message] } }, { status: 422 }),
      ),
    )
    const user = userEvent.setup()
    renderWithRouter(undefined, { route: '/login' })
    await screen.findByRole('heading', { level: 1, name: /log in/i })

    await fillLogin(user, 'wrong')

    expect(await screen.findByRole('alert')).toHaveTextContent(/check your email and password/i)
    expect(screen.getByLabelText(/^email/i)).toHaveAttribute('aria-invalid', 'true')
    expect(screen.getByLabelText(/^email/i)).toHaveAccessibleDescription(message)
    expect(header().getByRole('link', { name: /log in/i })).toBeInTheDocument()
  })

  it('on 429 asks the user to wait', async () => {
    server.use(
      http.post(`${API}/auth/login`, () => HttpResponse.json({ message: 'Too Many Attempts.' }, { status: 429 })),
    )
    const user = userEvent.setup()
    renderWithRouter(undefined, { route: '/login' })
    await screen.findByRole('heading', { level: 1, name: /log in/i })

    await fillLogin(user)

    expect(await screen.findByRole('alert')).toHaveTextContent(/too many attempts/i)
  })

  it('requires email and password before sending', async () => {
    let posted = false
    server.use(
      http.post(`${API}/auth/login`, () => {
        posted = true
        return HttpResponse.json({ data: ada })
      }),
    )
    const user = userEvent.setup()
    renderWithRouter(undefined, { route: '/login' })
    await screen.findByRole('heading', { level: 1, name: /log in/i })

    await user.click(screen.getByRole('button', { name: /^log in$/i }))

    expect(screen.getByText(/please enter your email address/i)).toBeInTheDocument()
    expect(screen.getByText(/please enter your password/i)).toBeInTheDocument()
    expect(screen.getByLabelText(/^email/i)).toHaveFocus()
    expect(posted).toBe(false)
  })

  it('ignores an off-site ?next= and goes home', async () => {
    const user = userEvent.setup()
    renderWithRouter(undefined, { route: '/login?next=//evil.example' })
    await screen.findByRole('heading', { level: 1, name: /log in/i })

    await fillLogin(user)

    expect(await screen.findByRole('heading', { level: 1, name: /top rated picks/i })).toBeInTheDocument()
  })
})
