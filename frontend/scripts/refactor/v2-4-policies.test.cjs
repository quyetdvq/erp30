const assert = require('node:assert/strict')
const crypto = require('node:crypto')
const fs = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')
const { test } = require('node:test')
const ts = require('typescript')

const frontend = path.resolve(__dirname, '../..')
const base = path.join(frontend, 'src/features/security')
const page = fs.readFileSync(path.join(base, 'pages/SecurityPoliciesView.vue'), 'utf8')
const script = page.split('<script setup lang="ts">')[1]?.split('</script>')[0]
const moduleDir = path.join(base, 'policy-editor')
const moduleNames = [
  'types',
  'model',
  'payload',
  'validation',
  'usePolicyReferences',
  'usePolicyParticipants',
  'usePolicyWorkflowStates',
]
const modules = Object.fromEntries(
  moduleNames.map((name) => [name, fs.readFileSync(path.join(moduleDir, `${name}.ts`), 'utf8')]),
)
const ORIGINAL = JSON.parse(fs.readFileSync(path.join(__dirname, 'v2-4-policies-contract.json'), 'utf8'))
const digest = (source) => crypto.createHash('sha256').update(source).digest('hex')

function fingerprint(node) {
  const tokens = []
  function walk(part) {
    tokens.push(ts.SyntaxKind[part.kind])
    if (ts.isIdentifier(part) || ts.isLiteralExpression(part)) tokens.push(part.text)
    ts.forEachChild(part, walk)
  }
  walk(node)
  return digest(tokens.join('\u001f'))
}
function sourceFile(name, content) {
  const tree = ts.createSourceFile(name, content, ts.ScriptTarget.Latest, true)
  assert.equal(tree.parseDiagnostics.length, 0, `${name}: invalid TypeScript syntax`)
  return tree
}
function locate(tree, name, type) {
  let found = null
  function visit(node) {
    if (type === 'function' && ts.isFunctionDeclaration(node) && node.name?.text === name)
      found = node
    if (type === 'variable' && ts.isVariableDeclaration(node) && node.name?.text === name)
      found = node
    ts.forEachChild(node, visit)
  }
  visit(tree)
  assert.ok(found, `missing ${type} ${name}`)
  return found
}

function pure(name) {
  const out = { exports: {} }
  const js = ts.transpileModule(modules[name], {
    compilerOptions: { target: ts.ScriptTarget.ES2022, module: ts.ModuleKind.CommonJS },
  }).outputText
  vm.runInNewContext(js, { exports: out.exports, module: out })
  return out.exports
}

const ownerOf = (name) => {
  if (['loadReferences', 'loadActions', 'labelForOrganization', 'labelForResource',
       'actionCodeFor', 'labelForParticipant', 'participantOptionsFor', 'effectsSummary'].includes(name))
    return 'usePolicyReferences'
  return 'usePolicyParticipants'
}

test('Security Policies template and scoped CSS exactly match accepted V2.3 source', () => {
  const start = page.indexOf('<template>')
  const end = page.lastIndexOf('</template>')
  assert.ok(start > 0 && end > start)
  assert.equal(digest(page.slice(start, end + '</template>'.length)), ORIGINAL.template)
  assert.equal(digest(page.slice(page.indexOf('<style scoped>'))), ORIGINAL.style)
  for (const permission of [
    "hasBasePermission('policy', 'create')",
    "hasBasePermission('policy', 'edit')",
    'view-permission="policy.view"',
    'edit-permission="policy.edit"',
    'toggle-permission="policy.setstate"',
    "'ALLOW'",
    "'DENY'",
    "'ABSOLUTE_DENY'",
  ]) {
    assert.ok(page.includes(permission) || modules.types.includes(permission), `permission/effect ${permission}`)
  }
})

test('untouched list, save action selection and lifecycle toggle functions retain source AST', () => {
  assert.ok(script)
  const tree = sourceFile('SecurityPoliciesView.ts', script)
  for (const [name, expected] of Object.entries(ORIGINAL.pageHandlers))
    assert.equal(fingerprint(locate(tree, name, 'function')), expected, name)
  assert.equal(fingerprint(locate(tree, 'columns', 'variable')), ORIGINAL.variables.columns)
})

test('relocated catalogues, remote participant fetch and option computations preserve AST', () => {
  const trees = Object.fromEntries(
    Object.entries(modules).map(([name, body]) => [name, sourceFile(name, body)]),
  )
  for (const [name, expected] of Object.entries(ORIGINAL.movedFunctions)) {
    const target = ownerOf(name)
    assert.equal(fingerprint(locate(trees[target], name, 'function')), expected, name)
  }
  const movedVariables = {
    actionOptions: 'usePolicyReferences',
    participantSelectOptions: 'usePolicyParticipants',
    isRemoteParticipant: 'usePolicyParticipants',
    workflowStateOptions: 'usePolicyWorkflowStates',
  }
  for (const [name, target] of Object.entries(movedVariables))
    assert.equal(fingerprint(locate(trees[target], name, 'variable')), ORIGINAL.variables[name], name)
})

