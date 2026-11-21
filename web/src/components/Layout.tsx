import { Link, Outlet, useSearchParams } from 'react-router'
import { getCategories } from '../api/client'
import { useApi } from '../hooks/useApi'
import type { LayoutContext } from '../hooks/useCategories'
import { CategoryNav } from './CategoryNav'
import { CurrencySelect } from './CurrencySelect'
import { Footer } from './Footer'
import { BagIcon } from './icons/BagIcon'
import { SearchBar } from './SearchBar'
import { UserMenu } from './UserMenu'

export function Layout() {
  const [params] = useSearchParams()
  const categories = useApi((signal) => getCategories(signal), [])
  const context: LayoutContext = { categories }

  return (
    <div className="app">
      <a href="#main" className="skip-link">
        Skip to content
      </a>
      <header className="header">
        <div className="header__inner container">
          <Link to="/" className="brand">
            <span className="brand__logo">
              <BagIcon />
            </span>
            <span className="brand__name">Product Catalogue</span>
          </Link>
          <SearchBar key={params.get('q') ?? ''} />
          <div className="header__actions">
            <CurrencySelect />
            <UserMenu />
          </div>
        </div>
        <div className="header__nav">
          <div className="container">
            <CategoryNav state={categories} />
          </div>
        </div>
      </header>
      <main id="main" className="main container" tabIndex={-1}>
        <Outlet context={context} />
      </main>
      <Footer categories={categories} />
    </div>
  )
}
