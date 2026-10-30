<script setup lang="ts">
import { rowAllowedActions } from '@/components/lists/rowActionPolicy'
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  ListPagination,
  ListQueryControls,
  ListShell,
  ListTable,
  TableRowActions,
  TableStatus,
} from '@/components/lists'
import type {
  ListColumn,
  ListFilterDefinition,
  ListQuery,
  ListSortOption,
} from '@/components/lists/listTypes'
import { useListState } from '@/composables/useListState'
import { useBasePermission } from '@/composables/useBasePermission'
import SearchableSelect from '@/components/forms/SearchableSelect.vue'
import UserAvatarManager from '@/components/users/UserAvatarManager.vue'
import { uploadUserAvatar } from '@/services/userAvatar'
import { avatarSelectionIssue } from '@/services/userAvatarRules'
import { useAuthStore } from '@/stores/auth'
import { changedMembershipGroupIds, mergeMembershipForUser } from '@/features/security/user-editor/userMembership'
import {
  buildCreateUserPayload,
  buildEditUserPayload,
  fullNameIssue,
  selectableGroupOptions,
  submitCreatedUserAvatar,
  type UserCreateDraft,
  type UserEditOriginal,
} from '@/features/security/user-editor/userCreateFlow'
import {
  changeSecurityUserState,
  getGroupMembers,
  getSecurityUserPermissions,
  listAuthorizationGroupPage,
  listResourcePage,
  listSecurityUserPage,
  resetSecurityUserPassword,
  saveGroupMembers,
  saveSecurityUser,
  saveSecurityUserPermissions,
  type SecurityActionSummary,
  type UserState,
} from '@/services/security'
import { reportApiError } from '@/services/errorBoundary'
import { loadLookupPages } from '@/services/listLookups'
import type { ManagedUser } from '@/services/security'
import type { AuthorizationGroup, Resource } from '@/api/generated/types.gen'

const { t } = useI18n()
const { hasBasePermission, refreshBasePermissions } = useBasePermission()
const auth = useAuthStore()
const state = useListState<ManagedUser>((query) => listSecurityUserPage(query), {
  sort: 'username',
})
const selectedId = ref<string | null>(null)
const selected = computed(
  () => state.rows.value.find((row) => String(row.id) === selectedId.value) ?? null,
)
const detailOpen = ref(false)
const tab = ref<'overview' | 'groups' | 'permissions'>('overview')
const formOpen = ref(false)
const passwordOpen = ref(false)
const editingId = ref<string | undefined>()
const busy = ref(false)
const error = ref<unknown | null>(null)
const form = ref<UserCreateDraft>({ username: '', name: '', email: '', password: '', state: 'ACTIVE', group_ids: [] })
const editOriginal = ref<UserEditOriginal | null>(null)
const createdFlow = reactive<{ createdUserId: string | null }>({ createdUserId: null })
const avatarPartialError = ref(false)
const createAvatarFile = ref<File | null>(null)
const createAvatarUrl = ref<string | null>(null)
const createAvatarIssue = ref<'type' | 'size' | null>(null)
const createAvatarInput = ref<HTMLInputElement | null>(null)
function clearCreateAvatar(): void {
  if (createAvatarUrl.value) URL.revokeObjectURL(createAvatarUrl.value)
  createAvatarUrl.value = null
  createAvatarFile.value = null
  createAvatarIssue.value = null
  avatarPartialError.value = false
  if (createAvatarInput.value) createAvatarInput.value.value = ''
}
function chooseCreateAvatar(event: Event): void {
  const file = (event.target as HTMLInputElement).files?.[0] ?? null
  clearCreateAvatar()
  if (!file) return
  createAvatarIssue.value = avatarSelectionIssue(file)
  if (createAvatarIssue.value) return
  createAvatarFile.value = file
  createAvatarUrl.value = URL.createObjectURL(file)
}
onBeforeUnmount(() => {
  clearCreateAvatar()
  ++groupLookupVersion
  ++detailVersion
})

const formNameError = ref<'required' | 'too_long' | null>(null)
const groupOptions = ref<Array<{ value: string; label: string }>>([])
const groupLookupState = ref<'idle' | 'loading' | 'ready' | 'error'>('idle')
const canPickCreateGroups = computed(() =>
  hasBasePermission('authorization_group', 'view') &&
  hasBasePermission('authorization_group', 'assign_member'),
)
let groupLookupVersion = 0
async function loadCreateGroups(): Promise<void> {
  if (!canPickCreateGroups.value || !formOpen.value || editingId.value) return
  const version = ++groupLookupVersion
  const generation = auth.getSessionGeneration()
  groupLookupState.value = 'loading'
  try {
    const page = await loadLookupPages(listAuthorizationGroupPage, { sort: 'name' })
    if (version !== groupLookupVersion || generation !== auth.getSessionGeneration()) return
    groupOptions.value = selectableGroupOptions(page)
    groupLookupState.value = 'ready'
  } catch (caught: unknown) {
    if (version !== groupLookupVersion || generation !== auth.getSessionGeneration()) return
    groupLookupState.value = 'error'
    reportApiError(caught)
  }
}
function closeForm(): void {
  if (busy.value) return
  ++groupLookupVersion
  clearCreateAvatar()
  formOpen.value = false
}

