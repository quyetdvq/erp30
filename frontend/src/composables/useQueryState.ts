import { computed, ref, shallowRef, type Ref } from 'vue'

export type QueryStatus = 'idle' | 'loading' | 'refreshing' | 'loaded' | 'empty' | 'error'

export type QueryState<T> = {
  data: Readonly<Ref<T | null>>
  error: Readonly<Ref<unknown | null>>
  status: Readonly<Ref<QueryStatus>>
  hasData: Readonly<Ref<boolean>>
  isLoading: Readonly<Ref<boolean>>
  isRefreshing: Readonly<Ref<boolean>>
  isEmpty: Readonly<Ref<boolean>>
  execute: () => Promise<T>
  refresh: () => Promise<T>
  reset: () => void
}

export type QueryStateOptions<T> = {
  request: () => Promise<T>
  isEmpty?: (value: T) => boolean
}

function defaultIsEmpty(value: unknown): boolean {
  return Array.isArray(value) && value.length === 0
}

export function useQueryState<T>(options: QueryStateOptions<T>): QueryState<T> {
  const data = shallowRef<T | null>(null)
  const error = shallowRef<unknown | null>(null)
  const status = ref<QueryStatus>('idle')
  const requestVersion = ref(0)
  const hasData = computed(() => data.value !== null)
  const isLoading = computed(() => status.value === 'loading')
  const isRefreshing = computed(() => status.value === 'refreshing')
  const isEmpty = computed(() => status.value === 'empty')

  async function execute(): Promise<T> {
    const version = ++requestVersion.value
    const refreshing = data.value !== null
    status.value = refreshing ? 'refreshing' : 'loading'
    error.value = null
    try {
      const result = await options.request()
      if (version !== requestVersion.value) return result
      data.value = result
      status.value = (options.isEmpty || defaultIsEmpty)(result) ? 'empty' : 'loaded'
      return result
    } catch (requestError: unknown) {
      if (version === requestVersion.value) {
        error.value = requestError
        status.value = 'error'
      }
      throw requestError
    }
  }

  function reset(): void {
    requestVersion.value += 1
    data.value = null
    error.value = null
    status.value = 'idle'
  }

  return {
    data,
    error,
    status,
    hasData,
    isLoading,
    isRefreshing,
    isEmpty,
    execute,
    refresh: execute,
    reset,
  }
}
