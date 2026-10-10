<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type {
  Department,
  Employee,
  EmployeeAssignment,
  EmployeeAssignmentCreate,
  EmployeeAssignmentPatch,
  EmployeeCreate,
  Organization,
  Position,
} from '@/api/generated/types.gen'
import ContactList from '@/components/contacts/ContactList.vue'
import SearchableSelect from '@/components/forms/SearchableSelect.vue'
import {
  ListPagination,
  ListQueryControls,
  ListShell,
  ListTable,
  TableRowActions,
} from '@/components/lists'
import {
  type ListFilterDefinition,
  type ListColumn,
  type ListQuery,
} from '@/components/lists/listTypes'
import { useListState } from '@/composables/useListState'
import { listOrganizationPage } from '@/services/foundation'
import {
  changeAssignmentState,
  changeEmployeeState,
  listAssignmentPage,
  listDepartmentPage,
  listEmployeePage,
  listPositionPage,
  saveAssignment,
  saveEmployee,
} from '@/services/employeeStructure'
import { reportApiError } from '@/services/errorBoundary'
import { loadLookupPages } from '@/services/listLookups'
import { hasPrimaryOverlap, wouldCreateManagerCycle } from '@/modules/employeeRules'
import { useBasePermission } from '@/composables/useBasePermission'

type Gender = 'MALE' | 'FEMALE' | 'OTHER' | null
type Tab = 'overview' | 'assignments' | 'contacts' | 'audit'
const { t, locale } = useI18n()
const { hasBasePermission } = useBasePermission()
const today = () => new Date().toISOString().slice(0, 10)

const state = useListState<Employee>((query) => listEmployeePage(query), { sort: 'employee_name' })
const organizations = ref<Organization[]>([])
const departments = ref<Department[]>([])
const positions = ref<Position[]>([])
const assignments = ref<EmployeeAssignment[]>([])
const employeeLookup = ref<Employee[]>([])
const selectedEmployeeAssignments = ref<EmployeeAssignment[]>([])
const selectedId = ref<string | null>(null)
const activeTab = ref<Tab>('overview')
const employeeModal = ref(false)
const employeeDetailOpen = ref(false)
const assignmentModal = ref(false)
const editingEmployeeId = ref<string | undefined>()
const editingAssignmentId = ref<string | undefined>()
const busy = ref(false)
const error = ref<unknown | null>(null)
const assignmentMode = ref<'active' | 'history'>('active')
const employeeForm = ref({
  employee_code: '',
  employee_name: '',
  user_id: '',
  gender: null as Gender,
  date_of_birth: '',
  place_of_origin: '',
  hire_date: '',
  manager_employee_id: '',
  status: 'ACTIVE' as 'ACTIVE' | 'INACTIVE',
  description: '',
})
const assignmentForm = ref({
  organization_id: '',
  department_id: '',
  position_id: '',
  is_primary: false,
  effective_from: today(),
  effective_to: '',
  status: 'ACTIVE' as 'ACTIVE' | 'INACTIVE',
  description: '',
})

const selected = computed<Employee>(
  () => state.rows.value.find((row) => String(row.id) === selectedId.value) as Employee,
)
const activeEmployees = computed(() =>
  employeeLookup.value.filter((row) => row.status === 'ACTIVE'),
)
function upsertEmployeeLookup(row: Employee): void {
  const id = String(row.id)
  const index = employeeLookup.value.findIndex((entry) => String(entry.id) === id)
  if (index === -1) employeeLookup.value = [...employeeLookup.value, row]
  else
    employeeLookup.value = employeeLookup.value.map((entry, current) =>
      current === index ? row : entry,
    )
}

const managerOptions = computed(() =>
  activeEmployees.value.filter(
    (row) =>
      String(row.id) !== selectedId.value ||
      String(row.id) === employeeForm.value.manager_employee_id,
  ),
)
const managerSelectOptions = computed(() =>
  managerOptions.value.map((row) => ({
    value: String(row.id),
    label: `${row.employee_code} - ${row.employee_name}`,
  })),
)
const activeOrganizations = computed(() =>
  organizations.value.filter((row) => row.status === 'ACTIVE'),
)
const activeDepartments = computed(() => departments.value.filter((row) => row.status === 'ACTIVE'))
const activePositions = computed(() => positions.value.filter((row) => row.status === 'ACTIVE'))
const organizationSelectOptions = computed(() =>
  activeOrganizations.value.map((row) => ({
    value: String(row.id),
    label: `${row.code} - ${row.name}`,
  })),
)
const departmentSelectOptions = computed(() =>
  activeDepartments.value.map((row) => ({
    value: String(row.id),
    label: `${row.code} - ${row.name}`,
  })),
)
const positionSelectOptions = computed(() =>
  activePositions.value.map((row) => ({
    value: String(row.id),
    label: `${row.code} - ${row.name}`,
  })),
)
const selectedAssignments = computed(() =>
  selectedEmployeeAssignments.value.filter((row) =>
    assignmentMode.value === 'active' ? row.status === 'ACTIVE' : row.status !== 'ACTIVE',
  ),
)
const currentPrimary = (employeeId: string | number | bigint): EmployeeAssignment | undefined =>
  assignments.value.find(
    (row) =>
      row.status === 'ACTIVE' &&
      row.is_primary &&
      row.effective_from <= today() &&
      (!row.effective_to || row.effective_to >= today()) &&
      String(row.employee_id) === String(employeeId),
  )
