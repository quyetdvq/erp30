// Output naming only: keep the existing code-splitting groups and their sizes intact.
// The content hash is part of the filename so two chunks may share a readable prefix.
type NamedChunk = {
  name: string
  facadeModuleId?: string | null
}

const HASH = '[hash:8]'
const MAX_SLUG_LENGTH = 30

const libraryNames: Record<string, string> = {
  'naive-ui': 'lib-ui',
  'vue-runtime': 'lib-vue',
  icons: 'lib-icons',
  vendor: 'lib-vendor',
}

function shortSlug(name: string): string {
  const normalized = name
    .replace(/View$/, '')
    .replace(/([a-z0-9])([A-Z])/g, '$1-$2')
    .replace(/([A-Z])([A-Z][a-z])/g, '$1-$2')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')

  if (normalized.length <= MAX_SLUG_LENGTH) return normalized || 'chunk'

  // Prefer whole words over a truncated final word. The hash ensures uniqueness.
  const shortened = normalized.slice(0, MAX_SLUG_LENGTH).replace(/-[^-]*$/, '')
  return shortened || normalized.slice(0, MAX_SLUG_LENGTH) || 'chunk'
}

/**
 * Chunk filename families:
 *   app-<entry>-<hash>.js                  HTML entry (configured separately)
 *   lib-ui|lib-vue|lib-icons|lib-vendor-<hash>.js
 *   page-<short-kebab-name>-<hash>.js     Lazy-loaded view
 *   shared-<short-kebab-name>-<hash>.js   Other application chunks
 *
 * All generated JS names are ASCII, bounded in length and content-hashed.
 * Do not derive names from route combinations such as naive-ui~index~Foo~Bar.
 */
export function buildChunkFileName(chunk: NamedChunk): string {
  const groupName = chunk.name.split('~', 1)[0]
  const libraryName = libraryNames[groupName]
  if (libraryName) return `assets/${libraryName}-${HASH}.js`

  const isView = /[\\/]src[\\/](?:views[\\/]|(?:features[\\/][^\\/]+[\\/]|app[\\/])pages[\\/])/.test(
    chunk.facadeModuleId ?? '',
  )
  return `assets/${isView ? 'page' : 'shared'}-${shortSlug(chunk.name)}-${HASH}.js`
}