const resetPassword = ref('')
const groups = ref<AuthorizationGroup[]>([])
const resources = ref<Resource[]>([])
const resourceActions = ref<Record<string, SecurityActionSummary[]>>({})
const permissionCatalogLoaded = ref(false)
const permissionCatalogLoading = ref(false)
const groupDraftIds = ref<string[]>([])
const originalGroupIds = ref<string[]>([])
const groupDetailState = ref<'idle' | 'loading' | 'ready' | 'error'>('idle')
const groupMembershipConflict = ref(false)
const permissionDraft = ref<string[]>([])
const directPermissionState = ref<'idle' | 'loading' | 'ready' | 'error'>('idle')
// Invalidate in-flight results when the dialog closes or switches User.
let detailVersion = 0
const userStates: UserState[] = ['ACTIVE', 'INACTIVE', 'LOCKED', 'SUSPENDED', 'BANNED', 'DISABLED']
const filters: readonly ListFilterDefinition[] = [
  {
    key: 'status',
    labelKey: 'users.status',
    type: 'select',
    options: userStates.map((value) => ({
      value,
      labelKey: `status.${value.toLowerCase()}`,
    })),
  },
]
const sortOptions: readonly ListSortOption[] = [
  { value: 'username', labelKey: 'users.username' },
  { value: 'email', labelKey: 'users.email' },
  { value: 'status', labelKey: 'users.status' },
  { value: 'id', labelKey: 'users.id' },
]
const columns: readonly ListColumn<ManagedUser>[] = [
  { key: 'id', labelKey: 'users.id', sortable: true },
  { key: 'username', labelKey: 'users.username', sortable: true },
  { key: 'name', labelKey: 'users.name', sortable: true },
  { key: 'email', labelKey: 'users.email' },
  { key: 'groups', labelKey: 'users.groups' },
  { key: 'status', labelKey: 'users.status', sortable: true, align: 'center' },
  { key: 'actions', labelKey: 'security.actions', align: 'end' },
]
const changedGroupIds = computed(() => changedMembershipGroupIds(
  originalGroupIds.value,
  groupDraftIds.value,
  groups.value.map((group) => String(group.id)),
))
function sameDetail(version: number, userId: string, generation: number): boolean {
  return detailOpen.value && version === detailVersion &&
    selectedId.value === userId && generation === auth.getSessionGeneration()
}

