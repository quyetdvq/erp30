import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { reactive } from 'vue'
import { i18n } from '@/i18n'
import MyWorkflowActionsView from '../pages/MyWorkflowActionsView.vue'
import type { MyWorkflowPage } from '@/services/myWorkflow'

const mock = vi.hoisted(() => ({
  auth: { user: { id: '1' } as { id: string } | null },
  getPage: vi.fn(),
}))
vi.mock('@/stores/auth', () => ({ useAuthStore: () => mock.auth }))
vi.mock('@/services/myWorkflow', async (importOriginal) => {
  const original = await importOriginal<typeof import('@/services/myWorkflow')>()
  return { ...original, getMyWorkflowPage: mock.getPage }
})

function page(items: MyWorkflowPage['items'] = [], next_cursor: string | null = null): MyWorkflowPage {
  return { items, next_cursor, execution_enabled: true }
}
const record = {
  resource_type: 'product', resource_id: '25', current_state: 'REVIEW',
  available_actions: [{ action_code: 'approve', status: 'ACTIVE' }],
  selected_at: '2026-10-26T08:37:16Z',
}
function view() {
  return mount(MyWorkflowActionsView, {
    global: {
      plugins: [i18n],
      stubs: { RouterLink: { props: ['to'], template: '<a :href="to"><slot /></a>' } },
    },
  })
}
beforeEach(() => {
  mock.auth.user = { id: '1' }
  mock.getPage.mockReset().mockResolvedValue(page())
})

describe('NFX-B read-only action candidates view', () => {
  it('shows only API-authorized actions and safe detail links, no inline approval mutation', async () => {
    mock.getPage.mockResolvedValue(page([record]))
    const wrapper = view()
    await flushPromises()
    expect(wrapper.text()).toContain(`${i18n.global.t('myWork.resourceTypes.product')} #25`)
    expect(wrapper.text()).toContain('approve')
    expect(wrapper.find('a[href="/products/25"]').exists()).toBe(true)
    expect(wrapper.find('button[data-action="approve"]').exists()).toBe(false)
    expect(wrapper.text()).toContain(i18n.global.t('myWork.description'))
  })

  it('does not infer a completed task from an empty authorized read model', async () => {
    const wrapper = view()
    await flushPromises()
    expect(wrapper.text()).toContain(i18n.global.t('myWork.empty'))
    expect(wrapper.findAll('[role="listitem"]')).toHaveLength(0)
  })

  it('handles an empty candidate page with a next cursor instead of claiming exhaustion', async () => {
    mock.getPage.mockResolvedValueOnce(page([], 'NEXT')).mockResolvedValueOnce(page([record]))
    const wrapper = view()
    await flushPromises()
    expect(wrapper.find('[data-testid="my-work-more"]').exists()).toBe(true)
    await wrapper.get('[data-testid="my-work-more"]').trigger('click')
    await flushPromises()
    expect(mock.getPage).toHaveBeenLastCalledWith('all', 'NEXT', expect.any(AbortSignal))
    expect(wrapper.findAll('[role="listitem"]')).toHaveLength(1)
  })

  it('clears the cursor and results when the resource filter changes', async () => {
    mock.getPage.mockResolvedValueOnce(page([record], 'NEXT'))
      .mockResolvedValueOnce(page([], null))
    const wrapper = view()
    await flushPromises()
    await wrapper.get('[data-testid="my-work-resource-filter"]').setValue('province')
    await flushPromises()
    expect(mock.getPage).toHaveBeenLastCalledWith('province', null, expect.any(AbortSignal))
    expect(wrapper.findAll('[role="listitem"]')).toHaveLength(0)
    expect(wrapper.find('[data-testid="my-work-more"]').exists()).toBe(false)
  })

  it('refreshes from the first page to recheck Workflow state and PBAC', async () => {
    mock.getPage.mockResolvedValueOnce(page([record], 'NEXT')).mockResolvedValueOnce(page())
    const wrapper = view()
    await flushPromises()
    await wrapper.get('[data-testid="my-work-refresh"]').trigger('click')
    await flushPromises()
    expect(mock.getPage).toHaveBeenLastCalledWith('all', null, expect.any(AbortSignal))
    expect(wrapper.findAll('[role="listitem"]')).toHaveLength(0)
    expect(wrapper.find('[data-testid="my-work-more"]').exists()).toBe(false)
  })

  it('discards late responses from the prior user session', async () => {
    let resolveFirst: (data: MyWorkflowPage) => void = () => {}
    const first = new Promise<MyWorkflowPage>(resolve => { resolveFirst = resolve })
    mock.getPage.mockReturnValueOnce(first).mockResolvedValueOnce(page())
    mock.auth.user = reactive({ id: '1' })
    const wrapper = view()
    await wrapper.vm.$nextTick()
    mock.auth.user.id = '2'
    await wrapper.vm.$nextTick()
    resolveFirst(page([record]))
    await flushPromises()
    expect(wrapper.findAll('[role="listitem"]')).toHaveLength(0)
  })
})
