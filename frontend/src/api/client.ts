import { client } from '@/api/generated/client.gen'
import { apiClient } from '@/services/api'

/**
 * The generated SDK is configured once with FE-001's canonical Axios instance.
 * That keeps auth cookies, locale, request ids, and error interception in one
 * transport path while preserving generated operation signatures.
 */
client.setConfig({
  axios: apiClient,
  headers: {
    Accept: 'application/json',
  },
})

export const typedClient = client

export function withTypedClient<T>(
  request: (client: typeof typedClient) => Promise<T>,
): Promise<T> {
  return request(typedClient)
}
