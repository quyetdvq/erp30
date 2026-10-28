import { describe, expect, it, vi } from 'vitest'
import {
  buildCreateUserPayload,
  buildEditUserPayload,
  fullNameIssue,
  selectableGroupOptions,
  submitCreatedUserAvatar,
} from '@/features/security/user-editor/userCreateFlow'

describe('Shield User create/edit UX', () => {
  const input = {
    username: 'nguyenvana',
    name: '  Nguyễn Văn A  ',
    email: 'vana@example.test',
    password: 'TestPassword123!',
    state: 'ACTIVE' as const,
    group_ids: ['2', '4'],
  }

  it('requires a Unicode full name in Create but preserves the legacy API contract', () => {
    expect(fullNameIssue('   ')).toBe('required')
    expect(fullNameIssue('😊'.repeat(256))).toBe('too_long')
    expect(buildCreateUserPayload(input).name).toBe('Nguyễn Văn A')
    expect(buildCreateUserPayload({ ...input, group_ids: [] }).group_ids).toEqual([])
  })

  it('only lists active Group IDs, and preserves both selected groups', () => {
    const options = selectableGroupOptions([
      { id: '2', name: 'Security', code: 'ADMIN', status: 'ACTIVE' },
      { id: '4', name: 'Sales', code: 'SALES', status: 'ACTIVE' },
      { id: '8', name: 'Inactive', code: 'OLD', status: 'INACTIVE' },
    ])
    expect(options.map((item) => item.value)).toEqual(['2', '4'])
    expect(buildCreateUserPayload(input).group_ids).toEqual(['2', '4'])
    expect(() => buildCreateUserPayload({ ...input, group_ids: ['2', '2'] })).toThrow('INVALID_GROUP_IDS')
  })

  it('PATCH does not replace memberships and allows old NULL names to remain', () => {
    expect(buildEditUserPayload(
      { username: 'old', email: 'old@example.test', name: null },
      { username: 'new', email: 'old@example.test', name: '' },
    )).toEqual({ username: 'new', email: 'old@example.test' })
    expect(buildEditUserPayload(
      { username: 'old', email: 'old@example.test', name: 'Old Name' },
      { username: 'new', email: 'old@example.test', name: ' New Name ' },
    )).toEqual({ username: 'new', email: 'old@example.test', name: 'New Name' })
  })

  it('when Avatar PUT fails after POST, retry performs PUT only', async () => {
    const context: { createdUserId: string | null } = { createdUserId: null }
    const create = vi.fn<() => Promise<{ id: string }>>().mockResolvedValue({ id: '77' })
    const upload = vi.fn<(id: string) => Promise<void>>().mockRejectedValueOnce(new Error('Forbidden')).mockResolvedValue(undefined)
    await expect(submitCreatedUserAvatar(context, create, upload)).rejects.toThrow('Forbidden')
    expect(context.createdUserId).toBe('77')
    await submitCreatedUserAvatar(context, create, upload)
    expect(create).toHaveBeenCalledTimes(1)
    expect(upload).toHaveBeenCalledTimes(2)
    expect(upload).toHaveBeenCalledWith('77')
  })
})
