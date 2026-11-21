import { Link } from 'react-router'
import { getFeatured } from '../api/client'
import { ArrowRightIcon } from '../components/icons/ArrowRightIcon'
import { ProductGrid } from '../components/ProductGrid'
import { ErrorState, GridSkeleton } from '../components/Status'
import { useCategories } from '../hooks/useCategories'
import { useProductApi } from '../hooks/useProductApi'

function CategoryTiles() {
  const state = useCategories()

  // The header nav already reports loading/errors for categories; here we only show what loaded.
  if (state.status === 'loading') {
    return (
      <ul className="category-tiles" aria-hidden="true">
        {[0, 1, 2, 3].map((index) => (
          <li key={index} className="category-tile category-tile--skeleton skeleton" />
        ))}
      </ul>
    )
  }
  if (state.status === 'error' || state.data.length === 0) {
    return <p className="muted">Categories are not available right now.</p>
  }
  return (
    <ul className="category-tiles">
      {state.data.map((category) => (
        <li key={category.slug}>
          <Link to={`/categories/${category.slug}`} className="category-tile">
            <span className="category-tile__name">{category.name}</span>
            <span className="category-tile__cta">
              Shop now <ArrowRightIcon />
            </span>
          </Link>
        </li>
      ))}
    </ul>
  )
}

export function HomePage() {
  const state = useProductApi((signal, currency) => getFeatured({ currency, signal }), [])

  return (
    <>
      <section className="hero" aria-labelledby="hero-title">
        <div className="hero__content">
          <p className="hero__eyebrow">Hand-picked favourites</p>
          <h1 id="hero-title" className="hero__title">
            Discover our top rated picks
          </h1>
          <p className="hero__lead">
            The highest rated products from across the catalogue, plus every category in one place. Found
            something you like? Send us an enquiry.
          </p>
          <div className="hero__actions">
            <a href="#shop-by-category" className="button button--light button--large">
              Browse categories
            </a>
            <a href="#top-picks" className="button button--ghost button--large">
              See top picks
            </a>
          </div>
        </div>
      </section>

      <section id="shop-by-category" className="section" aria-labelledby="categories-heading">
        <div className="section__header">
          <h2 id="categories-heading">Shop by category</h2>
        </div>
        <CategoryTiles />
      </section>

      <section id="top-picks" className="section" aria-labelledby="featured-heading">
        <div className="section__header">
          <h2 id="featured-heading">Featured products</h2>
          <p className="section__sub">Our five highest rated products right now.</p>
        </div>
        {state.status === 'loading' && <GridSkeleton label="Loading featured products…" count={5} />}
        {state.status === 'error' && (
          <ErrorState error={state.error} onRetry={state.retry} title="Featured products could not be loaded." />
        )}
        {state.status === 'success' && (
          <ProductGrid products={state.data} emptyMessage="No featured products right now." topRated />
        )}
      </section>
    </>
  )
}
