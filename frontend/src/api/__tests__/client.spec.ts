import { describe, expect, it } from 'vitest'

import { client } from '@/api/generated/client.gen'
import { typedClient, withTypedClient } from '@/api/client'
import { apiClient } from '@/services/api'

describe('typed API foundation', () => {
  it('uses the generated client with FE-001 canonical transport', async () => {
    expect(typedClient).toBe(client)
    expect(typedClient.getConfig().axios).toBe(apiClient)
    await expect(withTypedClient((current) => Promise.resolve(current))).resolves.toBe(typedClient)
  })
})
