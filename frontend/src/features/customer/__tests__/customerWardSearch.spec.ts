import { beforeEach, describe, expect, it, vi } from 'vitest'

const http = vi.hoisted(() => ({
  get: vi.fn<(...args: unknown[]) => Promise<{
    data: { data: Array<{ id: string; name: string; code: string; province_id: string }>;
      meta: { page: number; page_size: number; total: number } }
  }>>(),
}))
vi.mock('@/services/api', () => ({ apiClient: http }))

import { searchCustomerWards } from '@/services/customer'

describe('CUS Ward search', () => {
  beforeEach(() => vi.clearAllMocks())

  it('searches the selected province at the server with bounded, paginated results', async () => {
    http.get.mockResolvedValue({
      data: { data: [{ id: '300', code: 'W300', name: 'Phường A', province_id: '42' }],
        meta: { page: 2, page_size: 50, total: 83 } },
    })
    const result = await searchCustomerWards('42', '  Phường A  ', 2)
    expect(http.get).toHaveBeenCalledWith('/api/v1/wards', {
      params: { q: 'Phường A', page: 2, page_size: 50, filter: { province_id: '42' } },
    })
    expect(result.meta.total).toBe(83)
    expect(result.data[0]?.id).toBe('300')
  })

  it('never runs an unscoped Ward query before Province selection', async () => {
    const result = await searchCustomerWards('', 'Phường A')
    expect(result).toEqual({ data: [], meta: { page: 1, page_size: 50, total: 0 } })
    expect(http.get).not.toHaveBeenCalled()
  })
})
