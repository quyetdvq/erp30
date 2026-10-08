import { beforeEach, describe, expect, it, vi } from 'vitest'

const sdkMocks = vi.hoisted(() => ({
  createProductGroup: vi.fn<(...args: unknown[]) => unknown>(),
  updateProductGroup: vi.fn<(...args: unknown[]) => unknown>(),
  createProductUom: vi.fn<(...args: unknown[]) => unknown>(),
  updateProductUom: vi.fn<(...args: unknown[]) => unknown>(),
  createProduct: vi.fn<(...args: unknown[]) => unknown>(),
  updateProduct: vi.fn<(...args: unknown[]) => unknown>(),
}))

vi.mock('@/api/generated/sdk.gen', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/api/generated/sdk.gen')>()),
  ...sdkMocks,
}))

import { saveProduct, saveProductGroup, saveProductUom } from '@/services/productCatalog'

function resource() {
  return { data: { data: { id: '15' } } }
}

function bodyOf(mock: { mock: { calls: unknown[][] } }): unknown {
  const call = mock.mock.calls[0]?.[0]
  if (!call || typeof call !== 'object' || !('body' in call))
    throw new Error('Missing request body')
  return call.body
}

describe('product catalog patch payloads', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('omits status from Product Group PATCH and keeps it for create', async () => {
    sdkMocks.updateProductGroup.mockResolvedValue(resource())
    await saveProductGroup({ code: 'GROUP', name: 'Group', status: 'ACTIVE' }, '15')
    expect(bodyOf(sdkMocks.updateProductGroup)).toEqual({
      code: 'GROUP',
      name: 'Group',
    })

    sdkMocks.createProductGroup.mockResolvedValue(resource())
    await saveProductGroup({ code: 'GROUP', name: 'Group', status: 'ACTIVE' })
    expect(bodyOf(sdkMocks.createProductGroup)).toMatchObject({
      status: 'ACTIVE',
    })
  })

  it('omits status from Product UOM and Product PATCH', async () => {
    sdkMocks.updateProductUom.mockResolvedValue(resource())
    await saveProductUom({ code: 'EA', name: 'Each', status: 'INACTIVE' }, '15')
    expect(bodyOf(sdkMocks.updateProductUom)).toEqual({
      code: 'EA',
      name: 'Each',
    })

    sdkMocks.updateProduct.mockResolvedValue(resource())
    await saveProduct({ product_name: 'Sample', status: 'ACTIVE' }, '15')
    expect(bodyOf(sdkMocks.updateProduct)).toEqual({
      product_name: 'Sample',
    })
  })
})
