import { describe, expect, it } from 'vitest'
import { searchableSelectFilter } from '@/components/forms/searchableSelect'
import { geographicOption, singleLocationId, wardMatchesSelection } from '../customerLocationSelect'

describe('CUS searchable province/ward selectors', () => {
  it('formats province options with the same code/name labels as P1 Contact', () => {
    expect(geographicOption({ id: '10', code: 'HN', name: 'Hà Nội' }))
      .toEqual({ value: '10', label: 'HN - Hà Nội' })
  })
  it('prefers canonical full ward names and still exposes the ward code for search', () => {
    const option = geographicOption({ id: '150', code: 'W150', name: 'Tân Tạo', name_full: 'Phường Tân Tạo' })
    expect(option).toEqual({ value: '150', label: 'W150 - Phường Tân Tạo' })
    expect(searchableSelectFilter('tân tạo', option)).toBe(true)
    expect(searchableSelectFilter('w150', option)).toBe(true)
  })
  it('normalizes only a single string ID; clearing returns an empty string', () => {
    expect(singleLocationId('17')).toBe('17')
    expect(singleLocationId('')).toBe('')
    expect(singleLocationId(['17', '20'])).toBe('')
  })
  it('does not accept a stale ward from a different province', () => {
    expect(wardMatchesSelection({ id: 150, name: 'Tân Tạo', province_id: 8 }, '9', '150')).toBe(false)
    expect(wardMatchesSelection({ id: 150, name: 'Tân Tạo', province_id: 8 }, '8', '150')).toBe(true)
  })
  it('does not accept a ward for another selected ID or without a province', () => {
    expect(wardMatchesSelection({ id: 150, name: 'Tân Tạo', province_id: 8 }, '8', '151')).toBe(false)
    expect(wardMatchesSelection({ id: 150, name: 'Tân Tạo', province_id: 8 }, '', '150')).toBe(false)
  })
  it('never uses numeric ID as a display label when a code is missing', () => {
    expect(geographicOption({ id: '150', name: 'Tân Tạo' }).label).toBe('Tân Tạo')
  })
})