test('save still fails closed, surfaces duplicate scope and toggles status only through original service', () => {
  assert.ok(script)
  const save = script.slice(script.indexOf('async function save()'), script.indexOf('async function toggle('))
  const order = [
    'busy.value = true',
    'error.value = null',
    'duplicateScopeConflict.value = false',
    'if (workflowStatesLoading.value || workflowStatesUnavailable.value) return',
    'await savePolicy(policyPayload(form.value), editingId.value)',
    'markClean()',
    'modalOpen.value = false',
    'await load()',
    "notice?.code === 'POLICY_SCOPE_ALREADY_EXISTS'",
    'busy.value = false',
  ]
  let cursor = -1
  for (const needle of order) {
    const next = save.indexOf(needle, cursor + 1)
    assert.ok(next > cursor, `${needle} reordered/removed`)
    cursor = next
  }
  assert.match(script, /const status = row\.status === 'ACTIVE' \? 'INACTIVE' : 'ACTIVE'/)
  assert.match(script, /await changePolicyState\(String\(row\.id\), status\)/)
  assert.match(script, /currentParticipantRequestId\(\) === requestId/)
  assert.match(modules.usePolicyParticipants, /requestId !== participantRequestId/)
  assert.match(modules.usePolicyWorkflowStates, /requestId !== workflowStateRequestId/)
})

test('state catalog failure blocks save while preserving state-specific policy', () => {
  assert.match(modules.usePolicyWorkflowStates, /policyHasUnavailableState\(form\.value, result\.states\)/)
  assert.match(modules.usePolicyWorkflowStates, /workflowStatesUnavailable\.value = true/)
  assert.match(page, /workflowStatesLoading\s*\|\|\s*workflowStatesUnavailable\s*\|\|\s*!form\.resource_id/)
  assert.match(page, /workflowStatesLoading \|\|\s*workflowStatesUnavailable \|\|\s*form\.actions\.length === 0/)
  assert.match(modules.payload, /state_id: form\.state_id \|\| null/)
  assert.match(modules.types, /'ALLOW', 'DENY', 'ABSOLUTE_DENY'/)
  assert.ok(!page.includes('from \'@/api/generated/sdk.gen\''), 'no unexpected replacement calls')
})

test('deterministic differential mapping/payload/state/scope versus accepted V2.3 logic', () => {
  const model = pure('model')
  const payload = pure('payload')
  const validation = pure('validation')
  const legacyCreate = (organizations, resources) => ({
    organization_id: organizations[0] ? String(organizations[0].id) : '',
    resource_id: resources[0] ? String(resources[0].id) : '',
    state_id: '', participant_type: 'USER', participant_ref_id: '', actions: [],
  })
  const legacyEdit = (policy) => ({
    organization_id: String(policy.organization_id),
    resource_id: String(policy.resource_id),
    state_id: policy.state_id ? String(policy.state_id) : '',
    participant_type: policy.participant_type,
    participant_ref_id: String(policy.participant_ref_id),
    actions: policy.actions.map((a) => ({ resource_action_id: String(a.resource_action_id), effect: a.effect })),
  })
  const legacyPayload = (f) => ({
    organization_id: f.organization_id, resource_id: f.resource_id,
    state_id: f.state_id || null, participant_type: f.participant_type,
    participant_ref_id: f.participant_ref_id, actions: f.actions,
  })
  const legacyScope = (f) => [f.organization_id, f.resource_id, f.state_id || 'ALL_STATES', f.participant_type, f.participant_ref_id].join(' / ')
  let seed = 124545
  const random = () => { seed = (Math.imul(seed, 1664525) + 1013904223) >>> 0; return seed }
  const rand = (n) => random() % n
  const types = ['USER', 'GROUP', 'POSITION', 'DEPARTMENT', 'ORGANIZATION']
  const effects = ['ALLOW', 'DENY', 'ABSOLUTE_DENY']
  const same = (a,b,why) => assert.equal(JSON.stringify(a),JSON.stringify(b),why)
  for (let i = 0; i < 15000; i++) {
    const id = () => String(9007199254740991n + BigInt(random()))
    const policy = {
      id: id(), organization_id: id(), resource_id: id(),
      state_id: rand(4) ? id() : null,
      participant_type: types[rand(types.length)], participant_ref_id: id(),
      actions: Array.from({ length: rand(6) }, () => ({ resource_action_id: id(), effect: effects[rand(effects.length)] })),
    }
    const oldForm = legacyEdit(policy)
    const newForm = model.policyToDraft(policy)
    same(newForm, oldForm, 'row-to-form '+i)
    same(payload.policyPayload(newForm), legacyPayload(oldForm), 'wire payload '+i)
    same(validation.policyScopeSummary(newForm), legacyScope(oldForm), 'scope summary '+i)
    const allowed = Array.from({ length: rand(6) }, () => ({ id: id() }))
    const oldBlocked = Boolean(oldForm.state_id && !allowed.some(item => item.id === oldForm.state_id))
    assert.equal(validation.policyHasUnavailableState(newForm, allowed), oldBlocked, 'state predicate '+i)
    const orgs = Array.from({ length: rand(3) }, () => ({ id: id() }))
    const resources = Array.from({ length: rand(3) }, () => ({ id: id() }))
    same(model.createPolicyDraft(orgs,resources), legacyCreate(orgs, resources), 'create default '+i)
  }
})
