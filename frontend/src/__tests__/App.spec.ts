import { describe, it, expect, vi } from 'vitest'
import { createPinia } from 'pinia'

import { mount } from '@vue/test-utils'
// The App unit test stubs RouterView, so it must not load the real router's
// eagerly imported AppShell (and its public logo asset) in Vitest/JSDOM.
vi.mock('@/router', () => ({
  default: {
    currentRoute: { value: { path: '/' } },
    replace: vi.fn<() => Promise<void>>(async () => undefined),
  },
}))

import App from '../App.vue'

describe('App', () => {
  it('mounts renders properly', () => {
    const wrapper = mount(App, {
      global: {
        plugins: [createPinia()],
        stubs: { RouterView: true },
      },
    })
    expect(wrapper.exists()).toBe(true)
  })
})
