import type { Product } from '../api/types'
import { ProductTile } from './ProductTile'
import { EmptyState } from './Status'

type ProductGridProps = {
  products: Product[]
  emptyMessage?: string
  /** Every product in this grid gets the "Top rated" badge. */
  topRated?: boolean
}

export function ProductGrid({ products, emptyMessage = 'No products to show.', topRated = false }: ProductGridProps) {
  if (products.length === 0) {
    return <EmptyState>{emptyMessage}</EmptyState>
  }
  return (
    <ul className="grid">
      {products.map((product) => (
        <li key={product.id} className="grid__item">
          <ProductTile product={product} topRated={topRated} />
        </li>
      ))}
    </ul>
  )
}