function applyQuery(query: ListQuery): void {
  state.setSearch(query.q)
  state.setFilters(query.filter)
  state.setSort(query.sort)
  state.setPageSize(query.page_size)
  void load()
}
function resetQuery(): void {
  state.reset()
  void load()
}
async function load(): Promise<void> {
  error.value = null
  try {
    await state.apply()
    if (!selectedId.value && state.rows.value[0]) selectedId.value = String(state.rows.value[0].id)
  } catch (caught: unknown) {
    error.value = caught
    reportApiError(caught)
  }
}
async function loadDetailGroups(): Promise<void> {
  if (!selected.value || !detailOpen.value ||
      groupDetailState.value === 'ready' || groupDetailState.value === 'loading' ||
      !hasBasePermission('authorization_group', 'view')) return
  const version = detailVersion
  const userId = String(selected.value.id)
  const generation = auth.getSessionGeneration()
  groupDetailState.value = 'loading'
  error.value = null
  try {
    // One paginated catalog, never one GET /members per group on detail open.
    const result = await loadLookupPages(listAuthorizationGroupPage, { sort: 'name' })
    if (!sameDetail(version, userId, generation)) return
    groups.value = result
    groupDetailState.value = 'ready'
  } catch (caught: unknown) {
    if (!sameDetail(version, userId, generation)) return
    groupDetailState.value = 'error'
    error.value = caught
    reportApiError(caught)
  }
}
async function loadDirectPermissions(): Promise<void> {
  if (!selected.value || !detailOpen.value ||
      directPermissionState.value === 'ready' || directPermissionState.value === 'loading') return
  const version = detailVersion
  const userId = String(selected.value.id)
  const generation = auth.getSessionGeneration()
  directPermissionState.value = 'loading'
  error.value = null
  try {
    const direct = await getSecurityUserPermissions(userId)
    if (!sameDetail(version, userId, generation)) return
    permissionDraft.value = direct
    directPermissionState.value = 'ready'
  } catch (caught: unknown) {
    if (!sameDetail(version, userId, generation)) return
    directPermissionState.value = 'error'
    error.value = caught
    reportApiError(caught)
  }
}
async function loadPermissionCatalog(): Promise<void> {
  if (!detailOpen.value || permissionCatalogLoaded.value || permissionCatalogLoading.value || !selected.value) return
  const version = detailVersion
  const userId = String(selected.value.id)
  const generation = auth.getSessionGeneration()
  permissionCatalogLoading.value = true
  try {
    const resourcePage = await loadLookupPages((query) => listResourcePage(query, true), {
      sort: 'type',
    })
    if (!sameDetail(version, userId, generation)) return
    resources.value = resourcePage
    resourceActions.value = resourcePage.reduce<Record<string, SecurityActionSummary[]>>(
      (result, resource) => {
        result[String(resource.id)] = (resource.actions ?? []).map((action) => ({
          id: String(action.id),
          code: action.code,
          description: action.description,
        }))
        return result
      },
      {},
    )
    permissionCatalogLoaded.value = true
  } catch (caught: unknown) {
    if (!sameDetail(version, userId, generation)) return
    error.value = caught
    reportApiError(caught)
  } finally {
    if (version === detailVersion) permissionCatalogLoading.value = false
  }
}
function permissionKey(resource: Resource, action: SecurityActionSummary): string {
  return `${resource.type}.${action.code}`.toLowerCase()
}
function selectTab(nextTab: 'overview' | 'groups' | 'permissions'): void {
  tab.value = nextTab
  if (nextTab === 'groups') void loadDetailGroups()
  if (nextTab === 'permissions') {
    void loadDirectPermissions()
    void loadPermissionCatalog()
  }
}
function openDetails(row: ManagedUser): void {
  ++detailVersion
  selectedId.value = String(row.id)
  tab.value = 'overview'
  groups.value = []
  originalGroupIds.value = [...(row.groupIds ?? [])]
  groupDraftIds.value = [...(row.groupIds ?? [])]
  groupDetailState.value = 'idle'
  groupMembershipConflict.value = false
  error.value = null
  permissionDraft.value = []
  directPermissionState.value = 'idle'
  permissionCatalogLoaded.value = false
  permissionCatalogLoading.value = false
  resources.value = []
  resourceActions.value = {}
  detailOpen.value = true
  // Overview is fully backed by the scoped list response; zero detail GETs.
}
function closeDetails(): void {
  if (busy.value) return
  ++detailVersion
  detailOpen.value = false
  groupDetailState.value = 'idle'
  directPermissionState.value = 'idle'
  permissionCatalogLoaded.value = false
  permissionCatalogLoading.value = false
  resources.value = []
  resourceActions.value = {}
}
function openCreate(): void {
  editingId.value = undefined
  form.value = { username: '', name: '', email: '', password: '', state: 'ACTIVE', group_ids: [] }
  editOriginal.value = null
  createdFlow.createdUserId = null
  avatarPartialError.value = false
  clearCreateAvatar()
  formNameError.value = null
  groupOptions.value = []
  groupLookupState.value = 'idle'
  formOpen.value = true
  void loadCreateGroups()
}
function openEdit(row?: ManagedUser): void {
  if (row) selectedId.value = String(row.id)
  if (!selected.value) return
  editingId.value = String(selected.value.id)
  editOriginal.value = {
    username: selected.value.username,
    email: selected.value.email ?? '',
    name: selected.value.name ?? null,
  }
  form.value = {
    username: selected.value.username,
    name: selected.value.name ?? '',
    email: selected.value.email ?? '',
    password: '',
    state: (selected.value.status as UserState) || (selected.value.active ? 'ACTIVE' : 'INACTIVE'),
    group_ids: [],
  }
  formNameError.value = null
  createdFlow.createdUserId = null
  avatarPartialError.value = false
  clearCreateAvatar()
  ++groupLookupVersion
  closeDetails()
  formOpen.value = true
}
async function saveIdentity(): Promise<void> {
  if (busy.value || createAvatarIssue.value) return
  const old = editOriginal.value
  if (!createdFlow.createdUserId) {
    // Legacy accounts with NULL full_name can be edited without setting a name.
    formNameError.value = editingId.value && old?.name === null && !form.value.name.trim()
      ? null
      : fullNameIssue(form.value.name)
    if (formNameError.value) return
  }
  busy.value = true
  avatarPartialError.value = false
  error.value = null
  try {
    let savedId: string
    if (editingId.value && old) {
      const saved = await saveSecurityUser(buildEditUserPayload(old, form.value), editingId.value)
      savedId = String(saved.id)
    } else {
      const result = await submitCreatedUserAvatar(
        createdFlow,
        () => saveSecurityUser(buildCreateUserPayload(form.value)),
        createAvatarFile.value
          ? async (id) => { await uploadUserAvatar(id, createAvatarFile.value!) }
          : null,
      )
      savedId = result.id
    }
    formOpen.value = false
    ++groupLookupVersion
    clearCreateAvatar()
    selectedId.value = savedId
    await load()
  } catch (caught: unknown) {
    avatarPartialError.value = Boolean(createdFlow.createdUserId && createAvatarFile.value)
    error.value = caught
    reportApiError(caught)
  } finally {
    busy.value = false
  }
}
function avatarUpdated(value: boolean): void {
  if (selected.value) selected.value.has_avatar = value
  void load()
}

