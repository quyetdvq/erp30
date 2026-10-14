import { describe, expect, it } from 'vitest'
import type { Product, ProductUomConversion } from '@/api/generated/types.gen'
import { emptyConversion, emptyProductForm, productToForm, toCombo, toConversion } from '../model'
import { comboPayload, conversionsPayload, productPayload } from '../payload'
import {
  comboValidationKey,
  conversionValidationKey,
  decimalIsPositive,
  productValidationKey,
} from '../validation'

describe('Product Editor as-built payload and validation contracts', () => {
  const flags = { isChild: false, isCombo: false, isParentRole: false, editing: false }
  const groupOptions = [{ value: 'g1', disabled: false }]
  const uomOptions = [{ value: 'u1', disabled: false }]
  const form = () => ({
    ...emptyProductForm(),
    product_name: 'Example',
    base_uom_id: 'u1',
    product_group_id: 'g1',
  })

  it('initializes independent string-valued forms with the original defaults', () => {
    const first = emptyProductForm()
    const second = emptyProductForm()
    first.product_name = 'changed'
    expect(second.product_name).toBe('')
    expect(second.product_type).toBe('STANDARD')
    expect(second.serial_management_enabled).toBe(false)
    expect(second.status).toBe('ACTIVE')
    expect(emptyConversion()).toMatchObject({ usage_type: 'BOTH', status: 'ACTIVE', price: '' })
  })

  it('loads nullable product fields as strings and converts MySQL zero flags safely', () => {
    const row = {
      product_code: null,
      sku: 'SKU-1',
      barcode: null,
      product_type: 'STANDARD',
      product_name: 'Sample',
      product_child_name: null,
      parent_product_id: null,
      product_group_id: '7',
      base_uom_id: '3',
      price: '0.00',
      net_weight_g: 15,
      package_gross_weight_g: null,
      package_length_mm: null,
      package_width_mm: null,
      package_height_mm: null,
      serial_management_enabled: '0',
      description: null,
      status: 'ACTIVE',
    } as unknown as Product
    expect(productToForm(row)).toMatchObject({
      product_code: '',
      sku: 'SKU-1',
      price: '0.00',
      net_weight_g: '15',
      serial_management_enabled: false,
      description: '',
    })
    // Legacy database flags can be strings even though the generated Product type uses boolean.
    const enabledRow = {
      ...row,
      serial_management_enabled: '1',
    } as unknown as Product
    expect(productToForm(enabledRow).serial_management_enabled).toBe(true)
  })

  it('preserves nullable fields, decimal strings and optional omissions in CREATE payload', () => {
    const draft = form()
    draft.product_code = ''
    draft.sku = 'SKU-1'
    draft.price = '0.00'
    draft.net_weight_g = '1.250'
    draft.package_length_mm = '0'
    draft.description = ''
    expect(productPayload(draft, flags, [])).toStrictEqual({
      product_code: null,
      sku: 'SKU-1',
      barcode: null,
      product_type: 'STANDARD',
      product_group_id: 'g1',
      base_uom_id: 'u1',
      product_name: 'Example',
      price: '0.00',
      net_weight_g: '1.250',
      package_length_mm: '0',
      serial_management_enabled: false,
    })
  })

  it('sends COMBO component rows atomically on CREATE and never bundles them on edit', () => {
    const draft = { ...form(), product_type: 'COMBO' as const, serial_management_enabled: true }
    const items = [{ id: 'temporary', component_product_id: 'p2', quantity: '1.5000' }]
    const create = productPayload(draft, { ...flags, isCombo: true }, items)
    expect(create).toMatchObject({
      product_type: 'COMBO',
      serial_management_enabled: false,
      combo_items: [{ component_product_id: 'p2', quantity: '1.5000' }],
    })
    const update = productPayload(draft, { ...flags, isCombo: true, editing: true }, items)
    expect('combo_items' in update).toBe(false)
    expect(comboPayload(items)).toStrictEqual([{ component_product_id: 'p2', quantity: '1.5000' }])
  })

  it('keeps child-name and parent restrictions independent of UI visibility', () => {
    const draft = { ...form(), parent_product_id: 'p1', product_child_name: 'Variant', price: '12' }
    const child = productPayload(draft, { ...flags, isChild: true }, [])
    expect(child).toHaveProperty('product_child_name', 'Variant')
    expect(child).not.toHaveProperty('product_name')
    const parent = productPayload(draft, { ...flags, isParentRole: true }, [])
    expect(parent).not.toHaveProperty('price')
  })

  it('preserves validation precedence for name, UOM and historic disabled group', () => {
    const draft = form()
    const missingName = productValidationKey(
      { ...draft, product_name: '' },
      flags,
      groupOptions,
      uomOptions,
    )
    const missingUom = productValidationKey(
      { ...draft, base_uom_id: '' },
      flags,
      groupOptions,
      uomOptions,
    )
    const invalidGroup = productValidationKey(
      draft,
      flags,
      [{ value: 'g1', disabled: true }],
      uomOptions,
    )
    const invalidUom = productValidationKey(draft, flags, groupOptions, [
      { value: 'u1', disabled: true },
    ])
    expect(missingName).toBe('product.nameRequired')
    expect(missingUom).toBe('product.baseUomRequired')
    expect(invalidGroup).toBe('product.invalidGroupReference')
    expect(invalidUom).toBe('product.invalidUomReference')
    expect(productValidationKey(draft, flags, groupOptions, uomOptions)).toBeNull()
  })

  it('retains independent parent and child requirements', () => {
    const draft = form()
    const child = { isChild: true, isParentRole: false }
    const missingParent = productValidationKey(draft, child, groupOptions, uomOptions)
    const missingChildName = productValidationKey(
      { ...draft, parent_product_id: 'p1' },
      child,
      groupOptions,
      uomOptions,
    )
    const valid = productValidationKey(
      { ...draft, parent_product_id: 'p1', product_child_name: 'Child' },
      child,
      groupOptions,
      uomOptions,
    )
    expect(missingParent).toBe('product.parentRequired')
    expect(missingChildName).toBe('product.childRequired')
    expect(valid).toBeNull()
  })

  it('accepts only positive decimal strings using the existing strict rule', () => {
    for (const value of ['0', '0.000', '-1', '.5', '1e2', '2,5', '', ' 1']) {
      expect(decimalIsPositive(value)).toBe(false)
    }
    for (const value of ['0.001', '1', '1.00', '000.1']) {
      expect(decimalIsPositive(value)).toBe(true)
    }
  })

  it('keeps component required before quantity and conversion UOM before factor', () => {
    const item = { id: '', component_product_id: '', quantity: '0' }
    expect(comboValidationKey([item])).toBe('product.componentRequired')
    const withComponent = { ...item, component_product_id: 'p1' }
    expect(comboValidationKey([withComponent])).toBe('product.quantityRequired')
    expect(comboValidationKey([{ ...withComponent, quantity: '1.250' }])).toBeNull()
    const conversion = emptyConversion()
    expect(conversionValidationKey(conversion, 'u1')).toBe('product.alternateUomRequired')
    conversion.uom_id = 'u1'
    expect(conversionValidationKey(conversion, 'u1')).toBe('product.alternateUomRequired')
    conversion.uom_id = 'u2'
    expect(conversionValidationKey(conversion, 'u1')).toBe('product.factorRequired')
    conversion.conversion_factor = '2'
    expect(conversionValidationKey(conversion, 'u1')).toBe('product.priceRequired')
    conversion.usage_type = 'PURCHASE'
    expect(conversionValidationKey(conversion, 'u1')).toBeNull()
  })

  it('preserves conversion field omission, DECIMAL strings and status', () => {
    const row = {
      ...emptyConversion(),
      uom_id: 'u2',
      conversion_factor: '2.5000',
      status: 'INACTIVE' as const,
    }
    expect(conversionsPayload([row])).toStrictEqual([
      { uom_id: 'u2', conversion_factor: '2.5000', usage_type: 'BOTH', status: 'INACTIVE' },
    ])
    const withOptionals = conversionsPayload([{ ...row, price: '0.00', package_height_mm: '2.0' }])
    expect(withOptionals).toMatchObject([{ price: '0.00', package_height_mm: '2.0' }])
  })

  it('converts server combo and conversion data without numeric coercion', () => {
    const combo = toCombo({ id: 3, component_product_id: 12, quantity: '0.25000' } as never)
    expect(combo).toStrictEqual({ id: '3', component_product_id: '12', quantity: '0.25000' })
    const row = { ...emptyConversion(), id: '9', uom_id: '2', conversion_factor: '1.250000' }
    const conversion = toConversion(row as ProductUomConversion)
    expect(conversion).toMatchObject({ conversion_factor: '1.250000' })
  })
})
