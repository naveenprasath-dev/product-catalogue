import { useEffect, useId, useRef, useState, type ChangeEvent, type FormEvent } from 'react'
import { createEnquiry, toApiError, type ApiError } from '../api/client'
import type { FieldErrors, Product, User } from '../api/types'
import { useAuth } from '../auth/useAuth'

type EnquiryFormProps = { product: Product }

type Values = {
  name: string
  email: string
  phone: string
  message: string
  website: string
}

type FieldName = Exclude<keyof Values, 'website'>

type Phase =
  | { kind: 'idle' }
  | { kind: 'submitting' }
  | { kind: 'success'; name: string }
  | { kind: 'failed'; message: string }

const EMPTY: Values = { name: '', email: '', phone: '', message: '', website: '' }

/** Fills name/email from the signed-in user without overwriting anything already typed. */
function prefill(values: Values, user: User): Values {
  return { ...values, name: values.name || user.name, email: values.email || user.email }
}

// Limits mirror StoreEnquiryRequest; the server is the source of truth (these are UX only).
const LIMITS = { name: 100, email: 255, phone: 30, message: 2000 } as const
const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

function validate(values: Values): FieldErrors {
  const errors: FieldErrors = {}
  const name = values.name.trim()
  const email = values.email.trim()
  if (name === '') errors.name = ['Please enter your name.']
  else if (name.length > LIMITS.name) errors.name = [`Name must be ${LIMITS.name} characters or fewer.`]
  if (email === '') errors.email = ['Please enter your email address.']
  else if (!EMAIL_PATTERN.test(email)) errors.email = ['Please enter a valid email address.']
  if (values.phone.trim().length > LIMITS.phone) errors.phone = [`Phone must be ${LIMITS.phone} characters or fewer.`]
  if (values.message.length > LIMITS.message) {
    errors.message = [`Message must be ${LIMITS.message} characters or fewer.`]
  }
  return errors
}

const FIELD_NAMES: readonly FieldName[] = ['name', 'email', 'phone', 'message']

function failureMessage(error: ApiError): string {
  if (error.isRateLimited) return 'Too many requests. Please wait a moment and try again.'
  if (error.isValidation) return 'Please check the highlighted fields.'
  if (error.status === 0) return 'We could not reach the server. Your details are kept, so please try again.'
  if (error.status === 502) {
    return 'We could not send your enquiry just now. Your details are kept, so please try again.'
  }
  return 'Something went wrong. Your details are kept, so please try again.'
}