async function setState(row?: ManagedUser): Promise<void> {
  const target = row ?? selected.value
  if (!target) return
  busy.value = true
  try {
    await changeSecurityUserState(String(target.id), target.active ? 'INACTIVE' : 'ACTIVE')
    await load()
  } catch (caught: unknown) {
    error.value = caught
    reportApiError(caught)
  } finally {
    busy.value = false
  }
}
async function savePassword(): Promise<void> {
  if (!selected.value) return
  busy.value = true
  try {
    await resetSecurityUserPassword(String(selected.value.id), resetPassword.value)
    resetPassword.value = ''
    passwordOpen.value = false
  } catch (caught: unknown) {
    error.value = caught
    reportApiError(caught)
  } finally {
    busy.value = false
  }
}
function toggleGroup(groupId: string): void {
  if (!selected.value || busy.value || groupDetailState.value !== 'ready' ||
      !hasBasePermission('authorization_group', 'assign_member') ||
      !groups.value.some((group) => String(group.id) === groupId)) return
  if (groupDraftIds.value.includes(groupId)) {
    groupDraftIds.value = groupDraftIds.value.filter((id) => id !== groupId)
  } else {
    groupDraftIds.value = [...groupDraftIds.value, groupId]
  }
}
async function saveMembership(): Promise<void> {
  if (!selected.value || busy.value || groupDetailState.value !== 'ready' ||
      !hasBasePermission('authorization_group', 'assign_member')) return
  const userId = String(selected.value.id)
  const changeIds = [...changedGroupIds.value]
  if (!changeIds.length) return
  busy.value = true
  error.value = null
  groupMembershipConflict.value = false
  try {
    for (const groupId of changeIds) {
      // The existing API replaces a WHOLE group. Read only changed groups
      // immediately before PUT to retain other users' memberships.
      const current = await getGroupMembers(groupId)
      const next = mergeMembershipForUser(
        current.map((member) => String(member.id)),
        userId,
        originalGroupIds.value.includes(groupId),
        groupDraftIds.value.includes(groupId),
      )
      await saveGroupMembers(groupId, next)
      // Preserve completed writes if a later group fails; retries won't
      // re-submit earlier groups with an already-stale original snapshot.
      originalGroupIds.value = groupDraftIds.value.includes(groupId)
        ? [...new Set([...originalGroupIds.value, groupId])]
        : originalGroupIds.value.filter((id) => id !== groupId)
    }
    if (userId === String(auth.user?.id ?? '')) await refreshBasePermissions(true)
    await load()
    // The list result is authoritative after a successful save.
    if (selected.value && String(selected.value.id) === userId) {
      originalGroupIds.value = [...(selected.value.groupIds ?? [])]
      groupDraftIds.value = [...(selected.value.groupIds ?? [])]
    }
  } catch (caught: unknown) {
    if (caught instanceof Error && caught.message === 'USER_GROUP_MEMBERSHIP_CHANGED_RELOAD') {
      groupMembershipConflict.value = true
    } else {
      error.value = caught
      reportApiError(caught)
    }
  } finally {
    busy.value = false
  }
}
async function savePermissions(): Promise<void> {
  if (!selected.value || busy.value || directPermissionState.value !== 'ready' ||
      !permissionCatalogLoaded.value || !hasBasePermission('user', 'assign_permission')) return
  busy.value = true
  try {
    permissionDraft.value = await saveSecurityUserPermissions(
      String(selected.value.id),
      permissionDraft.value,
    )
    if (String(selected.value.id) === String(auth.user?.id ?? '')) await refreshBasePermissions(true)
    await load()
  } catch (caught: unknown) {
    error.value = caught
    reportApiError(caught)
  } finally {
    busy.value = false
  }
}
function groupLabel(user: ManagedUser): string {
  return user.groups.join(', ') || t('list.noValue')
}
onMounted(() => void load())
</script>

