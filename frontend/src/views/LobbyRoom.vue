<script setup lang="ts">
import { onMounted, onUnmounted, ref, computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useGame } from "@/composables/useGame";
import { useMercure } from "@/composables/useMercure";
import { usePlayerSession } from "@/composables/usePlayerSession";
import { apiFetch } from "@/composables/apiFetch";
import ConnectionStatus from "@/components/ConnectionStatus.vue";
import ChatPanel from "@/components/ChatPanel.vue";
import type { ChatMessage } from "@/composables/useChat";

const route = useRoute();
const router = useRouter();
const { startGame } = useGame();
const mercure = useMercure();
const { saveSession, updateSession } = usePlayerSession();

const lobbyId = route.params.id as string;
const myPlayerId = (route.query.playerId as string) ?? "";
const myPlayerName = (route.query.playerName as string) ?? "";

interface LobbyData {
  id: string;
  code: string;
  name: string;
  hostPlayer: { id: string; name: string; isBot: boolean };
  players: { id: string; name: string; isBot: boolean }[];
  maxPlayers: number;
  status: string;
  gameSessionId?: string;
}

const lobby = ref<LobbyData | null>(null);
const error = ref<string | null>(null);
const starting = ref(false);
const chatPanel = ref<InstanceType<typeof ChatPanel> | null>(null);
const codeCopied = ref(false);

const isHost = computed(() => lobby.value?.hostPlayer.id === myPlayerId);
const canStart = computed(() => (lobby.value?.players.length ?? 0) >= 2);
const canAddBot = computed(() => isHost.value && !lobby.value?.players.length || (lobby.value && lobby.value.players.length < lobby.value.maxPlayers));
const myPlayerIndex = computed(() => {
  if (!lobby.value) return -1;
  return lobby.value.players.findIndex((p) => p.id === myPlayerId);
});

const addingBot = ref(false);

async function handleAddBot() {
  if (!lobby.value) return;
  addingBot.value = true;
  error.value = null;
  try {
    const res = await apiFetch(`/lobbies/${lobbyId}/add-bot`, {
      method: "POST",
      body: JSON.stringify({ hostPlayerId: myPlayerId }),
    });
    if (!res.ok) {
      const data = await res.json().catch(() => ({}));
      throw new Error(data.error || "Failed to add bot");
    }
    lobby.value = await res.json();
  } catch (e) {
    error.value = e instanceof Error ? e.message : "Failed to add bot";
  } finally {
    addingBot.value = false;
  }
}

async function handleRemoveBot(botPlayerId: string) {
  if (!lobby.value) return;
  error.value = null;
  try {
    const res = await apiFetch(`/lobbies/${lobbyId}/remove-bot`, {
      method: "POST",
      body: JSON.stringify({ hostPlayerId: myPlayerId, botPlayerId }),
    });
    if (!res.ok) {
      const data = await res.json().catch(() => ({}));
      throw new Error(data.error || "Failed to remove bot");
    }
    lobby.value = await res.json();
  } catch (e) {
    error.value = e instanceof Error ? e.message : "Failed to remove bot";
  }
}

async function fetchLobby() {
  try {
    const res = await apiFetch(`/lobbies/${lobbyId}`);
    if (!res.ok) throw new Error("Lobby not found");
    lobby.value = await res.json();

    if (lobby.value?.status === "in_game") {
      await redirectToGame();
    }
  } catch (e) {
    error.value = e instanceof Error ? e.message : "Failed to load lobby";
  }
}

async function redirectToGame() {
  // Try to get gameSessionId from lobby data first
  if (lobby.value?.gameSessionId) {
    navigateToGame(lobby.value.gameSessionId);
    return;
  }

  // Fallback: fetch from /game endpoint
  try {
    const res = await apiFetch(`/lobbies/${lobbyId}/game`);
    if (!res.ok) throw new Error("Could not find game session");
    const data = await res.json();
    navigateToGame(data.gameSessionId);
  } catch (e) {
    error.value = e instanceof Error ? e.message : "Failed to find game session";
  }
}

function navigateToGame(gameSessionId: string) {
  updateSession({ gameId: gameSessionId });
  router.push({
    name: "game",
    params: { id: gameSessionId },
    query: {
      playerId: myPlayerId,
      playerName: myPlayerName,
      playerIndex: String(myPlayerIndex.value),
    },
  });
}

