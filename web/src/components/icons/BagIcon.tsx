import type { SVGProps } from 'react'

/** Decorative icon (hidden from assistive tech); colour comes from `currentColor`. */
export function BagIcon(props: SVGProps<SVGSVGElement>) {
  return (
    <svg viewBox="0 0 24 24" width="1em" height="1em" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" strokeWidth={2} strokeLinecap="round" strokeLinejoin="round" {...props}>
      <path d="M6 7h12l1 14H5L6 7Z" />
      <path d="M9 10V6a3 3 0 0 1 6 0v4" />
    </svg>
  )
}
