import { beforeEach, describe, expect, it, vi } from 'vitest'

const http = vi.hoisted(() => ({
  get: vi.fn<(...args: unknown[]) => Promise<unknown>>(),
  put: vi.fn<(...args: unknown[]) => Promise<unknown>>(),
  post: vi.fn<(...args: unknown[]) => Promise<unknown>>(),
}))
vi.mock('@/services/api', () => ({ apiClient: http }))

import {
  getBusinessWorkflowOptions, getWorkflowAssigneeCandidates,
  replaceWorkflowNotificationAssignees, executeBusinessWorkflowOption,
} from '@/services/businessWorkflow'
import { myWorkflowTypes } from '@/services/myWorkflow'
import { workflowDestination } from '@/services/notificationInbox'

beforeEach(() => {
  http.get.mockReset()
  http.post.mockReset()
  http.put.mockReset()
  http.get.mockResolvedValue({ data: { data: [], meta: { page: 1, page_size: 25, total: 0 } } })
  http.put.mockResolvedValue({ data: { data: { revision: '2', user_ids: ['3', '4'] } } })
})

describe('P2 Workflow/assignee transport shared by resource screens', () => {
  it('uses bounded, scoped User search, never the unrestricted Security Users endpoint', async () => {
    await getWorkflowAssigneeCandidates('customer_contact', '9', { q: 'quyet', page: 2, page_size: 25 })
    expect(http.get).toHaveBeenCalledWith(
      '/api/v1/business-workflow/customer_contact/9/assignee-candidates',
      { params: { q: 'quyet', page: 2, page_size: 25 } },
    )
  })

  it('preserves selected IDs and revision for atomic CAS PUT', async () => {
    await getWorkflowAssigneeCandidates('customer', '7', { ids: ['3', '4'] })
    expect(http.get).toHaveBeenCalledWith(
      '/api/v1/business-workflow/customer/7/assignee-candidates',
      { params: { page: 1, page_size: 25, ids: '3,4' } },
    )
    const saved = await replaceWorkflowNotificationAssignees('customer_contact', '9', '1', ['3', '4'])
    expect(http.put).toHaveBeenCalledWith(
      '/api/v1/business-workflow/customer_contact/9/notification-assignees',
      { expected_revision: '1', user_ids: ['3', '4'] },
    )
    expect(saved.user_ids).toEqual(['3', '4'])
  })

  it('executes only server-authorized Lifecycle actions and links P2 approval tasks', async () => {
    await getBusinessWorkflowOptions('customer_contact', '9')
    expect(http.get).toHaveBeenCalledWith('/api/v1/business-workflow/customer_contact/9/options')
    await executeBusinessWorkflowOption('customer_contact', '9', 'LIFECYCLE', {
      action_code: 'setstate', status: 'ACTIVE',
    })
    expect(http.post).toHaveBeenCalledWith('/api/v1/business-workflow/customer_contact/9/actions/setstate', {})
    expect(myWorkflowTypes).toContain('customer')
    expect(myWorkflowTypes).toContain('customer_contact')
    expect(workflowDestination('customer', '7')).toEqual({ path: '/customers/7', kind: 'detail' })
    expect(workflowDestination('customer_contact', '9')).toEqual({ path: '/customers', kind: 'list' })
  })
})
