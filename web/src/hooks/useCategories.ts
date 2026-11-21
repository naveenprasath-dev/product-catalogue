import { useOutletContext } from 'react-router'
import type { Category } from '../api/types'
import type { ApiState } from './useApi'

export type LayoutContext = { categories: ApiState<Category[]> }

/** Categories loaded once by Layout (header nav, footer and home tiles share one request). */
export function useCategories(): ApiState<Category[]> {
  return useOutletContext<LayoutContext>().categories
}
