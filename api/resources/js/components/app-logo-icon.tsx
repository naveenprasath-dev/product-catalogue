import type { SVGAttributes } from 'react';

/** Shopping-bag mark, the same shape as the shop's header icon. Colour comes from `currentColor` (a token class). */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            viewBox="0 0 24 24"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            stroke="currentColor"
            strokeWidth={2}
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
            focusable="false"
            {...props}
        >
            <path d="M6 7h12l1 14H5L6 7Z" />
            <path d="M9 10V6a3 3 0 0 1 6 0v4" />
        </svg>
    );
}
