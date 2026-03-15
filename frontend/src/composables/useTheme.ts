import { ref, computed, watch } from "vue";

export type ThemePreference = "light" | "dark" | "system";

const STORAGE_KEY = "kickandrun_theme";

const theme = ref<ThemePreference>(loadTheme());
const systemPrefersDark = ref(detectSystemDark());

function loadTheme(): ThemePreference {
  try {
    const stored = localStorage.getItem(STORAGE_KEY);
    if (stored === "light" || stored === "dark" || stored === "system") {
      return stored;
    }
  } catch {
    // Ignore storage errors
  }
  return "system";
}

function detectSystemDark(): boolean {
  if (typeof window === "undefined") return false;
  try {
    return window.matchMedia("(prefers-color-scheme: dark)").matches;
  } catch {
    return false;
  }
}

if (typeof window !== "undefined") {
  try {
    window.matchMedia("(prefers-color-scheme: dark)").addEventListener("change", (e) => {
      systemPrefersDark.value = e.matches;
    });
  } catch {
    // Ignore environments where matchMedia is unavailable
  }
}

const isDark = computed(() => {
  if (theme.value === "dark") return true;
  if (theme.value === "light") return false;
  return systemPrefersDark.value;
});

watch(
  isDark,
  (val) => {
    if (typeof document !== "undefined") {
      document.documentElement.classList.toggle("dark", val);
    }
  },
  { immediate: true },
);

watch(theme, (val) => {
  try {
    if (val === "system") {
      localStorage.removeItem(STORAGE_KEY);
    } else {
      localStorage.setItem(STORAGE_KEY, val);
    }
  } catch {
    // Ignore storage errors
  }
});

function toggle() {
  theme.value = isDark.value ? "light" : "dark";
}

export function useTheme() {
  return {
    theme,
    isDark,
    toggle,
    systemPrefersDark,
  };
}
