import { describe, it, expect } from 'vitest'
import type { Color } from '@/types/Colors'

describe('Color type', () => {
  it('accepts green as a valid color', () => {
    const color: Color = 'green'
    expect(color).toBe('green')
  })

  it('accepts yellow as a valid color', () => {
    const color: Color = 'yellow'
    expect(color).toBe('yellow')
  })

  it('accepts red as a valid color', () => {
    const color: Color = 'red'
    expect(color).toBe('red')
  })

  it('accepts black as a valid color', () => {
    const color: Color = 'black'
    expect(color).toBe('black')
  })

  it('accepts white as a valid color', () => {
    const color: Color = 'white'
    expect(color).toBe('white')
  })

  it('all 5 valid color values are strings', () => {
    const colors: Color[] = ['green', 'yellow', 'red', 'black', 'white']
    expect(colors).toHaveLength(5)
    colors.forEach((c) => expect(typeof c).toBe('string'))
  })
})