<template>
  <div class="page-heading">
    <div>
      <h1>{{ t('security.usersTitle') }}</h1>
      <p>{{ t('security.usersDescription') }}</p>
    </div>
    <button v-if="hasBasePermission('user', 'create')" type="button" class="primary-button" :disabled="busy"
      @click="openCreate">
      ＋ {{ t('common.create') }}
    </button>
  </div>
  <p v-if="error" class="inline-error" role="alert">{{ t('security.requestError') }}</p>
  <section class="content-panel security-list-panel">
    <div class="security-toolbar">
      <ListQueryControls :model-value="state.query" :filters="filters" :sort-options="sortOptions" :disabled="busy"
        @apply="applyQuery" @reset="resetQuery" />
    </div>
    <ListShell :initial-loading="state.isInitialLoading.value" :refreshing="state.isBackgroundRefreshing.value"
      :has-data="state.queryState.hasData.value" :empty-dataset="state.isEmptyDataset.value"
      :empty-filtered-result="state.isEmptyFilteredResult.value" :error="state.queryState.error.value">
      <ListTable :rows="state.rows.value" :columns="columns" :sort="state.query.sort" @sort-change="
        (sort) => {
          state.setSort(sort)
          void load()
        }
      "><template #cell-username="{ row }"><strong>{{ row.username || t('list.noValue')
      }}</strong></template><template #cell-email="{ row }">{{ row.email || t('list.noValue')
            }}</template><template #cell-name="{ row }">{{ row.name || t('list.noValue') }}</template><template
          #cell-status="{ row }">
          <TableStatus :status="row.status || row.active" />
        </template><template #cell-groups="{ row }"><span class="group-summary">{{ groupLabel(row)
        }}</span></template><template #cell-actions="{ row }">
          <TableRowActions authorization-mode="effective" :view-scope-verified="true" :allowed-actions="rowAllowedActions(row)" :active="row.active" :busy="busy" :show-view="true" :show-edit="true" :show-toggle="true"
            view-permission="user.view" edit-permission="user.edit" toggle-permission="user.setstate"
            :view-label="t('security.viewDetails')" :edit-label="t('common.edit')"
            :activate-label="t('common.activate')" :deactivate-label="t('security.inactivate')" @view="openDetails(row)"
            @edit="openEdit(row)" @toggle="void setState(row)" />
        </template></ListTable>
      <ListPagination :page="state.query.page" :page-size="state.query.page_size" :total="state.total.value"
        @update:page="
          (page) => {
            state.setPage(page)
            void load()
          }
        " @update:page-size="
          (size) => {
            state.setPageSize(size)
            void load()
          }
        " />
    </ListShell>
  </section>


  <div v-if="detailOpen && selected" class="modal-backdrop">
    <section class="modal-card tabbed-detail-workspace" role="dialog" aria-modal="true"
      :aria-label="`${t('users.detailTitle')}: ${selected.username}`">
      <header class="modal-card__header">
        <h2 class="modal-card-title">
          <NIcon :size="24" aria-hidden="true">
            <PeopleOutline />
          </NIcon>
          {{ selected.username }}

          <TableStatus :status="selected.status || selected.active" />
        </h2>
        <small>{{ selected.email }}</small>

        <button type="button" class="modal-card-button-close" :aria-label="t('common.close')" @click="closeDetails">
        </button>
      </header>

      <nav class="security-tabs" :aria-label="t('security.userDetailTabs')">
        <button v-for="item in ['overview', 'groups', 'permissions'] as const" :key="item" type="button"
          :class="{ active: tab === item }" @click="selectTab(item)">
          {{ t(`security.userTabs.${item}`) }}
        </button>
      </nav>

      <div v-if="tab === 'overview'" class="modal-card__body security-editor">
        <p>{{ t('security.userIdentitySourceNote') }}</p>
        <UserAvatarManager :user-id="String(selected.id)" :has-avatar="selected.has_avatar === true" :avatar-url="selected.avatar_url"
          :label="selected.name || selected.username"
          :can-edit="(hasBasePermission('user', 'edit') && selected.allowed_actions?.edit === true) || String(auth.user?.id) === String(selected.id)"
          @updated="avatarUpdated" />
        <dl class="identity-summary">
          <dt>{{ t('users.id') }}</dt>
          <dd>{{ selected.id }}</dd>
          <dt>{{ t('users.status') }}</dt>
          <dd>{{ selected.status || (selected.active ? 'ACTIVE' : 'INACTIVE') }}</dd>
          <dt>{{ t('security.directPermissionCount') }}</dt>
          <dd>{{ selected.direct_permission_count ?? 0 }}</dd>
        </dl>
      </div>


      <div v-else-if="tab === 'groups'" class="modal-card__body security-editor">
        <p class="security-note">{{ t('security.userMembershipSourceNote') }}</p>
        <p v-if="groupMembershipConflict" class="inline-error" role="alert">
          {{ t('security.userMembershipConflict') }}
        </p>
        <p v-if="groupDetailState === 'loading'" role="status">{{ t('common.loading') }}</p>
        <button v-else-if="groupDetailState === 'error'" type="button" class="secondary-button"
          @click="void loadDetailGroups()">{{ t('security.retryGroupLookup') }}</button>
        <label v-for="group in groups" :key="group.id" class="check-row"><input type="checkbox"
            :checked="groupDraftIds.includes(String(group.id))"
            :disabled="busy || groupDetailState !== 'ready' || !hasBasePermission('authorization_group', 'assign_member')"
            @change="toggleGroup(String(group.id))" /><span>{{ group.name }} ({{ group.code }})</span></label>
      </div>


      <div v-else class="modal-card__body security-editor">
        <p class="security-note">{{ t('security.permissionSourceNote') }}</p>
        <p v-if="directPermissionState === 'loading'" role="status">{{ t('common.loading') }}</p>
        <button v-else-if="directPermissionState === 'error'" type="button" class="secondary-button"
          @click="void loadDirectPermissions()">{{ t('security.retryGroupLookup') }}</button>
        <button v-if="directPermissionState === 'ready' && !permissionCatalogLoaded && !permissionCatalogLoading"
          type="button" class="secondary-button" @click="void loadPermissionCatalog()">
          {{ t('security.retryGroupLookup') }}
        </button>
        <div v-if="directPermissionState === 'ready'" class="permission-resource-list">
          <details v-for="resource in resources" :key="resource.id" class="permission-resource">
            <summary class="permission-resource__summary">
              <span>{{ resource.name || resource.type }}</span>
              <code>{{ resource.type }}</code>
            </summary>
            <div v-if="permissionCatalogLoading" class="security-note" role="status">
              {{ t('common.loading') }}
            </div>
            <div v-else class="permission-grid">
              <label v-for="action in resourceActions[String(resource.id)] ?? []" :key="action.id" class="check-row">
                <input v-model="permissionDraft" type="checkbox" :value="permissionKey(resource, action)"
                  :disabled="busy || !permissionCatalogLoaded || !hasBasePermission('user', 'assign_permission')" />
                <code>{{ permissionKey(resource, action) }}</code>
              </label>
              <p v-if="
                Object.prototype.hasOwnProperty.call(resourceActions, String(resource.id)) &&
                !(resourceActions[String(resource.id)] ?? []).length
              " class="security-note">
                {{ t('list.noValue') }}
              </p>
            </div>
          </details>
        </div>
      </div>


      <footer class="modal-card__footer">
        <template v-if="tab === 'overview'">
          <button v-if="hasBasePermission('user', 'edit') && selected.allowed_actions?.edit === true" type="button" class="secondary-button" :disabled="busy"
            @click="openEdit()">
            <NIcon :size="16" aria-hidden="true">
              <CreateOutline />
            </NIcon>
            {{ t('common.edit') }}
          </button>
          <button v-if="hasBasePermission('user', 'setstate') && selected.allowed_actions?.setstate === true" type="button" class="secondary-button" :disabled="busy"
            @click="void setState()">
            <NIcon :size="16" aria-hidden="true">
              <BanOutline />
            </NIcon>
            {{ selected.active ? t('security.inactivate') : t('security.activate') }}
          </button>

          <button v-if="hasBasePermission('user', 'reset_password')" type="button" class="secondary-button"
            :disabled="busy" @click="passwordOpen = true">
            <NIcon :size="16" aria-hidden="true">
              <Refresh />
            </NIcon>
            {{ t('security.resetPassword') }}
          </button>
        </template>


        <button v-else-if="tab === 'groups'" type="button" class="primary-button"
          :disabled="busy || groupMembershipConflict || groupDetailState !== 'ready' || changedGroupIds.length === 0 || !hasBasePermission('authorization_group', 'assign_member')" @click="void saveMembership()">
          <NIcon :size="16" aria-hidden="true">
            <SaveOutline />
          </NIcon>
          {{ busy ? t('common.saving') : t('security.saveMembers') }}
        </button>


        <button v-else type="button" class="primary-button"
          :disabled="busy || directPermissionState !== 'ready' || !permissionCatalogLoaded || !hasBasePermission('user', 'assign_permission')" @click="void savePermissions()">
          <NIcon :size="16" aria-hidden="true">
            <SaveOutline />
          </NIcon>
          {{ busy ? t('common.saving') : t('security.savePermissions') }}
        </button>
      </footer>


    </section>
  </div>


  <div v-if="formOpen" class="modal-backdrop">
    <section class="modal-card" role="dialog" aria-modal="true" :aria-label="t('security.userForm')">
      <header class="modal-card__header">
        <h2 class="modal-card-title">
          <NIcon :size="24" aria-hidden="true">
            <PeopleOutline />
          </NIcon>
          {{ editingId ? t('common.edit') : t('common.create') }} {{ t('users.user') }}
        </h2>
        <button type="button" class="modal-card-button-close" :aria-label="t('common.close')" @click="closeForm()">
        </button>
      </header>
      <form class="modal-card__body" id="form-user" @submit.prevent="void saveIdentity()">
        <label><span>{{ t('users.username') }}</span><input v-model="form.username" class="field-input"
            :disabled="Boolean(createdFlow.createdUserId)" required /></label>
        <label for="security-user-name"><span>{{ t('users.name') }}</span>
          <input id="security-user-name" v-model="form.name" class="field-input" :required="!editingId"
            :aria-invalid="Boolean(formNameError)" :aria-describedby="formNameError ? 'user-name-error' : undefined"
            :disabled="Boolean(createdFlow.createdUserId)" autocomplete="name" @input="formNameError = null" />
          <small v-if="formNameError" id="user-name-error" class="inline-error" role="alert">{{
            formNameError === 'required' ? t('security.userNameRequired') : t('security.userNameTooLong')
            }}</small>
        </label>
        <label><span>{{ t('users.email') }}</span><input v-model="form.email" class="field-input"
            :disabled="Boolean(createdFlow.createdUserId)" type="email" required /></label><label
          v-if="!editingId"><span>{{ t('security.password') }}</span><input v-model="form.password" class="field-input"
            :disabled="Boolean(createdFlow.createdUserId)" type="password" required
            autocomplete="new-password" /></label><label v-if="!editingId"><span>{{ t('users.status') }}</span><select
            v-model="form.state" class="field-select" :disabled="Boolean(createdFlow.createdUserId)">
            <option v-for="item in userStates" :key="item" :value="item">
              {{ t(`status.${item.toLowerCase()}`) }}
            </option>
          </select></label>
        <div v-if="!editingId" class="security-editor">
          <label for="security-user-groups">{{ t('security.createGroupsOptional') }}</label>
          <template v-if="canPickCreateGroups">
            <SearchableSelect id="security-user-groups" :model-value="form.group_ids" :options="groupOptions" multiple
              clearable :disabled="busy || Boolean(createdFlow.createdUserId) || groupLookupState === 'loading'"
              :placeholder="t('security.groupSearchHint')" :aria-label="t('security.createGroupsOptional')"
              @update:model-value="(values) => form.group_ids = Array.isArray(values) ? values : []" />
            <p v-if="groupLookupState === 'loading'" role="status" class="security-note">{{ t('common.loading') }}</p>
            <p v-if="groupLookupState === 'error'" class="inline-error" role="alert">{{ t('security.groupLookupFailed')
              }}</p>
            <button v-if="groupLookupState === 'error'" type="button" class="secondary-button" :disabled="busy"
              @click="void loadCreateGroups()">{{ t('security.retryGroupLookup') }}</button>
          </template>
          <p v-else class="security-note">{{ t('security.groupAssignmentPermissionHint') }}</p>
          <p class="security-note">{{ t('security.createGroupsNote') }}</p>
        </div>
        <div v-if="!editingId" class="security-editor">
          <label for="security-user-create-avatar">{{ t('security.avatarLabel') }} ({{ t('security.optional')
            }})</label>
          <input id="security-user-create-avatar" ref="createAvatarInput" type="file"
            accept="image/jpeg,image/png,image/webp" :disabled="busy || !hasBasePermission('user', 'edit')"
            @change="chooseCreateAvatar" />
          <img v-if="createAvatarUrl" :src="createAvatarUrl" :alt="t('security.avatarPreview')"
            class="security-avatar-preview" />
          <p v-if="createAvatarIssue" class="inline-error" role="alert">{{
            createAvatarIssue === 'size' ? t('security.avatarInvalidSize') : t('security.avatarInvalidType')
            }}</p>
          <p class="security-note">{{ t('security.avatarLimit') }}</p>
          <p v-if="!hasBasePermission('user', 'edit')" class="security-note">{{ t('security.avatarPermissionNote') }}
          </p>
          <button v-if="createAvatarFile" type="button" class="secondary-button" :disabled="busy"
            @click="clearCreateAvatar">{{ t('security.avatarClearSelection') }}</button>
        </div>
        <p v-if="createdFlow.createdUserId && avatarPartialError" role="alert" class="inline-error">
          {{ t('security.avatarPartialSave', { id: createdFlow.createdUserId }) }}
        </p>
      </form>

      <footer class="modal-card__footer">
        <button type="button" class="secondary-button" :disabled="busy" @click="closeForm()">
          <NIcon :size="16" aria-hidden="true">
            <Refresh />
          </NIcon>
          {{ t('common.cancel') }}
        </button>
        <button type="submit" class="primary-button" form="form-user" :disabled="busy">
          <NIcon :size="16" aria-hidden="true">
            <SaveOutline />
          </NIcon>
          {{ busy ? t('common.saving') : createdFlow.createdUserId && createAvatarFile ? t('security.retryAvatarUpload')
            :
            t('common.save') }}
        </button>
      </footer>
    </section>
  </div>



  <div v-if="passwordOpen" class="modal-backdrop">
    <section class="modal-card" role="dialog" aria-modal="true" :aria-label="t('security.resetPassword')">
      <header class="modal-card__header">
        <h2 class="modal-card-title">

          {{ t('security.resetPassword') }}</h2>
        <button type="button" class="modal-card-button-close" :aria-label="t('common.close')"
          @click="passwordOpen = false">
        </button>
      </header>
      <form class="modal-card__body" id="form-reset-password" @submit.prevent="savePassword">
        <label><span>{{ t('security.newPassword') }}</span><input v-model="resetPassword" class="field-input"
            type="password" required autocomplete="new-password" /></label>
        <p class="security-note">{{ t('security.passwordResetNote') }}</p>

      </form>
      <footer class="modal-card__footer">
        <button type="button" class="secondary-button" :disabled="busy" @click="passwordOpen = false">
          <NIcon :size="16" aria-hidden="true">
            <Refresh />
          </NIcon>
          {{ t('common.cancel') }}
        </button>
        <button type="submit" class="primary-button" form="form-reset-password" :disabled="busy">
          <NIcon :size="16" aria-hidden="true">
            <SaveOutline />
          </NIcon>
          {{ busy ? t('common.saving') : t('security.resetPassword') }}
        </button>
      </footer>
    </section>
  </div>
