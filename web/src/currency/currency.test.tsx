import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { backpack, currencyList, gbp, usd } from '../test/fixtures'
import { API, productHandlers } from '../test/handlers'
import { renderWithRouter } from '../test/render'
import { server } from '../test/server'
import { CURRENCY_STORAGE_KEY } from './storage'

/** Records the `currency` query param of every featured-products request (null = omitted). */
function trackFeaturedCurrency(): (string | null)[] {
  const seen: (string | null)[] = []
  server.events.on('request:start', ({ request }) => {
    const url = new URL(request.url)
    if (url.pathname.endsWith('/products/featured')) seen.push(url.searchParams.get('currency'))
  })
  return seen
}

function currencySelect() {
  return screen.findByRole('combobox', { name: 'Currency' })
}

function featuredSection() {
  return screen.getByRole('region', { name: /featured products/i })
}

afterEach(() => {
  server.events.removeAllListeners()
})

describe('currency selection', () => {
  it('shows the enabled currencies in the header, default selected', async () => {
    renderWithRouter()

    const select = await currencySelect()
    expect(within(screen.getByRole('banner')).getByRole('combobox', { name: 'Currency' })).toBe(select)
    expect(within(select).getAllByRole('option').map((option) => option.textContent)).toEqual([
      '$ USD',
      '€ EUR',
      '£ GBP',
    ])
    expect(select).toHaveValue('USD')
  })

  it('switching currency re-fetches products and shows the converted price; the choice is saved', async () => {
    const seen = trackFeaturedCurrency()
    const user = userEvent.setup()
    renderWithRouter()
    expect(await within(featuredSection()).findByText('$55.99')).toBeInTheDocument()

    await user.selectOptions(await currencySelect(), 'EUR')

    // jacket: 55.99 USD × 0.92 = 51.51 EUR, converted by the (mock) server.
    expect(await within(featuredSection()).findByText('€51.51')).toBeInTheDocument()
    expect(within(featuredSection()).queryByText('$55.99')).not.toBeInTheDocument()
    expect(seen).toEqual([null, 'EUR'])
    expect(localStorage.getItem(CURRENCY_STORAGE_KEY)).toBe('EUR')
  })

  it('restores a saved preference and never shows the default currency first', async () => {
    localStorage.setItem(CURRENCY_STORAGE_KEY, 'GBP')
    const seen = trackFeaturedCurrency()
    renderWithRouter()

    // 55.99 × 0.79 = 44.23
    expect(await within(featuredSection()).findByText('£44.23')).toBeInTheDocument()
    expect(await currencySelect()).toHaveValue('GBP')
    expect(seen).toEqual(['GBP'])
  })

  it('falls back to the default when the saved currency is no longer enabled', async () => {
    localStorage.setItem(CURRENCY_STORAGE_KEY, 'JPY')
    const seen = trackFeaturedCurrency()
    renderWithRouter()

    expect(await within(featuredSection()).findByText('$55.99')).toBeInTheDocument()
    expect(await currencySelect()).toHaveValue('USD')
    expect(seen).toEqual([null])
    expect(localStorage.getItem(CURRENCY_STORAGE_KEY)).toBeNull()
  })

  it('hides the dropdown when /currencies fails; prices show in the server default', async () => {
    localStorage.setItem(CURRENCY_STORAGE_KEY, 'EUR')
    server.use(http.get(`${API}/currencies`, () => HttpResponse.json({ message: 'Server Error' }, { status: 500 })))
    const seen = trackFeaturedCurrency()
    renderWithRouter()

    expect(await within(featuredSection()).findByText('$55.99')).toBeInTheDocument()
    expect(screen.queryByRole('combobox', { name: 'Currency' })).not.toBeInTheDocument()
    expect(seen).toEqual([null])
  })

  it('resets to the default and re-fetches when the API rejects the currency (disabled meanwhile)', async () => {
    localStorage.setItem(CURRENCY_STORAGE_KEY, 'EUR')
    let listCalls = 0
    server.use(
      // First list still has EUR; after the rejection the reloaded list no longer does.
      http.get(`${API}/currencies`, () => {
        listCalls += 1
        return HttpResponse.json(listCalls === 1 ? currencyList : { ...currencyList, data: [usd, gbp] })
      }),
      ...productHandlers([usd, gbp]),
    )
    const seen = trackFeaturedCurrency()
    renderWithRouter()

    expect(await within(featuredSection()).findByText('$55.99')).toBeInTheDocument()
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
    expect(seen).toEqual(['EUR', null])
    expect(localStorage.getItem(CURRENCY_STORAGE_KEY)).toBeNull()
    await waitFor(() => expect(listCalls).toBe(2))
    const select = await currencySelect()
    expect(select).toHaveValue('USD')
    await waitFor(() =>
      expect(within(select).getAllByRole('option').map((option) => option.textContent)).toEqual(['$ USD', '£ GBP']),
    )
  })

  it('still works when localStorage is unavailable', async () => {
    const getItem = vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
      throw new DOMException('blocked', 'SecurityError')
    })
    const setItem = vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
      throw new DOMException('blocked', 'SecurityError')
    })
    try {
      const user = userEvent.setup()
      renderWithRouter()
      expect(await within(featuredSection()).findByText('$55.99')).toBeInTheDocument()

      await user.selectOptions(await currencySelect(), 'EUR')
      expect(await within(featuredSection()).findByText('€51.51')).toBeInTheDocument()
    } finally {
      getItem.mockRestore()
      setItem.mockRestore()
    }
  })

  it('on the product page, switching keeps the enquiry draft and updates the price', async () => {
    const user = userEvent.setup()
    renderWithRouter(undefined, { route: `/products/${backpack.id}` })
    await screen.findByRole('heading', { level: 1, name: backpack.title })
    expect(screen.getByText('$109.95')).toBeInTheDocument()
    await user.type(screen.getByLabelText(/^message/i), 'Is this waterproof?')

    await user.selectOptions(await currencySelect(), 'EUR')

    // 109.95 × 0.92 = 101.15
    expect(await screen.findByText('€101.15')).toBeInTheDocument()
    expect(screen.queryByText('$109.95')).not.toBeInTheDocument()
    expect(screen.getByLabelText(/^message/i)).toHaveValue('Is this waterproof?')
  })

  it('category and search pages request the selected currency', async () => {
    localStorage.setItem(CURRENCY_STORAGE_KEY, 'EUR')
    const { unmount } = renderWithRouter(undefined, { route: '/categories/mens-clothing' })
    expect((await screen.findAllByText('€51.51')).length).toBeGreaterThan(0)
    unmount()

    renderWithRouter(undefined, { route: '/search?q=backpack' })
    expect(await screen.findByText('€101.15')).toBeInTheDocument()
  })
})
