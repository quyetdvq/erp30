import { expect, test } from '@playwright/test'
import type { ProductGroup, ProductUom } from '../src/api/generated/types.gen'
import {
  databaseFlagEnabled,
  productRoleLabel,
  productUomName,
  selectableProductGroupOptions,
  selectableProductUomOptions,
} from '../src/services/productPresentation'

/** Reference filtering is a client-side safeguard; the backend remains authoritative. */
test('Product Group choices exclude DRAFT, missing or inactive ancestors', () => {
  const groups = [
    { id: '1', code: 'ROOT', name: 'Active root', status: 'ACTIVE', parent_id: null },
    { id: '2', code: 'CH', name: 'Active child', status: 'ACTIVE', parent_id: '1' },
    { id: '3', code: 'DR', name: 'Draft group', status: 'DRAFT', parent_id: null },
    { id: '4', code: 'BAD', name: 'Child of draft', status: 'ACTIVE', parent_id: '3' },
    { id: '5', code: 'MIS', name: 'Missing parent', status: 'ACTIVE', parent_id: '99' },
    { id: '6', code: 'C1', name: 'Cycle one', status: 'ACTIVE', parent_id: '7' },
    { id: '7', code: 'C2', name: 'Cycle two', status: 'ACTIVE', parent_id: '6' },
  ] as ProductGroup[]

  expect(selectableProductGroupOptions(groups).map((option) => option.value)).toEqual(['1', '2'])
  expect(selectableProductGroupOptions(groups, '3')).toEqual([
    { value: '1', label: 'Active root (ROOT)', disabled: false },
    { value: '2', label: 'Active child (CH)', disabled: false },
    { value: '3', label: 'Draft group (DR)', disabled: true },
  ])
})

test('Product UOM choices exclude DRAFT and preserve an invalid prior selection as disabled', () => {
  const uoms = [
    { id: '1', code: 'EA', name: 'Each', status: 'ACTIVE' },
    { id: '2', code: 'BOX', name: 'Box', status: 'DRAFT' },
    { id: '3', code: 'OLD', name: 'Obsolete', status: 'INACTIVE' },
  ] as ProductUom[]

  expect(selectableProductUomOptions(uoms)).toEqual([
    { value: '1', label: 'Each (EA)', disabled: false },
  ])
  expect(selectableProductUomOptions(uoms, '2').at(-1)).toEqual({
    value: '2',
    label: 'Box (BOX)',
    disabled: true,
  })
  expect(productUomName(uoms, '1')).toBe('Each')
})

test('MySQL TINYINT flags normalize without treating string zero as true', () => {
  for (const value of [0, '0', false, null, undefined, 'false', '']) {
    expect(databaseFlagEnabled(value)).toBe(false)
  }
  for (const value of [1, '1', true]) {
    expect(databaseFlagEnabled(value)).toBe(true)
  }
})

test('Product roles are presented as names instead of raw technical codes', () => {
  const translate = (key: string) =>
    ({
      'product.roleSimple': 'Sản phẩm đơn',
      'product.roleCombo': 'Bộ sản phẩm',
    })[key] ?? key
  expect(productRoleLabel('SIMPLE', translate)).toBe('Sản phẩm đơn')
  expect(productRoleLabel('COMBO', translate)).toBe('Bộ sản phẩm')
})
