import { describe, expect, it } from 'vitest'
import {
  numberSeriesSnapshotForAll,
  numberSeriesSnapshotForResource,
  numberSeriesSnapshotWithMutation,
  type NumberingSeriesRow,
} from '@/services/autoNumbering'

const row = (id: string, resourceId: string, status: 'ACTIVE' | 'INACTIVE' = 'ACTIVE'): NumberingSeriesRow => ({
  id, resource_id: resourceId, series_code: `SERIES_${id}`, name: `Number ${id}`,
  scope_type: 'GROUP', pattern: `R${id}-{SEQ:6}`, reset_policy: 'NEVER',
  generation_mode: 'AUTO_REQUIRED', start_number: '1', increment_by: 1,
  is_default: false, status,
})

describe('view-local numbering snapshots', () => {
  it('commits a complete All-Resource result with correct empty Resource buckets and stable order', () => {
    const snapshot = numberSeriesSnapshotForAll(['2', '3', '4'], [row('21', '2'), row('31', '3'), row('22', '2')])
    expect([...snapshot.keys()]).toEqual(['2', '3', '4'])
    expect(snapshot.get('2')?.map((item) => item.id)).toEqual(['21', '22'])
    expect(snapshot.get('3')?.map((item) => item.id)).toEqual(['31'])
    expect(snapshot.has('4')).toBe(true)
    expect(snapshot.get('4')).toEqual([])
  })

  it('rejects unauthorized or cross-Resource rows, invalid IDs and duplicates before commit', () => {
    expect(() => numberSeriesSnapshotForAll(['2'], [row('31', '3')])).toThrow(/unauthorized/)
    expect(() => numberSeriesSnapshotForResource(new Map(), '2', [row('31', '3')])).toThrow(/cross-resource/)
    expect(() => numberSeriesSnapshotForAll(['0'], [])).toThrow(/Invalid/)
    expect(() => numberSeriesSnapshotForResource(new Map(), '2', [row('21', '2'), row('21', '2')])).toThrow(/invalid/)
  })

  it('replaces exactly one scoped snapshot and preserves other Resource data', () => {
    const initial = numberSeriesSnapshotForAll(['2', '3'], [row('21', '2'), row('31', '3')])
    const next = numberSeriesSnapshotForResource(initial, '2', [row('22', '2')])
    expect(initial.get('2')?.[0]?.id).toBe('21')
    expect(next.get('2')?.[0]?.id).toBe('22')
    expect(next.get('3')).toEqual(initial.get('3'))
  })

  it('merges canonical PATCH and state responses without mutating the prior snapshot', () => {
    const initial = numberSeriesSnapshotForAll(['2', '3'], [row('21', '2'), row('22', '2'), row('31', '3')])
    const changed = numberSeriesSnapshotWithMutation(initial, '2', row('21', '2', 'INACTIVE'))
    expect(changed.get('2')?.map((item) => item.id)).toEqual(['21', '22'])
    expect(changed.get('2')?.[0]?.status).toBe('INACTIVE')
    expect(initial.get('2')?.[0]?.status).toBe('ACTIVE')
    expect(changed.get('3')).toEqual(initial.get('3'))
    const created = numberSeriesSnapshotWithMutation(changed, '2', row('23', '2'))
    expect(created.get('2')?.map((item) => item.id)).toEqual(['21', '22', '23'])
  })

  it('does not equate a lone mutation result with a complete Resource list', () => {
    const initial = numberSeriesSnapshotForAll(['2'], [row('21', '2')])
    expect(numberSeriesSnapshotWithMutation(initial, '3', row('31', '3'))).toBe(initial)
    expect(initial.has('3')).toBe(false)
  })

  it('refuses to apply a mutation response for another Resource', () => {
    const initial = numberSeriesSnapshotForAll(['2', '3'], [row('21', '2')])
    expect(() => numberSeriesSnapshotWithMutation(initial, '2', row('31', '3'))).toThrow(/cross-resource/)
    expect(initial.get('2')?.[0]?.id).toBe('21')
  })
})
