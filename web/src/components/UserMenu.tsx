import { useState } from 'react'
import { Link, useLocation } from 'react-router'
import { authPath } from '../auth/redirect'
import { useAuth } from '../auth/useAuth'
import { UserIcon } from './icons/UserIcon'

/** Header account area: Log in / Register for guests, "Hi, name" + Log out when signed in. */
export function UserMenu() {
  const { status, user, logout } = useAuth()
  const location = useLocation()
  const [pending, setPending] = useState(false)
  const [failed, setFailed] = useState(false)
  const here = `${location.pathname}${location.search}`

  // Nothing while we ask the API who we are, so guests don't see a flash of the wrong state.
  if (status === 'loading') return <div className="user-menu" aria-hidden="true" />

  if (status === 'guest' || user === null) {
    return (
      <nav className="user-menu" aria-label="Account">
        <Link to={authPath('/login', here)} className="user-menu__login">
          <UserIcon className="user-menu__icon" />
          <span className="user-menu__label">Log in</span>
        </Link>
        <Link to={authPath('/register', here)} className="button button--secondary button--small user-menu__register">
          Register
        </Link>
      </nav>
    )
  }

  async function handleLogout() {
    setPending(true)
    setFailed(false)
    try {
      await logout()
    } catch {
      setFailed(true)
    } finally {
      setPending(false)
    }
  }

  return (
    <div className="user-menu" aria-label="Account" role="group">
      <span className="user-menu__avatar" aria-hidden="true">
        {user.name.trim().charAt(0).toUpperCase() || '?'}
      </span>
      <span className="user-menu__name">Hi, {user.name}</span>
      <button type="button" className="button button--secondary button--small" onClick={handleLogout} disabled={pending}>
        {pending ? 'Logging out…' : 'Log out'}
      </button>
      {failed && (
        <p className="user-menu__error" role="alert">
          Could not log out. Please try again.
        </p>
      )}
    </div>
  )
}
