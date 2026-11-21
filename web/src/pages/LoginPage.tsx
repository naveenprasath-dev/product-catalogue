import { useEffect, useId, useRef, useState, type FormEvent } from 'react'
import { Link, Navigate, useNavigate, useSearchParams } from 'react-router'
import { toApiError } from '../api/client'
import type { FieldErrors } from '../api/types'
import { authFailureMessage } from '../auth/messages'
import { authPath, safeNext } from '../auth/redirect'
import { useAuth } from '../auth/useAuth'
import { FormField } from '../components/FormField'
import { Loading } from '../components/Status'

export function LoginPage() {
  const { status, login } = useAuth()
  const navigate = useNavigate()
  const [params] = useSearchParams()
  const next = safeNext(params.get('next'))
  const id = useId()

  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [remember, setRemember] = useState(false)
  const [errors, setErrors] = useState<FieldErrors>({})
  const [alert, setAlert] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)
  const alertRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    if (alert !== null) alertRef.current?.focus()
  }, [alert])

  if (status === 'loading') return <Loading />
  // Already signed in (and not mid-submit, which navigates itself): nothing to do here.
  if (status === 'authenticated' && !submitting) return <Navigate to={next} replace />

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (submitting) return
    const clientErrors: FieldErrors = {}
    if (email.trim() === '') clientErrors.email = ['Please enter your email address.']
    if (password === '') clientErrors.password = ['Please enter your password.']
    setErrors(clientErrors)
    if (Object.keys(clientErrors).length > 0) {
      setAlert(null)
      document.getElementById(`${id}-${clientErrors.email ? 'email' : 'password'}`)?.focus()
      return
    }

    setSubmitting(true)
    setAlert(null)
    try {
      await login({ email: email.trim(), password, remember })
      navigate(next, { replace: true })
    } catch (caught) {
      const error = toApiError(caught)
      setErrors(error.fieldErrors)
      setAlert(authFailureMessage(error, 'Please check your email and password.'))
      setSubmitting(false)
    }
  }

  return (
    <section className="auth">
      <h1 className="page-title">Log in</h1>
      <form className="form" onSubmit={handleSubmit} noValidate>
        {alert !== null && (
          <div className="form__alert" role="alert" ref={alertRef} tabIndex={-1}>
            <p>{alert}</p>
          </div>
        )}
        <FormField
          id={`${id}-email`}
          label="Email"
          name="email"
          type="email"
          autoComplete="email"
          value={email}
          onChange={(event) => setEmail(event.target.value)}
          errors={errors.email}
          required
        />
        <FormField
          id={`${id}-password`}
          label="Password"
          name="password"
          type="password"
          autoComplete="current-password"
          value={password}
          onChange={(event) => setPassword(event.target.value)}
          errors={errors.password}
          required
        />
        <div className="field field--checkbox">
          <input
            id={`${id}-remember`}
            type="checkbox"
            checked={remember}
            onChange={(event) => setRemember(event.target.checked)}
          />
          <label htmlFor={`${id}-remember`}>Remember me</label>
        </div>
        <button type="submit" className="button" disabled={submitting}>
          {submitting ? 'Logging in…' : 'Log in'}
        </button>
      </form>
      <p className="auth__switch">
        New here? <Link to={authPath('/register', next)}>Create an account</Link>
      </p>
    </section>
  )
}
