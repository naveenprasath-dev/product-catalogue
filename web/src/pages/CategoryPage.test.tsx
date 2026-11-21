import { screen, within } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { mensClothingListing } from '../test/fixtures'
import { API } from '../test/handlers'
import { renderWithRouter } from '../test/render'
import { server } from '../test/server'

function titlesIn(section: HTMLElement): (string | null)[] {
  return within(section)
    .getAllByRole('heading', { level: 3 })
    .map((heading) => heading.textContent)
}

describe('CategoryPage', () => {
  it('shows "Top rated" and "More products" sections', async () => {
    renderWithRouter(undefined, { route: '/categories/mens-clothing' })

    expect(screen.getByRole('status')).toHaveTextContent(/loading category/i)
    expect(await screen.findByRole('heading', { level: 1, name: "men's clothing" })).toBeInTheDocument()

    const topRated = screen.getByRole('region', { name: 'Top rated' })
    const more = screen.getByRole('region', { name: 'More products' })
    expect(titlesIn(topRated)).toEqual(mensClothingListing.top_rated.map((p) => p.title))
    expect(titlesIn(more)).toEqual(mensClothingListing.others.map((p) => p.title))
  })

  it('shows an empty message when there are no other products', async () => {
    server.use(
      http.get(`${API}/categories/:slug/products`, () =>
        HttpResponse.json({ data: { ...mensClothingListing, others: [] } }),
      ),
    )
    renderWithRouter(undefined, { route: '/categories/mens-clothing' })

    const more = await screen.findByRole('region', { name: 'More products' })
    expect(within(more).getByText(/no more products in this category/i)).toBeInTheDocument()
  })

  it('shows a not-found message for an unknown category', async () => {
    renderWithRouter(undefined, { route: '/categories/does-not-exist' })
    expect(await screen.findByRole('heading', { level: 1, name: /category not found/i })).toBeInTheDocument()
  })

  it('shows an error state when the API fails', async () => {
    server.use(
      http.get(`${API}/categories/:slug/products`, () => HttpResponse.json({ message: 'Server Error' }, { status: 500 })),
    )
    renderWithRouter(undefined, { route: '/categories/mens-clothing' })
    expect(await screen.findByRole('alert')).toHaveTextContent(/could not be loaded/i)
    expect(screen.getByRole('button', { name: /try again/i })).toBeInTheDocument()
  })

  it('shows a breadcrumb, the product count and "Top rated" badges only on the top section', async () => {
    renderWithRouter(undefined, { route: '/categories/mens-clothing' })
    await screen.findByRole('heading', { level: 1, name: "men's clothing" })

    const crumbs = screen.getByRole('navigation', { name: 'Breadcrumb' })
    expect(within(crumbs).getByRole('link', { name: 'Home' })).toHaveAttribute('href', '/')
    expect(within(crumbs).getByText("men's clothing")).toHaveAttribute('aria-current', 'page')

    const total = mensClothingListing.top_rated.length + mensClothingListing.others.length
    expect(screen.getByText(`${total} products`)).toBeInTheDocument()

    const topRated = screen.getByRole('region', { name: 'Top rated' })
    const more = screen.getByRole('region', { name: 'More products' })
    expect(within(topRated).getAllByText('Top rated', { selector: '.badge' })).toHaveLength(
      mensClothingListing.top_rated.length,
    )
    expect(within(more).queryByText('Top rated', { selector: '.badge' })).not.toBeInTheDocument()
  })
})
