import { Link } from 'react-router'
import { ChevronRightIcon } from './icons/ChevronRightIcon'

export type Crumb = { label: string; to?: string }

type BreadcrumbsProps = { items: Crumb[] }

/** Home › … › current page. The last item is the current page (aria-current, not a link). */
export function Breadcrumbs({ items }: BreadcrumbsProps) {
  return (
    <nav className="breadcrumbs" aria-label="Breadcrumb">
      <ol className="breadcrumbs__list">
        {items.map((item, index) => {
          const last = index === items.length - 1
          return (
            <li key={`${index}-${item.label}`} className="breadcrumbs__item">
              {last || item.to === undefined ? (
                <span aria-current={last ? 'page' : undefined} className="breadcrumbs__current">
                  {item.label}
                </span>
              ) : (
                <Link to={item.to}>{item.label}</Link>
              )}
              {!last && <ChevronRightIcon className="breadcrumbs__sep" />}
            </li>
          )
        })}
      </ol>
    </nav>
  )
}
