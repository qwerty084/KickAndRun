<script setup lang="ts">
import { onMounted, onUnmounted, ref, computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useGame } from "@/composables/useGame";

const route = useRoute();
const router = useRouter();
const { startGame } = useGame();

const lobbyId = route.params.id as string;
const myPlayerId = (route.query.playerId as string) ?? "";
const myPlayerName = (route.query.playerName as string) ?? "";

const API_BASE = import.meta.env.VITE_API_BASE_URL ?? "/api";

interface LobbyData {
  id: string;
  code: string;
  name: string;
  hostPlayer: { id: string; name: string };
  players: { id: string; name: string }[];
  maxPlayers: number;
  status: string;
}

const lobby = ref<LobbyData | null>(null);
const error = ref<string | null>(null);
const starting = ref(false);

const isHost = computed(() => lobby.value?.hostPlayer.id === myPlayerId);
const canStart = computed(() => (lobby.value?.players.length ?? 0) >= 2);
const myPlayerIndex = computed(() => {
  if (!lobby.value) return -1;
  return lobby.value.players.findIndex((p) => p.id === myPlayerId);
});

let pollTimer: ReturnType<typeof setInterval> | null = null;

async function fetchLobby() {
  try {
    const res = await fetch(`${API_BASE}/lobbies/${lobbyId}`);
    if (!res.ok) throw new Error("Lobby not found");
    lobby.value = await res.json();

    // If game already started, redirect
    if (lobby.value?.status === "in_game") {
      await findAndJoinGame();
    }
  } catch (e) {
    error.value = e instanceof Error ? e.message : "Failed to load lobby";
  }
}

async function findAndJoinGame() {
  // Lobby is in_game — we need the game session ID
  // For now, we'll need to know the gameSessionId. Let's try starting (which returns it if already started)
  // or we check if the start response already gave us the ID
  // Actually the start endpoint returns the gameSessionId. We can also add a lobby endpoint.
  // For simplicity, let's try to start — if it fails with conflict, we need another approach.
  // TODO: Add GET /api/lobbies/:id/game endpoint on backend
  error.value = "Game has started! Refreshing...";
}

async function handleStart() {
  starting.value = true;
  error.value = null;
  try {
    const result = await startGame(lobbyId);
    if (result) {
      router.push({
        name: "game",
        params: { id: result.gameSessionId },
        query: {
          playerId: myPlayerId,
          playerName: myPlayerName,
          playerIndex: String(myPlayerIndex.value),
        },
      });
    }
  } catch (e) {
    error.value = e instanceof Error ? e.message : "Failed to start game";
  } finally {
    starting.value = false;
  }
}

onMounted(() => {
  fetchLobby();
  pollTimer = setInterval(fetchLobby, 3000);
});

onUnmounted(() => {
  if (pollTimer) clearInterval(pollTimer);
});

