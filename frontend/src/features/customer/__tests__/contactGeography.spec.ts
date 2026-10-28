import { describe, expect, it } from 'vitest'
import { contactProvinceLabel, contactWardLabel } from '../contactGeography'

const contact = {
  province_id: '8', ward_id: '150',
  province_name: 'Tên tỉnh đã lưu', ward_name: 'Tên phường đã lưu',
}

describe('CUS Contact detail geography', () => {
  it('prefers matching province name to persisted label', () => {
    expect(contactProvinceLabel(contact, { id: '8', name: 'Vĩnh Phúc' })).toBe('Vĩnh Phúc')
  })
  it('falls back to province snapshot when lookup is not available or mismatched', () => {
    expect(contactProvinceLabel(contact, null)).toBe('Tên tỉnh đã lưu')
    expect(contactProvinceLabel(contact, { id: '9', name: 'Tỉnh khác' })).toBe('Tên tỉnh đã lưu')
  })
  it('prefers canonical full ward name for matching ID and province', () => {
    expect(contactWardLabel(contact, { id: '150', province_id: '8', name: 'Tân Tạo', name_full: 'Phường Tân Tạo' }))
      .toBe('Phường Tân Tạo')
  })
  it('uses ward name when full name is not available', () => {
    expect(contactWardLabel(contact, { id: '150', province_id: '8', name: 'Tân Tạo' })).toBe('Tân Tạo')
  })
  it('ignores a lookup from a different province', () => {
    expect(contactWardLabel(contact, { id: '150', province_id: '9', name: 'Phường không khớp' }))
      .toBe('Tên phường đã lưu')
  })
  it('falls back to ward snapshot when lookup is not available', () => {
    expect(contactWardLabel(contact)).toBe('Tên phường đã lưu')
  })
  it('never exposes a raw ID or a snapshot without a location ID', () => {
    expect(contactProvinceLabel({ ...contact, province_id: null })).toBeNull()
    expect(contactWardLabel({ ...contact, ward_id: null })).toBeNull()
    expect(contactWardLabel({ ...contact, ward_name: null })).toBeNull()
  })
  it('trims persisted labels instead of showing whitespace', () => {
    expect(contactProvinceLabel({ ...contact, province_name: '   ' })).toBeNull()
    expect(contactWardLabel({ ...contact, ward_name: '  Khu A  ' })).toBe('Khu A')
  })
})
