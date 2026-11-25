import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import AvatarFrameDecor from '@/components/players/AvatarFrameDecor.vue'
import BundleCard from '@/components/shop/BundleCard.vue'
import ShopItemPreview from '@/components/shop/ShopItemPreview.vue'

describe('Noctis collection rendering', () => {
  it('renders transparent artwork for the frame instead of pixel sprites', () => {
    const wrapper=mount(AvatarFrameDecor,{props:{frame:'noctis'}})
    expect(wrapper.find('img.decor__artwork').attributes('src')).toBe('/images/cosmetics/noctis/frame.webp')
    expect(wrapper.find('.decor__artwork-glint').exists()).toBe(true)
    expect(wrapper.find('.pixel-sprite').exists()).toBe(false)
    wrapper.unmount()
  })
  it('uses the illustrated collection cover in the storefront', () => {
    const wrapper=mount(BundleCard,{props:{bundle:{name:'Затмение',price:149900,metadata:{theme:'noctis'},bundle:{theme:'noctis',items:[],value:215000,price_for_you:149900}}},global:{plugins:[createPinia()]}})
    expect(wrapper.find('.bundle__art-cover').attributes('src')).toBe('/images/cosmetics/noctis/cover.webp')
    expect(wrapper.find('.bundle__hero').exists()).toBe(false)
    expect(wrapper.text()).toContain('149')
    wrapper.unmount()
  })
  it.each([['avatar_frame','.decor__artwork'],['skin_backdrop','.mc-backdrop--noctis'],['profile_layout','.preview__page--noctis'],['name_effect','.name-fx--noctis'],['chat_bubble','.bubble-fx--noctis']])('previews the actual %s cosmetic', (type,selector) => {
    const wrapper=mount(ShopItemPreview,{props:{item:{type,effect_value:'noctis',name:'Noctis'}},global:{plugins:[createPinia()]}})
    expect(wrapper.find(selector).exists()).toBe(true)
    wrapper.unmount()
  })
})
