import { describe, expect, it, vi } from 'vitest'
import { createListQuery } from '@/components/lists/listTypes'

const http = vi.hoisted(() => ({
  get: vi.fn<(url: string, options: { params: Record<string, string | number> }) => Promise<{
    data: { data: []; meta: { page: number; page_size: number; total: number } }
  }>>(),
}))
vi.mock('@/services/api', () => ({ apiClient: http }))

import { listCustomerPage } from '@/services/customer'

describe('CUS shared ListQueryControls adapter', () => {
  it('translates approved list filters to the flat CUS backend contract', async () => {
    http.get.mockResolvedValue({ data: { data: [], meta: { page: 2, page_size: 15, total: 0 } } })
    await listCustomerPage(createListQuery({
      q: 'ABC', page: 2, page_size: 15, sort: '-name',
      filter: { status: 'ACTIVE', customer_type: 'ORGANIZATION', customer_group_code: 'TMDT' },
    }))
    expect(http.get).toHaveBeenCalledWith('/api/v1/customers', {
      params: {
        q: 'ABC', page: 2, page_size: 15, sort: '-name',
        status: 'ACTIVE', customer_type: 'ORGANIZATION', customer_group_code: 'TMDT',
      },
    })
    expect(Object.keys(http.get.mock.lastCall?.[1]?.params ?? {})).not.toContain('filter')
  })

  it('sends only CustomerSql-whitelisted single-field sorts and never causes a compound-sort 400', async () => {
    http.get.mockResolvedValue({ data: { data: [], meta: { page: 1, page_size: 15, total: 0 } } })
    for (const sort of ['customer_code', '-customer_code', 'name', '-name', 'id', '-id']) {
      await listCustomerPage(createListQuery({ sort }))
      expect(http.get.mock.lastCall?.[1]?.params.sort).toBe(sort)
    }
    for (const sort of ['name,customer_code', 'status', '-unknown', '']) {
      await listCustomerPage(createListQuery({ sort }))
      expect(http.get.mock.lastCall?.[1]?.params.sort).toBe('name')
    }
  })

  it('ignores unapproved filter keys rather than sending unexpected backend params', async () => {
    http.get.mockResolvedValue({ data: { data: [], meta: { page: 1, page_size: 15, total: 0 } } })
    await listCustomerPage(createListQuery({ filter: { random_client_key: 'bypass' } }))
    expect(http.get.mock.lastCall?.[1]?.params).toEqual({
      page: 1, page_size: 15, q: '', sort: 'name',
    })
  })
})
