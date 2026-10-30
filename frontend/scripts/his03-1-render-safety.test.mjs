import { test } from 'node:test'
import assert from 'node:assert/strict'
import { historyChangeDisplay, historyChangeLines } from '../src/features/history/historyPresentation.ts'

const event = (changes) => ({ changes })

test('HIS-03.1 compact changes render original before/after and empty-object markers', () => {
  assert.deepEqual(historyChangeDisplay(event({ status: ['DRAFT', 'ACTIVE'], phone: {} }), 'Protected'), {
    lines: ['status: DRAFT → ACTIVE', 'phone: Protected'], invalid: false,
  })
  assert.deepEqual(historyChangeDisplay(event({ status: [null, 'ACTIVE'] }), 'Protected'), {
    lines: ['status: — → ACTIVE'], invalid: false,
  })
  assert.deepEqual(historyChangeDisplay(event(null), 'Protected'), { lines: [], invalid: false })
})

test('HIS-03.1 never renders old-format or unreviewed values and keeps a UI-visible warning', () => {
  for (const changes of [
    { phone: { changed: true } },
    { phone: { before: 'OLD SECRET', after: 'NEW SECRET' } },
    { phone: [{ secret: 'OLD SECRET' }, 'NEW SECRET'] },
    { status: ['DRAFT'] },
    { 'INVALID KEY': ['OLD SECRET', 'NEW SECRET'] },
    ['OLD SECRET', 'NEW SECRET'],
  ]) {
    assert.deepEqual(historyChangeDisplay(event(changes), 'Protected'), {
      lines: [], invalid: true,
    })
    assert.throws(() => historyChangeLines(event(changes), 'Protected'), /HISTORY_COMPACT_CHANGES_INVALID/)
  }
})
