import { useParams } from 'react-router'
import { getProduct } from '../api/client'
import { Breadcrumbs } from '../components/Breadcrumbs'
import { EnquiryForm } from '../components/EnquiryForm'
import { StarRating } from '../components/StarRating'
import { ErrorState, ProductSkeleton } from '../components/Status'
import { useProductApi } from '../hooks/useProductApi'
import { formatPrice } from '../lib/format'
import { NotFoundPage } from './NotFoundPage'

export function ProductPage() {
  const { id = '' } = useParams()
  const state = useProductApi((signal, currency) => getProduct(id, { currency, signal }), [id])

  if (state.status === 'error') {
    if (state.error.isNotFound) {
      return <NotFoundPage title="Product not found" message="We could not find that product." />
    }
    return <ErrorState error={state.error} onRetry={state.retry} title="This product could not be loaded." />
  }

  // Switching currency re-fetches the same product: keep the page (and any half-typed enquiry) and
  // only swap the price for a placeholder, so no price is ever shown in the old currency.
  const product =
    state.status === 'success'
      ? state.data
      : state.previous !== undefined && String(state.previous.id) === id
        ? state.previous
        : undefined
  if (product === undefined) {
    return <ProductSkeleton label="Loading product…" />
  }
  const priceLoading = state.status === 'loading'

  return (
    <>
      <Breadcrumbs
        items={[
          { label: 'Home', to: '/' },
          { label: product.category.name, to: `/categories/${product.category.slug}` },
          { label: product.title },
        ]}
      />
      <div className="product">
        <div className="product__media">
          <img src={product.image_url} alt={product.title} className="product__image" />
        </div>
        <div className="product__body">
          <p className="product__category">{product.category.name}</p>
          <h1 className="product__title">{product.title}</h1>
          <StarRating rate={product.rating.rate} count={product.rating.count} size="lg" />
          {priceLoading ? (
            <p className="product__price" role="status">
              <span className="visually-hidden">Updating price…</span>
              <span className="skeleton skeleton--line product__price-skeleton" aria-hidden="true" />
            </p>
          ) : (
            <p className="product__price">{formatPrice(product.price, product.currency)}</p>
          )}
          <a href="#enquiry" className="button button--large product__cta">
            Enquire about this product
          </a>
          <h2 className="product__subheading">About this product</h2>
          <p className="product__description">{product.description}</p>
          {/* key: a fresh form per product, so values never leak between products. */}
          <EnquiryForm key={product.id} product={product} />
        </div>
      </div>
    </>
  )
}
