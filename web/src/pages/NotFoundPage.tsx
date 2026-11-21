import { Link } from 'react-router'

type NotFoundPageProps = {
  title?: string
  message?: string
}

export function NotFoundPage({
  title = 'Page not found',
  message = 'The page you are looking for does not exist.',
}: NotFoundPageProps) {
  return (
    <section className="not-found">
      <h1>{title}</h1>
      <p>{message}</p>
      <Link to="/" className="button">
        Back to the home page
      </Link>
    </section>
  )
}
