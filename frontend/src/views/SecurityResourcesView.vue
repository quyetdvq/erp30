<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { Resource } from '@/api/generated/types.gen'
import {
  ListPagination,
  ListQueryControls,
  ListShell,
  ListTable,
  TableRowActions,
} from '@/components/lists'
import type { ListColumn, ListQuery } from '@/components/lists/listTypes'
import { useListState } from '@/composables/useListState'
import {
  deleteResourceActionById,
  deleteResourceById,
  getResourceActions,
  listResourcePage,
  saveResource,
  saveResourceAction,
  type SecurityAction,
} from '@/services/security'
import { reportApiError } from '@/services/errorBoundary'

const { t } = useI18n()
const state = useListState<Resource>((query) => listResourcePage(query), { sort: 'type' })
const selectedId = ref<string | null>(null)
const selected = computed(
  () => state.rows.value.find((row) => String(row.id) === selectedId.value) ?? null,
)
const actions = ref<SecurityAction[]>([])
const tab = ref<'resource' | 'actions'>('resource')
const modalOpen = ref(false)
const actionModalOpen = ref(false)
const editingId = ref<string | undefined>()
const editingActionId = ref<string | undefined>()
const busy = ref(false)
const error = ref<unknown | null>(null)
const form = ref({ type: '', name: '', domain: 'SYSTEM' as Resource['domain'] })
const actionForm = ref({ code: '', description: '' })
const columns: readonly ListColumn<Resource>[] = [
  { key: 'type', labelKey: 'security.type', sortable: true },
  { key: 'name', labelKey: 'security.name', sortable: true },
  { key: 'domain', labelKey: 'security.domain', sortable: true },
  { key: 'lifecycle_id', labelKey: 'security.lifecycle' },
  { key: 'action_count', labelKey: 'security.actionCount' },
  { key: 'actions', labelKey: 'security.actions' },
]
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
    if (selected.value) await loadActions()
  } catch (caught: unknown) {
    error.value = caught
    reportApiError(caught)
  }
}
async function loadActions(): Promise<void> {
  if (!selected.value) return
  try {
    actions.value = await getResourceActions(String(selected.value.id))
  } catch (caught: unknown) {
    error.value = caught
    reportApiError(caught)
  }
}
function selectResource(row: Resource): void {
  selectedId.value = String(row.id)
  tab.value = 'resource'
  void loadActions()
}
function openActionsTab(): void {
  tab.value = 'actions'
  void loadActions()
}
function openCreate(): void {
  editingId.value = undefined
  form.value = { type: '', name: '', domain: 'SYSTEM' }
  modalOpen.value = true
}
function openEdit(row: Resource): void {
  editingId.value = String(row.id)
  form.value = { type: row.type, name: row.name, domain: row.domain }
  modalOpen.value = true
}
async function save(): Promise<void> {
  busy.value = true
  error.value = null
  try {
    const saved = editingId.value
      ? await saveResource({ name: form.value.name }, editingId.value)
      : await saveResource({
          type: form.value.type,
          name: form.value.name,
          domain: form.value.domain,
        })
    modalOpen.value = false
    selectedId.value = String(saved.id)
    await load()
  } catch (caught: unknown) {
    error.value = caught
    reportApiError(caught)
  } finally {
    busy.value = false
  }
}
async function remove(row: Resource): Promise<void> {
  if (typeof window !== 'undefined' && !window.confirm(t('security.deleteConfirm'))) return
  busy.value = true
  error.value = null
  try {
    await deleteResourceById(String(row.id))
    if (selectedId.value === String(row.id)) selectedId.value = null
    await load()
  } catch (caught: unknown) {
    error.value = caught
    reportApiError(caught)
  } finally {
    busy.value = false
  }
}
function openActionCreate(): void {
  editingActionId.value = undefined
  actionForm.value = { code: '', description: '' }
  actionModalOpen.value = true
}
function openActionEdit(action: SecurityAction): void {
  editingActionId.value = action.id
  actionForm.value = { code: action.code, description: action.description ?? '' }
  actionModalOpen.value = true
}
async function saveAction(): Promise<void> {
  if (!selected.value) return
  busy.value = true
  error.value = null
  try {
    await saveResourceAction(
      String(selected.value.id),
      editingActionId.value
        ? { description: actionForm.value.description || null }
        : { code: actionForm.value.code, description: actionForm.value.description || undefined },
      editingActionId.value,
    )
    actionModalOpen.value = false
    await loadActions()
  } catch (caught: unknown) {
    error.value = caught
    reportApiError(caught)
  } finally {
    busy.value = false
  }
}
async function removeAction(action: SecurityAction): Promise<void> {
  if (
    !selected.value ||
    (typeof window !== 'undefined' && !window.confirm(t('security.deleteConfirm')))
  )
    return
  busy.value = true
  error.value = null
  try {
    await deleteResourceActionById(String(selected.value.id), action.id)
    await loadActions()
  } catch (caught: unknown) {
    error.value = caught
    reportApiError(caught)
  } finally {
    busy.value = false
  }
}
onMounted(() => void load())
</script>

