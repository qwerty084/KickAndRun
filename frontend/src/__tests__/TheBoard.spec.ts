import { describe, it, expect } from 'vitest'
import { mount, shallowMount } from '@vue/test-utils'
import TheBoard from '@/components/TheBoard.vue'
import BaseElement from '@/components/BaseElement.vue'
import PlayingField from '@/components/PlayingField.vue'

describe('TheBoard', () => {
  it('renders without errors', () => {
    const wrapper = shallowMount(TheBoard)
    expect(wrapper.exists()).toBe(true)
  })

  it('contains 4 BaseElement components (one per player)', () => {
    const wrapper = shallowMount(TheBoard)
    expect(wrapper.findAllComponents(BaseElement)).toHaveLength(4)
  })

  it('renders PlayingField components', () => {
    const wrapper = mount(TheBoard)
    expect(wrapper.findAllComponents(PlayingField).length).toBeGreaterThan(0)
  })

  it('contains green player color', () => {
    const wrapper = shallowMount(TheBoard)
    const greenBase = wrapper
      .findAllComponents(BaseElement)
      .find((b) => b.props('color') === 'green')
    expect(greenBase).toBeDefined()
  })

  it('contains yellow player color', () => {
    const wrapper = shallowMount(TheBoard)
    const yellowBase = wrapper
      .findAllComponents(BaseElement)
      .find((b) => b.props('color') === 'yellow')
    expect(yellowBase).toBeDefined()
  })

  it('contains red player color', () => {
    const wrapper = shallowMount(TheBoard)
    const redBase = wrapper
      .findAllComponents(BaseElement)
      .find((b) => b.props('color') === 'red')
    expect(redBase).toBeDefined()
  })

  it('contains black player color', () => {
    const wrapper = shallowMount(TheBoard)
    const blackBase = wrapper
      .findAllComponents(BaseElement)
      .find((b) => b.props('color') === 'black')
    expect(blackBase).toBeDefined()
  })
})
