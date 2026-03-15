import { describe, it, expect } from 'vitest'
import { mount, shallowMount } from '@vue/test-utils'
import BaseElement from '@/components/BaseElement.vue'
import PlayingField from '@/components/PlayingField.vue'

describe('BaseElement', () => {
  it('renders without errors with a color prop', () => {
    const wrapper = shallowMount(BaseElement, { props: { color: 'green' } })
    expect(wrapper.exists()).toBe(true)
  })

  it('contains 4 PlayingField child components', () => {
    const wrapper = mount(BaseElement, { props: { color: 'yellow' } })
    expect(wrapper.findAllComponents(PlayingField)).toHaveLength(4)
  })

  it('does not render "B" label text (removed debug label)', () => {
    const wrapper = mount(BaseElement, { props: { color: 'red' } })
    expect(wrapper.text()).not.toContain('B')
  })

  it('applies rotation class when provided', () => {
    const wrapper = mount(BaseElement, {
      props: { color: 'green', rotationClass: 'rotate-180' },
    })
    expect(wrapper.html()).toContain('rotate-180')
  })

  it('defaults to no rotation class', () => {
    const wrapper = mount(BaseElement, { props: { color: 'black' } })
    const labelDiv = wrapper.find('.flex.h-full')
    expect(labelDiv.classes()).not.toContain('rotate-180')
  })
})
