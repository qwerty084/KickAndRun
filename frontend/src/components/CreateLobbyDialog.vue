<script setup lang="ts">
import { ref } from "vue";

const emit = defineEmits<{ create: [name: string, hostName: string]; close: [] }>();

const gameName = ref("");
const hostName = ref("");
const submitted = ref(false);

function handleSubmit() {
  submitted.value = true;
  if (!gameName.value.trim() || !hostName.value.trim()) return;
  emit("create", gameName.value.trim(), hostName.value.trim());
}
</script>

<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="$emit('close')">
      <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" aria-hidden="true"></div>

      <div
        class="relative w-full max-w-md rounded-2xl bg-white dark:bg-neutral-800 shadow-2xl border border-neutral-200 dark:border-neutral-700 p-6"
      >
        <button
          class="absolute top-4 right-4 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300 transition-colors"
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

        <h2 class="text-xl font-bold text-neutral-900 dark:text-neutral-100 mb-1">Create a New Game</h2>
        <p class="text-sm text-neutral-500 dark:text-neutral-400 mb-6">Set up a lobby and invite your friends to play.</p>

        <form @submit.prevent="handleSubmit" class="space-y-4">
          <div>
            <label for="game-name" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1.5">
              Game Name
            </label>
            <input
              id="game-name"
              v-model="gameName"
              type="text"
              placeholder="e.g. Friday Night Game"
              maxlength="50"
              class="w-full rounded-xl border border-neutral-300 dark:border-neutral-600 bg-neutral-50 dark:bg-neutral-700 px-4 py-2.5 text-sm text-neutral-900 dark:text-neutral-100 placeholder-neutral-400 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 outline-none transition-all"
              :class="{ 'border-red-400 dark:border-red-500': submitted && !gameName.trim() }"
            />
            <p v-if="submitted && !gameName.trim()" class="mt-1 text-xs text-red-500">Please enter a game name.</p>
          </div>

          <div>
            <label for="host-name" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1.5">
              Your Name
            </label>
            <input
              id="host-name"
              v-model="hostName"
              type="text"
              placeholder="e.g. Max"
              maxlength="30"
              class="w-full rounded-xl border border-neutral-300 dark:border-neutral-600 bg-neutral-50 dark:bg-neutral-700 px-4 py-2.5 text-sm text-neutral-900 dark:text-neutral-100 placeholder-neutral-400 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 outline-none transition-all"
              :class="{ 'border-red-400 dark:border-red-500': submitted && !hostName.trim() }"
            />
            <p v-if="submitted && !hostName.trim()" class="mt-1 text-xs text-red-500">Please enter your name.</p>
          </div>

          <div class="flex gap-3 pt-2">
            <button
              type="button"
              class="flex-1 rounded-xl border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-700 text-neutral-700 dark:text-neutral-300 font-medium py-2.5 text-sm hover:bg-neutral-50 dark:hover:bg-neutral-600 transition-colors"
              @click="$emit('close')"
            >
              Cancel
            </button>
            <button
              type="submit"
              class="flex-1 rounded-xl bg-amber-500 hover:bg-amber-600 active:bg-amber-700 text-white font-semibold py-2.5 text-sm transition-colors duration-150"
            >
              Create Game
            </button>
          </div>
        </form>
      </div>
    </div>
  </Teleport>
</template>
