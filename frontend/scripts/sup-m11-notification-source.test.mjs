import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

const root = resolve(import.meta.dirname, '../..')
const file = path => readFileSync(resolve(root, path), 'utf8')
const dialog = file('frontend/src/features/supplier/components/SupplierStateAction.vue')
const write = file('backend/app/Modules/Supplier/SupplierWriteService.php')
const notificationTarget = file('backend/app/Modules/Notifications/SupplierNotificationTarget.php')
const assignee = file('backend/app/Modules/Notifications/WorkflowNotificationAssigneesService.php')
const producer = file('backend/app/Modules/Notifications/NotificationOutboxProducer.php')
const eventRules = file('backend/app/Modules/Notifications/WorkflowNotificationRules.php')
const delivery = file('backend/app/Modules/Notifications/Events/WorkflowActionRequiredHandler.php')
const push = file('backend/app/Modules/Notifications/NotificationPushWorker.php')
const myWork = file('backend/app/Modules/Notifications/MyWorkflowService.php')
const generic = file('backend/app/Modules/Workflow/Execution/BusinessWorkflowRules.php')
const api = file('frontend/src/services/businessWorkflow.ts')
const nav = file('frontend/src/services/notificationInbox.ts')
const types = file('frontend/src/services/myWorkflow.ts')
const contract = file('contracts/openapi/openapi.yaml')

const check = (name, condition) => test(name, () => assert.ok(condition))
check('Supplier renders M11 notification section only behind the existing inbox UI flag',
  dialog.includes('v-if="currentSupplier && inboxUiEnabled"') && dialog.includes("t('workflow.notificationSection')"))
check('same CUS remote recipient picker (max 25) and save button',
  dialog.includes('<RemoteMultiSearchableSelect') && dialog.includes(':max-selections="25"') &&
  dialog.includes("t('workflow.saveNotificationAssignees')"))
check('all Supplier assignment calls use M11 endpoints and the canonical resource',
  dialog.includes("getWorkflowNotificationAssignees('supplier'") &&
  dialog.includes("getWorkflowAssigneeCandidates('supplier'") &&
  dialog.includes("replaceWorkflowNotificationAssignees(\n      'supplier'"))
check('saved revision CAS is independent of Supplier row_version CAS',
  dialog.includes('assignees.value.revision') && dialog.includes('snapshot.row_version') &&
  dialog.includes('supplierStateBody(target, snapshot.row_version)'))
check('dirty unsaved recipients prevent Submit, just as in CUS',
  dialog.includes('!assigneeChanged.value') && dialog.includes("t('workflow.notificationAssignmentDirty')"))
check('saving recipients remains a separate explicit action before state transition',
  dialog.includes('async function saveAssignees()') && dialog.includes('async function apply()'))
check('Supplier state still never uses generic execute (CAS cannot be bypassed)',
  !dialog.includes('executeBusinessWorkflowOption') &&
  dialog.includes('supplierSetState(String(snapshot.id), supplierStateBody(target, snapshot.row_version))'))
check('Supplier mutation enqueues inside owning transaction after CAS and History',
  write.includes("NotificationOutboxProducer::enqueueWorkflowTransition(") &&
  write.includes("$this->db, 'supplier', $id, (string)$old['status'], $target, 'setstate'") &&
  write.indexOf('->transitioned(\'supplier\'') < write.indexOf('NotificationOutboxProducer::enqueueWorkflowTransition(') &&
  write.indexOf('NotificationOutboxProducer::enqueueWorkflowTransition(') < write.indexOf('private function transaction('))
check('M11 assignment target SQL filters using trusted GROUP and does not expose bank columns',
  assignee.includes("->where('group_organization_id', $this->access->trustedGroupRootId())") &&
  assignee.includes("select('id,group_organization_id,status,row_version')"))
check('M11 recipient eligibility rechecks current Supplier VIEW + setstate + transition PBAC',
  notificationTarget.includes("$access->canView(self::RESOURCE, $row)") &&
  notificationTarget.includes("$access->decideMutation(self::RESOURCE, 'setstate', $row)") &&
  notificationTarget.includes("$access->decideTransitionAction(self::RESOURCE, 'setstate', $row)"))
check('M11 action targets are configured Lifecycle transitions, not literal states',
  notificationTarget.includes("transitionTarget(self::RESOURCE, $row, 'setstate')") &&
  !notificationTarget.includes("'DRAFT' =>") && !notificationTarget.includes("'ACTIVE' =>"))
check('Notification outbox accepts supplier while validated generic adapters are retained',
  producer.includes("if ($resource !== 'supplier')") &&
  producer.includes('BusinessWorkflowRules::table($resource)'))
check('M11 event schema only permits Supplier CAS setstate',
  eventRules.includes("$resource === 'supplier' && $action !== 'setstate'"))
check('Inbox dispatcher rechecks Supplier recipient rights without invoking generic Workflow',
  delivery.includes("$resource === 'supplier'") &&
  delivery.includes('SupplierNotificationTarget::options(new User($userRow), $this->db, $id, $target)'))
check('Push worker rechecks Supplier recipient permissions against live State',
  push.includes("$payload['resource_type'] === 'supplier'") &&
  push.includes('SupplierNotificationTarget::options(new User($user), $this->db, (string) $event[\'source_id\'])'))
check('My Work read-only workflow list uses dedicated Supplier adapter',
  myWork.includes("(string) $row['resource_type'] === 'supplier'") &&
  myWork.includes('SupplierNotificationTarget::options($this->actor, $this->db'))
check('generic Workflow table() still rejects Supplier at execution boundary',
  !generic.includes("'supplier'=>'suppliers'") && !generic.includes("'supplier' => 'suppliers'"))
check('Supplier is offered in My Work and notification links use canonical detail route',
  types.includes("'customer_contact', 'supplier',") &&
  nav.includes("if (resourceType === 'supplier') return { path: `/suppliers/${id}`, kind: 'detail' }"))
check('M11 existing wire endpoints are reused without any Supplier-specific notification routes',
  api.includes('/notification-assignees') && api.includes('/assignee-candidates') &&
  !dialog.includes('/api/v1/suppliers/') && !dialog.includes('workflow/execute'))
check('CUSR unchanged and localization includes new My Work Supplier resource',
  file('frontend/src/i18n/locales/vi.ts').includes("supplier: 'Nhà cung cấp',") &&
  file('frontend/src/i18n/locales/en.ts').includes("supplier: 'Supplier',"))

check('OpenAPI notification-only resource includes Supplier but generic Workflow execute stays closed',
  /WorkflowNotificationResource:\s*[\s\S]*?supplier_contact, supplier\]/.test(contract) &&
  /notification-assignees:\s*parameters:\s*- \$ref: '#\/components\/parameters\/WorkflowNotificationResource'/.test(contract) &&
  /BusinessWorkflowResource:\s*[\s\S]*?enum: \[province[^\n]+employee_contact\]/.test(contract))
check('existing M11 candidates endpoint is now documented with actual parser bounds',
  contract.includes('operationId: listWorkflowAssigneeCandidates') &&
  contract.includes('maximum: 50, default: 25') &&
  contract.includes('maxLength: 100') &&
  contract.includes('maxLength: 500'))