export function EnquiryForm({ product }: EnquiryFormProps) {
  const { user } = useAuth()
  const [values, setValues] = useState<Values>(() => (user ? prefill(EMPTY, user) : EMPTY))
  // The session may resolve after the form mounts: pre-fill once per user (adjusting state during
  // render, as React recommends, instead of an effect). The user id itself is never sent; the API
  // takes it from the session.
  const [prefilledFor, setPrefilledFor] = useState<number | null>(user?.id ?? null)
  if (user !== null && prefilledFor !== user.id) {
    setPrefilledFor(user.id)
    setValues((current) => prefill(current, user))
  }
  const [errors, setErrors] = useState<FieldErrors>({})
  const [phase, setPhase] = useState<Phase>({ kind: 'idle' })
  const formId = useId()
  const thankYouRef = useRef<HTMLHeadingElement>(null)
  const alertRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    if (phase.kind === 'success') thankYouRef.current?.focus()
    if (phase.kind === 'failed') alertRef.current?.focus()
  }, [phase])

  function update(field: keyof Values) {
    return (event: ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
      setValues((current) => ({ ...current, [field]: event.target.value }))
    }
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (phase.kind === 'submitting') return

    const clientErrors = validate(values)
    setErrors(clientErrors)
    const firstInvalid = FIELD_NAMES.find((field) => clientErrors[field] !== undefined)
    if (firstInvalid !== undefined) {
      setPhase({ kind: 'idle' })
      document.getElementById(`${formId}-${firstInvalid}`)?.focus()
      return
    }

    setPhase({ kind: 'submitting' })
    try {
      await createEnquiry({
        product_id: product.id,
        name: values.name.trim(),
        email: values.email.trim(),
        phone: values.phone.trim() || undefined,
        message: values.message.trim() || undefined,
        website: values.website,
      })
      setPhase({ kind: 'success', name: values.name.trim() })
    } catch (caught) {
      const error = toApiError(caught)
      setErrors(error.fieldErrors)
      setPhase({ kind: 'failed', message: failureMessage(error) })
    }
  }

  if (phase.kind === 'success') {
    return (
      <section className="thank-you" role="status" aria-labelledby={`${formId}-thanks`}>
        <h2 id={`${formId}-thanks`} ref={thankYouRef} tabIndex={-1}>
          Thank you, {phase.name}!
        </h2>
        <p>
          Your enquiry about <strong>{product.title}</strong> has been sent to our sales team. We will be in
          touch soon.
        </p>
      </section>
    )
  }

  const submitting = phase.kind === 'submitting'
  // Errors for fields the form doesn't show (e.g. product_id) go into the summary.
  const otherErrors = Object.entries(errors)
    .filter(([field]) => !(FIELD_NAMES as readonly string[]).includes(field))
    .flatMap(([, messages]) => messages)

  function fieldProps(field: FieldName) {
    const fieldErrors = errors[field]
    const hasError = fieldErrors !== undefined && fieldErrors.length > 0
    return {
      id: `${formId}-${field}`,
      name: field,
      value: values[field],
      onChange: update(field),
      'aria-invalid': hasError,
      'aria-describedby': hasError ? `${formId}-${field}-error` : undefined,
    }
  }

  function fieldError(field: FieldName) {
    const fieldErrors = errors[field]
    if (fieldErrors === undefined || fieldErrors.length === 0) return null
    return (
      <p id={`${formId}-${field}-error`} className="field__error">
        {fieldErrors.join(' ')}
      </p>
    )
  }

  return (
    <section id="enquiry" className="enquiry" aria-labelledby={`${formId}-title`}>
      <h2 id={`${formId}-title`} className="enquiry__title">
        Ask our sales team
      </h2>
      <p className="enquiry__intro">Send us your questions about this product and we will reply by email.</p>
      <form className="form" onSubmit={handleSubmit} noValidate>
        {phase.kind === 'failed' && (
          <div className="form__alert" role="alert" ref={alertRef} tabIndex={-1}>
            <p>{phase.message}</p>
            {otherErrors.length > 0 && (
              <ul>
                {otherErrors.map((message) => (
                  <li key={message}>{message}</li>
                ))}
              </ul>
            )}
          </div>
        )}

        <div className="field">
          <label htmlFor={`${formId}-name`} className="field__label">
            Name <span className="field__required">(required)</span>
          </label>
          <input
            {...fieldProps('name')}
            type="text"
            className="input"
            autoComplete="name"
            maxLength={LIMITS.name}
            required
          />
          {fieldError('name')}
        </div>

        <div className="field">
          <label htmlFor={`${formId}-email`} className="field__label">
            Email <span className="field__required">(required)</span>
          </label>
          <input
            {...fieldProps('email')}
            type="email"
            className="input"
            autoComplete="email"
            maxLength={LIMITS.email}
            required
          />
          {fieldError('email')}
        </div>

        <div className="field">
          <label htmlFor={`${formId}-phone`} className="field__label">
            Phone <span className="field__optional">(optional)</span>
          </label>
          <input {...fieldProps('phone')} type="tel" className="input" autoComplete="tel" maxLength={LIMITS.phone} />
          {fieldError('phone')}
        </div>

        <div className="field">
          <label htmlFor={`${formId}-message`} className="field__label">
            Message <span className="field__optional">(optional)</span>
          </label>
          <textarea {...fieldProps('message')} className="input textarea" rows={5} maxLength={LIMITS.message} />
          {fieldError('message')}
        </div>

        {/* Honeypot: hidden from people and assistive tech; bots that fill it are dropped by the API. */}
        <div className="honeypot" aria-hidden="true">
          <label htmlFor={`${formId}-website`}>Website</label>
          <input
            id={`${formId}-website`}
            name="website"
            type="text"
            tabIndex={-1}
            autoComplete="off"
            value={values.website}
            onChange={update('website')}
          />
        </div>

        <button type="submit" className="button" disabled={submitting}>
          {submitting ? 'Sending…' : 'Send enquiry'}
        </button>
      </form>
    </section>
  )
}
