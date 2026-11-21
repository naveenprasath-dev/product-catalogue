import { Link } from 'react-router'
import type { Category } from '../api/types'
import { useAuth } from '../auth/useAuth'
import type { ApiState } from '../hooks/useApi'

type FooterProps = { categories: ApiState<Category[]> }

/** Real links only: pages and categories that exist, plus account links. No invented policies. */
export function Footer({ categories }: FooterProps) {
  const { status, user } = useAuth()
  return (
    <footer className="footer">
      <div className="container footer__grid">
        <div className="footer__col footer__about">
          <p className="footer__brand">Product Catalogue</p>
          <p className="footer__text">Browse the catalogue and send our sales team an enquiry about any product.</p>
        </div>
        <nav className="footer__col" aria-label="Shop">
          <h2 className="footer__heading">Shop</h2>
          <ul className="footer__list">
            <li>
              <Link to="/">Home</Link>
            </li>
            <li>
              <Link to="/search">Search</Link>
            </li>
          </ul>
        </nav>
        {categories.status === 'success' && categories.data.length > 0 && (
          <nav className="footer__col" aria-label="Footer categories">
            <h2 className="footer__heading">Categories</h2>
            <ul className="footer__list">
              {categories.data.map((category) => (
                <li key={category.slug}>
                  <Link to={`/categories/${category.slug}`} className="capitalize">
                    {category.name}
                  </Link>
                </li>
              ))}
            </ul>
          </nav>
        )}
        <nav className="footer__col" aria-label="Footer account">
          <h2 className="footer__heading">Account</h2>
          {status === 'authenticated' && user !== null ? (
            <p className="footer__text">Signed in as {user.name}</p>
          ) : (
            <ul className="footer__list">
              <li>
                <Link to="/login">Log in</Link>
              </li>
              <li>
                <Link to="/register">Create an account</Link>
              </li>
            </ul>
          )}
        </nav>
      </div>
      <div className="container footer__bottom">Product Catalogue PoC</div>
    </footer>
  )
}
