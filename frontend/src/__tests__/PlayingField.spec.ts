import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import PlayingField from '@/components/PlayingField.vue'

describe('PlayingField', () => {
  it('renders without errors', () => {
    const wrapper = mount(PlayingField, { props: { fieldColor: 'white' } })
    expect(wrapper.exists()).toBe(true)
  })

  it('displays text prop when provided', () => {
    const wrapper = mount(PlayingField, { props: { fieldColor: 'white', text: 'A' } })
    expect(wrapper.text()).toContain('A')
  })

  it('applies green color class', () => {
    const wrapper = mount(PlayingField, { props: { fieldColor: 'green' } })
    expect(wrapper.classes()).toContain('green')
  })

  it('applies yellow color class', () => {
    const wrapper = mount(PlayingField, { props: { fieldColor: 'yellow' } })
    expect(wrapper.classes()).toContain('yellow')
  })

  it('applies red color class', () => {
    const wrapper = mount(PlayingField, { props: { fieldColor: 'red' } })
    expect(wrapper.classes()).toContain('red')
  })

  it('applies black color class', () => {
    const wrapper = mount(PlayingField, { props: { fieldColor: 'black' } })
    expect(wrapper.classes()).toContain('black')
  })

  it('applies white color class', () => {
    const wrapper = mount(PlayingField, { props: { fieldColor: 'white' } })
    expect(wrapper.classes()).toContain('white')
  })

  it('applies rotation class when provided', () => {
    const wrapper = mount(PlayingField, {
      props: { fieldColor: 'white', rotationClass: 'rotate-90' },
    })
    expect(wrapper.find('span').classes()).toContain('rotate-90')
  })

  it('defaults to empty text', () => {
    const wrapper = mount(PlayingField, { props: { fieldColor: 'white' } })
    expect(wrapper.find('span').text()).toBe('')
  })

  it('defaults to black textColor', () => {
    const wrapper = mount(PlayingField, { props: { fieldColor: 'white' } })
    expect(wrapper.find('span').classes()).toContain('black')
  })

  it('applies white textColor class correctly', () => {
    const wrapper = mount(PlayingField, { props: { fieldColor: 'black', textColor: 'white' } })
    expect(wrapper.find('span').classes()).toContain('white')
  })

  it('applies no rotation class by default', () => {
    const wrapper = mount(PlayingField, { props: { fieldColor: 'white' } })
    const span = wrapper.find('span')
    expect(span.classes()).not.toContain('rotate-90')
    expect(span.classes()).not.toContain('rotate-180')
  })
})
