<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { AuthorizationGroup, Resource } from '@/api/generated/types.gen'
import {
  ListPagination,
  ListQueryControls,
  ListShell,
  ListTable,
  TableRowActions,
  TableStatus,
} from '@/components/lists'
import type { ListColumn, ListQuery } from '@/components/lists/listTypes'
import { useListState } from '@/composables/useListState'
import {
  changeAuthorizationGroupState,
  getGroupMembers,
  getGroupPermissions,
  getResourceActions,
  listSecurityUserPage,
  listResourcePage,
  listAuthorizationGroupPage,
  saveAuthorizationGroup,
  saveGroupMembers,
  saveGroupPermissions,
  type SecurityAction,
  type SecurityMember,
  type SecurityPermission,
} from '@/services/security'
import { reportApiError } from '@/services/errorBoundary'
import type { ManagedUser } from '@/services/admin'

const { t } = useI18n()
const state = useListState<AuthorizationGroup>((query) => listAuthorizationGroupPage(query), {
  sort: 'name',
})
const selectedId = ref<string | null>(null)
const selected = computed(
  () => state.rows.value.find((row) => String(row.id) === selectedId.value) ?? null,
)
const tab = ref<'overview' | 'members' | 'permissions'>('overview')
const modalOpen = ref(false)
const editingId = ref<string | undefined>()
const busy = ref(false)
const error = ref<unknown | null>(null)
const form = ref({ code: '', name: '', description: '', status: 'ACTIVE' as 'ACTIVE' | 'INACTIVE' })
const members = ref<SecurityMember[]>([])
const permissions = ref<SecurityPermission[]>([])
const users = ref<ManagedUser[]>([])
const allActions = ref<SecurityAction[]>([])
const resources = ref<Resource[]>([])
const memberDraft = ref<string[]>([])
const permissionDraft = ref<string[]>([])
const detailOpen = ref(false)

const columns: readonly ListColumn<AuthorizationGroup>[] = [
  { key: 'code', labelKey: 'security.code', sortable: true },
  { key: 'name', labelKey: 'security.name', sortable: true },
  { key: 'status', labelKey: 'security.status', sortable: true },
  { key: 'member_count', labelKey: 'security.memberCount' },
  { key: 'permission_count', labelKey: 'security.permissionCount' },
  { key: 'actions', labelKey: 'security.actions' },
]

