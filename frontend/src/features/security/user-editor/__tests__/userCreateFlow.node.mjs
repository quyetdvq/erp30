import assert from 'node:assert/strict'
import { test } from 'node:test'
import {
  fullNameIssue,
  buildCreateUserPayload,
  buildEditUserPayload,
  selectableGroupOptions,
  submitCreatedUserAvatar,
} from '../userCreateFlow.ts'

test('new users need trimmed Unicode full name at most 255 code points', () => {
  assert.equal(fullNameIssue('   '), 'required')
  assert.equal(fullNameIssue('T '.repeat(256)), 'too_long')
  assert.equal(fullNameIssue('  Nguyễn Thị Hạnh   '), null)
  const payload = buildCreateUserPayload({ username:'test',name:'  Nguyễn Thị Hạnh  ',email:'test@example.test',password:'12345678',state:'ACTIVE',group_ids:[] })
  assert.equal(payload.name, 'Nguyễn Thị Hạnh')
  assert.deepEqual(payload.group_ids, [])
})

test('create uses only selected active canonical group IDs; zero groups works', () => {
  const opts = selectableGroupOptions([
    { id:'2', name:'Quản trị', code:'ADMIN', status:'ACTIVE' },
    { id:'4', name:'Phòng bán', code:'SALES', status:'ACTIVE' },
    { id:'8', name:'Khóa', code:'LOCKED', status:'INACTIVE' },
  ])
  assert.deepEqual(opts.map(o => o.value), ['2','4'])
  assert.match(opts[0].label, /ADMIN/)
  const draft = { username:'test',name:'Nguyễn A',email:'test@example.test',password:'12345678',state:'ACTIVE',group_ids:['2','4'] }
  assert.deepEqual(buildCreateUserPayload(draft).group_ids, ['2','4'])
  assert.throws(() => buildCreateUserPayload({...draft,group_ids:['2','2']}))
  assert.throws(() => buildCreateUserPayload({...draft,group_ids:['02']}))
})

test('edit never sends group_ids and may leave a legacy NULL name unchanged', () => {
  assert.deepEqual(buildEditUserPayload({username:'legacy',email:'legacy@example.test',name:null}, {username:'legacy2',email:'legacy@example.test',name:''}), {username:'legacy2',email:'legacy@example.test'})
  assert.deepEqual(buildEditUserPayload({username:'old',email:'a@example.test',name:'Old'}, {username:'new',email:'a@example.test',name:'  New   '}), {username:'new',email:'a@example.test',name:'New'})
  assert.throws(() => buildEditUserPayload({username:'old',email:'a@example.test',name:'Old'}, {username:'new',email:'a@example.test',name:' '}))
})

test('avatar failure saves created user id and retry only uploads, never re-creates user', async () => {
  const state = {createdUserId:null}
  let posts = 0
  let uploads = 0
  const create = async () => { posts++; return {id:99} }
  const upload = async (id) => { assert.equal(id,'99'); uploads++; if (uploads === 1) throw new Error('403') }
  await assert.rejects(submitCreatedUserAvatar(state, create, upload), /403/)
  assert.equal(state.createdUserId, '99')
  assert.equal(posts,1)
  assert.equal((await submitCreatedUserAvatar(state, create, upload)).id, '99')
  assert.equal(posts,1)
  assert.equal(uploads,2)
})
