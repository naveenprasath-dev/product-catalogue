import { useParams } from 'react-router'
import { getCategoryProducts } from '../api/client'
import { Breadcrumbs } from '../components/Breadcrumbs'
import { ProductGrid } from '../components/ProductGrid'
import { ErrorState, GridSkeleton } from '../components/Status'
import { useApi } from '../hooks/useApi'
import { NotFoundPage } from './NotFoundPage'

export function CategoryPage() {
  const { slug = '' } = useParams()
  const state = useApi((signal) => getCategoryProducts(slug, signal), [slug])

  switch (state.status) {
    case 'loading':
      return <GridSkeleton label="Loading category…" count={4} />
    case 'error':
      if (state.error.isNotFound) {
        return <NotFoundPage title="Category not found" message="We could not find that category." />
      }
      return <ErrorState error={state.error} onRetry={state.retry} title="This category could not be loaded." />
    case 'success': {
      const { category, top_rated: topRated, others } = state.data
      const total = topRated.length + others.length
      return (
        <>
          <Breadcrumbs items={[{ label: 'Home', to: '/' }, { label: category.name }]} />
          <div className="page-header">
            <h1 className="page-title capitalize">{category.name}</h1>
            <p className="page-header__count">
              {total} {total === 1 ? 'product' : 'products'}
            </p>
          </div>
          <section className="section" aria-labelledby="top-rated-heading">
            <div className="section__header">
              <h2 id="top-rated-heading">Top rated</h2>
            </div>
            <ProductGrid products={topRated} emptyMessage="No products in this category yet." topRated />
          </section>
          <section className="section" aria-labelledby="more-heading">
            <div className="section__header">
              <h2 id="more-heading">More products</h2>
            </div>
            <ProductGrid products={others} emptyMessage="No more products in this category." />
          </section>
        </>
      )
    }
  }
}
