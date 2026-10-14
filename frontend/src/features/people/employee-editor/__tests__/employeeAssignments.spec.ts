import { ref } from 'vue'
import { describe, expect, it } from 'vitest'
import type { EmployeeAssignment } from '@/api/generated/types.gen'
import { today } from '../model'
import type { AssignmentMode } from '../types'
import { useEmployeeAssignments } from '../useEmployeeAssignments'

describe('Employees V2.3 — assignment ownership', () => {
  const assignment = (id: string, props: Record<string, unknown> = {}): EmployeeAssignment =>
    ({
      id,
      employee_id: '1',
      organization_id: '9',
      is_primary: true,
      effective_from: '2000-01-01',
      effective_to: null,
      status: 'ACTIVE',
      ...props,
    }) as unknown as EmployeeAssignment

  it('separates active/history tabs while retaining original ordering', () => {
    const mode = ref<'active' | 'history'>('active')
    const state = useEmployeeAssignments(ref<string | null>('1'), mode)
    state.selectedEmployeeAssignments.value = [
      assignment('1'),
      assignment('2', { status: 'INACTIVE' }),
      assignment('3'),
    ]
    expect(state.selectedAssignments.value.map((row) => row.id)).toEqual(['1', '3'])
    mode.value = 'history'
    expect(state.selectedAssignments.value.map((row) => row.id)).toEqual(['2'])
  })

  it('finds active current-primary only, without filtering by current selection', () => {
    const state = useEmployeeAssignments(ref<string | null>('1'), ref<AssignmentMode>('active'))
    state.assignments.value = [
      assignment('future', { effective_from: '9999-01-01' }),
      assignment('inactive', { status: 'INACTIVE' }),
      assignment('secondary', { is_primary: false }),
      assignment('past', { effective_to: '2001-01-01' }),
      assignment('current', { effective_from: today(), employee_id: '2' }),
    ]
    expect(state.currentPrimary('2')?.id).toBe('current')
    expect(state.currentPrimary('1')).toBeUndefined()
  })

  it('clears selected assignments when no employee is selected', async () => {
    const selectedId = ref<string | null>(null)
    const state = useEmployeeAssignments(selectedId, ref<AssignmentMode>('active'))
    state.selectedEmployeeAssignments.value = [assignment('old')]
    await state.loadAssignments()
    expect(state.selectedEmployeeAssignments.value).toEqual([])
  })
})
