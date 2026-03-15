<script setup lang="ts">
import { ref, onMounted, onUnmounted, nextTick, watch } from "vue";
import { useAuthStore } from "@/stores/auth";

const emit = defineEmits<{ close: [] }>();
const authStore = useAuthStore();

const mode = ref<"login" | "register">("login");
const username = ref("");
const password = ref("");
const submitted = ref(false);
const usernameInput = ref<HTMLInputElement | null>(null);

// Clear server errors when user starts typing
watch([username, password], () => {
  if (authStore.error) authStore.error = null;
});

async function handleSubmit() {
  submitted.value = true;
  if (!username.value.trim() || !password.value) return;

  const success =
    mode.value === "login"
      ? await authStore.login(username.value.trim(), password.value)
      : await authStore.register(username.value.trim(), password.value);

  if (success) {
    emit("close");
  }
}

function handleEscape(e: KeyboardEvent) {
  if (e.key === "Escape") emit("close");
}

onMounted(() => {
  document.addEventListener("keydown", handleEscape);
  nextTick(() => usernameInput.value?.focus());
});

onUnmounted(() => {
  document.removeEventListener("keydown", handleEscape);
});
</script>

<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="$emit('close')">
      <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" aria-hidden="true"></div>

      <div
        role="dialog"
        aria-modal="true"
        class="relative w-full max-w-md rounded-2xl bg-white dark:bg-neutral-800 shadow-2xl border border-neutral-200 dark:border-neutral-700 p-6"
      >
        <button
          aria-label="Close"
          class="absolute top-4 right-4 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300 hover:scale-110 transition-all"
          @click="$emit('close')"
        >
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
            <path
              fill-rule="evenodd"
              d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
              clip-rule="evenodd"
            />
          </svg>
        </button>

        <!-- Tab Switcher -->
        <div class="flex gap-1 mb-6 bg-neutral-100 dark:bg-neutral-700 rounded-xl p-1">
          <button
            class="flex-1 py-2 text-sm font-medium rounded-lg transition-colors"
            :class="
              mode === 'login'
                ? 'bg-white dark:bg-neutral-600 text-neutral-900 dark:text-neutral-100 shadow-sm'
                : 'text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-300'
            "
            @click="
              mode = 'login';
              authStore.error = null;
              submitted = false;
            "
          >
            Log in
          </button>
          <button
            class="flex-1 py-2 text-sm font-medium rounded-lg transition-colors"
            :class="
              mode === 'register'
                ? 'bg-white dark:bg-neutral-600 text-neutral-900 dark:text-neutral-100 shadow-sm'
                : 'text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-300'
            "
            @click="
              mode = 'register';
              authStore.error = null;
              submitted = false;
            "
          >
            Register
          </button>
        </div>

        <h2 class="text-xl font-bold text-neutral-900 dark:text-neutral-100 mb-1">
          {{ mode === "login" ? "Welcome back" : "Create an account" }}
        </h2>
        <p class="text-sm text-neutral-500 dark:text-neutral-400 mb-6">
          {{ mode === "login" ? "Log in to track your games." : "Pick a username to get started." }}
        </p>

        <div
          v-if="authStore.error"
          class="mb-4 p-3 rounded-lg bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 text-sm"
        >
          {{ authStore.error }}
        </div>

        <form @submit.prevent="handleSubmit" class="space-y-4">
          <div>
            <label for="auth-username" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1.5">
              Username
            </label>
            <input
              id="auth-username"
              ref="usernameInput"
              v-model="username"
              type="text"
              placeholder="e.g. MaxMuster"
              minlength="3"
              maxlength="64"
              autocomplete="username"
              class="w-full rounded-xl border border-neutral-300 dark:border-neutral-600 bg-neutral-50 dark:bg-neutral-700 px-4 py-2.5 text-sm text-neutral-900 dark:text-neutral-100 placeholder-neutral-400 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 outline-none transition-all"
              :class="{ 'border-red-400 dark:border-red-500': submitted && !username.trim() }"
            />
            <p v-if="submitted && !username.trim()" class="mt-1 text-xs text-red-500">Username is required.</p>
            <p
              v-else-if="username.length > 0 && username.trim().length < 3"
              class="mt-1 text-xs text-amber-600 dark:text-amber-400"
            >
              At least 3 characters needed
            </p>
          </div>

          <div>
            <label for="auth-password" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1.5">
              Password
            </label>
            <input
              id="auth-password"
              v-model="password"
              type="password"
              :placeholder="mode === 'register' ? 'At least 6 characters' : 'Your password'"
              :minlength="mode === 'register' ? 6 : 1"
              autocomplete="current-password"
              class="w-full rounded-xl border border-neutral-300 dark:border-neutral-600 bg-neutral-50 dark:bg-neutral-700 px-4 py-2.5 text-sm text-neutral-900 dark:text-neutral-100 placeholder-neutral-400 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 outline-none transition-all"
              :class="{ 'border-red-400 dark:border-red-500': submitted && !password }"
            />
            <p v-if="submitted && !password" class="mt-1 text-xs text-red-500">Password is required.</p>
            <p
              v-else-if="mode === 'register' && password.length > 0 && password.length < 6"
              class="mt-1 text-xs text-amber-600 dark:text-amber-400"
            >
              At least 6 characters needed
            </p>
          </div>

          <div class="flex gap-3 pt-2">
            <button
              type="button"
              class="flex-1 rounded-xl border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-700 text-neutral-700 dark:text-neutral-300 font-medium py-2.5 text-sm hover:bg-neutral-50 dark:hover:bg-neutral-600 hover:-translate-y-0.5 transition-all duration-200"
              @click="$emit('close')"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="authStore.loading"
              class="flex-1 rounded-xl bg-amber-500 hover:bg-amber-600 active:bg-amber-700 text-white font-semibold py-2.5 text-sm hover:-translate-y-0.5 hover:shadow-lg transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:translate-y-0 disabled:hover:shadow-none"
            >
              {{ authStore.loading ? "..." : mode === "login" ? "Log in" : "Register" }}
            </button>
          </div>
        </form>

        <p class="mt-4 text-center text-xs text-neutral-400 dark:text-neutral-500">
          Playing as a guest? Just skip this — you can play without an account.
        </p>
      </div>
    </div>
  </Teleport>
</template>
