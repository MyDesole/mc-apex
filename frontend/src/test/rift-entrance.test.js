import { afterEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import ProfileEntrance from '@/components/players/ProfileEntrance.vue'

afterEach(() => { vi.useRealTimers(); vi.unstubAllGlobals() })
describe('Rift entrance lifecycle', () => {
  it('disappears after its short intro and clears timers on unmount', async () => {
    vi.useFakeTimers()
    const wrapper = mount(ProfileEntrance, { props: { entrance: 'rift' } })
    await nextTick()
    expect(wrapper.find('.entrance__rift-aperture').exists()).toBe(true)
    await vi.advanceTimersByTimeAsync(2200)
    expect(wrapper.find('.entrance').exists()).toBe(false)
    wrapper.unmount()
    expect(vi.getTimerCount()).toBe(0)
  })
  it('does not show an intro when the visitor requests reduced motion', async () => {
    vi.stubGlobal('matchMedia', () => ({ matches: true }))
    const wrapper = mount(ProfileEntrance, { props: { entrance: 'rift' } })
    await nextTick()
    expect(wrapper.find('.entrance').exists()).toBe(false)
    wrapper.unmount()
  })
})
