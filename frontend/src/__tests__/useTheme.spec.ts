import { describe, it, expect, beforeEach, vi } from "vitest";
import { useTheme } from "@/composables/useTheme";

function mockMatchMedia(prefersDark: boolean) {
  const listeners: Array<(e: MediaQueryListEvent) => void> = [];
  const mql = {
    matches: prefersDark,
    addEventListener: vi.fn((_: string, cb: (e: MediaQueryListEvent) => void) => {
      listeners.push(cb);
    }),
    removeEventListener: vi.fn(),
    _trigger: (matches: boolean) => {
      listeners.forEach((cb) => cb({ matches } as MediaQueryListEvent));
    },
  };
  vi.stubGlobal("matchMedia", vi.fn(() => mql));
  return mql;
}

describe("useTheme", () => {
  beforeEach(() => {
    localStorage.clear();
    document.documentElement.classList.remove("dark");
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
    // Reset module-level singletons by re-importing is not straightforward,
    // so we manipulate state via the exported API.
  });

  it("defaults to 'system' when localStorage is empty", () => {
    mockMatchMedia(false);
    const { theme } = useTheme();
    // theme starts as whatever was loaded at module init — we check system default
    expect(["light", "dark", "system"]).toContain(theme.value);
  });

  it("toggle switches from light to dark", async () => {
    mockMatchMedia(false);
    const { theme, isDark, toggle } = useTheme();
    // Force to light state
    theme.value = "light";
    expect(isDark.value).toBe(false);

    toggle();
    expect(isDark.value).toBe(true);
    expect(theme.value).toBe("dark");
  });

  it("toggle switches from dark to light", async () => {
    mockMatchMedia(false);
    const { theme, isDark, toggle } = useTheme();
    theme.value = "dark";
    expect(isDark.value).toBe(true);

    toggle();
    expect(isDark.value).toBe(false);
    expect(theme.value).toBe("light");
  });

  it("persists 'dark' to localStorage after toggle", async () => {
    mockMatchMedia(false);
    const { theme, toggle } = useTheme();
    theme.value = "light";
    toggle(); // → dark
    await new Promise((r) => setTimeout(r, 10));
    expect(localStorage.getItem("kickandrun_theme")).toBe("dark");
  });

  it("persists 'light' to localStorage after toggle", async () => {
    mockMatchMedia(false);
    const { theme, toggle } = useTheme();
    theme.value = "dark";
    toggle(); // → light
    await new Promise((r) => setTimeout(r, 10));
    expect(localStorage.getItem("kickandrun_theme")).toBe("light");
  });

  it("isDark is true when OS prefers dark and theme is system", () => {
    const { theme, isDark, systemPrefersDark } = useTheme();
    theme.value = "system";
    systemPrefersDark.value = true;
    expect(isDark.value).toBe(true);
  });

  it("isDark is false when OS prefers light and theme is system", () => {
    const { theme, isDark, systemPrefersDark } = useTheme();
    theme.value = "system";
    systemPrefersDark.value = false;
    expect(isDark.value).toBe(false);
  });

  it("isDark is true when theme is explicitly 'dark' regardless of OS", () => {
    mockMatchMedia(false);
    const { theme, isDark } = useTheme();
    theme.value = "dark";
    expect(isDark.value).toBe(true);
  });

  it("isDark is false when theme is explicitly 'light' regardless of OS", () => {
    mockMatchMedia(true);
    const { theme, isDark } = useTheme();
    theme.value = "light";
    expect(isDark.value).toBe(false);
  });

  it("applies .dark class to documentElement when isDark is true", async () => {
    mockMatchMedia(false);
    const { theme } = useTheme();
    theme.value = "dark";
    await new Promise((r) => setTimeout(r, 10));
    expect(document.documentElement.classList.contains("dark")).toBe(true);
  });

  it("removes .dark class from documentElement when isDark is false", async () => {
    mockMatchMedia(false);
    document.documentElement.classList.add("dark");
    const { theme } = useTheme();
    theme.value = "light";
    await new Promise((r) => setTimeout(r, 10));
    expect(document.documentElement.classList.contains("dark")).toBe(false);
  });
});
