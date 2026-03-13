import { test, expect } from '@playwright/test'

test.describe('Game Board', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/')
  })

  test('renders the game board', async ({ page }) => {
    await expect(page.locator('.grid')).toBeVisible()
  })

  test('renders 4 player bases', async ({ page }) => {
    // Each base has a "B" label
    const bases = page.locator('text=B')
    await expect(bases).toHaveCount(4)
  })

  test('renders green player elements', async ({ page }) => {
    const greenElements = page.locator('.playingfield.green')
    await expect(greenElements).not.toHaveCount(0)
  })

  test('renders yellow player elements', async ({ page }) => {
    const yellowElements = page.locator('.playingfield.yellow')
    await expect(yellowElements).not.toHaveCount(0)
  })

  test('renders red player elements', async ({ page }) => {
    const redElements = page.locator('.playingfield.red')
    await expect(redElements).not.toHaveCount(0)
  })

  test('renders black player elements', async ({ page }) => {
    const blackElements = page.locator('.playingfield.black')
    await expect(blackElements).not.toHaveCount(0)
  })

  test('page has correct title structure', async ({ page }) => {
    await expect(page.locator('main')).toBeVisible()
  })
})
