import { Link, useSearchParams } from 'react-router'
import { searchProducts } from '../api/client'
import { SearchIcon } from '../components/icons/SearchIcon'
import { ProductGrid } from '../components/ProductGrid'
import { ErrorState, GridSkeleton } from '../components/Status'
import { useProductApi } from '../hooks/useProductApi'

type SearchResultsProps = { term: string }

function SearchResults({ term }: SearchResultsProps) {
  const state = useProductApi((signal, currency) => searchProducts(term, { currency, signal }), [term])

  switch (state.status) {
    case 'loading':
      return <GridSkeleton label="Searching…" count={4} />
    case 'error':
      return <ErrorState error={state.error} onRetry={state.retry} title="Search failed." />
    case 'success':
      if (state.data.length === 0) {
        return (
          <div className="empty" role="status">
            <span className="empty__icon">
              <SearchIcon />
            </span>
            <p className="empty__title">No products match “{term}”. Try another term.</p>
            <p className="empty__text">Check the spelling, use a more general word, or browse a category instead.</p>
            <Link to="/" className="button button--secondary">
              Back to the home page
            </Link>
          </div>
        )
      }
      return (
        <>
          <p className="result-count" role="status">
            {state.data.length} {state.data.length === 1 ? 'product' : 'products'} found
          </p>
          <ProductGrid products={state.data} />
        </>
      )
  }
}

export function SearchPage() {
  const [params] = useSearchParams()
  const term = (params.get('q') ?? '').trim()

  if (term === '') {
    return (
      <>
        <h1 className="page-title">Search</h1>
        <p className="muted">Type a product name, description or category in the search box above.</p>
      </>
    )
  }

  return (
    <>
      <div className="page-header">
        <h1 className="page-title">Results for “{term}”</h1>
      </div>
      <SearchResults term={term} />
    </>
  )
}
