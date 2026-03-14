import { ref, computed } from "vue";
import { defineStore } from "pinia";
import { apiFetch, setAuthToken, clearAuthToken, getAuthToken } from "@/composables/apiFetch";

export interface AuthUser {
  id: string;
  username: string;
}

export const useAuthStore = defineStore("auth", () => {
  const user = ref<AuthUser | null>(null);
  const loading = ref(false);
  const error = ref<string | null>(null);

  const isAuthenticated = computed(() => user.value !== null);

  async function register(username: string, password: string): Promise<boolean> {
    loading.value = true;
    error.value = null;
    try {
      const res = await apiFetch("/auth/register", {
        method: "POST",
        body: JSON.stringify({ username, password }),
      });

      if (!res.ok) {
        const data = await res.json().catch(() => ({}));
        error.value = data.error || `Registration failed: ${res.statusText}`;
        return false;
      }

      const data = await res.json();
      setAuthToken(data.token);
      user.value = data.user;
      return true;
    } catch (e) {
      error.value = e instanceof Error ? e.message : "Registration failed";
      return false;
    } finally {
      loading.value = false;
    }
  }

  async function login(username: string, password: string): Promise<boolean> {
    loading.value = true;
    error.value = null;
    try {
      const res = await apiFetch("/auth/login", {
        method: "POST",
        body: JSON.stringify({ username, password }),
      });

      if (!res.ok) {
        const data = await res.json().catch(() => ({}));
        error.value = data.error || `Login failed: ${res.statusText}`;
        return false;
      }

      const data = await res.json();
      setAuthToken(data.token);
      user.value = data.user;
      return true;
    } catch (e) {
      error.value = e instanceof Error ? e.message : "Login failed";
      return false;
    } finally {
      loading.value = false;
    }
  }

  function logout() {
    clearAuthToken();
    user.value = null;
  }

  async function loadUser(): Promise<void> {
    const token = getAuthToken();
    if (!token) return;

    try {
      const res = await apiFetch("/auth/me");
      if (res.ok) {
        user.value = await res.json();
      } else {
        clearAuthToken();
      }
    } catch {
      clearAuthToken();
    }
  }

  return { user, loading, error, isAuthenticated, register, login, logout, loadUser };
});
