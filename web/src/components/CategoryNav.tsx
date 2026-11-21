import { NavLink } from 'react-router'
import type { Category } from '../api/types'
import type { ApiState } from '../hooks/useApi'

type CategoryNavProps = { state: ApiState<Category[]> }

/** Second header row; scrolls horizontally on small screens. */
export function CategoryNav({ state }: CategoryNavProps) {
  return (
    <nav className="category-nav" aria-label="Categories">
      {state.status === 'loading' && <p className="category-nav__note">Loading categories…</p>}
      {state.status === 'error' && (
        <p className="category-nav__note">
          Categories unavailable.{' '}
          <button type="button" className="link-button" onClick={state.retry}>
            Try again
          </button>
        </p>
      )}
      {state.status === 'success' && state.data.length === 0 && (
        <p className="category-nav__note">No categories yet.</p>
      )}
      {state.status === 'success' && state.data.length > 0 && (
        <ul className="category-nav__list">
          <li>
            <NavLink to="/" end className="category-nav__link">
              All
            </NavLink>
          </li>
          {state.data.map((category) => (
            <li key={category.slug}>
              <NavLink to={`/categories/${category.slug}`} className="category-nav__link">
                {category.name}
              </NavLink>
            </li>
          ))}
        </ul>
      )}
    </nav>
  )
}
