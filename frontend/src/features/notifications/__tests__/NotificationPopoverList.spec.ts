import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { i18n } from '@/i18n'
import NotificationPopoverList from '../components/NotificationPopoverList.vue'
import type { InboxNotification } from '@/services/notificationInbox'

function item(id: string, resource_type?: string): InboxNotification {
  return {
    id,
    type: resource_type ? 'workflow.action_required' : 'import.completed',
    priority: 'NORMAL',
    title_key: resource_type ? 'notifications.workflow.actionRequired.title' : 'notifications.import.completed.title',
    body_key: resource_type ? 'notifications.workflow.actionRequired.body' : 'notifications.import.completed.body',
    params: resource_type ? { resource_type, state_code: 'REVIEW' } : { success_rows: 10, error_rows: 0 },
    target: resource_type ? { type: 'workflow_target', resource_type, id } : { type: 'import_job', id },
    read_at: null,
    created_at: '2026-10-26T08:00:00Z',
  }
}

function render(items: InboxNotification[], busy = false, error = false) {
  return mount(NotificationPopoverList, {
    props: { items, busy, error },
    global: {
      plugins: [i18n],
      stubs: {
        RouterLink: {
          props: ['to'],
          emits: ['click'],
          template: '<a :data-to="to" @click="$emit(\'click\')"><slot /></a>',
        },
      },
    },
  })
}

describe('M11 NFX-A1 popover actions', () => {
  it('opens the exact route, closes the popover and does not mark read on navigation', async () => {
    const wrapper = render([item('11')])
    const link = wrapper.get('[data-to="/products/import-jobs/11"]')
    await link.trigger('click')
    expect(wrapper.emitted('navigate')).toHaveLength(1)
    expect(wrapper.emitted('markRead')).toBeUndefined()
  })

  it('shows a list label for list-only workflow types and no link for unknown types', () => {
    const wrapper = render([item('12', 'department'), item('13', 'unknown')])
    expect(wrapper.findAll('[data-to]')).toHaveLength(1)
    expect(wrapper.get('[data-to="/people/departments"]').text()).toBeTruthy()
    expect(wrapper.text()).toContain(i18n.global.t('notificationsInbox.targetUnavailable'))
  })

  it('allows marking one item read independently of navigation', async () => {
    const wrapper = render([item('14')])
    await wrapper.get('button').trigger('click')
    expect(wrapper.emitted('markRead')?.[0]).toEqual(['14'])
    expect(wrapper.emitted('navigate')).toBeUndefined()
  })

  it('limits the popup to five entries and shows loading/error instead of stale content', () => {
    const items = Array.from({ length: 7 }, (_, n) => item(String(n + 1)))
    expect(render(items).findAll('article')).toHaveLength(5)
    expect(render(items, true).findAll('article')).toHaveLength(0)
    expect(render(items, false, true).findAll('article')).toHaveLength(0)
  })
})
