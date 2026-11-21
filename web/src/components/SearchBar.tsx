import { useId, useState, type FormEvent } from 'react'
import { useNavigate, useSearchParams } from 'react-router'
import { SearchIcon } from './icons/SearchIcon'

// Mirrors the API's limit (longer terms → 422).
export const SEARCH_MAX_LENGTH = 100

export function SearchBar() {
  const [params] = useSearchParams()
  const currentTerm = params.get('q') ?? ''
  // Re-mount with the URL's term when it changes (e.g. back/forward), via the key in Layout.
  const [term, setTerm] = useState(currentTerm)
  const [error, setError] = useState<string | null>(null)
  const navigate = useNavigate()
  const inputId = useId()
  const errorId = useId()

  function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const trimmed = term.trim()
    if (trimmed === '') {
      setError('Enter a search term.')
      return
    }
    setError(null)
    navigate(`/search?${new URLSearchParams({ q: trimmed }).toString()}`)
  }

  return (
    <form className="search" role="search" onSubmit={handleSubmit} noValidate>
      <label htmlFor={inputId} className="visually-hidden">
        Search products
      </label>
      <div className="search__field">
        <SearchIcon className="search__icon" />
        <input
          id={inputId}
          type="search"
          className="input search__input"
          placeholder="Search products"
          value={term}
          maxLength={SEARCH_MAX_LENGTH}
          onChange={(event) => setTerm(event.target.value)}
          aria-invalid={error !== null}
          aria-describedby={error !== null ? errorId : undefined}
        />
        <button type="submit" className="button search__button">
          Search
        </button>
      </div>
      {error !== null && (
        <p id={errorId} className="field__error search__error" role="alert">
          {error}
        </p>
      )}
    </form>
  )
}