</template>

<style scoped>
.security-avatar-preview {
  width: 82px;
  height: 82px;
  object-fit: cover;
  border-radius: 50%;
}

.security-list-panel {
  display: grid;
  gap: 14px;
  padding: 18px 20px 20px;
}

.security-toolbar {
  display: flex;
  align-items: end;
  justify-content: space-between;
  gap: 10px;
  flex-wrap: wrap;
}

.group-summary {
  color: #345f77;
  font-size: 12px;
}

.inline-error {
  margin: 0 0 12px;
  color: #b64444;
  font-size: 12px;
}


.security-tabs {
  display: flex;
  gap: 6px;
  border-bottom: 1px solid #e3ebef;
}

.security-tabs button {
  padding: 9px 12px;
  border: 0;
  border-bottom: 2px solid transparent;
  background: transparent;
  color: #60798d;
  font-size: 12px;
  cursor: pointer;
}

.security-tabs button.active,
.security-tabs button:focus-visible {
  border-bottom-color: #0873b8;
  color: #075f9b;
  font-weight: 700;
}

.security-editor {
  display: grid;
  gap: 12px;
}

.security-note {
  color: #587080;
  font-size: 13px;
}

.check-row {
  display: flex;
  gap: 9px;
  align-items: center;
  padding: 0;
}

