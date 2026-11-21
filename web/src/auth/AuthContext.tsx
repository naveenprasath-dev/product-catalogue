import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react'
import * as api from '../api/client'
import type { LoginInput, RegisterInput, User } from '../api/types'
import { AuthContext, type AuthStatus, type AuthValue } from './context'

type AuthProviderProps = { children: ReactNode }

type State = { status: AuthStatus; user: User | null }

/**
 * Holds the signed-in customer. The session itself is an HttpOnly cookie (Sanctum SPA auth),
 * so nothing is stored in localStorage; on start we just ask the API who we are.
 */
export function AuthProvider({ children }: AuthProviderProps) {
  const [state, setState] = useState<State>({ status: 'loading', user: null })

  useEffect(() => {
    const controller = new AbortController()
    api
      .me(controller.signal)
      .then((user) => setState(user ? { status: 'authenticated', user } : { status: 'guest', user: null }))
      .catch((error: unknown) => {
        if (error instanceof DOMException && error.name === 'AbortError') return
        // Can't tell who we are (network/5xx): browse as a guest; the shop works without an account.
        setState({ status: 'guest', user: null })
      })
    return () => controller.abort()
  }, [])

  const login = useCallback(async (input: LoginInput) => {
    const user = await api.login(input)
    setState({ status: 'authenticated', user })
    return user
  }, [])

  const register = useCallback(async (input: RegisterInput) => {
    const user = await api.register(input)
    setState({ status: 'authenticated', user })
    return user
  }, [])

  const logout = useCallback(async () => {
    try {
      await api.logout()
    } catch (error) {
      // 401: the session had already ended, which is the outcome we wanted.
      if (!(error instanceof api.ApiError && error.isUnauthenticated)) throw error
    }
    setState({ status: 'guest', user: null })
  }, [])

  const value = useMemo<AuthValue>(
    () => ({ user: state.user, status: state.status, login, register, logout }),
    [state, login, register, logout],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}
