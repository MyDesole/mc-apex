import { describe, expect, it, vi, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import ProfilePet from '@/components/players/ProfilePet.vue'
import PetFigure from '@/components/pets/PetFigure.vue'
import CozyKoala from '@/components/pets/CozyKoala.vue'
import StarlitFox from '@/components/pets/StarlitFox.vue'
afterEach(() => vi.useRealTimers())
describe('Personal Starlit Fox', () => {
  it.each([['desole_fox','.starlit-fox'],['kllivv_koala','.cozy-koala']])('uses %s artwork in the profile and pet inventory', (pet,selector) => {
    vi.useFakeTimers()
    const profile=mount(ProfilePet,{props:{pet},global:{plugins:[createPinia()],stubs:{RouterLink:true}}})
    const inventory=mount(PetFigure,{props:{species:pet}})
    expect(profile.find('.pet__skin '+selector).exists()).toBe(true)
    expect(inventory.find(selector).exists()).toBe(true)
    expect(profile.find('.pet__skin .pixel-sprite').exists()).toBe(false)
    profile.unmount(); inventory.unmount()
    expect(vi.getTimerCount()).toBe(0)
  })
  it.each([[StarlitFox,'.starlit-fox'],[CozyKoala,'.cozy-koala']])('closes its eyes while sleeping and restores them when awake', async (component,selector) => {
    const fox=mount(component,{props:{state:'sleep'}})
    expect(fox.find(selector+'__eyes').exists()).toBe(false)
    await fox.setProps({state:'walk'})
    expect(fox.find(selector+'__eyes').exists()).toBe(true)
    expect(fox.classes()).toContain(selector.slice(1)+'--walk')
    fox.unmount()
  })
})