function subscribeMercure() {
  mercure.subscribe(`lobby/${lobbyId}`, (payload) => {
    const p = payload as Record<string, unknown>;
    const eventType = p.event as string;

    if (p.lobby) {
      lobby.value = p.lobby as LobbyData;
    }

    if (eventType === "chat_message" && p.message) {
      chatPanel.value?.addMessage(p.message as ChatMessage);
    }

    if (eventType === "game_started") {
      const gameSessionId =
        (p.gameSessionId as string) ?? (p.lobby as LobbyData | undefined)?.gameSessionId;
      if (gameSessionId) {
        navigateToGame(gameSessionId);
      } else {
        redirectToGame();
      }
    }
  });
}

function unsubscribeMercure() {
  mercure.unsubscribe();
}

async function handleStart() {
  starting.value = true;
  error.value = null;
  try {
    const result = await startGame(lobbyId);
    if (result) {
      navigateToGame(result.gameSessionId);
    }
  } catch (e) {
    error.value = e instanceof Error ? e.message : "Failed to start game";
  } finally {
    starting.value = false;
  }
}

onMounted(() => {
  fetchLobby();
  subscribeMercure();
  if (myPlayerId && myPlayerName) {
    saveSession({ playerId: myPlayerId, playerName: myPlayerName, lobbyId });
  }
});

onUnmounted(() => {
  unsubscribeMercure();
});

const playerColors = ["green", "yellow", "red", "black"] as const;

async function copyLobbyCode() {
  if (!lobby.value) return;
  try {
    await navigator.clipboard.writeText(lobby.value.code);
    codeCopied.value = true;
    setTimeout(() => (codeCopied.value = false), 2000);
  } catch {
    // Fallback: select text for manual copy
  }
}
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-amber-50 to-orange-50 dark:from-neutral-900 dark:to-neutral-800">
    <header class="px-4 py-3 flex items-center gap-4">
      <button
        class="inline-flex items-center gap-1.5 text-sm font-medium text-amber-700 dark:text-amber-400 hover:text-amber-900 dark:hover:text-amber-300 hover:underline transition-colors"
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
      <ConnectionStatus :status="mercure.status.value" class="ml-auto" />
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
              Code:
              <span class="font-mono font-bold text-amber-600 dark:text-amber-400 text-lg">{{ lobby.code }}</span>
              <button
                class="ml-2 text-xs font-medium px-2 py-0.5 rounded-lg transition-all duration-200"
                :class="
                  codeCopied
                    ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400'
                    : 'bg-neutral-100 dark:bg-neutral-700 text-neutral-600 dark:text-neutral-400 hover:bg-amber-100 dark:hover:bg-amber-900/30 hover:text-amber-700 dark:hover:text-amber-400'
                "
                @click="copyLobbyCode"
              >
                {{ codeCopied ? "✓ Copied!" : "📋 Copy" }}
              </button>
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
                  <span v-if="player.isBot" class="text-xs ml-1">🤖</span>
                  <span v-if="player.id === lobby.hostPlayer.id" class="text-xs text-amber-600 dark:text-amber-400 ml-1">
                    👑 Host
                  </span>
                  <span v-if="player.id === myPlayerId" class="text-xs text-neutral-500 ml-1">(You)</span>
                </p>
                <p class="text-xs text-neutral-500 dark:text-neutral-400 capitalize">{{ playerColors[index] }}</p>
              </div>
              <button
                v-if="isHost && player.isBot"
                class="text-xs text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 font-medium px-2 py-1 rounded hover:bg-red-50 dark:hover:bg-red-900/20 hover:underline transition-colors"
                @click="handleRemoveBot(player.id)"
              >
                Remove
              </button>
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

            <!-- Add Bot button -->
            <button
              v-if="isHost && canAddBot"
              :disabled="addingBot"
              class="w-full flex items-center justify-center gap-2 p-3 rounded-xl border-2 border-dashed border-amber-300 dark:border-amber-600 text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-900/20 hover:-translate-y-0.5 transition-all duration-200 font-medium text-sm"
              @click="handleAddBot"
            >
              <span>🤖</span>
              {{ addingBot ? "Adding..." : "Add Bot" }}
            </button>
          </div>

          <div v-if="isHost" class="text-center">
            <button
              :disabled="!canStart || starting"
              class="w-full py-3 rounded-xl font-semibold text-white shadow-lg transition-all duration-200"
              :class="
                canStart && !starting
                  ? 'bg-green-600 hover:bg-green-700 active:bg-green-800 shadow-green-500/25 hover:shadow-green-500/40 hover:-translate-y-0.5'
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

      <!-- Chat -->
      <div v-if="lobby && myPlayerId" class="mt-4">
        <ChatPanel
          ref="chatPanel"
          context="lobby"
          :context-id="lobbyId"
          :my-player-id="myPlayerId"
        />
      </div>
    </main>
  </div>
</template>
