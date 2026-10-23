import { apiClient } from '@/services/api'

/** Static, same-API-origin image URLs; only validated upload names are accepted. */
export function uploadImageUrl(path: string | null | undefined, type: 'product' | 'avatar'): string | null {
  if (typeof path !== 'string') return null
  // The live API serves files at /writable/uploads; recognize the old /uploads
  // prefix during rollout without ever falling back to binary PHP endpoints.
  const pattern = new RegExp(`^/(?:writable/)?uploads/(20\\d{2})/(20\\d{2})\\d{4}${type}\\d{7}\\.(?:jpg|png|webp)$`)
  const match = path.match(pattern)
  if (!match || match[1] !== match[2]) return null
  const base = apiClient.defaults.baseURL || '/'
  return new URL(path, new URL(base, window.location.origin)).href
}
