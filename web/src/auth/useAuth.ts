import { useContext } from 'react'
import { AuthContext, type AuthValue } from './context'

/** Current shop customer + login/register/logout. Must be used inside <AuthProvider>. */
export function useAuth(): AuthValue {
  const value = useContext(AuthContext)
  if (value === null) {
    throw new Error('useAuth must be used inside <AuthProvider>')
  }
  return value
}
