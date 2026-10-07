import { computed, ref, shallowRef, type Ref } from 'vue'

export type MutationStatus = 'idle' | 'pending' | 'success' | 'error'

export type MutationState<TInput, TOutput> = {
  status: Readonly<Ref<MutationStatus>>
  data: Readonly<Ref<TOutput | null>>
  error: Readonly<Ref<unknown | null>>
  isPending: Readonly<Ref<boolean>>
  execute: (input: TInput) => Promise<TOutput>
  reset: () => void
}

export function useMutationState<TInput, TOutput>(
  request: (input: TInput) => Promise<TOutput>,
): MutationState<TInput, TOutput> {
  const status = ref<MutationStatus>('idle')
  const data = shallowRef<TOutput | null>(null)
  const error = ref<unknown | null>(null)
  const isPending = computed(() => status.value === 'pending')
  let requestVersion = 0

  async function execute(input: TInput): Promise<TOutput> {
    const version = ++requestVersion
    status.value = 'pending'
    error.value = null
    try {
      const result = await request(input)
      if (version === requestVersion) {
        data.value = result
        status.value = 'success'
      }
      return result
    } catch (requestError: unknown) {
      if (version === requestVersion) {
        error.value = requestError
        status.value = 'error'
      }
      throw requestError
    }
  }

  function reset(): void {
    requestVersion += 1
    status.value = 'idle'
    data.value = null
    error.value = null
  }

  return { status, data, error, isPending, execute, reset }
}
