import { formatRating } from '../lib/format'
import { StarIcon } from './icons/StarIcon'

type StarRatingProps = {
  rate: number
  count: number
  size?: 'sm' | 'lg'
}

const STARS = [0, 1, 2, 3, 4]

/** Five stars filled to `rate` (partial stars via a clipped overlay), announced as one text label. */
export function StarRating({ rate, count, size = 'sm' }: StarRatingProps) {
  const percent = Math.max(0, Math.min(100, (rate / 5) * 100))
  const label = `Rated ${formatRating(rate)} out of 5 from ${count} ${count === 1 ? 'review' : 'reviews'}`
  return (
    <span className={`stars stars--${size}`} role="img" aria-label={label}>
      <span className="stars__track" aria-hidden="true">
        {STARS.map((star) => (
          <StarIcon key={star} />
        ))}
        {/* Width is data (the rating), not styling; colours stay in tokens. */}
        <span className="stars__fill" style={{ width: `${percent}%` }}>
          {STARS.map((star) => (
            <StarIcon key={star} />
          ))}
        </span>
      </span>
      <span className="stars__value" aria-hidden="true">
        {formatRating(rate)}
      </span>
      <span className="stars__count" aria-hidden="true">
        ({count})
      </span>
    </span>
  )
}
