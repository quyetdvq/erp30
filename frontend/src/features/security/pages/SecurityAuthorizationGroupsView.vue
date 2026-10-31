<script setup lang="ts">
import AppDialogHost from '@/components/ui/AppDialogHost.vue'
import { rowAllowedActions } from '@/components/lists/rowActionPolicy'
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
import { loadLookupPages } from '@/services/listLookups'
import type { ManagedUser } from '@/services/security'
import { useBasePermission } from '@/composables/useBasePermission'

const { t } = useI18n()
const { hasBasePermission, refreshBasePermissions } = useBasePermission()
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
  { key: 'status', labelKey: 'security.status', sortable: true, align: 'center' },
  { key: 'actions', labelKey: 'security.actions', align: 'end' },
]

const actionGroups = computed(() => {
  const groups = new Map<string, SecurityAction[]>()
  for (const action of allActions.value) {
    groups.set(action.resource_id, [...(groups.get(action.resource_id) ?? []), action])
  }
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
      loadLookupPages((query) => listResourcePage(query, true), { sort: 'type' }),
    ])
    members.value = nextMembers
    permissions.value = nextPermissions
    resources.value = resourcePage
    allActions.value = resourcePage.flatMap((resource) =>
      (resource.actions ?? []).map((action) => ({
        id: String(action.id),
        resource_id: String(resource.id),
        code: action.code,
        description: action.description,
      })),
    )
    memberDraft.value = nextMembers.map((member) => String(member.id))
    permissionDraft.value = nextPermissions.map((permission) =>
      String(permission.resource_action_id),
    )
    try {
      users.value = await loadLookupPages(listSecurityUserPage, { sort: 'username' })
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
    await refreshBasePermissions(true)
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
    await refreshBasePermissions(true)
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
    <button data-ds-icon="add" v-if="hasBasePermission('authorization_group', 'create')" type="button" class="primary-button"
      @click="openCreate">
      {{ t('common.create') }}
    </button>
  </div>
  <p v-if="error" class="inline-error" role="alert">{{ t('security.requestError') }}</p>
  <section class="content-panel security-list-panel ds-page-panel">
    <div class="security-toolbar ds-page-toolbar">
      <ListQueryControls :show-page-size="false" :model-value="state.query" @apply="applyQuery" @reset="resetQuery" >
        <template #actions>
          <button data-ds-icon="refresh" type="button"
            class="secondary-button" @click="void load">
            {{ t('common.refresh') }}
          </button>
        </template>
      </ListQueryControls>
    </div>
    <ListShell :initial-loading="state.isInitialLoading.value" :refreshing="state.isBackgroundRefreshing.value"
      :has-data="state.queryState.hasData.value" :empty-dataset="state.isEmptyDataset.value"
      :empty-filtered-result="state.isEmptyFilteredResult.value" :error="state.queryState.error.value">
      <ListTable :rows="state.rows.value" :columns="columns" :sort="state.query.sort" @sort-change="
        (sort) => {
          state.setSort(sort)
          void load()
        }
      ">
        <template #cell-status="{ row }">
          <TableStatus :status="row.status" />
        </template>
        <template #cell-actions="{ row }">
          <TableRowActions authorization-mode="effective" :view-scope-verified="true" :allowed-actions="rowAllowedActions(row)" :active="row.status === 'ACTIVE'" :busy="busy" :show-view="true" :show-edit="true"
            :show-toggle="true" view-permission="authorization_group.view" edit-permission="authorization_group.edit"
            toggle-permission="authorization_group.setstate" :edit-label="t('common.edit')"
            :view-label="t('common.view')" :activate-label="t('common.activate')"
            :deactivate-label="t('common.deactivate')" @view="selectGroup(row)" @edit="openEdit(row)"
            @toggle="toggle(row)" />
        </template>
      </ListTable>
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

  <AppDialogHost :open="Boolean(detailOpen && selected)" size="lg" @close="detailOpen = false">
    <section v-if="detailOpen && selected" class="ds-dialog-surface modal-card tabbed-detail-workspace" role="dialog" aria-modal="true"
      :aria-label="`${t('security.authorizationGroup')}: ${selected.name}`">
      <header class="modal-card__header">
        <h2 class="modal-card-title">
          <NIcon :size="24">
            <PeopleOutline />
          </NIcon>
          {{ selected.name }}
          <TableStatus :status="selected.status" />
        </h2>
        <small>{{ selected.code }} · {{ t('security.groupIdentityHint') }}</small>
        <button type="button" class="modal-card-button-close" :aria-label="t('common.close')"
          @click="detailOpen = false" data-ds-icon="close">
        </button>

      </header>
      <nav class="security-tabs ds-detail-tabs ds-detail-tabs--spaced" :aria-label="t('security.groupDetailTabs')">
        <button v-for="item in ['overview', 'members', 'permissions'] as const" :key="item" type="button"
          :class="{ active: tab === item }" @click="selectTab(item)">
          {{ t(`security.tabs.${item}`) }}
        </button>
      </nav>


      <div v-if="tab === 'overview'" class="modal-card__body security-detail-copy">
        <p>{{ selected.description || t('security.noDescription') }}</p>
        <p class="security-note">{{ t('security.basePermissionNote') }}</p>
      </div>

      <div v-else-if="tab === 'members'" class="modal-card__body security-editor">
        <p class="security-note">{{ t('security.memberSourceNote') }}</p>
        <div v-if="members.length" class="detail-table-wrap ds-table-scroll">
          <table class="detail-table ds-data-grid">
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
                <td>
                  <TableStatus :status="member.status || member.active" />
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-else class="empty-state">{{ t('security.noMembers') }}</p>
        <div v-if="users.length" class="member-picker">
          <p class="security-note">{{ t('security.memberPickerNote') }}</p>
          <label v-for="user in users" :key="user.id" class="check-row">
            <input type="checkbox" :checked="memberDraft.includes(String(user.id))"
              @change="toggleMember(String(user.id))" />
            <span>{{ user.username || t('list.noValue') }} (#{{ user.id }})</span>
          </label>
        </div>
        <p v-else class="empty-state">{{ t('security.noUsersForMembers') }}</p>
      </div>


      <div v-else class="modal-card__body security-editor">
        <p class="security-note">{{ t('security.permissionSourceNote') }}</p>
        <div v-if="permissionRows.length" class="detail-table-wrap ds-table-scroll">
          <table class="detail-table ds-data-grid">
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
              <input type="checkbox" :checked="hasPermission(action.id)" @change="togglePermission(action.id)" />
              {{ action.code }}


            </label>



          </div>
        </div>
      </div>


      <footer class="modal-card__footer">
        <button type="button" class="secondary-button" @click="detailOpen = false" data-ds-icon="close">

          {{ t('common.close') }}
        </button>
        <button v-if="tab === 'members'" type="button" class="primary-button" :disabled="busy ||
          selected.status !== 'ACTIVE' ||
          !hasBasePermission('authorization_group', 'assign_member')
          " @click="saveMembers" data-ds-icon="save">

          {{ busy ? t('common.saving') : t('security.saveMembers') }}
        </button>
        <button v-else-if="tab === 'permissions'" type="button" class="primary-button" :disabled="busy ||
          selected.status !== 'ACTIVE' ||
          !hasBasePermission('authorization_group', 'assign_permission')
          " @click="savePermissions" data-ds-icon="save">

          {{ busy ? t('common.saving') : t('security.savePermissions') }}
        </button>
      </footer>

    </section>
  </AppDialogHost>

  <AppDialogHost :open="modalOpen" size="lg" @close="modalOpen = false">
    <section v-if="modalOpen" class="ds-dialog-surface modal-card" role="dialog" aria-modal="true" :aria-label="t('security.groupForm')">
      <header class="modal-card__header">
        <h2 class="modal-card-title">
          <NIcon :size="24">
            <PeopleOutline />
          </NIcon>
          {{ editingId ? t('common.edit') : t('common.create') }}
          {{ t('security.authorizationGroup') }}
        </h2>
        <button type="button" class="modal-card-button-close" :aria-label="t('common.close')" @click="modalOpen = false" data-ds-icon="close">
        </button>

      </header>
      <form class="modal-card__body" id="authorization-group-form" @submit.prevent="save">
        <label><span>{{ t('security.code') }}</span><input v-model="form.code" class="field-input" required
            :disabled="Boolean(editingId)" /></label><label><span>{{ t('security.name') }}</span><input
            v-model="form.name" class="field-input" required /></label><label><span>{{ t('security.description')
            }}</span><textarea v-model="form.description" class="field-input" rows="3" /></label><label><span>{{
              t('security.status') }}</span><select v-model="form.status" class="field-select">
            <option value="ACTIVE">{{ t('status.active') }}</option>
            <option value="INACTIVE">{{ t('status.inactive') }}</option>
          </select></label>

      </form>
      <footer class="modal-card__footer">
        <button data-ds-icon="cancel" type="button" class="secondary-button" form="authorization-group-form" @click="modalOpen = false">
          {{ t('common.cancel') }}</button><button type="submit" class="primary-button" :disabled="busy" data-ds-icon="save">

          {{ busy ? t('common.saving') : t('common.save') }}
        </button>
      </footer>
    </section>
  </AppDialogHost>
</template>

<style scoped>.detail-table { min-width: 560px; }

.authorization-group-modal__header-actions {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
}

.security-detail-copy,
.security-editor {
  display: grid;
  gap: 12px;
}

.security-note {
  margin: 0;
  color: var(--ds-text-secondary);
  font-size: 12px;
  line-height: 1.6;
}

.check-row {
  display: flex;
  align-items: center;
  gap: 4px;
  min-height: 24px;
  color: var(--ds-text-primary);
  font-size: 12px;
}

.permission-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 12px;
}

.permission-group {
  gap: 4px;
  padding: 8px 12px;
  border: 1px solid var(--ds-border-subtle);
  border-radius: 6px;
}

.permission-group strong {
  color: var(--ds-text-primary);
  font-family: ui-monospace, monospace;
  font-size: 12px;
}

.effect {
  display: inline-flex;
  padding: 3px 7px;
  border: 1px solid var(--ds-success);
  background: var(--ds-success-soft);
  color: var(--ds-success);
  font-family: ui-monospace, monospace;
  font-size: 10px;
  font-weight: 700;
}

.inline-error {
  margin: 0 0 12px;
  color: var(--ds-danger);
  font-size: 12px;
}

.modal-card__body label {
  gap: 5px;
  color: var(--ds-text-secondary);
  font-size: 12px;
  font-weight: 400;
}

.modal-card__body textarea {
  height: auto;
  padding: 8px 10px;
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

  }
</style>
