const assert = require('node:assert/strict')
const crypto = require('node:crypto')
const fs = require('node:fs')
const path = require('node:path')
const { test } = require('node:test')
const ts = require('typescript')

const frontend = path.resolve(__dirname, '../..')
const people = path.join(frontend, 'src/features/people')
const page = fs.readFileSync(path.join(people, 'pages/PeopleEmployeesView.vue'), 'utf8')
const script = page.split('<script setup lang="ts">')[1]?.split('</script>')[0]
const modules = Object.fromEntries(
  ['types', 'model', 'payload', 'validation', 'useEmployeeLookups', 'useEmployeeAssignments'].map(
    (name) => [name, fs.readFileSync(path.join(people, `employee-editor/${name}.ts`), 'utf8')],
  ),
)
const digest = (source) => crypto.createHash('sha256').update(source).digest('hex')

// Pinned to the accepted V2.2.1 ZIP; do not recalculate from edited V2.3 files.
const ORIGINAL = {
  template: '9e52ea10589360dfa46f71f8af8fc5fc3cc7c04b046251daa733f233c5d0d91f',
  style: 'fe969edf4ee2d965606494b78d60af5060dcf4b1c37c76ffd2630a9b3376ab11',
  pageHandlers: {
    closeEmployeeForm: '6c293b18d6127a5e104ec82c0e40f6017be6b8c19154fb5b3beb97946cd28b5a',
    closeAssignmentForm: '4d529a34b4c112bbd7a52894b06dc563dbc494d0dbb706846f78986c4d5fc12b',
    load: '5e47dcdd0e07f9dbb7aa0bc864c172a5f86163f5424547880129718da171f4bb',
    applyQuery: 'c4beba81c1fe9ce5a86b1727d89db7000fedb3ee6db2807bcaa871fb0d9e0427',
    resetQuery: '3e033fe19c6723ed9359cb846446effee30dbadcc92ab34d1f928c8717027d5d',
    openEmployeeDetail: 'ddb29dbe89efa5ea74fde392367b1fd75ee255401656aef5e60c206f1849ddfe',
    afterWorkflowChange: 'bfe801dc90c97b869e95b247e0ebb0e0755f1b650f932a519c566db9debb8c40',
    toggleAssignment: 'cc76c2de02e73fc318040e542d5746774ea5fbad69ad8717050f65b864989392',
    displayDate: 'ae0940f01701156869f7efe5fa4065c746120e7d99e9288977ca3d2a5b0909e6',
  },
  movedFunctions: {
    upsertEmployeeLookup: '2ef959ad6f8e4d25afcbee03d6812c45501a1c688b7dd2cc928ffb5077c32996',
    loadLookups: '13c0ecdb56def939aa61ce3317f79cc76c56f0a277ed655ce31c7e0f8714e58d',
    loadAssignments: 'f6e5be1197da06fcc5d8cfadc92f292a18ddb1c8287cd515f4b1186733966826',
    loadAllAssignments: '4dcc35f9f255be1b7470433f409e19bb3e0f756304602b4a4a864ca46d61b17d',
  },
  movedComputeds: {
    managerSelectOptions: 'a91d3e4261e274608f367ddc2494df111b64714c1bbe7cdca4bce52504ef31fb',
    organizationSelectOptions: '6517ecabffd60a496acf9b7de897790cbf649a6a0f7d0a8359901fd15fc4be23',
    departmentSelectOptions: '1e68851cd5e9c61ed5d051597391f1705376dbf0d951019d6da7d9d77a441f07',
    positionSelectOptions: 'b3747c7972e4090a8b8627e6778e59d19e64c1227554d29b78faba39d6a794bd',
    selectedAssignments: '5b83c2c2ce5f98918fd18f75b6626d3e65913359a6868928387eed954170e1b1',
    currentPrimary: '6e0221fe9bbfc17742c57fb3534e2d3dfe30d92911a9804e825ad65593b2e713',
  },
  pageComputeds: {
    filters: '48b12850f47efa26e726faedb4096a7b00319f8a82abf51dfd7da1d0a02f5ae1',
    columns: '926a7190125e7a8e8dd5daa5598eb213a1832ddf7b8d2cfe0119601fc2bfb583',
    assignmentOverlap: 'b10c0898329b8d3a4fb33418a6cfc48baa16a16344146171902a263aaa909d92',
  },
}
function fingerprint(node) {
  const shape = []
  function visit(part) {
    shape.push(ts.SyntaxKind[part.kind])
    if (ts.isIdentifier(part) || ts.isLiteralExpression(part)) shape.push(part.text)
    ts.forEachChild(part, visit)
  }
  visit(node)
  return digest(shape.join('\u001f'))
}
function sourceFile(label, content) {
  const ast = ts.createSourceFile(label, content, ts.ScriptTarget.Latest, true)
  assert.equal(ast.parseDiagnostics.length, 0, `${label}: TypeScript syntax failed`)
  return ast
}
function locate(tree, name, kind) {
  let found = null
  function visit(node) {
    if (kind === 'function' && ts.isFunctionDeclaration(node) && node.name?.text === name)
      found = node
    if (kind === 'computed' && ts.isVariableDeclaration(node) && ts.isIdentifier(node.name) && node.name.text === name)
      found = node
    ts.forEachChild(node, visit)
  }
  visit(tree)
  assert.ok(found, `missing ${kind} ${name}`)
  return found
}