const actionGroups = computed(() => {
  const groups = new Map<string, SecurityAction[]>()
  for (const action of allActions.value)
    groups.set(action.resource_id, [...(groups.get(action.resource_id) ?? []), action])
  return [...groups.entries()].map(([resourceId, actions]) => ({ resourceId, actions }))
})
const permissionRows = computed(() =>
  permissions.value.map((permission) => {
    const action = allActions.value.find(
      (candidate) => String(candidate.id) === String(permission.resource_action_id),
    )
    const resource = resources.value.find(
      (candidate) => String(candidate.id) === String(action?.resource_id),
    )
    return {
      ...permission,
      resource_id: resource ? String(resource.id) : action?.resource_id,
      resource_name: resource?.name,
      resource_type: resource?.type,
      action: action?.code,
      effect: 'ALLOW' as const,
      code: action?.code,
    }
  }),
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
    if (selectedId.value && !selected.value)
      selectedId.value = state.rows.value[0] ? String(state.rows.value[0].id) : null
  } catch (caught: unknown) {
    error.value = caught
    reportApiError(caught)
  }
}
async function loadDetail(): Promise<void> {
  if (!selected.value) return
  try {
    const [nextMembers, nextPermissions, resourcePage] = await Promise.all([
      getGroupMembers(String(selected.value.id)),
      getGroupPermissions(String(selected.value.id)),
      listResourcePage({ q: '', page: 1, page_size: 200, sort: 'type', filter: {} }),
    ])
    const actions = await Promise.all(
      resourcePage.data.map((resource) => getResourceActions(String(resource.id))),
    )
    members.value = nextMembers
    permissions.value = nextPermissions
    resources.value = resourcePage.data
    allActions.value = actions.flat()
    memberDraft.value = nextMembers.map((member) => String(member.id))
    permissionDraft.value = nextPermissions.map((permission) =>
      String(permission.resource_action_id),
    )
    try {
      users.value = (
        await listSecurityUserPage({
          q: '',
          page: 1,
          page_size: 200,
          sort: 'username',
          filter: {},
        })
      ).data
    } catch {
      users.value = []
    }
  } catch (caught: unknown) {
    error.value = caught
    reportApiError(caught)
  }
}
function selectGroup(row: AuthorizationGroup): void {
  selectedId.value = String(row.id)
  tab.value = 'overview'
  detailOpen.value = true
  void loadDetail()
}
function selectTab(nextTab: 'overview' | 'members' | 'permissions'): void {
  tab.value = nextTab
  if (nextTab !== 'overview' && allActions.value.length === 0) void loadDetail()
}
function openCreate(): void {
  editingId.value = undefined
  form.value = { code: '', name: '', description: '', status: 'ACTIVE' }
  modalOpen.value = true
}
function openEdit(row: AuthorizationGroup): void {
  editingId.value = String(row.id)
  form.value = {
    code: row.code,
    name: row.name,
    description: row.description ?? '',
    status: row.status,
  }
  modalOpen.value = true
}
async function save(): Promise<void> {
  busy.value = true
  error.value = null
  try {
    const saved = await saveAuthorizationGroup(
      {
        code: form.value.code,
        name: form.value.name,
        description: form.value.description || undefined,
        status: form.value.status,
      },
      editingId.value,
    )
    modalOpen.value = false
    selectedId.value = String(saved.id)
    await load()
    await loadDetail()
  } catch (caught: unknown) {
    error.value = caught
    reportApiError(caught)
  } finally {
    busy.value = false
  }
}
async function toggle(row: AuthorizationGroup): Promise<void> {
  busy.value = true
  error.value = null
  try {
    await changeAuthorizationGroupState(
      String(row.id),
      row.status === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE',
    )
    await load()
    if (selectedId.value === String(row.id)) await loadDetail()
  } catch (caught: unknown) {
    error.value = caught
    reportApiError(caught)
  } finally {
    busy.value = false
  }
}
async function saveMembers(): Promise<void> {
  if (!selected.value) return
  busy.value = true
  error.value = null
  try {
    members.value = await saveGroupMembers(String(selected.value.id), memberDraft.value)
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
  error.value = null
  try {
    permissions.value = await saveGroupPermissions(String(selected.value.id), permissionDraft.value)
    await load()
  } catch (caught: unknown) {
    error.value = caught
    reportApiError(caught)
  } finally {
    busy.value = false
  }
}
function hasPermission(id: string): boolean {
  return permissionDraft.value.includes(id)
}
function togglePermission(id: string): void {
  permissionDraft.value = hasPermission(id)
    ? permissionDraft.value.filter((value) => value !== id)
    : [...permissionDraft.value, id]
}
function toggleMember(id: string): void {
  memberDraft.value = memberDraft.value.includes(id)
    ? memberDraft.value.filter((value) => value !== id)
    : [...memberDraft.value, id]
}
onMounted(() => void load())
</script>

<template>
  <div class="page-heading">
    <div>
      <h1>{{ t('security.groupsTitle') }}</h1>
      <p>{{ t('security.groupsDescription') }}</p>
    </div>
    <button type="button" class="primary-button" @click="openCreate">
      ＋ {{ t('common.create') }}
    </button>
  </div>
  <p v-if="error" class="inline-error" role="alert">{{ t('security.requestError') }}</p>
  <section class="content-panel security-list-panel">
    <div class="security-toolbar">
      <ListQueryControls
        :model-value="state.query"
        @apply="applyQuery"
        @reset="resetQuery"
      /><button type="button" class="secondary-button" @click="void load">
        ↻ {{ t('common.refresh') }}
      </button>
    </div>
    <ListShell
      :initial-loading="state.isInitialLoading.value"
      :refreshing="state.isBackgroundRefreshing.value"
      :has-data="state.queryState.hasData.value"
      :empty-dataset="state.isEmptyDataset.value"
      :empty-filtered-result="state.isEmptyFilteredResult.value"
      :error="state.queryState.error.value"
    >
      <ListTable
        :rows="state.rows.value"
        :columns="columns"
        :sort="state.query.sort"
        @sort-change="
          (sort) => {
            state.setSort(sort)
            void load()
          }
        "
      >
        <template #cell-status="{ row }"><TableStatus :status="row.status" /></template>
        <template #cell-member_count="{ row }">{{
          row.id === selected?.id ? members.length : t('list.noValue')
        }}</template>
        <template #cell-permission_count="{ row }">{{
          row.id === selected?.id ? permissions.length : t('list.noValue')
        }}</template>
        <template #cell-actions="{ row }"
          ><TableRowActions
            :active="row.status === 'ACTIVE'"
            :busy="busy"
            :show-view="true"
            :show-edit="true"
            :show-toggle="true"
            :edit-label="t('common.edit')"
            :view-label="t('common.view')"
            :activate-label="t('common.activate')"
            :deactivate-label="t('common.deactivate')"
            @view="selectGroup(row)"
            @edit="openEdit(row)"
            @toggle="toggle(row)"
        /></template>
      </ListTable>
      <ListPagination
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
      />
    </ListShell>
  </section>

  <div v-if="detailOpen && selected" class="modal-backdrop" @click.self="detailOpen = false">
    <section
      class="modal-card modal-card--wide"
      role="dialog"
      aria-modal="true"
      :aria-label="`${t('security.authorizationGroup')}: ${selected.name}`"
    >
      <header class="security-detail-heading">
        <div>
          <h2>{{ selected.name }}</h2>
          <small>{{ selected.code }} · {{ t('security.groupIdentityHint') }}</small>
        </div>
        <div class="detail-heading-actions">
          <TableStatus :status="selected.status" />
          <button
            type="button"
            class="icon-button"
            :aria-label="t('common.close')"
            @click="detailOpen = false"
          >
            ×
          </button>
        </div>
      </header>
      <nav class="security-tabs" aria-label="Authorization Group detail tabs">
        <button
          v-for="item in ['overview', 'members', 'permissions'] as const"
          :key="item"
          type="button"
          :class="{ active: tab === item }"
          @click="selectTab(item)"
        >
          {{ t(`security.tabs.${item}`) }}
        </button>
      </nav>
      <div v-if="tab === 'overview'" class="security-detail-copy">
        <p>{{ selected.description || t('security.noDescription') }}</p>
        <p class="security-note">{{ t('security.basePermissionNote') }}</p>
      </div>
      <div v-else-if="tab === 'members'" class="security-editor">
        <p class="security-note">{{ t('security.memberSourceNote') }}</p>
        <div v-if="members.length" class="detail-table-wrap">
          <table class="detail-table">
            <caption class="sr-only">
              {{
                t('security.memberList')
              }}
            </caption>
            <thead>
              <tr>
                <th scope="col">{{ t('users.id') }}</th>
                <th scope="col">{{ t('users.username') }}</th>
                <th scope="col">{{ t('users.status') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="member in members" :key="member.id">
                <td>{{ member.id }}</td>
                <td>{{ member.username }}</td>
                <td><TableStatus :status="member.status || member.active" /></td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-else class="empty-state">{{ t('security.noMembers') }}</p>
        <div v-if="users.length" class="member-picker">
          <p class="security-note">{{ t('security.memberPickerNote') }}</p>
          <label v-for="user in users" :key="user.id" class="check-row">
            <input
              type="checkbox"
              :checked="memberDraft.includes(String(user.id))"
              @change="toggleMember(String(user.id))"
            />
            <span>{{ user.username || t('list.noValue') }} (#{{ user.id }})</span>
          </label>
        </div>
        <p v-else class="empty-state">{{ t('security.noUsersForMembers') }}</p>
        <button
          type="button"
          class="primary-button"
          :disabled="busy || selected.status !== 'ACTIVE'"
          @click="saveMembers"
        >
          {{ busy ? t('common.saving') : t('security.saveMembers') }}
        </button>
      </div>
      <div v-else class="security-editor">
        <p class="security-note">{{ t('security.permissionSourceNote') }}</p>
        <div v-if="permissionRows.length" class="detail-table-wrap">
          <table class="detail-table">
            <caption class="sr-only">
              {{
                t('security.permissionList')
              }}
            </caption>
            <thead>
              <tr>
                <th scope="col">{{ t('security.resource') }}</th>
                <th scope="col">{{ t('security.action') }}</th>
                <th scope="col">{{ t('security.effect') }}</th>
                <th scope="col">{{ t('security.permissionId') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="permission in permissionRows" :key="permission.resource_action_id">
                <td>
                  {{ permission.resource_name || permission.resource_type || t('list.noValue') }}
                </td>
                <td>
                  <code>{{ permission.action || permission.code || t('list.noValue') }}</code>
                </td>
                <td><span class="effect effect--allow">ALLOW</span></td>
                <td>
                  <code>{{ permission.resource_action_id }}</code>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-else class="empty-state">{{ t('security.noPermissions') }}</p>
        <div class="permission-grid">
          <div v-for="group in actionGroups" :key="group.resourceId" class="permission-group">
            <strong>{{
              resources.find((resource) => String(resource.id) === group.resourceId)?.name ||
              group.resourceId
            }}</strong>
            <label v-for="action in group.actions" :key="action.id" class="check-row">
              <input
                type="checkbox"
                :checked="hasPermission(action.id)"
                @change="togglePermission(action.id)"
              />
              {{ action.code }}
            </label>
          </div>
        </div>
        <button
          type="button"
          class="primary-button"
          :disabled="busy || selected.status !== 'ACTIVE'"
          @click="savePermissions"
        >
          {{ busy ? t('common.saving') : t('security.savePermissions') }}
        </button>
      </div>
    </section>
  </div>

  <div v-if="modalOpen" class="modal-backdrop" @click.self="modalOpen = false">
    <section
      class="modal-card"
      role="dialog"
      aria-modal="true"
      :aria-label="t('security.groupForm')"
    >
      <header class="modal-card__header">
        <h2>
          {{ editingId ? t('common.edit') : t('common.create') }}
          {{ t('security.authorizationGroup') }}
        </h2>
        <button
          type="button"
          class="icon-button"
          :aria-label="t('common.close')"
          @click="modalOpen = false"
        >
          ×
        </button>
      </header>
      <form class="modal-card__body" @submit.prevent="save">
        <label
          ><span>{{ t('security.code') }}</span
          ><input
            v-model="form.code"
            class="field-input"
            required
            :disabled="Boolean(editingId)" /></label
        ><label
          ><span>{{ t('security.name') }}</span
          ><input v-model="form.name" class="field-input" required /></label
        ><label
          ><span>{{ t('security.description') }}</span
          ><textarea v-model="form.description" class="field-input" rows="3" /></label
        ><label
          ><span>{{ t('security.status') }}</span
          ><select v-model="form.status" class="field-select">
            <option value="ACTIVE">{{ t('status.active') }}</option>
            <option value="INACTIVE">{{ t('status.inactive') }}</option>
          </select></label
        >
        <footer class="modal-card__footer">
          <button type="button" class="secondary-button" @click="modalOpen = false">
            {{ t('common.cancel') }}</button
          ><button type="submit" class="primary-button" :disabled="busy">
            {{ busy ? t('common.saving') : t('common.save') }}
          </button>
        </footer>
      </form>
    </section>
  </div>
</template>

<style scoped>
.security-list-panel,
.security-detail-panel {
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
.security-detail-heading {
  display: flex;
  justify-content: space-between;
  align-items: start;
  gap: 12px;
}
.detail-heading-actions {
  display: flex;
  align-items: center;
  gap: 10px;
}
.security-detail-heading h2 {
  margin: 0;
  color: #153956;
  font-size: 18px;
}
.security-detail-heading small {
  display: block;
  margin-top: 5px;
  color: #8191a0;
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
}
.security-tabs button.active {
  border-bottom-color: #0873b8;
  color: #075f9b;
  font-weight: 700;
}
.security-detail-copy,
.security-editor {
  display: grid;
  gap: 12px;
}
.security-note {
  margin: 0;
  color: #6c8191;
  font-size: 12px;
  line-height: 1.6;
}
.check-row {
  display: flex;
  align-items: center;
  gap: 8px;
  min-height: 34px;
  color: #34526b;
  font-size: 12px;
}
.permission-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 12px;
}
.permission-group {
  display: grid;
  gap: 4px;
  padding: 12px;
  border: 1px solid #dce6ec;
  border-radius: 6px;
}
.permission-group strong {
  color: #173c59;
  font-family: ui-monospace, monospace;
  font-size: 12px;
}
.detail-table-wrap {
  overflow-x: auto;
  border: 1px solid #dbe5ea;
  border-radius: 6px;
}
.detail-table {
  width: 100%;
  min-width: 560px;
  border-collapse: collapse;
  color: #183b57;
  font-size: 12px;
}
.detail-table th,
.detail-table td {
  padding: 9px 10px;
  border-bottom: 1px solid #e8eef1;
  text-align: start;
}
.detail-table th {
  background: #f7fafb;
  color: #536b7b;
  font-size: 11px;
}
.detail-table tbody tr:last-child td {
  border-bottom: 0;
}
.effect {
  display: inline-flex;
  padding: 3px 7px;
  border: 1px solid #b8dfcc;
  background: #effbf4;
  color: #1c8a62;
  font-family: ui-monospace, monospace;
  font-size: 10px;
  font-weight: 700;
}
.inline-error {
  margin: 0 0 12px;
  color: #b64444;
  font-size: 12px;
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
.modal-card__body textarea {
  height: auto;
  padding: 8px 10px;
}
.modal-card__footer {
  justify-content: flex-end;
  margin: 5px -18px -18px;
  border-top: 1px solid #e4ebef;
  border-bottom: 0;
}
.icon-button {
  border: 0;
  background: transparent;
  color: #536b7b;
  font-size: 22px;
}
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}
@media (max-width: 700px) {
  .permission-grid {
    grid-template-columns: 1fr;
  }
  .security-list-panel,
  .security-detail-panel {
    padding: 15px 12px;
  }
}
</style>
