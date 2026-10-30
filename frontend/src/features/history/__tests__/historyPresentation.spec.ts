import { describe, expect, it } from 'vitest'
import { historyActorLabel, historyChangeLines, historyChangeDisplay } from '@/features/history/historyPresentation'
import type { BusinessHistoryEvent } from '@/services/history'

const base: BusinessHistoryEvent = {
  id: '1', resource_type: 'customer', resource_id: '42', event_type: 'CREATE',
  action_code: null, status_before: null, status_after: 'DRAFT', changes: null, checkpoint: null,
  actor_user_id: '1', actor_type: 'USER', actor_username: 'thangdv', actor_full_name: 'Nguyễn Văn A',
  request_id: 'req-1', created_at: '2026-10-29T09:52:19Z',
}

describe('HIS-02 actor presentation', () => {
  it('renders username and full name without an extra HTTP lookup', () => {
    expect(historyActorLabel(base, 'Người dùng', 'Hệ thống')).toBe('thangdv (Nguyễn Văn A)')
  })
  it('falls back safely if a user is deleted or has no name', () => {
    expect(historyActorLabel({ ...base, actor_username: null, actor_full_name: null }, 'Người dùng', 'Hệ thống')).toBe('Người dùng #1')
    expect(historyActorLabel({ ...base, actor_type: 'SYSTEM' }, 'Người dùng', 'Hệ thống')).toBe('Hệ thống')
  })
  it('does not turn redacted change markers into bank data', () => {
    expect(historyChangeLines({ ...base, changes: { phone: {}, customer_group_code: ['STANDARD', 'VIP1'] } }, 'Đã thay đổi'))
      .toEqual(['phone: Đã thay đổi', 'customer_group_code: STANDARD → VIP1'])
    const outdated = { ...base, changes: { phone: { changed: true } } as never }
    expect(() => historyChangeLines(outdated, 'Đã thay đổi')).toThrow('HISTORY_COMPACT_CHANGES_INVALID')
    expect(historyChangeDisplay(outdated, 'Đã thay đổi')).toEqual({ lines: [], invalid: true })
    expect(historyChangeDisplay({ ...base, changes: { status: ['DRAFT', 'ACTIVE'], phone: {} } }, 'Đã thay đổi'))
      .toEqual({ lines: ['status: DRAFT → ACTIVE', 'phone: Đã thay đổi'], invalid: false })
    expect(historyChangeDisplay({ ...base, changes: { status: [{ secret: 'NEVER_DISPLAY' }, 'ACTIVE'] } as never }, 'Đã thay đổi'))
      .toEqual({ lines: [], invalid: true })
    expect(historyChangeDisplay({ ...base, changes: null }, 'Đã thay đổi'))
      .toEqual({ lines: [], invalid: false })
  })
})
