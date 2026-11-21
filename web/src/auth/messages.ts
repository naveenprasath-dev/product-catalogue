import type { ApiError } from '../api/client'

/** Form-level message for a failed login/register request. */
export function authFailureMessage(error: ApiError, fallback: string): string {
  if (error.isRateLimited) return 'Too many attempts. Please wait a minute and try again.'
  if (error.isValidation) return fallback
  if (error.status === 0) return 'We could not reach the server. Please try again.'
  return 'Something went wrong. Please try again.'
}
