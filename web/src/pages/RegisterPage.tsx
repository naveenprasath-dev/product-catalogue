import { useEffect, useId, useRef, useState, type ChangeEvent, type FormEvent } from 'react'
import { Link, Navigate, useNavigate, useSearchParams } from 'react-router'
import { toApiError } from '../api/client'
import type { FieldErrors, RegisterInput } from '../api/types'
import { authFailureMessage } from '../auth/messages'
import { authPath, safeNext } from '../auth/redirect'
import { useAuth } from '../auth/useAuth'
import { FormField } from '../components/FormField'
import { Loading } from '../components/Status'

const EMPTY: RegisterInput = { name: '', email: '', password: '', password_confirmation: '' }
const FIELDS = ['name', 'email', 'password', 'password_confirmation'] as const

// UX only; the server (Password::defaults(): min 8) decides. Mirrors the PLAN.md auth contract.
function validate(values: RegisterInput): FieldErrors {
  const errors: FieldErrors = {}
  if (values.name.trim() === '') errors.name = ['Please enter your name.']
  if (values.email.trim() === '') errors.email = ['Please enter your email address.']
  if (values.password.length < 8) errors.password = ['Password must be at least 8 characters.']
  if (values.password_confirmation !== values.password) {
    errors.password_confirmation = ['Passwords do not match.']
  }
  return errors
}

export function RegisterPage() {
  const { status, register } = useAuth()
  const navigate = useNavigate()
  const [params] = useSearchParams()
  const next = safeNext(params.get('next'))
  const id = useId()

  const [values, setValues] = useState<RegisterInput>(EMPTY)
  const [errors, setErrors] = useState<FieldErrors>({})
  const [alert, setAlert] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)
  const alertRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    if (alert !== null) alertRef.current?.focus()
  }, [alert])

  if (status === 'loading') return <Loading />
  if (status === 'authenticated' && !submitting) return <Navigate to={next} replace />

  function update(field: keyof RegisterInput) {
    return (event: ChangeEvent<HTMLInputElement>) =>
      setValues((current) => ({ ...current, [field]: event.target.value }))
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (submitting) return
    const clientErrors = validate(values)
    setErrors(clientErrors)
    const firstInvalid = FIELDS.find((field) => clientErrors[field] !== undefined)
    if (firstInvalid !== undefined) {
      setAlert(null)
      document.getElementById(`${id}-${firstInvalid}`)?.focus()
      return
    }

    setSubmitting(true)
    setAlert(null)
    try {
      await register({ ...values, name: values.name.trim(), email: values.email.trim() })
      navigate(next, { replace: true })
    } catch (caught) {
      const error = toApiError(caught)
      setErrors(error.fieldErrors)
      setAlert(authFailureMessage(error, 'Please check the highlighted fields.'))
      setSubmitting(false)
    }
  }

  return (
    <section className="auth">
      <h1 className="page-title">Create an account</h1>
      <form className="form" onSubmit={handleSubmit} noValidate>
        {alert !== null && (
          <div className="form__alert" role="alert" ref={alertRef} tabIndex={-1}>
            <p>{alert}</p>
          </div>
        )}
        <FormField
          id={`${id}-name`}
          label="Name"
          name="name"
          autoComplete="name"
          maxLength={255}
          value={values.name}
          onChange={update('name')}
          errors={errors.name}
          required
        />
        <FormField
          id={`${id}-email`}
          label="Email"
          name="email"
          type="email"
          autoComplete="email"
          maxLength={255}
          value={values.email}
          onChange={update('email')}
          errors={errors.email}
          required
        />
        <FormField
          id={`${id}-password`}
          label="Password"
          name="password"
          type="password"
          autoComplete="new-password"
          value={values.password}
          onChange={update('password')}
          errors={errors.password}
          required
        />
        <p id={`${id}-password-hint`} className="field__hint">At least 8 characters.</p>
        <FormField
          id={`${id}-password_confirmation`}
          label="Confirm password"
          name="password_confirmation"
          type="password"
          autoComplete="new-password"
          value={values.password_confirmation}
          onChange={update('password_confirmation')}
          errors={errors.password_confirmation}
          required
        />
        <button type="submit" className="button" disabled={submitting}>
          {submitting ? 'Creating account…' : 'Create account'}
        </button>
      </form>
      <p className="auth__switch">
        Already have an account? <Link to={authPath('/login', next)}>Log in</Link>
      </p>
    </section>
  )
}
