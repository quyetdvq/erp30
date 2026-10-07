import { describe, expect, it } from 'vitest'

import { useMutationState } from '@/composables/useMutationState'
import { useQueryState } from '@/composables/useQueryState'

function deferred<T>(): {
  promise: Promise<T>
  resolve: (value: T) => void
  reject: (error: unknown) => void
} {
  let resolve!: (value: T) => void
  let reject!: (error: unknown) => void
  const promise = new Promise<T>((promiseResolve, promiseReject) => {
    resolve = promiseResolve
    reject = promiseReject
  })
  return { promise, resolve, reject }
}

describe('shared query and mutation state', () => {
  it('keeps valid data during a failed refresh and distinguishes empty results', async () => {
    let calls = 0
    const query = useQueryState({
      request: async () => {
        calls += 1
        if (calls === 1) return [{ id: '9007199254740993', amount: '0.10' }]
        throw new Error('temporary outage')
      },
    })

    expect(query.status.value).toBe('idle')
    await query.execute()
    expect(query.status.value).toBe('loaded')
    expect(query.data.value).toEqual([{ id: '9007199254740993', amount: '0.10' }])

    await expect(query.refresh()).rejects.toThrow('temporary outage')
    expect(query.status.value).toBe('error')
    expect(query.hasData.value).toBe(true)
    expect(query.data.value?.[0]?.id).toBe('9007199254740993')

    const empty = useQueryState({ request: async () => [] as string[] })
    await empty.execute()
    expect(empty.status.value).toBe('empty')
    expect(empty.isEmpty.value).toBe(true)
  })

  it('ignores stale query results and exposes mutation transitions', async () => {
    const first = deferred<string[]>()
    const second = deferred<string[]>()
    let call = 0
    const query = useQueryState({
      request: () => {
        call += 1
        return call === 1 ? first.promise : second.promise
      },
    })
    const firstRun = query.execute()
    const secondRun = query.refresh()
    second.resolve(['latest'])
    await secondRun
    first.resolve(['stale'])
    await firstRun
    expect(query.data.value).toEqual(['latest'])

    const mutation = useMutationState(async (input: { id: string }) => ({
      id: input.id,
      amount: '1.25',
    }))
    expect(mutation.status.value).toBe('idle')
    await mutation.execute({ id: '9007199254740993' })
    expect(mutation.status.value).toBe('success')
    expect(mutation.data.value).toEqual({ id: '9007199254740993', amount: '1.25' })
  })

  it('retains mutation error state until reset', async () => {
    const mutation = useMutationState(async () => {
      throw new Error('validation failed')
    })
    await expect(mutation.execute(undefined)).rejects.toThrow('validation failed')
    expect(mutation.status.value).toBe('error')
    expect(mutation.error.value).toBeInstanceOf(Error)
    mutation.reset()
    expect(mutation.status.value).toBe('idle')
    expect(mutation.error.value).toBeNull()
  })
})
