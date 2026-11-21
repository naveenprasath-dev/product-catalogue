import type { SVGProps } from 'react'

/** Decorative icon (hidden from assistive tech); colour comes from `currentColor`. */
export function StarIcon(props: SVGProps<SVGSVGElement>) {
  return (
    <svg viewBox="0 0 24 24" width="1em" height="1em" aria-hidden="true" focusable="false" fill="currentColor" {...props}>
      <path d="M12 2.5l2.94 5.96 6.56.95-4.75 4.63 1.12 6.54L12 17.5l-5.87 3.08 1.12-6.54L2.5 9.41l6.56-.95L12 2.5Z" />
    </svg>
  )
}
