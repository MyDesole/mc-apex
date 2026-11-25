import { describe, expect, it, vi, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import ProfilePet from '@/components/players/ProfilePet.vue'
import PetFigure from '@/components/pets/PetFigure.vue'
import StarlitFox from '@/components/pets/StarlitFox.vue'
afterEach(() => vi.useRealTimers())
describe('Personal Starlit Fox', () => {
  it('uses its original artwork in the profile and pet inventory', () => {
    vi.useFakeTimers()
    const profile=mount(ProfilePet,{props:{pet:'desole_fox'},global:{plugins:[createPinia()],stubs:{RouterLink:true}}})
    const inventory=mount(PetFigure,{props:{species:'desole_fox'}})
    expect(profile.find('.pet__skin .starlit-fox').exists()).toBe(true)
    expect(inventory.find('.starlit-fox').exists()).toBe(true)
    expect(profile.find('.pet__skin .pixel-sprite').exists()).toBe(false)
    profile.unmount(); inventory.unmount()
    expect(vi.getTimerCount()).toBe(0)
  })
  it('closes its eyes while sleeping and restores them when awake', async () => {
    const fox=mount(StarlitFox,{props:{state:'sleep'}})
    expect(fox.find('.starlit-fox__eyes').exists()).toBe(false)
    await fox.setProps({state:'walk'})
    expect(fox.find('.starlit-fox__eyes').exists()).toBe(true)
    expect(fox.classes()).toContain('starlit-fox--walk')
    fox.unmount()
  })
})
