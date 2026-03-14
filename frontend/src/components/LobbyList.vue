<script setup lang="ts">
import type { Lobby } from "@/composables/useLobby";
import LobbyCard from "./LobbyCard.vue";

interface Props {
  lobbies: Lobby[];
  loading: boolean;
  error: string | null;
}

defineProps<Props>();
defineEmits<{ join: [lobbyId: string]; refresh: [] }>();
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-5">
      <h2 class="text-xl font-bold text-neutral-900 dark:text-neutral-100">Open Games</h2>
      <button
        class="inline-flex items-center gap-1.5 text-sm font-medium text-amber-600 dark:text-amber-400 hover:text-amber-800 dark:hover:text-amber-300 hover:underline transition-colors"
        :disabled="loading"
        @click="$emit('refresh')"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          class="h-4 w-4"
          :class="{ 'animate-spin': loading }"
          viewBox="0 0 20 20"
          fill="currentColor"
        >
          <path
            fill-rule="evenodd"
            d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z"
            clip-rule="evenodd"
          />
        </svg>
        Refresh
      </button>
    </div>

    <div v-if="error" class="rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 p-4 text-sm text-red-700 dark:text-red-400 mb-4">
      {{ error }}
    </div>

    <div v-if="loading && lobbies.length === 0" class="grid gap-4 sm:grid-cols-2">
      <div v-for="i in 4" :key="'skeleton-' + i" class="rounded-2xl bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 p-5 shadow-sm animate-pulse">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0 flex-1">
            <div class="h-5 w-3/4 rounded bg-neutral-200 dark:bg-neutral-700"></div>
            <div class="mt-2 h-3 w-1/3 rounded bg-neutral-200 dark:bg-neutral-700"></div>
          </div>
          <div class="h-5 w-12 rounded-full bg-neutral-200 dark:bg-neutral-700"></div>
        </div>
        <div class="mt-4 flex items-center gap-3">
          <div class="flex -space-x-1.5">
            <div class="w-7 h-7 rounded-full bg-neutral-200 dark:bg-neutral-700 border-2 border-white dark:border-neutral-800"></div>
          </div>
          <div class="h-3 w-24 rounded bg-neutral-200 dark:bg-neutral-700"></div>
        </div>
        <div class="mt-4 h-10 rounded-xl bg-neutral-200 dark:bg-neutral-700"></div>
      </div>
    </div>

    <div v-else-if="lobbies.length === 0" class="text-center py-12">
      <div class="text-4xl mb-3">🎲</div>
      <p class="text-neutral-500 dark:text-neutral-400 text-sm">No open games yet. Be the first to create one!</p>
    </div>

    <div v-else class="grid gap-4 sm:grid-cols-2">
      <LobbyCard v-for="lobby in lobbies" :key="lobby.id" :lobby="lobby" @join="$emit('join', $event)" />
    </div>
  </div>
</template>
