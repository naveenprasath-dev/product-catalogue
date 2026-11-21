import type { ReactNode } from 'react'
import type { ApiError } from '../api/client'

type LoadingProps = { label?: string }

export function Loading({ label = 'Loading…' }: LoadingProps) {
  return (
    <p className="status status--loading" role="status">
      <span className="status__spinner" aria-hidden="true" />
      {label}
    </p>
  )
}

type EmptyStateProps = { children: ReactNode }

export function EmptyState({ children }: EmptyStateProps) {
  return (
    <p className="status status--empty" role="status">
      {children}
    </p>
  )
}

type ErrorStateProps = {
  error: ApiError
  onRetry?: () => void
  title?: string
}

function describe(error: ApiError): string {
  if (error.status === 0) return 'We could not reach the server. Check your connection and try again.'
  if (error.isRateLimited) return 'Too many requests. Please wait a moment and try again.'
  return 'Something went wrong on our side. Please try again.'
}

export function ErrorState({ error, onRetry, title = 'Sorry, this could not be loaded.' }: ErrorStateProps) {
  return (
    <div className="status status--error" role="alert">
      <p className="status__title">{title}</p>
      <p>{describe(error)}</p>
      {onRetry && (
        <button type="button" className="button button--secondary" onClick={onRetry}>
          Try again
        </button>
      )}
    </div>
  )
}

type SkeletonProps = { label: string; count?: number }

/** Placeholder cards while a product grid loads; the label is announced, the shapes are hidden. */
export function GridSkeleton({ label, count = 4 }: SkeletonProps) {
  return (
    <div role="status" className="skeleton-wrap">
      <span className="visually-hidden">{label}</span>
      <ul className="grid" aria-hidden="true">
        {Array.from({ length: count }, (_, index) => (
          <li key={index} className="grid__item">
            <div className="card card--skeleton">
              <div className="card__media skeleton" />
              <div className="card__body">
                <div className="skeleton skeleton--line" />
                <div className="skeleton skeleton--line skeleton--short" />
                <div className="skeleton skeleton--line skeleton--price" />
              </div>
            </div>
          </li>
        ))}
      </ul>
    </div>
  )
}

/** Placeholder for the product detail page (image + text column). */
export function ProductSkeleton({ label }: SkeletonProps) {
  return (
    <div role="status" className="skeleton-wrap">
      <span className="visually-hidden">{label}</span>
      <div className="product" aria-hidden="true">
        <div className="product__media skeleton skeleton--media" />
        <div className="product__body">
          <div className="skeleton skeleton--line skeleton--short" />
          <div className="skeleton skeleton--title" />
          <div className="skeleton skeleton--line" />
          <div className="skeleton skeleton--line" />
          <div className="skeleton skeleton--line skeleton--short" />
        </div>
      </div>
    </div>
  )
}
