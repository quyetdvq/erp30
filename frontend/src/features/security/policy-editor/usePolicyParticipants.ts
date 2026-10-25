import { computed, onBeforeUnmount, ref, type Ref } from 'vue'
import type {
  AuthorizationGroup,
  Department,
  Organization,
  Position,
} from '@/api/generated/types.gen'
import type { ListQuery } from '@/components/lists/listTypes'
import { withTypedClient } from '@/api/client'
import { listDepartments, listOrganizations, listPositions } from '@/api/generated/sdk.gen'
import { i18n } from '@/i18n'
import { listAuthorizationGroupPage, listSecurityUserPage, type ManagedUser } from '@/services/security'
import { reportApiError } from '@/services/errorBoundary'
import type { ParticipantOption, ParticipantType, PolicyDraft } from './types'

type ParticipantLookups = {
  organizations: Ref<Organization[]>
  groups: Ref<AuthorizationGroup[]>
  users: Ref<ManagedUser[]>
  departments: Ref<Department[]>
  positions: Ref<Position[]>
  waitForPendingParticipantCatalog?: (type: ParticipantType) => Promise<void>
}

/** Remote participant options retain the original request-id guard and debounce semantics. */
export function usePolicyParticipants(
  form: Ref<PolicyDraft>,
  error: Ref<unknown | null>,
  { organizations, groups, users, departments, positions, waitForPendingParticipantCatalog }: ParticipantLookups,
) {
  const participantOptions = ref<ParticipantOption[]>([])
  const participantLoading = ref(false)
  let participantRequestId = 0
  let participantSearchTimer: ReturnType<typeof setTimeout> | undefined
  const participantSelectOptions = computed(() =>
    participantOptions.value.map((option) => ({ value: option.id, label: option.label })),
  )
  const isRemoteParticipant = computed(() => true) // GROUP also supports remote search beyond page 1

  function preserveParticipantSelection(): void {
    const id = form.value.participant_ref_id
    if (id && !participantOptions.value.some((option) => option.id === id)) {
      participantOptions.value = [{ id, label: `#${id}` }, ...participantOptions.value]
    }
  }
  async function loadParticipantOptions(
    type: ParticipantType,
    query: string,
    resetSelection = false,
  ): Promise<void> {
    const requestId = ++participantRequestId
    participantLoading.value = true
    const listQuery: ListQuery = { q: query, page: 1, page_size: 50, sort: 'name', filter: {} }
    try {
      if (!query) await waitForPendingParticipantCatalog?.(type)
      if (requestId !== participantRequestId || type !== form.value.participant_type) return
      let options: ParticipantOption[]
      switch (type) {
        case 'USER': {
          if (!query && users.value.length) {
            options = users.value.map((user) => ({
              id: String(user.id),
              label: user.name || user.username,
            }))
          } else {
            const page = await listSecurityUserPage({ ...listQuery, sort: 'username' })
            options = page.data.map((user) => ({
              id: String(user.id),
              label: `${user.username}${user.name ? ` · ${user.name}` : ''}`,
            }))
          }
          break
        }
        case 'POSITION': {
          if (!query && positions.value.length) {
            options = positions.value.map((item) => ({
              id: String(item.id),
              label: `${item.code} · ${item.name}`,
            }))
          } else {
            const response = await withTypedClient((client) =>
              listPositions({
                client,
                headers: { 'Accept-Language': i18n.global.locale.value as 'vi' | 'en' },
                query: { ...listQuery, filter: {} },
                throwOnError: true,
              }),
            )
            options = (response.data.data ?? []).map((item: Position) => ({
              id: String(item.id),
              label: `${item.code} · ${item.name}`,
            }))
          }
          break
        }
        case 'DEPARTMENT': {
          if (!query && departments.value.length) {
            options = departments.value.map((item) => ({
              id: String(item.id),
              label: `${item.code} · ${item.name}`,
            }))
          } else {
            const response = await withTypedClient((client) =>
              listDepartments({
                client,
                headers: { 'Accept-Language': i18n.global.locale.value as 'vi' | 'en' },
                query: { ...listQuery, filter: {} },
                throwOnError: true,
              }),
            )
            options = (response.data.data ?? []).map((item: Department) => ({
              id: String(item.id),
              label: `${item.code} · ${item.name}`,
            }))
          }
          break
        }
        case 'ORGANIZATION': {
          if (!query && organizations.value.length) {
            options = organizations.value.map((item) => ({
              id: String(item.id),
              label: `${item.code} · ${item.name}`,
            }))
          } else {
            const response = await withTypedClient((client) =>
              listOrganizations({
                client,
                headers: { 'Accept-Language': i18n.global.locale.value as 'vi' | 'en' },
                query: { ...listQuery, filter: {} },
                throwOnError: true,
              }),
            )
            options = (response.data.data ?? []).map((item: Organization) => ({
              id: String(item.id),
              label: `${item.code} · ${item.name}`,
            }))
          }
          break
        }
        case 'GROUP': {
          const catalog = !query && groups.value.length
            ? groups.value
            : (await listAuthorizationGroupPage(listQuery)).data
          options = catalog.map((group) => ({
            id: String(group.id),
            label: `${group.code} · ${group.name}`,
          }))
          break
        }
      }
      if (requestId !== participantRequestId || type !== form.value.participant_type) return
      participantOptions.value = options
      if (resetSelection) form.value.participant_ref_id = options[0]?.id ?? ''
      else preserveParticipantSelection()
    } catch (caught: unknown) {
      if (requestId === participantRequestId) {
        error.value = caught
        reportApiError(caught)
      }
    } finally {
      if (requestId === participantRequestId) participantLoading.value = false
    }
  }
  function onParticipantTypeChange(type: ParticipantType): void {
    if (participantSearchTimer) clearTimeout(participantSearchTimer)
    participantSearchTimer = undefined
    form.value.participant_ref_id = ''
    participantOptions.value = []
    void loadParticipantOptions(type, '', true)
  }
  function searchParticipants(query: string): void {
    if (!isRemoteParticipant.value) return
    if (participantSearchTimer) clearTimeout(participantSearchTimer)
    const type = form.value.participant_type
    participantSearchTimer = setTimeout(() => {
      if (form.value.participant_type === type) void loadParticipantOptions(type, query)
    }, 250)
  }

  onBeforeUnmount(() => {
    if (participantSearchTimer) clearTimeout(participantSearchTimer)
  })

  return {
    participantOptions,
    participantLoading,
    participantSelectOptions,
    isRemoteParticipant,
    loadParticipantOptions,
    onParticipantTypeChange,
    searchParticipants,
    currentParticipantRequestId: () => participantRequestId,
  }
}
