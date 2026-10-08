<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
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
import {
  changeSecurityUserState,
  getGroupMembers,
  getSecurityUserPermissions,
  listAuthorizationGroupPage,
  listResourceActionCatalog,
  listResourcePage,
  listSecurityUserPage,
  resetSecurityUserPassword,
  saveGroupMembers,
  saveSecurityUser,
  saveSecurityUserPermissions,
  type SecurityAction,
  type SecurityMember,
  type UserState,
} from '@/services/security'
import { reportApiError } from '@/services/errorBoundary'
import type { ManagedUser } from '@/services/admin'
import type { AuthorizationGroup, Resource } from '@/api/generated/types.gen'

const { t } = useI18n()
const { hasBasePermission, refreshBasePermissions } = useBasePermission()
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
const form = ref({ username: '', email: '', password: '', state: 'ACTIVE' as UserState })
const resetPassword = ref('')
const groups = ref<AuthorizationGroup[]>([])
const resources = ref<Resource[]>([])
const resourceActions = ref<Record<string, SecurityAction[]>>({})
const permissionCatalogLoaded = ref(false)
const permissionCatalogLoading = ref(false)
const membersByGroup = ref<Record<string, SecurityMember[]>>({})
const originalMembersByGroup = ref<Record<string, string[]>>({})
const permissionDraft = ref<string[]>([])
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
const selectedGroups = computed(() =>
  groups.value.filter((group) =>
    (membersByGroup.value[String(group.id)] ?? []).some(
      (member) => String(member.id) === selectedId.value,
    ),
  ),
)

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
async function loadDetail(): Promise<void> {
  if (!selected.value) return
  try {
    const [groupPage, resourcePage, direct] = await Promise.all([
      listAuthorizationGroupPage({ q: '', page: 1, page_size: 200, sort: 'name', filter: {} }),
      listResourcePage({ q: '', page: 1, page_size: 200, sort: 'type', filter: {} }),
      getSecurityUserPermissions(String(selected.value.id)),
    ])
    groups.value = groupPage.data
    resources.value = resourcePage.data
    permissionDraft.value = direct
    const entries = await Promise.all(
      groups.value.map(
        async (group) => [String(group.id), await getGroupMembers(String(group.id))] as const,
      ),
    )
    membersByGroup.value = Object.fromEntries(entries)
    originalMembersByGroup.value = Object.fromEntries(
      entries.map(([id, members]) => [id, members.map((member) => String(member.id))]),
    )
  } catch (caught: unknown) {
    error.value = caught
    reportApiError(caught)
  }
}
async function loadPermissionCatalog(): Promise<void> {
  if (permissionCatalogLoaded.value || permissionCatalogLoading.value) return
  permissionCatalogLoading.value = true
  try {
    const catalog = await listResourceActionCatalog()
    resourceActions.value = catalog.reduce<Record<string, SecurityAction[]>>((result, action) => {
      const resourceId = String(action.resource_id)
      ;(result[resourceId] ??= []).push(action)
      return result
    }, {})
    permissionCatalogLoaded.value = true
  } catch (caught: unknown) {
    error.value = caught
    reportApiError(caught)
  } finally {
    permissionCatalogLoading.value = false
  }
}
function permissionKey(resource: Resource, action: SecurityAction): string {
  return `${resource.type}.${action.code}`.toLowerCase()
}
function selectTab(nextTab: 'overview' | 'groups' | 'permissions'): void {
  tab.value = nextTab
  if (nextTab === 'permissions') void loadPermissionCatalog()
}
function openDetails(row: ManagedUser): void {
  selectedId.value = String(row.id)
  tab.value = 'overview'
  detailOpen.value = true
  void loadDetail()
}
function openCreate(): void {
  editingId.value = undefined
  form.value = { username: '', email: '', password: '', state: 'ACTIVE' }
  formOpen.value = true
}
function openEdit(row?: ManagedUser): void {
  if (row) selectedId.value = String(row.id)
  if (!selected.value) return
  editingId.value = String(selected.value.id)
  form.value = {
    username: selected.value.username,
    email: selected.value.email ?? '',
    password: '',
    state: (selected.value.status as UserState) || (selected.value.active ? 'ACTIVE' : 'INACTIVE'),
  }
  detailOpen.value = false
  formOpen.value = true
}
async function saveIdentity(): Promise<void> {
  busy.value = true
  error.value = null
  try {
    const saved = editingId.value
      ? await saveSecurityUser(
          { username: form.value.username, email: form.value.email },
          editingId.value,
        )
      : await saveSecurityUser({
          username: form.value.username,
          email: form.value.email,
          password: form.value.password,
          state: form.value.state,
        })
    formOpen.value = false
    selectedId.value = String(saved.id)
    await load()
  } catch (caught: unknown) {
    error.value = caught
    reportApiError(caught)
  } finally {
    busy.value = false
  }
}
async function setState(row?: ManagedUser): Promise<void> {
  const target = row ?? selected.value
  if (!target) return
  busy.value = true
  try {
    await changeSecurityUserState(String(target.id), target.active ? 'INACTIVE' : 'ACTIVE')
    await load()
    if (detailOpen.value) await loadDetail()
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
  if (!selected.value) return
  const members = membersByGroup.value[groupId] ?? []
  if (selectedGroups.value.some((group) => String(group.id) === groupId))
    membersByGroup.value[groupId] = members.filter(
      (member) => String(member.id) !== String(selected.value?.id),
    )
  else
    membersByGroup.value[groupId] = [
      ...members,
      {
        id: String(selected.value.id),
        username: selected.value.username,
        active: selected.value.active,
        status: selected.value.status,
      },
    ]
}
async function saveMembership(): Promise<void> {
  if (!selected.value) return
  busy.value = true
  try {
    for (const group of groups.value) {
      const groupId = String(group.id)
      const nextIds = [
        ...new Set((membersByGroup.value[groupId] ?? []).map((member) => String(member.id))),
      ].sort()
      const previousIds = [...(originalMembersByGroup.value[groupId] ?? [])].sort()
      if (nextIds.join(',') !== previousIds.join(','))
        membersByGroup.value[groupId] = await saveGroupMembers(groupId, nextIds)
    }
    permissionCatalogLoaded.value = false
    await refreshBasePermissions()
    await loadDetail()
    await load()
  } catch (caught: unknown) {
    error.value = caught
    reportApiError(caught)
  } finally {
    busy.value = false
  }
}
async function savePermissions(): Promise<void> {
  if (!selected.value) return
  busy.value = true
  try {
    permissionDraft.value = await saveSecurityUserPermissions(
      String(selected.value.id),
      permissionDraft.value,
    )
    permissionCatalogLoaded.value = false
    await refreshBasePermissions()
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
    <button
      v-if="hasBasePermission('user', 'create')"
      type="button"
      class="primary-button"
      :disabled="busy"
      @click="openCreate"
    >
      ＋ {{ t('common.create') }}
    </button>
  </div>
  <p v-if="error" class="inline-error" role="alert">{{ t('security.requestError') }}</p>
  <section class="content-panel security-list-panel">
    <div class="security-toolbar">
      <ListQueryControls
        :model-value="state.query"
        :filters="filters"
        :sort-options="sortOptions"
        :disabled="busy"
        @apply="applyQuery"
        @reset="resetQuery"
      />
    </div>
    <ListShell
      :initial-loading="state.isInitialLoading.value"
      :refreshing="state.isBackgroundRefreshing.value"
      :has-data="state.queryState.hasData.value"
      :empty-dataset="state.isEmptyDataset.value"
      :empty-filtered-result="state.isEmptyFilteredResult.value"
      :error="state.queryState.error.value"
      ><ListTable
        :rows="state.rows.value"
        :columns="columns"
        :sort="state.query.sort"
        @sort-change="
          (sort) => {
            state.setSort(sort)
            void load()
          }
        "
        ><template #cell-username="{ row }"
          ><strong>{{ row.username || t('list.noValue') }}</strong></template
        ><template #cell-email="{ row }">{{ row.email || t('list.noValue') }}</template
        ><template #cell-name="{ row }">{{ row.name || t('list.noValue') }}</template
        ><template #cell-status="{ row }"
          ><TableStatus :status="row.status || row.active" /></template
        ><template #cell-groups="{ row }"
          ><span class="group-summary">{{ groupLabel(row) }}</span></template
        ><template #cell-actions="{ row }"
          ><TableRowActions
            :active="row.active"
            :busy="busy"
            :show-view="true"
            :show-edit="true"
            :show-toggle="true"
            view-permission="user.view"
            edit-permission="user.edit"
            toggle-permission="user.setstate"
            :view-label="t('security.viewDetails')"
            :edit-label="t('common.edit')"
            :activate-label="t('common.activate')"
            :deactivate-label="t('security.inactivate')"
            @view="openDetails(row)"
            @edit="openEdit(row)"
            @toggle="void setState(row)" /></template></ListTable
      ><ListPagination
        :page="state.query.page"
        :page-size="state.query.page_size"
        :total="state.total.value"
        @update:page="
          (page) => {
            state.setPage(page)
            void load()
          }
        "
        @update:page-size="
          (size) => {
            state.setPageSize(size)
            void load()
          }
        "
    /></ListShell>
  </section>
  <div v-if="detailOpen && selected" class="modal-backdrop" @click.self="detailOpen = false">
    <section
      class="modal-card modal-card--wide"
      role="dialog"
      aria-modal="true"
      :aria-label="`${t('users.detailTitle')}: ${selected.username}`"
    >
      <header class="modal-card__header security-detail-heading">
        <div>
          <h2>{{ selected.username }}</h2>
          <small>{{ selected.email }}</small>
        </div>
        <div class="detail-heading-actions">
          <TableStatus :status="selected.status || selected.active" /><button
            type="button"
            class="icon-button"
            :aria-label="t('common.close')"
            @click="detailOpen = false"
          >
            ×
          </button>
        </div>
      </header>
      <nav class="security-tabs" :aria-label="t('security.userDetailTabs')">
        <button
          v-for="item in ['overview', 'groups', 'permissions'] as const"
          :key="item"
          type="button"
          :class="{ active: tab === item }"
          @click="selectTab(item)"
        >
          {{ t(`security.userTabs.${item}`) }}
        </button>
      </nav>
      <div v-if="tab === 'overview'" class="modal-card__body security-editor">
        <p>{{ t('security.userIdentitySourceNote') }}</p>
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
        <p class="security-note">{{ t('security.memberSourceNote') }}</p>
        <label v-for="group in groups" :key="group.id" class="check-row"
          ><input
            type="checkbox"
            :checked="selectedGroups.some((item) => String(item.id) === String(group.id))"
            :disabled="busy || !hasBasePermission('authorization_group', 'assign_member')"
            @change="toggleGroup(String(group.id))"
          /><span>{{ group.name }} ({{ group.code }})</span></label
        >
      </div>
      <div v-else class="modal-card__body security-editor">
        <p class="security-note">{{ t('security.permissionSourceNote') }}</p>
        <div class="permission-resource-list">
          <details v-for="resource in resources" :key="resource.id" class="permission-resource">
            <summary class="permission-resource__summary">
              <span>{{ resource.name || resource.type }}</span>
              <code>{{ resource.type }}</code>
            </summary>
            <div v-if="permissionCatalogLoading" class="security-note" role="status">
              {{ t('common.loading') }}
            </div>
            <div v-else class="permission-grid">
              <label
                v-for="action in resourceActions[String(resource.id)] ?? []"
                :key="action.id"
                class="check-row"
              >
                <input
                  v-model="permissionDraft"
                  type="checkbox"
                  :value="permissionKey(resource, action)"
                  :disabled="busy || !hasBasePermission('user', 'assign_permission')"
                />
                <code>{{ permissionKey(resource, action) }}</code>
              </label>
              <p
                v-if="
                  Object.prototype.hasOwnProperty.call(resourceActions, String(resource.id)) &&
                  !(resourceActions[String(resource.id)] ?? []).length
                "
                class="security-note"
              >
                {{ t('list.noValue') }}
              </p>
            </div>
          </details>
        </div>
      </div>
      <footer class="modal-card__footer">
        <template v-if="tab === 'overview'">
          <button
            v-if="hasBasePermission('user', 'edit')"
            type="button"
            class="secondary-button"
            :disabled="busy"
            @click="openEdit()"
          >
            {{ t('common.edit') }}
          </button>
          <button
            v-if="hasBasePermission('user', 'setstate')"
            type="button"
            class="secondary-button"
            :disabled="busy"
            @click="void setState()"
          >
            {{ selected.active ? t('security.inactivate') : t('security.activate') }}
          </button>
          <button
            v-if="hasBasePermission('user', 'reset_password')"
            type="button"
            class="secondary-button"
            :disabled="busy"
            @click="passwordOpen = true"
          >
            {{ t('security.resetPassword') }}
          </button>
        </template>
        <button
          v-else-if="tab === 'groups'"
          type="button"
          class="primary-button"
          :disabled="busy || !hasBasePermission('authorization_group', 'assign_member')"
          @click="void saveMembership"
        >
          {{ busy ? t('common.saving') : t('security.saveMembers') }}
        </button>
        <button
          v-else
          type="button"
          class="primary-button"
          :disabled="busy || !hasBasePermission('user', 'assign_permission')"
          @click="void savePermissions"
        >
          {{ busy ? t('common.saving') : t('security.savePermissions') }}
        </button>
      </footer>
    </section>
  </div>
  <div v-if="formOpen" class="modal-backdrop" @click.self="formOpen = false">
    <section
      class="modal-card"
      role="dialog"
      aria-modal="true"
      :aria-label="t('security.userForm')"
    >
      <header class="modal-card__header">
        <h2>{{ editingId ? t('common.edit') : t('common.create') }} {{ t('users.user') }}</h2>
        <button
          type="button"
          class="icon-button"
          :aria-label="t('common.close')"
          @click="formOpen = false"
        >
          ×
        </button>
      </header>
      <form class="modal-card__body" @submit.prevent="void saveIdentity">
        <label
          ><span>{{ t('users.username') }}</span
          ><input v-model="form.username" class="field-input" required /></label
        ><label
          ><span>{{ t('users.email') }}</span
          ><input v-model="form.email" class="field-input" type="email" required /></label
        ><label v-if="!editingId"
          ><span>{{ t('security.password') }}</span
          ><input
            v-model="form.password"
            class="field-input"
            type="password"
            required
            autocomplete="new-password" /></label
        ><label v-if="!editingId"
          ><span>{{ t('users.status') }}</span
          ><select v-model="form.state" class="field-select">
            <option v-for="item in userStates" :key="item" :value="item">
              {{ t(`status.${item.toLowerCase()}`) }}
            </option>
          </select></label
        >
        <footer class="modal-card__footer">
          <button type="button" class="secondary-button" :disabled="busy" @click="formOpen = false">
            {{ t('common.cancel') }}
          </button>
          <button type="submit" class="primary-button" :disabled="busy">
            {{ busy ? t('common.saving') : t('common.save') }}
          </button>
        </footer>
      </form>
    </section>
  </div>
  <div v-if="passwordOpen" class="modal-backdrop" @click.self="passwordOpen = false">
    <section
      class="modal-card"
      role="dialog"
      aria-modal="true"
      :aria-label="t('security.resetPassword')"
    >
      <header class="modal-card__header">
        <h2>{{ t('security.resetPassword') }}</h2>
        <button
          type="button"
          class="icon-button"
          :aria-label="t('common.close')"
          @click="passwordOpen = false"
        >
          ×
        </button>
      </header>
      <form class="modal-card__body" @submit.prevent="void savePassword">
        <label
          ><span>{{ t('security.newPassword') }}</span
          ><input
            v-model="resetPassword"
            class="field-input"
            type="password"
            required
            autocomplete="new-password"
        /></label>
        <p class="security-note">{{ t('security.passwordResetNote') }}</p>
        <footer class="modal-card__footer">
          <button
            type="button"
            class="secondary-button"
            :disabled="busy"
            @click="passwordOpen = false"
          >
            {{ t('common.cancel') }}
          </button>
          <button type="submit" class="primary-button" :disabled="busy">
            {{ busy ? t('common.saving') : t('security.resetPassword') }}
          </button>
        </footer>
      </form>
    </section>
  </div>
</template>

<style scoped>
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
.security-detail-heading {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  align-items: start;
}
.security-detail-heading h2 {
  margin: 0;
  color: #153956;
  font-size: 18px;
}
.security-detail-heading small {
  display: block;
  margin-top: 5px;
  color: #536b7b;
}
.detail-heading-actions,
.detail-actions {
  display: flex;
  gap: 8px;
  align-items: center;
  flex-wrap: wrap;
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
  padding: 6px 0;
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
.permission-resource > .security-note {
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
.modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 40;
  display: grid;
  place-items: center;
  padding: 20px;
  background: rgb(18 38 54 / 45%);
}
.modal-card {
  width: min(100%, 620px);
  max-height: calc(100vh - 40px);
  overflow: auto;
  border-radius: 7px;
  background: #fff;
  box-shadow: 0 20px 60px rgb(18 42 62 / 24%);
}
.modal-card--wide {
  width: min(100%, 900px);
}
.modal-card__header,
.modal-card__footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 15px 18px;
  border-bottom: 1px solid #e4ebef;
}
.modal-card__footer {
  justify-content: flex-end;
  border-top: 1px solid #e4ebef;
  border-bottom: 0;
}
.modal-card__body > .modal-card__footer {
  margin: 5px -18px -18px;
}
.modal-card__header h2 {
  margin: 0;
  color: #173c59;
  font-size: 18px;
}
.modal-card__body {
  display: grid;
  gap: 12px;
  padding: 18px;
}
.modal-card__body label {
  display: grid;
  gap: 5px;
  color: #536b7b;
  font-size: 12px;
  font-weight: 600;
}
.modal-card__body input,
.modal-card__body select {
  min-height: 34px;
  border: 1px solid #c7d6df;
  border-radius: 4px;
  padding: 7px 9px;
  color: #183b57;
  font: inherit;
}
.icon-button {
  border: 0;
  background: transparent;
  color: #536b7b;
  font-size: 22px;
  cursor: pointer;
}
@media (max-width: 700px) {
  .security-list-panel {
    padding: 15px 12px;
  }
  .identity-summary {
    grid-template-columns: 1fr;
    gap: 2px;
  }
  .modal-backdrop {
    padding: 10px;
  }
}
</style>