const organizationName = (id?: string | number | bigint) =>
  organizations.value.find((row) => String(row.id) === String(id))?.name ?? '—'
const departmentName = (id?: string | number | bigint) =>
  departments.value.find((row) => String(row.id) === String(id))?.name ?? '—'
const positionName = (id?: string | number | bigint) =>
  positions.value.find((row) => String(row.id) === String(id))?.name ?? '—'
const genderLabel = (value: Gender): string =>
  value === 'MALE'
    ? t('people.genderMale')
    : value === 'FEMALE'
      ? t('people.genderFemale')
      : value === 'OTHER'
        ? t('people.genderOther')
        : t('people.genderUnspecified')
const filters = computed<ListFilterDefinition[]>(() => [
  {
    key: 'status',
    labelKey: 'people.status',
    type: 'select',
    options: [
      { value: 'ACTIVE', labelKey: 'status.active' },
      { value: 'INACTIVE', labelKey: 'status.inactive' },
    ],
  },
  {
    key: 'organization_id',
    labelKey: 'people.organization',
    type: 'select',
    searchable: true,
    options: organizations.value.map((row) => ({
      value: String(row.id),
      labelKey: row.name,
      label: `${row.code} - ${row.name}`,
    })),
  },
  {
    key: 'department_id',
    labelKey: 'people.department',
    type: 'select',
    searchable: true,
    options: departments.value.map((row) => ({
      value: String(row.id),
      labelKey: row.name,
      label: `${row.code} - ${row.name}`,
    })),
  },
  {
    key: 'position_id',
    labelKey: 'people.position',
    type: 'select',
    searchable: true,
    options: positions.value.map((row) => ({
      value: String(row.id),
      labelKey: row.name,
      label: `${row.code} - ${row.name}`,
    })),
  },
])
const columns: readonly ListColumn<Employee>[] = [
  { key: 'employee_code', labelKey: 'people.employeeCode' },
  { key: 'employee_name', labelKey: 'people.name', sortable: true, sortField: 'employee_name' },
  {
    key: 'organization',
    labelKey: 'people.organization',
    value: (row) => organizationName(currentPrimary(row.id)?.organization_id),
  },
  {
    key: 'department',
    labelKey: 'people.department',
    value: (row) => departmentName(currentPrimary(row.id)?.department_id),
  },
  {
    key: 'position',
    labelKey: 'people.position',
    value: (row) => positionName(currentPrimary(row.id)?.position_id),
  },
  {
    key: 'manager',
    labelKey: 'people.manager',
    value: (row) =>
      employeeLookup.value.find((manager) => String(manager.id) === String(row.manager_employee_id))
        ?.employee_name ?? '—',
  },
  { key: 'status', labelKey: 'people.status' },
  { key: 'actions', labelKey: 'people.actions' },
]

