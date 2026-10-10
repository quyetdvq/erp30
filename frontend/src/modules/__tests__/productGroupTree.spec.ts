import { describe, expect, it } from 'vitest'

import { buildVisibleProductGroupTree } from '@/modules/productGroupTree'

type Group = { id: string; parent_id: string | null; code: string }

function group(id: string, parent_id: string | null, code = id): Group {
  return { id, parent_id, code }
}

function childrenByParent(rows: readonly Group[], descending = false): Map<string, Group[]> {
  const result = new Map<string, Group[]>()
  for (const row of rows) {
    const key = row.parent_id ?? 'ROOT'
    const children = result.get(key)
    if (children) children.push(row)
    else result.set(key, [row])
  }
  for (const children of result.values()) {
    children.sort((a, b) => (descending ? -1 : 1) * a.code.localeCompare(b.code))
  }
  return result
}

function ids(rows: readonly Group[]): string[] {
  return rows.map((row) => row.id)
}

describe('Product Groups visible tree (current page)', () => {
  it('does not reintroduce collapsed children or grandchildren as root rows', () => {
    const source = [
      group('1', null, 'A'),
      group('2', '1', 'A1'),
      group('3', '2', 'A1a'),
      group('4', null, 'B'),
    ]
    const children = childrenByParent(source)

    const collapsed = buildVisibleProductGroupTree(source, children, new Set())
    expect(ids(collapsed)).toEqual(['1', '4'])
    expect(collapsed[0]).toMatchObject({ depth: 0, hasChildren: true, expanded: false })

    const parentExpanded = buildVisibleProductGroupTree(source, children, new Set(['1']))
    expect(ids(parentExpanded)).toEqual(['1', '2', '4'])
    expect(parentExpanded[1]).toMatchObject({ depth: 1, hasChildren: true, expanded: false })

    const fullyExpanded = buildVisibleProductGroupTree(source, children, new Set(['1', '2']))
    expect(ids(fullyExpanded)).toEqual(['1', '2', '3', '4'])
    expect(fullyExpanded.map((row) => row.depth)).toEqual([0, 1, 2, 0])
    expect(ids(buildVisibleProductGroupTree(source, children, new Set()))).toEqual(['1', '4'])
  })

  it('treats missing-page parents as provisional roots but respects their collapse', () => {
    const source = [group('9', '999', 'Orphan'), group('10', '9', 'Child')]
    const children = childrenByParent(source)

    expect(ids(buildVisibleProductGroupTree(source, children, new Set()))).toEqual(['9'])
    const expanded = buildVisibleProductGroupTree(source, children, new Set(['9']))
    expect(ids(expanded)).toEqual(['9', '10'])
    expect(expanded.map((row) => row.depth)).toEqual([0, 1])
  })

  it('preserves hierarchy while sorting siblings locally and never mutates source rows', () => {
    const source = [
      group('1', null, 'B'),
      group('2', null, 'A'),
      group('3', '1', 'Z'),
      group('4', '1', 'C'),
    ]
    const original = [...source]
    const ascending = buildVisibleProductGroupTree(source, childrenByParent(source), new Set(['1']))
    const descending = buildVisibleProductGroupTree(
      source,
      childrenByParent(source, true),
      new Set(['1']),
      (a, b) => b.code.localeCompare(a.code),
    )

    expect(ids(ascending)).toEqual(['2', '1', '4', '3'])
    expect(ids(descending)).toEqual(['1', '3', '4', '2'])
    expect(descending.map((row) => row.depth)).toEqual([0, 1, 1, 0])
    expect(source).toEqual(original)
    expect(source.every((row) => !('depth' in row))).toBe(true)
  })

  it('keeps disconnected cyclic data inspectable without infinite recursion', () => {
    const source = [group('a', 'b'), group('b', 'a'), group('1', null)]
    const visible = buildVisibleProductGroupTree(source, childrenByParent(source), new Set(['a']))
    expect(ids(visible)).toEqual(['1', 'a', 'b'])
    expect(visible.map((row) => row.depth)).toEqual([0, 0, 1])
  })
})