test('Employees template and scoped CSS match the approved V2.2.1 bytes', () => {
  const from = page.indexOf('<template>')
  const to = page.lastIndexOf('</template>')
  assert.ok(from !== -1 && to > from)
  assert.equal(digest(page.slice(from, to + '</template>'.length)), ORIGINAL.template)
  assert.equal(digest(page.slice(page.indexOf('<style scoped>'))), ORIGINAL.style)
})

test('unchanged pagination, routing, modal close, workflow and locale handlers preserve AST', () => {
  assert.ok(script)
  const tree = sourceFile('PeopleEmployeesView.ts', script)
  for (const [name, oldHash] of Object.entries(ORIGINAL.pageHandlers))
    assert.equal(fingerprint(locate(tree, name, 'function')), oldHash, name)
  for (const [name, oldHash] of Object.entries(ORIGINAL.pageComputeds))
    assert.equal(fingerprint(locate(tree, name, 'computed')), oldHash, name)
})

test('relocated lookup and assignment fetch functions preserve original AST', () => {
  const lookups = sourceFile('useEmployeeLookups.ts', modules.useEmployeeLookups)
  const assignments = sourceFile('useEmployeeAssignments.ts', modules.useEmployeeAssignments)
  for (const [name, oldHash] of Object.entries(ORIGINAL.movedFunctions)) {
    const tree = ['upsertEmployeeLookup', 'loadLookups'].includes(name) ? lookups : assignments
    assert.equal(fingerprint(locate(tree, name, 'function')), oldHash, name)
  }
  for (const [name, oldHash] of Object.entries(ORIGINAL.movedComputeds)) {
    const tree = ['selectedAssignments', 'currentPrimary'].includes(name) ? assignments : lookups
    assert.equal(fingerprint(locate(tree, name, 'computed')), oldHash, name)
  }
})

test('form, payload and manager validation modules are syntactically valid and composed', () => {
  for (const [name, content] of Object.entries(modules)) sourceFile(`${name}.ts`, content)
  for (const call of [
    'emptyEmployeeForm()',
    'employeeToForm(row)',
    'emptyAssignmentForm()',
    'assignmentToForm(row)',
    'useEmployeeLookups(selectedId, employeeForm, error)',
    'useEmployeeAssignments(selectedId, assignmentMode)',
    'employeeManagerErrorKey(employeeLookup.value, employeeForm.value, editingEmployeeId.value)',
    'assignmentErrorKey(assignmentOverlap.value, assignmentForm.value)',
    'employeePayload(employeeForm.value)',
    'assignmentPayload(assignmentForm.value, selectedId.value, editingAssignmentId.value)',
  ]) assert.ok(script.replace(/\s+/g, '').replace(/,\)/g, ')').includes(call.replace(/\s+/g, '')), `composition missing ${call}`)
})

test('save API call, dirty form and refetch ordering are preserved', () => {
  const employeeSave = script.slice(script.indexOf('async function saveEmployeeForm()'), script.indexOf('async function afterWorkflowChange()'))
  const assignmentSave = script.slice(script.indexOf('async function saveAssignmentForm()'), script.indexOf('async function toggleAssignment('))
  for (const [block, ordered] of [
    [employeeSave, [
      'const managerIssue = managerError()', 'busy.value = true', 'error.value = null',
      'await saveEmployee(', 'upsertEmployeeLookup(saved)', 'employeeDraft.markClean()',
      'employeeModal.value = false', 'await load()', 'finally', 'busy.value = false',
    ]],
    [assignmentSave, [
      'assignmentErrorKey(', 'if (!selectedId.value) return', 'busy.value = true',
      'await saveAssignment(', 'assignmentDraft.markClean()', 'assignmentModal.value = false',
      'await Promise.all([loadAssignments(), loadAllAssignments()])', 'await load()',
      'finally', 'busy.value = false',
    ]],
  ]) {
    let end = -1
    for (const needle of ordered) {
      const found = block.indexOf(needle, end + 1)
      assert.ok(found > end, `${needle} reordered or removed`)
      end = found
    }
  }
  assert.match(script, /watch\(selectedId, async \(\) => \{\s+activeTab\.value = 'overview'\s+await loadAssignments\(\)/)
  assert.match(script, /onMounted\(async \(\) => \{\s+await Promise\.all\(\[loadLookups\(\), load\(\), loadAllAssignments\(\)\]\)/)
})
