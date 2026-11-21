/// <reference types="vite/client" />

interface ImportMetaEnv {
  /** Base URL of our API, e.g. http://localhost:8000/api/v1. Read only by src/api/client.ts. */
  readonly VITE_API_URL?: string
}

interface ImportMeta {
  readonly env: ImportMetaEnv
}
