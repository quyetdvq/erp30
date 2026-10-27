import { describe, expect, it } from 'vitest'
import { notificationDestination, notificationTarget, type InboxNotification } from '@/services/notificationInbox'

const sample: InboxNotification = {
  id: '1',
  type: 'import.completed', priority: 'NORMAL',
  title_key: 'notifications.import.completed.title',
  body_key: 'notifications.import.completed.body',
  params: { success_rows: 5, error_rows: 0 },
  target: { type: 'import_job', id: '25' },
  read_at: null, created_at: '2026-10-26T08:00:00Z',
}

function workflow(resource_type: string, id = '21'): InboxNotification {
  return {
    ...sample,
    type: 'workflow.action_required',
    target: { type: 'workflow_target', resource_type, id },
    params: { resource_type, state_code: 'REVIEW' },
  }
}

describe('M11 NFX-A1 destination allowlist', () => {
  it('links to exact Import and Product detail routes', () => {
    expect(notificationDestination(sample)).toEqual({ path: '/products/import-jobs/25', kind: 'detail' })
    expect(notificationDestination(workflow('product'))).toEqual({ path: '/products/21', kind: 'detail' })
    expect(notificationTarget(sample)).toBe('/products/import-jobs/25')
  })

  it('labels list-only workflow routes truthfully', () => {
    expect(notificationDestination(workflow('department'))).toEqual({ path: '/people/departments', kind: 'list' })
    expect(notificationDestination(workflow('organization_contact'))).toEqual({ path: '/organizations', kind: 'list' })
  })

  it('rejects unknown resource identifiers, malformed ids and prototype keys', () => {
    for (const name of ['not_a_resource', '__proto__', 'constructor', 'toString']) {
      expect(notificationDestination(workflow(name))).toBeNull()
    }
    for (const id of ['0', '-1', '01', '1/../../security', '2?redirect=https://example.com', '9'.repeat(30)]) {
      expect(notificationDestination({ ...sample, target: { type: 'import_job', id } })).toBeNull()
    }
    expect(notificationTarget(workflow('__proto__'))).toBeNull()
  })
})
