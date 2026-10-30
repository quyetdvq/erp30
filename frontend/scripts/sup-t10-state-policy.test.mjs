import assert from 'node:assert/strict'
import { test } from 'node:test'
import {
  canSubmitSupplierTransition, supplierTransitionTargets,
} from '../src/features/supplier/supplierStatePolicy.ts'

test('no fallback targets if Supplier snapshot or permission is absent', () => {
  assert.deepEqual(supplierTransitionTargets(null), [])
  assert.deepEqual(supplierTransitionTargets({}), [])
  assert.deepEqual(supplierTransitionTargets({ setstate_targets: ['ACTIVE'] }), [])
  assert.deepEqual(supplierTransitionTargets({ allowed_actions: { setstate: false }, setstate_targets: ['ACTIVE'] }), [])
})
test('all valid targets originate exclusively from server Lifecycle hints', () => {
  const row = { allowed_actions: { setstate: true }, setstate_targets: ['REVIEW_PENDING', 'APPROVED'] }
  assert.deepEqual(supplierTransitionTargets(row), ['REVIEW_PENDING', 'APPROVED'])
  assert.equal(canSubmitSupplierTransition(row, 'APPROVED'), true)
  assert.equal(canSubmitSupplierTransition(row, 'ACTIVE'), false)
  assert.equal(canSubmitSupplierTransition(row, ''), false)
})
test('untrusted, malformed, duplicate or non-code targets are rejected', () => {
  const row = { allowed_actions: { setstate: true }, setstate_targets: [
    'REVIEW_PENDING', 'REVIEW_PENDING', 'lowercase', 'A'.repeat(21), 'DROP TABLE;', 'APPROVED',
  ] }
  assert.deepEqual(supplierTransitionTargets(row), ['REVIEW_PENDING', 'APPROVED'])
})
test('revoked row setstate hint fails closed, independently of targets', () => {
  const row = { allowed_actions: { setstate: false }, setstate_targets: ['APPROVED'] }
  assert.equal(canSubmitSupplierTransition(row, 'APPROVED'), false)
})
