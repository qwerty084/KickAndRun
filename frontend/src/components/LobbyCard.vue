<script setup lang="ts">
import type { Lobby } from "@/composables/useLobby";

interface Props {
  lobby: Lobby;
}

defineProps<Props>();
defineEmits<{ join: [lobbyId: string] }>();

const playerColors = ["bg-green-500", "bg-amber-400", "bg-red-500", "bg-neutral-800"];
</script>

<template>
  <div
    class="group relative rounded-2xl bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 p-5 shadow-sm hover:shadow-md transition-all duration-200"
  >
    <div class="flex items-start justify-between gap-3">
      <div class="min-w-0">
        <h3 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100 truncate">
          {{ lobby.name }}
        </h3>
        <p class="mt-0.5 text-xs font-mono text-neutral-400 dark:text-neutral-500">
          Code: {{ lobby.code }}
        </p>
      </div>
      <span
        class="shrink-0 inline-flex items-center rounded-full bg-amber-100 dark:bg-amber-900/40 px-2.5 py-0.5 text-xs font-medium text-amber-800 dark:text-amber-300"
      >
        {{ lobby.players.length }} / {{ lobby.maxPlayers }}
      </span>
    </div>

    <div class="mt-4 flex items-center gap-3">
      <div class="flex -space-x-1.5">
        <div
          v-for="(player, i) in lobby.players"
          :key="player.id"
          :class="[playerColors[i % playerColors.length], 'w-7 h-7 rounded-full border-2 border-white dark:border-neutral-800 flex items-center justify-center']"
          :title="player.name"
        >
          <span class="text-[10px] font-bold text-white">{{ player.name.charAt(0).toUpperCase() }}</span>
        </div>
      </div>
      <span class="text-sm text-neutral-500 dark:text-neutral-400">
        Host: <span class="font-medium text-neutral-700 dark:text-neutral-300">{{ lobby.hostPlayer.name }}</span>
      </span>
    </div>

    <button
      v-if="lobby.players.length < lobby.maxPlayers"
      class="mt-4 w-full rounded-xl bg-amber-500 hover:bg-amber-600 active:bg-amber-700 text-white font-semibold py-2.5 text-sm transition-colors duration-150"
      @click="$emit('join', lobby.id)"
    >
      Join Game
    </button>
    <div
      v-else
      class="mt-4 w-full rounded-xl bg-neutral-100 dark:bg-neutral-700 text-neutral-400 dark:text-neutral-500 font-semibold py-2.5 text-sm text-center"
    >
      Full
    </div>
  </div>
</template>
