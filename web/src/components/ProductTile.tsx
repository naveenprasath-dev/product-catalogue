import { Link } from 'react-router'
import type { Product } from '../api/types'
import { formatPrice } from '../lib/format'
import { StarRating } from './StarRating'

type ProductTileProps = {
  product: Product
  /** Show the "Top rated" badge (featured picks and a category's top 3). */
  topRated?: boolean
}

/**
 * Product card. The title link is stretched over the whole card (CSS ::after), so the card is
 * clickable while the link's accessible name stays just the title.
 */
export function ProductTile({ product, topRated = false }: ProductTileProps) {
  return (
    <article className="card">
      <div className="card__media">
        <img src={product.image_url} alt={product.title} loading="lazy" className="card__image" />
        {topRated && <span className="badge card__badge">Top rated</span>}
      </div>
      <div className="card__body">
        <p className="card__category">{product.category.name}</p>
        <h3 className="card__title">
          <Link to={`/products/${product.id}`} className="card__link">
            {product.title}
          </Link>
        </h3>
        <StarRating rate={product.rating.rate} count={product.rating.count} />
        <p className="card__price">{formatPrice(product.price)}</p>
      </div>
    </article>
  )
}