const playerColors = ["green", "yellow", "red", "black"] as const;
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-amber-50 to-orange-50 dark:from-neutral-900 dark:to-neutral-800">
    <header class="px-4 py-3 flex items-center gap-4">
      <button
        class="inline-flex items-center gap-1.5 text-sm font-medium text-amber-700 dark:text-amber-400 hover:text-amber-900 dark:hover:text-amber-300 transition-colors"
        @click="router.push({ name: 'home' })"
      >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
          <path
            fill-rule="evenodd"
            d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z"
            clip-rule="evenodd"
          />
        </svg>
        Back to Home
      </button>
    </header>

    <main class="max-w-lg mx-auto px-4 pt-8">
      <div class="bg-white dark:bg-neutral-800 rounded-2xl shadow-xl p-8">
        <div v-if="error" class="mb-4 p-3 rounded-lg bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 text-sm">
          {{ error }}
        </div>

        <template v-if="lobby">
          <div class="text-center mb-6">
            <h1 class="text-2xl font-bold text-neutral-900 dark:text-neutral-100">{{ lobby.name }}</h1>
            <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
              Code: <span class="font-mono font-bold text-amber-600 dark:text-amber-400 text-lg">{{ lobby.code }}</span>
            </p>
            <p class="text-xs text-neutral-400 dark:text-neutral-500 mt-1">Share this code with friends to join</p>
          </div>

          <div class="space-y-3 mb-8">
            <h2 class="text-sm font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wide">
              Players ({{ lobby.players.length }}/{{ lobby.maxPlayers }})
            </h2>
            <div
              v-for="(player, index) in lobby.players"
              :key="player.id"
              class="flex items-center gap-3 p-3 rounded-xl border transition-colors"
              :class="
                player.id === myPlayerId
                  ? 'border-amber-300 dark:border-amber-600 bg-amber-50 dark:bg-amber-900/20'
                  : 'border-neutral-200 dark:border-neutral-700'
              "
            >
              <div
                class="w-8 h-8 rounded-full border-2 border-black flex-shrink-0"
                :class="{
                  'bg-green-700': playerColors[index] === 'green',
                  'bg-amber-400': playerColors[index] === 'yellow',
                  'bg-red-600': playerColors[index] === 'red',
                  'bg-black': playerColors[index] === 'black',
                }"
              ></div>
              <div class="flex-1 min-w-0">
                <p class="font-medium text-neutral-900 dark:text-neutral-100 truncate">
                  {{ player.name }}
                  <span v-if="player.id === lobby.hostPlayer.id" class="text-xs text-amber-600 dark:text-amber-400 ml-1">
                    👑 Host
                  </span>
                  <span v-if="player.id === myPlayerId" class="text-xs text-neutral-500 ml-1">(You)</span>
                </p>
                <p class="text-xs text-neutral-500 dark:text-neutral-400 capitalize">{{ playerColors[index] }}</p>
              </div>
            </div>

            <!-- Empty slots -->
            <div
              v-for="i in lobby.maxPlayers - lobby.players.length"
              :key="'empty-' + i"
              class="flex items-center gap-3 p-3 rounded-xl border border-dashed border-neutral-300 dark:border-neutral-600"
            >
              <div class="w-8 h-8 rounded-full border-2 border-dashed border-neutral-300 dark:border-neutral-600"></div>
              <p class="text-sm text-neutral-400 dark:text-neutral-500">Waiting for player...</p>
            </div>
          </div>

          <div v-if="isHost" class="text-center">
            <button
              :disabled="!canStart || starting"
              class="w-full py-3 rounded-xl font-semibold text-white shadow-lg transition-all duration-200"
              :class="
                canStart && !starting
                  ? 'bg-green-600 hover:bg-green-700 active:bg-green-800 shadow-green-500/25 hover:shadow-green-500/40'
                  : 'bg-neutral-300 dark:bg-neutral-600 cursor-not-allowed'
              "
              @click="handleStart"
            >
              {{ starting ? "Starting..." : canStart ? "🎲 Start Game" : "Waiting for players..." }}
            </button>
            <p v-if="!canStart" class="mt-2 text-xs text-neutral-400">At least 2 players needed</p>
          </div>
          <div v-else class="text-center">
            <div class="py-3 rounded-xl bg-neutral-100 dark:bg-neutral-700/50 text-neutral-500 dark:text-neutral-400 font-medium">
              <div class="inline-block w-4 h-4 border-2 border-amber-500 border-t-transparent rounded-full animate-spin mr-2"></div>
              Waiting for host to start...
            </div>
          </div>
        </template>

        <template v-else-if="!error">
          <div class="text-center py-8">
            <div class="inline-block w-8 h-8 border-2 border-amber-500 border-t-transparent rounded-full animate-spin"></div>
            <p class="mt-3 text-neutral-500">Loading lobby...</p>
          </div>
        </template>
      </div>
    </main>
  </div>
</template>
