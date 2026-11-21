import { useParams } from 'react-router'
import { getProduct } from '../api/client'
import { Breadcrumbs } from '../components/Breadcrumbs'
import { EnquiryForm } from '../components/EnquiryForm'
import { StarRating } from '../components/StarRating'
import { ErrorState, ProductSkeleton } from '../components/Status'
import { useApi } from '../hooks/useApi'
import { formatPrice } from '../lib/format'
import { NotFoundPage } from './NotFoundPage'

export function ProductPage() {
  const { id = '' } = useParams()
  const state = useApi((signal) => getProduct(id, signal), [id])

  switch (state.status) {
    case 'loading':
      return <ProductSkeleton label="Loading product…" />
    case 'error':
      if (state.error.isNotFound) {
        return <NotFoundPage title="Product not found" message="We could not find that product." />
      }
      return <ErrorState error={state.error} onRetry={state.retry} title="This product could not be loaded." />
    case 'success': {
      const product = state.data
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
              <p className="product__price">{formatPrice(product.price)}</p>
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
  }
}
