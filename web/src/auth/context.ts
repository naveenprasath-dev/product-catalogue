import { createContext } from 'react'
import type { LoginInput, RegisterInput, User } from '../api/types'

export type AuthStatus = 'loading' | 'guest' | 'authenticated'

export type AuthValue = {
  user: User | null
  status: AuthStatus
  login: (input: LoginInput) => Promise<User>
  register: (input: RegisterInput) => Promise<User>
  logout: () => Promise<void>
}

export const AuthContext = createContext<AuthValue | null>(null)