async function loadLookups(): Promise<void> {
  try {
    const [orgs, deps, poss, employees] = await Promise.all([
      loadLookupPages(listOrganizationPage),
      loadLookupPages(listDepartmentPage),
      loadLookupPages(listPositionPage),
      loadLookupPages(listEmployeePage, { sort: 'employee_name' }),
    ])
    employeeLookup.value = employees
    organizations.value = orgs
    departments.value = deps
    positions.value = poss
  } catch (caught: unknown) {
    error.value = caught
    reportApiError(caught)
  }
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
async function loadAssignments(): Promise<void> {
  if (!selectedId.value) {
    selectedEmployeeAssignments.value = []
    return
  }
  try {
    selectedEmployeeAssignments.value = await loadLookupPages(listAssignmentPage, {
      filter: { employee_id: selectedId.value },
    })
  } catch (caught: unknown) {
    selectedEmployeeAssignments.value = []
    reportApiError(caught)
  }
}
async function loadAllAssignments(): Promise<void> {
  try {
    assignments.value = await loadLookupPages(listAssignmentPage)
  } catch (caught: unknown) {
    assignments.value = []
    reportApiError(caught)
  }
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
function openCreate(): void {
  editingEmployeeId.value = undefined
  employeeForm.value = {
    employee_code: '',
    employee_name: '',
    user_id: '',
    gender: null,
    date_of_birth: '',
    place_of_origin: '',
    hire_date: '',
    manager_employee_id: '',
    status: 'ACTIVE',
    description: '',
  }
  employeeModal.value = true
}
function openEdit(row: Employee | null): void {
  if (!row) return
  editingEmployeeId.value = String(row.id)
  employeeForm.value = {
    employee_code: row.employee_code,
    employee_name: row.employee_name,
    user_id: row.user_id ? String(row.user_id) : '',
    gender: row.gender ?? null,
    date_of_birth: row.date_of_birth ?? '',
    place_of_origin: row.place_of_origin ?? '',
    hire_date: row.hire_date ?? '',
    manager_employee_id: row.manager_employee_id ? String(row.manager_employee_id) : '',
    status: row.status,
    description: row.description ?? '',
  }
  employeeModal.value = true
}
function openEmployeeDetail(row: Employee): void {
  selectedId.value = String(row.id)
  employeeDetailOpen.value = true
}
function managerWouldCycle(candidate: string): boolean {
  return wouldCreateManagerCycle(employeeLookup.value, editingEmployeeId.value ?? '', candidate)
}
function managerError(): string | null {
  if (employeeForm.value.manager_employee_id === editingEmployeeId.value)
    return t('people.managerSelfError')
  if (managerWouldCycle(employeeForm.value.manager_employee_id))
    return t('people.managerCycleError')
  const selectedManager = employeeLookup.value.find(
    (row) => String(row.id) === employeeForm.value.manager_employee_id,
  )
  if (selectedManager && selectedManager.status !== 'ACTIVE')
    return t('people.managerInactiveError')
  return null
}
async function saveEmployeeForm(): Promise<void> {
  const managerIssue = managerError()
  if (managerIssue) {
    error.value = new Error(managerIssue)
    return
  }
  busy.value = true
  error.value = null
  try {
    const payload = {
      employee_code: employeeForm.value.employee_code,
      employee_name: employeeForm.value.employee_name,
      gender: employeeForm.value.gender,
      status: employeeForm.value.status,
      ...(employeeForm.value.user_id ? { user_id: employeeForm.value.user_id } : {}),
      ...(employeeForm.value.date_of_birth
        ? { date_of_birth: employeeForm.value.date_of_birth }
        : { date_of_birth: null }),
      ...(employeeForm.value.place_of_origin
        ? { place_of_origin: employeeForm.value.place_of_origin }
        : { place_of_origin: null }),
      ...(employeeForm.value.hire_date
        ? { hire_date: employeeForm.value.hire_date }
        : { hire_date: null }),
      ...(employeeForm.value.manager_employee_id
        ? { manager_employee_id: employeeForm.value.manager_employee_id }
        : { manager_employee_id: null }),
      ...(employeeForm.value.description
        ? { description: employeeForm.value.description }
        : { description: null }),
    }
    const saved = await saveEmployee(payload as unknown as EmployeeCreate, editingEmployeeId.value)
    upsertEmployeeLookup(saved)
    employeeModal.value = false
    await load()
  } catch (caught: unknown) {
    error.value = caught
    reportApiError(caught)
  } finally {
    busy.value = false
  }
}
async function toggleEmployee(row: Employee): Promise<void> {
  busy.value = true
  error.value = null
  try {
    const updated = await changeEmployeeState(
      String(row.id),
      row.status === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE',
    )
    upsertEmployeeLookup(updated)
    await load()
  } catch (caught: unknown) {
    error.value = caught
    reportApiError(caught)
  } finally {
    busy.value = false
  }
}
function openAssignmentCreate(): void {
  editingAssignmentId.value = undefined
  assignmentForm.value = {
    organization_id: '',
    department_id: '',
    position_id: '',
    is_primary: false,
    effective_from: today(),
    effective_to: '',
    status: 'ACTIVE',
    description: '',
  }
  assignmentModal.value = true
}
function openAssignmentEdit(row: EmployeeAssignment): void {
  editingAssignmentId.value = String(row.id)
  assignmentForm.value = {
    organization_id: String(row.organization_id),
    department_id: row.department_id ? String(row.department_id) : '',
    position_id: row.position_id ? String(row.position_id) : '',
    is_primary: row.is_primary,
    effective_from: row.effective_from,
    effective_to: row.effective_to ?? '',
    status: row.status,
    description: row.description ?? '',
  }
  assignmentModal.value = true
}
const assignmentOverlap = computed(() =>
  hasPrimaryOverlap(
    selectedEmployeeAssignments.value,
    assignmentForm.value,
    editingAssignmentId.value,
  ),
)
async function saveAssignmentForm(): Promise<void> {
  if (assignmentOverlap.value) {
    error.value = new Error(t('people.primaryOverlap'))
    return
  }
  if (!assignmentForm.value.organization_id) {
    error.value = new Error(t('people.organizationRequired'))
    return
  }
  if (!selectedId.value) return
  busy.value = true
  error.value = null
  try {
    const base = {
      organization_id: assignmentForm.value.organization_id,
      ...(assignmentForm.value.department_id
        ? { department_id: assignmentForm.value.department_id }
        : {}),
      ...(assignmentForm.value.position_id
        ? { position_id: assignmentForm.value.position_id }
        : {}),
      is_primary: assignmentForm.value.is_primary,
      effective_from: assignmentForm.value.effective_from,
      effective_to: assignmentForm.value.effective_to || null,
      status: assignmentForm.value.status,
      ...(assignmentForm.value.description
        ? { description: assignmentForm.value.description }
        : { description: null }),
    }
    const payload = editingAssignmentId.value
      ? (base as EmployeeAssignmentPatch)
      : ({ ...base, employee_id: selectedId.value } as EmployeeAssignmentCreate)
    await saveAssignment(payload, editingAssignmentId.value)
    assignmentModal.value = false
    await Promise.all([loadAssignments(), loadAllAssignments()])
    await load()
  } catch (caught: unknown) {
    error.value = caught
    reportApiError(caught)
  } finally {
    busy.value = false
  }
}
async function toggleAssignment(row: EmployeeAssignment): Promise<void> {
  busy.value = true
  error.value = null
  try {
    await changeAssignmentState(String(row.id), row.status === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE')
    await Promise.all([loadAssignments(), loadAllAssignments()])
  } catch (caught: unknown) {
    error.value = caught
    reportApiError(caught)
  } finally {
    busy.value = false
  }
}
function displayDate(value: string | null | undefined): string {
  if (!value) return '—'
  return new Intl.DateTimeFormat(locale.value, {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).format(new Date(`${value}T00:00:00`))
}
watch(selectedId, async () => {
  activeTab.value = 'overview'
  await loadAssignments()
})
onMounted(async () => {
  await Promise.all([loadLookups(), load(), loadAllAssignments()])
})
</script>

<template>
  <div class="page-heading">
    <div>
      <h1>{{ t('people.employeesTitle') }}</h1>
      <p>{{ t('people.employeesDescription') }}</p>
    </div>
    <button
      v-if="hasBasePermission('employee', 'create')"
      type="button"
      class="primary-button"
      @click="openCreate"
    >
      ＋ {{ t('common.create') }}
    </button>
  </div>
  <section class="content-panel location-panel">
    <div class="location-toolbar">
      <ListQueryControls
        :model-value="state.query"
        :filters="filters"
        @apply="applyQuery"
        @reset="resetQuery"
      /><button type="button" class="secondary-button" @click="void load()">
        ↻ {{ t('common.refresh') }}
      </button>
    </div>
    <p v-if="error" class="inline-error" role="alert">
      {{ t('people.requestError') }} <span v-if="error instanceof Error">{{ error.message }}</span>
    </p>
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
        <template #cell-employee_code="{ row }">
          <strong>{{ row.employee_code }}</strong>
        </template>
        <template #cell-actions="{ row }">
          <TableRowActions
            :active="row.status === 'ACTIVE'"
            :busy="busy"
            :show-view="true"
            :show-edit="true"
            :show-toggle="true"
            view-permission="employee.view"
            edit-permission="employee.edit"
            toggle-permission="employee.setstate"
            :view-label="t('common.view')"
            :edit-label="t('common.edit')"
            :activate-label="t('common.activate')"
            :deactivate-label="t('common.deactivate')"
            @view="openEmployeeDetail(row)"
            @edit="openEdit(row)"
            @toggle="toggleEmployee(row)"
          />
        </template>
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
  <div v-if="employeeDetailOpen && selected" class="employee-detail-backdrop">
    <section
      class="employee-detail-modal"
      role="dialog"
      aria-modal="true"
      :aria-label="selected.employee_name"
    >
      <header class="detail-header">
        <div>
          <span class="eyebrow">{{ selected.employee_code }}</span>
          <h2>{{ selected.employee_name }}</h2>
          <p>
            {{ t(`status.${selected.status.toLowerCase()}`) }} ·
            {{ selected.user_id ? t('people.userLinked') : t('people.userNotLinked') }}
          </p>
        </div>
        <button
          v-if="hasBasePermission('employee', 'edit')"
          type="button"
          class="secondary-button"
          @click="openEdit(selected)"
        >
          {{ t('common.edit') }}
        </button>
        <button
          type="button"
          class="icon-button employee-detail-close"
          :aria-label="t('common.close')"
          @click="employeeDetailOpen = false"
        >
          ×
        </button>
      </header>
      <div class="detail-tabs" role="tablist">
        <button
          v-for="tab in ['overview', 'assignments', 'contacts', 'audit'] as Tab[]"
          :key="tab"
          type="button"
          role="tab"
          :aria-selected="activeTab === tab"
          @click="activeTab = tab"
        >
          {{ t(`people.tabs.${tab}`) }}
        </button>
      </div>
      <div v-if="activeTab === 'overview'" class="detail-grid">
        <div>
          <span>{{ t('people.gender') }}</span
          ><strong>{{ genderLabel(selected.gender ?? null) }}</strong>
        </div>
        <div>
          <span>{{ t('people.dateOfBirth') }}</span
          ><strong>{{ displayDate(selected.date_of_birth) }}</strong>
        </div>
        <div>
          <span>{{ t('people.hireDate') }}</span
          ><strong>{{ displayDate(selected.hire_date) }}</strong>
        </div>
        <div>
          <span>{{ t('people.manager') }}</span
          ><strong>{{
            state.rows.value.find((row) => String(row.id) === String(selected.manager_employee_id))
              ?.employee_name ?? '—'
          }}</strong>
        </div>
        <div class="detail-grid__wide">
          <span>{{ t('people.placeOfOrigin') }}</span
          ><strong>{{ selected.place_of_origin || '—' }}</strong>
        </div>
        <div class="detail-grid__wide">
          <span>{{ t('people.description') }}</span
          ><strong>{{ selected.description || '—' }}</strong>
        </div>
      </div>
      <div v-else-if="activeTab === 'assignments'" class="assignment-panel">
        <header>
          <div class="detail-tabs__sub" role="tablist">
            <button
              type="button"
              :aria-selected="assignmentMode === 'active'"
              @click="assignmentMode = 'active'"
            >
              {{ t('people.activeAssignments') }}</button
            ><button
              type="button"
              :aria-selected="assignmentMode === 'history'"
              @click="assignmentMode = 'history'"
            >
              {{ t('people.assignmentHistory') }}
            </button>
          </div>
          <button
            v-if="hasBasePermission('employee_assignment', 'create')"
            type="button"
            class="primary-button"
            @click="openAssignmentCreate"
          >
            ＋ {{ t('people.addAssignment') }}
          </button>
        </header>
        <p v-if="assignmentOverlap" class="hint">{{ t('people.primaryOverlap') }}</p>
        <table class="data-table">
          <thead>
            <tr>
              <th>{{ t('people.organization') }}</th>
              <th>{{ t('people.department') }}</th>
              <th>{{ t('people.position') }}</th>
              <th>{{ t('people.primary') }}</th>
              <th>{{ t('people.effectiveFrom') }}</th>
              <th>{{ t('people.effectiveTo') }}</th>
              <th>{{ t('people.status') }}</th>
              <th>{{ t('people.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in selectedAssignments" :key="row.id">
              <td>{{ organizationName(row.organization_id) }}</td>
              <td>{{ departmentName(row.department_id) }}</td>
              <td>{{ positionName(row.position_id) }}</td>
              <td>{{ row.is_primary ? t('people.primary') : t('people.secondary') }}</td>
              <td>{{ displayDate(row.effective_from) }}</td>
              <td>{{ displayDate(row.effective_to) }}</td>
              <td>{{ t(`status.${row.status.toLowerCase()}`) }}</td>
              <td>
                <TableRowActions
                  :active="row.status === 'ACTIVE'"
                  :busy="busy"
                  :show-edit="true"
                  :show-toggle="true"
                  edit-permission="employee_assignment.edit"
                  toggle-permission="employee_assignment.setstate"
                  :edit-label="t('common.edit')"
                  :activate-label="t('common.activate')"
                  :deactivate-label="t('common.deactivate')"
                  @edit="openAssignmentEdit(row)"
                  @toggle="toggleAssignment(row)"
                />
              </td>
            </tr>
          </tbody>
        </table>
        <p v-if="selectedAssignments.length === 0" class="empty-state">
          {{ t('people.noAssignments') }}
        </p>
      </div>
      <ContactList
        v-else-if="activeTab === 'contacts'"
        :owner-id="String(selected?.id ?? '')"
        owner-type="EMPLOYEE"
      />
      <div v-else class="audit-panel">
        <header class="audit-panel__header">
          <div>
            <span class="eyebrow">{{ selected.employee_code }}</span>
            <h3>{{ t('people.audit') }}</h3>
          </div>
          <span class="status-pill" :class="{ 'status-pill--off': selected.status !== 'ACTIVE' }">
            {{ t(`status.${selected.status.toLowerCase()}`) }}
          </span>
        </header>
        <p>{{ t('people.auditDescription') }}</p>
        <dl class="audit-panel__summary">
          <dt>{{ t('people.employeeCode') }}</dt>
          <dd>{{ selected.employee_code }}</dd>
          <dt>{{ t('people.status') }}</dt>
          <dd>{{ t(`status.${selected.status.toLowerCase()}`) }}</dd>
          <dt>{{ t('people.hireDate') }}</dt>
          <dd>{{ displayDate(selected.hire_date) }}</dd>
          <dt>{{ t('people.manager') }}</dt>
          <dd>
            {{
              employeeLookup.find((row) => String(row.id) === String(selected.manager_employee_id))
                ?.employee_name ?? '—'
            }}
          </dd>
        </dl>
      </div>
    </section>
  </div>
  <div v-if="employeeModal" class="modal-backdrop">
    <section
      class="modal-card"
      role="dialog"
      aria-modal="true"
      :aria-label="t('people.employeeForm')"
    >
      <header class="modal-card__header">
        <h2>
          {{ editingEmployeeId ? t('common.edit') : t('common.create') }} {{ t('people.employee') }}
        </h2>
        <button
          type="button"
          class="icon-button"
          :aria-label="t('common.close')"
          @click="employeeModal = false"
        >
          ×
        </button>
      </header>
      <form class="modal-card__body" @submit.prevent="saveEmployeeForm">
        <div class="form-grid">
          <label
            ><span>{{ t('people.employeeCode') }}</span
            ><input v-model="employeeForm.employee_code" required /></label
          ><label
            ><span>{{ t('people.name') }}</span
            ><input v-model="employeeForm.employee_name" required
          /></label>
        </div>
        <div class="form-grid">
          <label
            ><span>{{ t('people.userId') }}</span
            ><input v-model="employeeForm.user_id" inputmode="numeric" /></label
          ><label
            ><span>{{ t('people.gender') }}</span
            ><select v-model="employeeForm.gender">
              <option :value="null">{{ t('people.genderUnspecified') }}</option>
              <option value="MALE">{{ t('people.genderMale') }}</option>
              <option value="FEMALE">{{ t('people.genderFemale') }}</option>
              <option value="OTHER">{{ t('people.genderOther') }}</option>
            </select></label
          >
        </div>
        <div class="form-grid">
          <label
            ><span>{{ t('people.dateOfBirth') }}</span
            ><input v-model="employeeForm.date_of_birth" type="date" /></label
          ><label
            ><span>{{ t('people.hireDate') }}</span
            ><input v-model="employeeForm.hire_date" type="date"
          /></label>
        </div>
        <label
          ><span>{{ t('people.placeOfOrigin') }}</span
          ><input v-model="employeeForm.place_of_origin" /></label
        ><label
          ><span>{{ t('people.manager') }}</span
          ><SearchableSelect
            :model-value="employeeForm.manager_employee_id"
            :options="managerSelectOptions"
            :placeholder="t('people.noManager')"
            :aria-label="t('people.manager')"
            @update:model-value="
              (value) => (employeeForm.manager_employee_id = typeof value === 'string' ? value : '')
            "
          /><small v-if="managerError()" class="field-error">{{ managerError() }}</small></label
        ><label
          ><span>{{ t('people.status') }}</span
          ><select v-model="employeeForm.status">
            <option value="ACTIVE">{{ t('status.active') }}</option>
            <option value="INACTIVE">{{ t('status.inactive') }}</option>
          </select></label
        ><label
          ><span>{{ t('people.description') }}</span
          ><textarea v-model="employeeForm.description" rows="3" />
        </label>
        <footer class="modal-card__footer">
          <button type="button" class="secondary-button" @click="employeeModal = false">
            {{ t('common.cancel') }}</button
          ><button
            type="submit"
            class="primary-button"
            :disabled="
              busy ||
              !!managerError() ||
              !hasBasePermission('employee', editingEmployeeId ? 'edit' : 'create')
            "
          >
            {{ busy ? t('common.saving') : t('common.save') }}
          </button>
        </footer>
      </form>
    </section>
  </div>
  <div v-if="assignmentModal" class="modal-backdrop">
    <section
      class="modal-card"
      role="dialog"
      aria-modal="true"
      :aria-label="t('people.assignmentForm')"
    >
      <header class="modal-card__header">
        <h2>
          {{ editingAssignmentId ? t('common.edit') : t('common.create') }}
          {{ t('people.assignment') }}
        </h2>
        <button
          type="button"
          class="icon-button"
          :aria-label="t('common.close')"
          @click="assignmentModal = false"
        >
          ×
        </button>
      </header>
      <form class="modal-card__body" @submit.prevent="saveAssignmentForm">
        <label
          ><span>{{ t('people.organization') }}</span
          ><SearchableSelect
            :model-value="assignmentForm.organization_id"
            :options="organizationSelectOptions"
            :placeholder="t('people.organizationRequired')"
            :aria-label="t('people.organization')"
            @update:model-value="
              (value) => (assignmentForm.organization_id = typeof value === 'string' ? value : '')
            "
        /></label>
        <div class="form-grid">
          <label
            ><span>{{ t('people.department') }}</span
            ><SearchableSelect
              :model-value="assignmentForm.department_id"
              :options="departmentSelectOptions"
              :placeholder="t('people.none')"
              :aria-label="t('people.department')"
              @update:model-value="
                (value) => (assignmentForm.department_id = typeof value === 'string' ? value : '')
              " /></label
          ><label
            ><span>{{ t('people.position') }}</span
            ><SearchableSelect
              :model-value="assignmentForm.position_id"
              :options="positionSelectOptions"
              :placeholder="t('people.none')"
              :aria-label="t('people.position')"
              @update:model-value="
                (value) => (assignmentForm.position_id = typeof value === 'string' ? value : '')
              "
          /></label>
        </div>
        <label class="checkbox-row"
          ><input v-model="assignmentForm.is_primary" type="checkbox" />
          <span>{{ t('people.primary') }}</span></label
        >
        <div class="form-grid">
          <label
            ><span>{{ t('people.effectiveFrom') }}</span
            ><input v-model="assignmentForm.effective_from" type="date" required /></label
          ><label
            ><span>{{ t('people.effectiveTo') }}</span
            ><input v-model="assignmentForm.effective_to" type="date"
          /></label>
        </div>
        <p v-if="assignmentOverlap" class="field-error">{{ t('people.primaryOverlap') }}</p>
        <label
          ><span>{{ t('people.status') }}</span
          ><select v-model="assignmentForm.status">
            <option value="ACTIVE">{{ t('status.active') }}</option>
            <option value="INACTIVE">{{ t('status.inactive') }}</option>
          </select></label
        ><label
          ><span>{{ t('people.description') }}</span
          ><textarea v-model="assignmentForm.description" rows="3" />
        </label>
        <footer class="modal-card__footer">
          <button type="button" class="secondary-button" @click="assignmentModal = false">
            {{ t('common.cancel') }}</button
          ><button
            type="submit"
            class="primary-button"
            :disabled="
              busy ||
              assignmentOverlap ||
              !hasBasePermission('employee_assignment', editingAssignmentId ? 'edit' : 'create')
            "
          >
            {{ busy ? t('common.saving') : t('common.save') }}
          </button>
        </footer>
      </form>
    </section>
  </div>
</template>

<style scoped>
.people-layout {
  overflow: hidden;
}
.row-selected td {
  background: #f0f8fb;
}
.location-panel {
  display: grid;
  gap: 14px;
  min-width: 0;
  padding: 18px 20px 20px;
}
.location-toolbar {
  display: flex;
  align-items: end;
  justify-content: space-between;
  gap: 10px;
  min-width: 0;
  flex-wrap: wrap;
}
.employee-detail-backdrop {
  position: fixed;
  inset: 0;
  z-index: 50;
  display: grid;
  place-items: center;
  padding: 20px;
  background: rgb(18 38 54 / 45%);
}
.employee-detail-modal {
  width: min(1800px, calc(100vw - 32px));
  min-width: 0;
  max-height: calc(100vh - 40px);
  overflow-x: hidden;
  overflow-y: auto;
  border: 1px solid #d5e1e8;
  border-radius: 7px;
  background: #fff;
  box-shadow: 0 22px 70px rgb(18 42 62 / 25%);
}
.detail-header,
.assignment-panel > header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 14px;
  flex-wrap: wrap;
}
.detail-header h2 {
  margin: 8px 0;
  color: #123653;
  font-size: 25px;
}
.detail-header p,
.eyebrow {
  margin: 0;
  color: #536b7b;
  font-size: 12px;
}
.detail-tabs {
  display: flex;
  gap: 4px;
  margin: 22px 0 0;
  padding: 0 22px;
  border-bottom: 1px solid #e1e9ee;
  min-width: 0;
  overflow-x: auto;
}
.detail-tabs button,
.detail-tabs__sub button {
  border: 0;
  border-bottom: 2px solid transparent;
  padding: 10px 12px;
  background: transparent;
  color: #61768a;
  cursor: pointer;
}
.detail-tabs button[aria-selected='true'],
.detail-tabs__sub button[aria-selected='true'] {
  border-bottom-color: #1674a6;
  color: #1674a6;
  font-weight: 700;
}
.detail-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 16px;
  padding: 24px 22px 28px;
}
.detail-grid > div {
  display: grid;
  gap: 4px;
  min-width: 0;
}
.detail-grid span {
  color: #536b7b;
  font-size: 11px;
}
.detail-grid strong {
  color: #2c4a62;
  font-size: 13px;
  overflow-wrap: anywhere;
}
.detail-grid__wide {
  grid-column: span 3;
}
.detail-tabs__sub {
  display: flex;
}
.assignment-panel header {
  align-items: center;
}
.detail-header,
.assignment-panel,
.employee-detail-modal > .detail-header {
  padding: 24px 22px 0;
}
.employee-detail-modal > .detail-header {
  padding-bottom: 0;
}
.employee-detail-close {
  align-self: start;
}
.assignment-panel {
  padding-bottom: 24px;
}
.assignment-panel > .data-table,
.assignment-panel > .empty-state,
.assignment-panel > .hint {
  margin-left: 0;
  margin-right: 0;
}
.employee-detail-modal :deep(.contact-list) {
  width: 100%;
  min-width: 0;
  padding: 0 22px 24px;
}
.employee-detail-modal :deep(.contact-list__header),
.employee-detail-modal :deep(.contact-list__toolbar) {
  min-width: 0;
}
.hint,
.field-error,
.inline-error {
  color: #b64444;
  font-size: 12px;
}
.empty-state {
  padding: 22px;
}
.audit-panel {
  display: grid;
  gap: 12px;
  padding: 24px 22px 28px;
}
.audit-panel__header {
  display: flex;
  align-items: start;
  justify-content: space-between;
  gap: 14px;
}
.audit-panel__header h3 {
  margin: 8px 0 0;
  color: #183b57;
  font-size: 18px;
}
.audit-panel > p {
  margin: 0;
  color: #536b7b;
}
.audit-panel__summary {
  display: grid;
  grid-template-columns: 160px minmax(0, 1fr);
  gap: 0;
  margin: 0;
}
.audit-panel__summary dt,
.audit-panel__summary dd {
  margin: 0;
  padding: 10px 0;
  border-bottom: 1px solid #edf2f4;
}
.audit-panel__summary dt {
  color: #536b7b;
  font-size: 12px;
  font-weight: 700;
}
.audit-panel__summary dd {
  min-width: 0;
  color: #183b57;
  overflow-wrap: anywhere;
}
.checkbox-row {
  display: flex !important;
  grid-template-columns: auto 1fr;
  align-items: center;
}
.modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 60;
  display: grid;
  place-items: center;
  padding: 20px;
  background: rgb(18 38 54 / 45%);
}
.modal-card {
  width: min(100%, 760px);
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
.modal-card__body input,
.modal-card__body select,
.modal-card__body textarea {
  min-height: 34px;
  border: 1px solid #c7d6df;
  border-radius: 4px;
  padding: 7px 9px;
  color: #183b57;
  font: inherit;
}
.modal-card__footer {
  justify-content: flex-end;
  margin: 6px -18px -18px;
  border-top: 1px solid #e4ebef;
  border-bottom: 0;
}
.icon-button {
  border: 0;
  background: transparent;
  color: #536b7b;
  font-size: 22px;
  cursor: pointer;
}
@media (max-width: 900px) {
  .detail-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .detail-grid__wide {
    grid-column: span 2;
  }
}
@media (max-width: 640px) {
  .detail-grid {
    grid-template-columns: 1fr;
  }
  .detail-grid__wide {
    grid-column: auto;
  }
  .audit-panel__summary {
    grid-template-columns: 1fr;
  }
  .audit-panel__summary dt {
    border-bottom: 0;
    padding-bottom: 3px;
  }
  .audit-panel__summary dd {
    padding-top: 3px;
  }
  .data-table {
    min-width: 820px;
  }
  .content-panel {
    overflow-x: auto;
  }
}
</style>