.permission-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 4px 16px;
  max-height: 360px;
  overflow: auto;
}

.permission-resource-list {
  display: grid;
  gap: 8px;
}

.permission-resource {
  border: 1px solid #dbe5ea;
  border-radius: 5px;
  background: #fbfdfe;
}

.permission-resource__summary {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 12px;
  color: #183b57;
  cursor: pointer;
  font-size: 12px;
  font-weight: 700;
}

.permission-resource__summary code {
  color: #587080;
  font-size: 11px;
  font-weight: 500;
}

.permission-resource[open] .permission-resource__summary {
  border-bottom: 1px solid #dbe5ea;
}

.permission-resource .permission-grid,
.permission-resource>.security-note {
  padding: 10px 12px;
}

.identity-summary {
  display: grid;
  grid-template-columns: 150px 1fr;
  gap: 8px 16px;
  margin: 8px 0;
}

.identity-summary dt {
  font-weight: 600;
  color: #587080;
}

.identity-summary dd {
  margin: 0;
}

.modal-card {

  width: min(100%, 620px);
  max-height: calc(100vh - 40px);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border-radius: 7px;
  background: #fff;
  box-shadow: 0 20px 60px rgb(18 42 62 / 24%);
}

.modal-card__body label {
  gap: 5px;
  color: #536b7b;
  font-size: 12px;
  font-weight: 600;
}

.modal-card__body input,
.modal-card__body select {
  min-height: 24px;
  border: 1px solid #c7d6df;
  border-radius: 4px;
  padding: 7px 9px;
  color: #183b57;
  font: inherit;
}

@media (max-width: 700px) {
  .security-list-panel {
    padding: 15px 12px;
  }

  .identity-summary {
    grid-template-columns: 1fr;
    gap: 2px;
  }
}
</style>