<template>
  <div class="page-heading">
    <div>
      <h1>{{ t('security.resourcesTitle') }}</h1>
      <p>{{ t('security.resourcesDescription') }}</p>
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
        ><template #cell-domain="{ row }"
          ><code>{{ row.domain }}</code></template
        ><template #cell-action_count="{ row }">{{
          row.id === selected?.id ? actions.length : t('list.noValue')
        }}</template
        ><template #cell-actions="{ row }"
          ><TableRowActions
            :busy="busy"
            :show-view="true"
            :show-edit="true"
            :show-delete="true"
            :edit-label="t('common.edit')"
            :view-label="t('common.view')"
            :delete-label="t('common.delete')"
            @view="selectResource(row)"
            @edit="openEdit(row)"
            @delete="remove(row)" /></template></ListTable
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
  <section v-if="selected" class="content-panel security-detail-panel">
    <header class="security-detail-heading">
      <div>
        <h2>{{ selected.name }}</h2>
        <small
          ><code>{{ selected.type }}</code> · {{ t('security.domain') }}:
          {{ selected.domain }}</small
        >
      </div>
    </header>
    <nav class="security-tabs" aria-label="Resource detail tabs">
      <button type="button" :class="{ active: tab === 'resource' }" @click="tab = 'resource'">
        {{ t('security.tabs.resource') }}</button
      ><button type="button" :class="{ active: tab === 'actions' }" @click="openActionsTab">
        {{ t('security.tabs.actions') }}
      </button>
    </nav>
    <div v-if="tab === 'resource'" class="security-detail-copy">
      <dl class="security-definition-list">
        <div>
          <dt>{{ t('security.type') }}</dt>
          <dd>{{ selected.type }}</dd>
        </div>
        <div>
          <dt>{{ t('security.domain') }}</dt>
          <dd>
            <code>{{ selected.domain }}</code
            ><small>{{ t('security.domainImmutable') }}</small>
          </dd>
        </div>
        <div>
          <dt>{{ t('security.lifecycle') }}</dt>
          <dd>{{ selected.lifecycle_id ?? t('list.noValue') }}</dd>
        </div>
      </dl>
      <p class="security-note">{{ t('security.resourceDomainNote') }}</p>
      <p class="security-note">{{ t('security.numberingExcluded') }}</p>
    </div>
    <div v-else class="security-editor">
      <div class="detail-actions">
        <h3>{{ t('security.resourceActions') }}</h3>
        <button type="button" class="primary-button" @click="openActionCreate">
          ＋ {{ t('common.create') }}
        </button>
      </div>
      <div class="action-list">
        <div v-for="action in actions" :key="action.id" class="action-row">
          <div>
            <strong>{{ action.code }}</strong
            ><small>{{ action.description || t('security.noDescription') }}</small>
          </div>
          <span
            ><button type="button" class="secondary-button" @click="openActionEdit(action)">
              {{ t('common.edit') }}</button
            ><button type="button" class="secondary-button" @click="removeAction(action)">
              {{ t('common.delete') }}
            </button></span
          >
        </div>
        <p v-if="actions.length === 0" class="empty-state">{{ t('security.noActions') }}</p>
      </div>
    </div>
  </section>
  <div v-if="modalOpen" class="modal-backdrop" @click.self="modalOpen = false">
    <section
      class="modal-card"
      role="dialog"
      aria-modal="true"
      :aria-label="t('security.resourceForm')"
    >
      <header class="modal-card__header">
        <h2>
          {{ editingId ? t('common.edit') : t('common.create') }} {{ t('security.resource') }}
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
          ><span>{{ t('security.type') }}</span
          ><input
            v-model="form.type"
            class="field-input"
            required
            :disabled="Boolean(editingId)" /></label
        ><label
          ><span>{{ t('security.name') }}</span
          ><input v-model="form.name" class="field-input" required /></label
        ><label
          ><span>{{ t('security.domain') }}</span
          ><select v-model="form.domain" class="field-select" :disabled="Boolean(editingId)">
            <option value="SYSTEM">SYSTEM</option>
            <option value="MASTER">MASTER</option>
            <option value="ORGANIZATION">ORGANIZATION</option></select
          ><small class="security-note">{{
            editingId ? t('security.domainImmutable') : t('security.domainCreateNote')
          }}</small></label
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
  <div v-if="actionModalOpen" class="modal-backdrop" @click.self="actionModalOpen = false">
    <section
      class="modal-card"
      role="dialog"
      aria-modal="true"
      :aria-label="t('security.actionForm')"
    >
      <header class="modal-card__header">
        <h2>
          {{ editingActionId ? t('common.edit') : t('common.create') }} {{ t('security.action') }}
        </h2>
        <button
          type="button"
          class="icon-button"
          :aria-label="t('common.close')"
          @click="actionModalOpen = false"
        >
          ×
        </button>
      </header>
      <form class="modal-card__body" @submit.prevent="saveAction">
        <label
          ><span>{{ t('security.actionCode') }}</span
          ><input
            v-model="actionForm.code"
            class="field-input"
            required
            :disabled="Boolean(editingActionId)" /></label
        ><label
          ><span>{{ t('security.description') }}</span
          ><textarea v-model="actionForm.description" class="field-input" rows="3" />
        </label>
        <footer class="modal-card__footer">
          <button type="button" class="secondary-button" @click="actionModalOpen = false">
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
.security-definition-list {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
  margin: 0;
}
.security-definition-list div {
  padding: 12px;
  border: 1px solid #dce6ec;
  border-radius: 6px;
}
.security-definition-list dt {
  color: #708499;
  font-size: 11px;
}
.security-definition-list dd {
  display: grid;
  gap: 5px;
  margin: 5px 0 0;
  color: #203f58;
  font-size: 13px;
  font-weight: 700;
}
.security-definition-list dd small {
  color: #8293a2;
  font-size: 11px;
  font-weight: 400;
}
.security-note {
  margin: 0;
  color: #6c8191;
  font-size: 12px;
  line-height: 1.6;
}
.detail-actions {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 10px;
}
.detail-actions h3 {
  margin: 0;
  color: #173c59;
  font-size: 15px;
}
.action-list {
  display: grid;
  gap: 8px;
}
.action-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  padding: 11px 12px;
  border: 1px solid #dce6ec;
  border-radius: 6px;
}
.action-row div {
  display: grid;
  gap: 4px;
}
.action-row strong {
  color: #173c59;
  font-family: ui-monospace, monospace;
}
.action-row small {
  color: #6d8292;
}
.action-row span {
  display: flex;
  gap: 6px;
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
code {
  font-family: ui-monospace, SFMono-Regular, Consolas, monospace;
  color: #0d5b83;
}
@media (max-width: 700px) {
  .security-definition-list {
    grid-template-columns: 1fr;
  }
  .action-row {
    align-items: flex-start;
    flex-direction: column;
  }
  .security-list-panel,
  .security-detail-panel {
    padding: 15px 12px;
  }
}
</style>
