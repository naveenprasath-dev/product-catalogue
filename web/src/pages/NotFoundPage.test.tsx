import { screen } from '@testing-library/react'
import { renderWithRouter } from '../test/render'

describe('NotFoundPage', () => {
  it('is shown for an unknown route', () => {
    renderWithRouter(undefined, { route: '/no/such/page' })
    expect(screen.getByRole('heading', { level: 1, name: /page not found/i })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: /back to the home page/i })).toHaveAttribute('href', '/')
  })
})
