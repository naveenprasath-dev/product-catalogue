import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { harddrive } from '../test/fixtures'
import { API } from '../test/handlers'
import { renderWithRouter } from '../test/render'
import { server } from '../test/server'

describe('Search', () => {
  it('submitting a term shows matching results', async () => {
    const user = userEvent.setup()
    renderWithRouter()

    await user.type(screen.getByRole('searchbox', { name: /search products/i }), 'hard drive')
    await user.click(screen.getByRole('button', { name: /^search$/i }))

    expect(await screen.findByRole('heading', { level: 1, name: /results for “hard drive”/i })).toBeInTheDocument()
    const main = screen.getByRole('main')
    expect(await within(main).findByRole('heading', { level: 3, name: harddrive.title })).toBeInTheDocument()
    expect(within(main).getAllByRole('heading', { level: 3 })).toHaveLength(1)
    expect(screen.getByText(/1 product found/i)).toBeInTheDocument()
  })

  it('blocks an empty term client-side without calling the API', async () => {
    let searched = false
    server.use(
      http.get(`${API}/products`, () => {
        searched = true
        return HttpResponse.json({ data: [] })
      }),
    )
    const user = userEvent.setup()
    renderWithRouter()

    await user.type(screen.getByRole('searchbox', { name: /search products/i }), '   ')
    await user.click(screen.getByRole('button', { name: /^search$/i }))

    expect(screen.getByRole('alert')).toHaveTextContent(/enter a search term/i)
    expect(screen.getByRole('searchbox', { name: /search products/i })).toHaveAttribute('aria-invalid', 'true')
    expect(screen.getByRole('heading', { level: 1, name: /top rated picks/i })).toBeInTheDocument()
    expect(searched).toBe(false)
  })

  it('shows an empty state when nothing matches', async () => {
    renderWithRouter(undefined, { route: '/search?q=unicorn' })
    expect(await screen.findByText(/no products match “unicorn”/i)).toBeInTheDocument()
  })

  it('reads the term from the URL and keeps it in the search box', async () => {
    renderWithRouter(undefined, { route: '/search?q=jacket' })
    expect(screen.getByRole('searchbox', { name: /search products/i })).toHaveValue('jacket')
    expect(await screen.findByRole('heading', { level: 3, name: /mens cotton jacket/i })).toBeInTheDocument()
  })

  it('shows an error state when the search fails', async () => {
    server.use(http.get(`${API}/products`, () => HttpResponse.json({ message: 'Server Error' }, { status: 500 })))
    renderWithRouter(undefined, { route: '/search?q=jacket' })
    expect(await screen.findByRole('alert')).toHaveTextContent(/search failed/i)
  })
})
