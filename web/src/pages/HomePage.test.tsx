import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { categories, featured } from '../test/fixtures'
import { API } from '../test/handlers'
import { renderWithRouter } from '../test/render'
import { server } from '../test/server'

describe('HomePage', () => {
  it('shows a loading state, then the featured products in order', async () => {
    renderWithRouter()

    expect(screen.getByRole('status')).toHaveTextContent(/loading featured products/i)
    expect(screen.getByRole('heading', { level: 1, name: /top rated picks/i })).toBeInTheDocument()

    await screen.findByRole('heading', { level: 3, name: featured[0]?.title })
    const titles = within(screen.getByRole('main'))
      .getAllByRole('heading', { level: 3 })
      .map((heading) => heading.textContent)
    expect(titles).toEqual(featured.map((product) => product.title))
    expect(screen.getByRole('img', { name: featured[0]?.title })).toBeInTheDocument()
    expect(screen.getAllByText('$55.99').length).toBeGreaterThan(0)
  })

  it('links each product to its detail page', async () => {
    renderWithRouter()
    const link = await screen.findByRole('link', { name: new RegExp(featured[0]?.title ?? '') })
    expect(link).toHaveAttribute('href', `/products/${featured[0]?.id}`)
  })

  it('shows an error state when the API fails, and recovers on retry', async () => {
    let calls = 0
    server.use(
      http.get(`${API}/products/featured`, () => {
        calls += 1
        return calls === 1
          ? HttpResponse.json({ message: 'Server Error' }, { status: 500 })
          : HttpResponse.json({ data: featured })
      }),
    )
    renderWithRouter()

    const alert = await screen.findByRole('alert')
    expect(alert).toHaveTextContent(/featured products could not be loaded/i)

    await userEvent.setup().click(within(alert).getByRole('button', { name: /try again/i }))
    expect(await screen.findByRole('heading', { level: 3, name: featured[0]?.title })).toBeInTheDocument()
  })

  it('shows an empty state when there are no featured products', async () => {
    server.use(http.get(`${API}/products/featured`, () => HttpResponse.json({ data: [] })))
    renderWithRouter()
    expect(await screen.findByText(/no featured products right now/i)).toBeInTheDocument()
  })

  it('shows a hero, "Shop by category" tiles and badged featured cards with star ratings', async () => {
    renderWithRouter()

    const hero = screen.getByRole('region', { name: /top rated picks/i })
    expect(within(hero).getByRole('link', { name: /browse categories/i })).toHaveAttribute('href', '#shop-by-category')

    const shop = screen.getByRole('region', { name: /shop by category/i })
    for (const category of categories) {
      expect(await within(shop).findByRole('link', { name: new RegExp(category.name, 'i') })).toHaveAttribute(
        'href',
        `/categories/${category.slug}`,
      )
    }

    const featuredSection = screen.getByRole('region', { name: /featured products/i })
    await within(featuredSection).findByRole('heading', { level: 3, name: featured[0]?.title })
    expect(within(featuredSection).getAllByText('Top rated', { selector: '.badge' })).toHaveLength(featured.length)
    expect(
      within(featuredSection).getByRole('img', { name: 'Rated 4.7 out of 5 from 500 reviews' }),
    ).toBeInTheDocument()
  })

  it('has footer links to real pages and categories only', async () => {
    renderWithRouter()
    const footer = screen.getByRole('contentinfo')
    expect(within(footer).getByRole('link', { name: 'Home' })).toHaveAttribute('href', '/')
    expect(within(footer).getByRole('link', { name: 'Log in' })).toHaveAttribute('href', '/login')
    expect(await within(footer).findByRole('link', { name: categories[0]?.name })).toHaveAttribute(
      'href',
      `/categories/${categories[0]?.slug}`,
    )
  })
})
