import type { InputHTMLAttributes } from 'react'

type FormFieldProps = {
  id: string
  label: string
  errors?: string[]
} & Omit<InputHTMLAttributes<HTMLInputElement>, 'id' | 'className'>

/** Labelled input with its error message wired up via aria-describedby / aria-invalid. */
export function FormField({ id, label, errors, ...input }: FormFieldProps) {
  const hasError = errors !== undefined && errors.length > 0
  return (
    <div className="field">
      <label htmlFor={id} className="field__label">
        {label}
      </label>
      <input
        {...input}
        id={id}
        className="input"
        aria-invalid={hasError}
        aria-describedby={hasError ? `${id}-error` : undefined}
      />
      {hasError && (
        <p id={`${id}-error`} className="field__error">
          {errors.join(' ')}
        </p>
      )}
    </div>
  )
}
