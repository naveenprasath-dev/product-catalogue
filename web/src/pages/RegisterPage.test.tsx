import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import type { RegisterInput } from '../api/types'
import { ada } from '../test/fixtures'
import { API } from '../test/handlers'
import { renderWithRouter } from '../test/render'
import { server } from '../test/server'

async function fillRegister(user: ReturnType<typeof userEvent.setup>, confirmation = 'Secret123') {
  await user.type(screen.getByLabelText(/^name/i), ada.name)
  await user.type(screen.getByLabelText(/^email/i), ada.email)
  await user.type(screen.getByLabelText(/^password/i), 'Secret123')
  await user.type(screen.getByLabelText(/^confirm password/i), confirmation)
  await user.click(screen.getByRole('button', { name: /create account/i }))
}

describe('RegisterPage', () => {
  it('creates the account, signs in and goes home', async () => {
    let body: RegisterInput | undefined
    server.use(
      http.post(`${API}/auth/register`, async ({ request }) => {
        body = (await request.json()) as RegisterInput
        return HttpResponse.json({ data: ada }, { status: 201 })
      }),
    )
    const user = userEvent.setup()
    renderWithRouter(undefined, { route: '/register' })
    await screen.findByRole('heading', { level: 1, name: /create an account/i })

    await fillRegister(user)

    expect(await screen.findByRole('heading', { level: 1, name: /top rated picks/i })).toBeInTheDocument()
    expect(within(screen.getByRole('banner')).getByText(`Hi, ${ada.name}`)).toBeInTheDocument()
    expect(body).toEqual({
      name: ada.name,
      email: ada.email,
      password: 'Secret123',
      password_confirmation: 'Secret123',
    })
  })

  it('shows server 422 errors under the matching fields', async () => {
    server.use(
      http.post(`${API}/auth/register`, () =>
        HttpResponse.json(
          { message: 'The email has already been taken.', errors: { email: ['The email has already been taken.'] } },
          { status: 422 },
        ),
      ),
    )
    const user = userEvent.setup()
    renderWithRouter(undefined, { route: '/register' })
    await screen.findByRole('heading', { level: 1, name: /create an account/i })

    await fillRegister(user)

    expect(await screen.findByRole('alert')).toHaveTextContent(/check the highlighted fields/i)
    expect(screen.getByLabelText(/^email/i)).toHaveAccessibleDescription('The email has already been taken.')
  })

  it('catches mismatched passwords before sending', async () => {
    let posted = false
    server.use(
      http.post(`${API}/auth/register`, () => {
        posted = true
        return HttpResponse.json({ data: ada }, { status: 201 })
      }),
    )
    const user = userEvent.setup()
    renderWithRouter(undefined, { route: '/register' })
    await screen.findByRole('heading', { level: 1, name: /create an account/i })

    await fillRegister(user, 'Different1')

    expect(screen.getByText(/passwords do not match/i)).toBeInTheDocument()
    expect(screen.getByLabelText(/^confirm password/i)).toHaveFocus()
    expect(posted).toBe(false)
  })
})
