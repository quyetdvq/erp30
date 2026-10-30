import { beforeEach, describe, expect, it, vi } from 'vitest'

const sdkMocks = vi.hoisted(() => ({
  createProductGroup: vi.fn<(...args: unknown[]) => unknown>(),
  updateProductGroup: vi.fn<(...args: unknown[]) => unknown>(),
  createProductUom: vi.fn<(...args: unknown[]) => unknown>(),
  updateProductUom: vi.fn<(...args: unknown[]) => unknown>(),
  createProduct: vi.fn<(...args: unknown[]) => unknown>(),
  updateProduct: vi.fn<(...args: unknown[]) => unknown>(),
  replaceProductUomConversions: vi.fn<(...args: unknown[]) => unknown>(),
  replaceComboItems: vi.fn<(...args: unknown[]) => unknown>(),
  listComboItems: vi.fn<(...args: unknown[]) => unknown>(),
}))

vi.mock('@/api/generated/sdk.gen', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/api/generated/sdk.gen')>()),
  ...sdkMocks,
}))

import { jsonBodySerializer } from '@/api/generated/client'

import {
  replaceProductComboItems,
  replaceProductConversions,
  saveProduct,
  saveProductGroup,
  saveProductUom,
} from '@/services/productCatalog'

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

  it('sends UOM conversions as a JSON items object, never as an implicit bare array', async () => {
    sdkMocks.replaceProductUomConversions.mockResolvedValue({ data: { data: [] } })
    const rows = [{
      uom_id: '2',
      conversion_factor: '12.500000',
      usage_type: 'PURCHASE' as const,
      status: 'ACTIVE' as const,
    }]
    await replaceProductConversions('1479', rows)
    expect(sdkMocks.replaceProductUomConversions).toHaveBeenCalledTimes(1)
    const request = sdkMocks.replaceProductUomConversions.mock.calls[0]![0] as {
      path: { id: string }
      body: unknown
      bodySerializer: (body: unknown) => unknown
      throwOnError: boolean
    }
    expect(request.path.id).toBe('1479')
    expect(request.body).toEqual({ items: rows })
    expect(request.bodySerializer).toBe(jsonBodySerializer.bodySerializer)
    expect(request.bodySerializer(request.body)).toBe(JSON.stringify({ items: rows }))
    expect(request.throwOnError).toBe(true)
  })

  it('sends explicit {items:[]} when the user deliberately removes all conversions', async () => {
    sdkMocks.replaceProductUomConversions.mockResolvedValue({ data: { data: [] } })
    await replaceProductConversions('1479', [])
    const request = sdkMocks.replaceProductUomConversions.mock.calls[0]![0] as {
      body: unknown
      bodySerializer: (body: unknown) => unknown
    }
    expect(request.body).toEqual({ items: [] })
    expect(request.bodySerializer(request.body)).toBe('{"items":[]}')
  })

  it('normalizes the generated SDK object variant without nesting items twice', async () => {
    sdkMocks.replaceProductUomConversions.mockResolvedValue({ data: { data: [] } })
    await replaceProductConversions('1479', { items: [{ uom_id: '2', conversion_factor: '1' }] })
    expect(bodyOf(sdkMocks.replaceProductUomConversions)).toEqual({
      items: [{ uom_id: '2', conversion_factor: '1' }],
    })
  })

  it('does not report success when the UOM replace API rejects the write', async () => {
    sdkMocks.replaceProductUomConversions.mockRejectedValue(new Error('400 MALFORMED_REQUEST'))
    await expect(replaceProductConversions('1479', [])).rejects.toThrow('MALFORMED_REQUEST')
  })

  it('re-reads combo items when replacement acknowledges with an empty collection', async () => {
    sdkMocks.replaceComboItems.mockResolvedValue({ data: { data: [] } })
    sdkMocks.listComboItems.mockResolvedValue({
      data: {
        data: [
          {
            id: '21',
            combo_product_id: '15',
            component_product_id: '30',
            quantity: '2.000000',
          },
        ],
      },
    })

    const result = await replaceProductComboItems('15', [
      { component_product_id: '30', quantity: '2.000000' },
    ])

    expect(result).toEqual([
      {
        id: '21',
        combo_product_id: '15',
        component_product_id: '30',
        quantity: '2.000000',
      },
    ])
    expect(sdkMocks.replaceComboItems).toHaveBeenCalledTimes(1)
    expect(sdkMocks.listComboItems).toHaveBeenCalledTimes(1)
  })
})
