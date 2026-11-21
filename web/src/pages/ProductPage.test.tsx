import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import type { EnquiryInput } from '../api/types'
import { ada, backpack } from '../test/fixtures'
import { API, signedIn, TEST_XSRF_TOKEN } from '../test/handlers'
import { renderWithRouter } from '../test/render'
import { server } from '../test/server'

const route = `/products/${backpack.id}`

async function renderProduct() {
  const user = userEvent.setup()
  renderWithRouter(undefined, { route })
  await screen.findByRole('heading', { level: 1, name: backpack.title })
  return user
}

async function fillValidForm(user: ReturnType<typeof userEvent.setup>) {
  await user.type(screen.getByLabelText(/^name/i), 'Ada Lovelace')
  await user.type(screen.getByLabelText(/^email/i), 'ada@example.com')
  await user.type(screen.getByLabelText(/^message/i), 'Is this waterproof?')
}

function sendButton() {
  return screen.getByRole('button', { name: /send enquiry/i })
}

describe('ProductPage', () => {
  it('shows the product details', async () => {
    renderWithRouter(undefined, { route })
    expect(screen.getByRole('status')).toHaveTextContent(/loading product/i)
    expect(await screen.findByRole('heading', { level: 1, name: backpack.title })).toBeInTheDocument()
    expect(screen.getByRole('img', { name: backpack.title })).toHaveAttribute('src', backpack.image_url)
    expect(screen.getByText('$109.95')).toBeInTheDocument()
    expect(screen.getByText(backpack.description)).toBeInTheDocument()
    expect(within(screen.getByRole('main')).getByRole('link', { name: backpack.category.name })).toHaveAttribute(
      'href',
      `/categories/${backpack.category.slug}`,
    )
  })

  it('shows a not-found message for an unknown product', async () => {
    renderWithRouter(undefined, { route: '/products/999' })
    expect(await screen.findByRole('heading', { level: 1, name: /product not found/i })).toBeInTheDocument()
  })

  it('shows required-field errors and does not send an incomplete enquiry', async () => {
    let posted = false
    server.use(
      http.post(`${API}/enquiries`, () => {
        posted = true
        return HttpResponse.json({ data: { id: 1, status: 'sent' } }, { status: 201 })
      }),
    )
    const user = await renderProduct()

    await user.click(sendButton())

    expect(screen.getByText(/please enter your name/i)).toBeInTheDocument()
    expect(screen.getByText(/please enter your email address/i)).toBeInTheDocument()
    expect(screen.getByLabelText(/^name/i)).toHaveAttribute('aria-invalid', 'true')
    expect(screen.getByLabelText(/^name/i)).toHaveFocus()

    await user.type(screen.getByLabelText(/^name/i), 'Ada')
    await user.type(screen.getByLabelText(/^email/i), 'not-an-email')
    await user.click(sendButton())
    expect(screen.getByText(/please enter a valid email address/i)).toBeInTheDocument()
    expect(posted).toBe(false)
  })

  it('sends the enquiry with the product attached and shows "Thank you"', async () => {
    let body: EnquiryInput | undefined
    server.use(
      http.post(`${API}/enquiries`, async ({ request }) => {
        body = (await request.json()) as EnquiryInput
        return HttpResponse.json({ data: { id: 12, status: 'sent' } }, { status: 201 })
      }),
    )
    const user = await renderProduct()

    await fillValidForm(user)
    await user.click(sendButton())

    expect(await screen.findByRole('heading', { name: /thank you, ada lovelace/i })).toBeInTheDocument()
    expect(screen.getByText(backpack.title, { selector: 'strong' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /send enquiry/i })).not.toBeInTheDocument()
    expect(body).toEqual({
      product_id: backpack.id,
      name: 'Ada Lovelace',
      email: 'ada@example.com',
      message: 'Is this waterproof?',
      website: '',
    })
  })

  it('on 502 shows a retry message, keeps the values, and succeeds on retry', async () => {
    let calls = 0
    server.use(
      http.post(`${API}/enquiries`, () => {
        calls += 1
        return calls === 1
          ? HttpResponse.json({ message: 'Your enquiry could not be sent.' }, { status: 502 })
          : HttpResponse.json({ data: { id: 13, status: 'sent' } }, { status: 201 })
      }),
    )
    const user = await renderProduct()

    await fillValidForm(user)
    await user.click(sendButton())

    expect(await screen.findByRole('alert')).toHaveTextContent(/could not send your enquiry.*try again/i)
    expect(screen.queryByRole('heading', { name: /thank you/i })).not.toBeInTheDocument()
    expect(screen.getByLabelText(/^name/i)).toHaveValue('Ada Lovelace')
    expect(screen.getByLabelText(/^email/i)).toHaveValue('ada@example.com')
    expect(screen.getByLabelText(/^message/i)).toHaveValue('Is this waterproof?')

    await user.click(sendButton())
    expect(await screen.findByRole('heading', { name: /thank you/i })).toBeInTheDocument()
  })

  it('shows server 422 errors next to the matching field', async () => {
    server.use(
      http.post(`${API}/enquiries`, () =>
        HttpResponse.json(
          {
            message: 'The email field must be a valid email address.',
            errors: { email: ['The email field must be a valid email address.'] },
          },
          { status: 422 },
        ),
      ),
    )
    const user = await renderProduct()

    await fillValidForm(user)
    await user.click(sendButton())

    expect(await screen.findByText('The email field must be a valid email address.')).toBeInTheDocument()
    expect(screen.getByLabelText(/^email/i)).toHaveAttribute('aria-invalid', 'true')
    expect(screen.getByLabelText(/^email/i)).toHaveAccessibleDescription(
      'The email field must be a valid email address.',
    )
  })

  it('on 429 asks the user to wait and try again', async () => {
    server.use(
      http.post(`${API}/enquiries`, () => HttpResponse.json({ message: 'Too Many Attempts.' }, { status: 429 })),
    )
    const user = await renderProduct()

    await fillValidForm(user)
    await user.click(sendButton())

    expect(await screen.findByRole('alert')).toHaveTextContent(/too many requests/i)
    expect(screen.getByLabelText(/^name/i)).toHaveValue('Ada Lovelace')
  })

  it('starts empty for a guest', async () => {
    await renderProduct()
    expect(screen.getByLabelText(/^name/i)).toHaveValue('')
    expect(screen.getByLabelText(/^email/i)).toHaveValue('')
  })

  it('pre-fills name and email for a signed-in user and sends the enquiry with CSRF (no user id)', async () => {
    let body: Record<string, unknown> | undefined
    let xsrf: string | null = null
    server.use(
      http.post(`${API}/enquiries`, async ({ request }) => {
        body = (await request.json()) as Record<string, unknown>
        xsrf = request.headers.get('X-XSRF-TOKEN')
        return HttpResponse.json({ data: { id: 14, status: 'sent' } }, { status: 201 })
      }),
      ...signedIn(),
    )
    const user = await renderProduct()

    expect(await screen.findByDisplayValue(ada.email)).toBe(screen.getByLabelText(/^email/i))
    expect(screen.getByLabelText(/^name/i)).toHaveValue(ada.name)

    // Still editable.
    await user.clear(screen.getByLabelText(/^name/i))
    await user.type(screen.getByLabelText(/^name/i), 'Ada L.')
    await user.click(sendButton())

    expect(await screen.findByRole('heading', { name: /thank you, ada l\./i })).toBeInTheDocument()
    expect(body).toMatchObject({ product_id: backpack.id, name: 'Ada L.', email: ada.email })
    expect(body).not.toHaveProperty('user_id')
    expect(xsrf).toBe(TEST_XSRF_TOKEN)
  })

  it('clears the pre-filled name and email after logging out on the product page', async () => {
    server.use(...signedIn())
    const user = await renderProduct()
    expect(await screen.findByDisplayValue(ada.email)).toBe(screen.getByLabelText(/^email/i))

    await user.click(screen.getByRole('button', { name: /log out/i }))

    await waitFor(() => expect(screen.getByLabelText(/^email/i)).toHaveValue(''))
    expect(screen.getByLabelText(/^name/i)).toHaveValue('')
  })

  it('keeps what the person typed themselves when logging out', async () => {
    server.use(...signedIn())
    const user = await renderProduct()
    expect(await screen.findByDisplayValue(ada.email)).toBe(screen.getByLabelText(/^email/i))

    await user.clear(screen.getByLabelText(/^name/i))
    await user.type(screen.getByLabelText(/^name/i), 'Grace Hopper')
    await user.type(screen.getByLabelText(/^message/i), 'Is this waterproof?')
    await user.click(screen.getByRole('button', { name: /log out/i }))

    await waitFor(() => expect(screen.getByLabelText(/^email/i)).toHaveValue(''))
    expect(screen.getByLabelText(/^name/i)).toHaveValue('Grace Hopper')
    expect(screen.getByLabelText(/^message/i)).toHaveValue('Is this waterproof?')
  })

  it('shows a breadcrumb, a visual star rating and a CTA to the enquiry form', async () => {
    await renderProduct()

    const crumbs = screen.getByRole('navigation', { name: 'Breadcrumb' })
    expect(within(crumbs).getByRole('link', { name: 'Home' })).toHaveAttribute('href', '/')
    expect(within(crumbs).getByRole('link', { name: backpack.category.name })).toHaveAttribute(
      'href',
      `/categories/${backpack.category.slug}`,
    )
    expect(within(crumbs).getByText(backpack.title)).toHaveAttribute('aria-current', 'page')

    expect(screen.getByRole('img', { name: 'Rated 3.9 out of 5 from 120 reviews' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: /enquire about this product/i })).toHaveAttribute('href', '#enquiry')
    expect(screen.getByRole('region', { name: /ask our sales team/i })).toHaveAttribute('id', 'enquiry')
  })
})
