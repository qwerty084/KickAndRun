import { test, expect } from "@playwright/test";

test.describe("Homepage", () => {
  test("loads and displays the title", async ({ page }) => {
    await page.goto("/");
    await expect(page.locator("h1")).toContainText("Mensch ärgere");
  });

  test("renders the game board", async ({ page }) => {
    await page.goto("/");
    await expect(page.locator(".playingfield").first()).toBeVisible();
  });

  test("shows Create Game and Join Game buttons", async ({ page }) => {
    await page.goto("/");
    await expect(page.getByRole("button", { name: /Create Game/i })).toBeVisible();
    await expect(page.getByRole("button", { name: /Join Game/i })).toBeVisible();
  });

  test("opens create lobby dialog on button click", async ({ page }) => {
    await page.goto("/");
    await page.getByRole("button", { name: /Create Game/i }).click();
    await expect(page.getByText(/Game Name/i)).toBeVisible();
  });
});

test.describe("Board rendering", () => {
  test("has colored base elements", async ({ page }) => {
    await page.goto("/");
    // Check that colored fields exist (green, yellow, red, black)
    await expect(page.locator(".playingfield.green").first()).toBeVisible();
    await expect(page.locator(".playingfield.yellow").first()).toBeVisible();
    await expect(page.locator(".playingfield.red").first()).toBeVisible();
    await expect(page.locator(".playingfield.black").first()).toBeVisible();
  });

  test("has white path fields", async ({ page }) => {
    await page.goto("/");
    const whiteFields = page.locator(".playingfield.white");
    await expect(whiteFields.first()).toBeVisible();
    // Should have many white path fields
    expect(await whiteFields.count()).toBeGreaterThan(20);
  });
});
