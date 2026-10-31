import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { NumberingSeriesCreate } from '@/api/generated/types.gen'

const { sdkValidate } = vi.hoisted(() => ({ sdkValidate: vi.fn() }))
vi.mock('@/api/generated/sdk.gen', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/api/generated/sdk.gen')>()),
  validateNumberingSeriesConfig: sdkValidate,
}))

import { validateNumberingSeries } from '@/services/autoNumbering'

const createPayload: NumberingSeriesCreate = {
  series_code: 'DEFAULT',
  name: 'Default Product Number',
  scope_type: 'GROUP',
  pattern: 'PRD-{SEQ:6}',
  reset_policy: 'NEVER',
  generation_mode: 'AUTO_IF_EMPTY',
  start_number: '1',
  increment_by: 1,
  is_default: true,
  status: 'ACTIVE',
}
const success = {
  data: {
    data: { valid: true, normalized_pattern: 'PRD-{SEQ:6}', validation_messages: [] },
  },
}

describe('Numbering validate-config create and edit context', () => {
  beforeEach(() => {
    sdkValidate.mockReset()
    sdkValidate.mockResolvedValue(success)
  })

  it('does not exclude any persisted series while previewing a new series', async () => {
    const result = await validateNumberingSeries('7', createPayload)
    expect(result.valid).toBe(true)
    expect(sdkValidate).toHaveBeenCalledOnce()
    const request = sdkValidate.mock.calls[0]![0]
    expect(request.path).toEqual({ resource_id: '7' })
    expect(request.body).toEqual(createPayload)
    expect(request.body).not.toHaveProperty('series_id')
    expect(request.throwOnError).toBe(true)
  })

  it('adds only the existing series ID to edit preview without mutating form data', async () => {
    await validateNumberingSeries('7', createPayload, '42')
    expect(sdkValidate).toHaveBeenCalledOnce()
    const request = sdkValidate.mock.calls[0]![0]
    expect(request.path).toEqual({ resource_id: '7' })
    expect(request.body).toEqual({ ...createPayload, series_id: '42' })
    expect(createPayload).not.toHaveProperty('series_id')
    expect(request.throwOnError).toBe(true)
  })

  it('keeps server authorization or duplicate errors fail-closed', async () => {
    sdkValidate.mockRejectedValueOnce({ response: { status: 403 } })
    await expect(validateNumberingSeries('7', createPayload, '42'))
      .rejects.toMatchObject({ response: { status: 403 } })
    sdkValidate.mockRejectedValueOnce({ response: { status: 409, data: { error: { code: 'NUM_SERIES_DUPLICATE' } } } })
    await expect(validateNumberingSeries('7', createPayload, '42'))
      .rejects.toMatchObject({ response: { status: 409 } })
  })
})
