/**
 * Проверка, что smoke-тесты действительно ловят ошибки рантайма.
 *
 * Если инструмент не ловит хотя бы такую ошибку, он бесполезен:
 * именно она однажды прошла сборку и упала в браузере.
 */
import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent, computed, h } from 'vue'

describe('smoke-тест ловит ошибки выполнения', () => {
  it('обращение к переменной до объявления', () => {
    const Broken = defineComponent({
      setup() {
        // Ошибка из реального проекта: Link() вызывается сразу и читает
        // переменную, объявленную ниже. В JS это ReferenceError.
        const label = link()
        const link = () => fallback.value
        const fallback = computed(() => 'значение')

        return () => h('div', label)
      },
    })

    expect(() => mount(Broken)).toThrow()
  })

  it('чтение свойства у undefined', () => {
    const Broken = defineComponent({
      props: { data: { type: Object, default: null } },
      setup(props) {
        return () => h('div', props.data.missing)
      },
    })

    expect(() => mount(Broken)).toThrow()
  })

  it('вызов несуществующего метода', () => {
    const Broken = defineComponent({
      setup() {
        return () => h('div', undefinedMethod())
      },
    })

    expect(() => mount(Broken)).toThrow()
  })

  it('корректный компонент монтируется без ошибок', () => {
    const Fine = defineComponent({
      setup() {
        const value = computed(() => 'ок')

        return () => h('div', value.value)
      },
    })

    const wrapper = mount(Fine)

    expect(wrapper.text()).toBe('ок')

    wrapper.unmount()
  })
})
