<script setup lang="ts">
import { onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import { useLobby } from "@/composables/useLobby";
import { usePlayerSession, type PlayerSession } from "@/composables/usePlayerSession";
import { apiFetch } from "@/composables/apiFetch";
import { useAuthStore } from "@/stores/auth";
import TheBoard from "@/components/TheBoard.vue";
import LobbyList from "@/components/LobbyList.vue";
import CreateLobbyDialog from "@/components/CreateLobbyDialog.vue";
import JoinLobbyDialog from "@/components/JoinLobbyDialog.vue";
import AuthDialog from "@/components/AuthDialog.vue";

const router = useRouter();
const { lobbies, loading, error, fetchLobbies, createLobby, joinLobby } = useLobby();
const { loadSession, clearSession } = usePlayerSession();
const authStore = useAuthStore();

const showCreateDialog = ref(false);
const showJoinDialog = ref(false);
const showAuthDialog = ref(false);
const activeSession = ref<PlayerSession | null>(null);

onMounted(async () => {
  fetchLobbies();
  authStore.loadUser();

  // Check for active session to show "Continue" button
  const session = loadSession();
  if (session?.gameId) {
    try {
      const res = await apiFetch(`/games/${session.gameId}/player/${session.playerId}`);
      if (res.ok) {
        activeSession.value = session;
      } else {
        clearSession();
      }
    } catch {
      // Game no longer exists
      clearSession();
    }
  } else if (session?.lobbyId) {
    activeSession.value = session;
  }
});

async function handleCreate(name: string, hostName: string) {
  const lobby = await createLobby(name, hostName);
  if (lobby) {
    showCreateDialog.value = false;
    router.push({
      name: "lobby",
      params: { id: lobby.id },
      query: { playerId: lobby.hostPlayer.id, playerName: lobby.hostPlayer.name },
    });
  }
}

async function handleJoinFromList() {
  showJoinDialog.value = true;
}

async function handleJoin(lobbyId: string, playerName: string) {
  const lobby = await joinLobby(lobbyId, playerName);
  if (lobby) {
    showJoinDialog.value = false;
    const players = lobby.players ?? [];
    const me = players.find((p: { name: string }) => p.name === playerName);
    router.push({
      name: "lobby",
      params: { id: lobbyId },
      query: { playerId: me?.id ?? "", playerName },
    });
  }
}

function resumeSession() {
  const session = activeSession.value;
  if (!session) return;

  if (session.gameId) {
    router.push({
      name: "game",
      params: { id: session.gameId },
      query: { playerId: session.playerId, playerName: session.playerName },
    });
  } else if (session.lobbyId) {
    router.push({
      name: "lobby",
      params: { id: session.lobbyId },
      query: { playerId: session.playerId, playerName: session.playerName },
    });
  }
}

function dismissSession() {
  clearSession();
  activeSession.value = null;
}
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-amber-50 via-orange-50 to-yellow-50 dark:from-neutral-900 dark:via-neutral-900 dark:to-neutral-800">
    <!-- Auth Header -->
    <header class="relative z-10 max-w-6xl mx-auto px-4 pt-4 flex justify-end">
      <div v-if="authStore.isAuthenticated" class="flex items-center gap-3">
        <span class="text-sm font-medium text-neutral-700 dark:text-neutral-300">
          👤 {{ authStore.user?.username }}
        </span>
        <button
          class="text-xs text-neutral-500 dark:text-neutral-400 hover:text-red-500 dark:hover:text-red-400 font-medium px-2 py-1 rounded hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"
          @click="authStore.logout()"
        >
          Log out
        </button>
      </div>
      <button
        v-else
        class="text-sm font-medium text-amber-700 dark:text-amber-400 hover:text-amber-900 dark:hover:text-amber-300 px-3 py-1.5 rounded-lg hover:bg-amber-100 dark:hover:bg-amber-900/30 transition-colors"
        @click="showAuthDialog = true"
      >
        Log in / Register
      </button>
    </header>

    <!-- Hero Section -->
    <section class="relative overflow-hidden">
      <div class="absolute inset-0 opacity-[0.03] dark:opacity-[0.05]" aria-hidden="true">
        <div class="absolute inset-0" style="background-image: radial-gradient(circle at 1px 1px, currentColor 1px, transparent 0); background-size: 40px 40px;"></div>
      </div>

      <div class="relative max-w-6xl mx-auto px-4 pt-12 pb-16 sm:pt-20 sm:pb-24">
        <div class="flex flex-col lg:flex-row items-center gap-10 lg:gap-16">
          <!-- Left: Title & CTA -->
          <div class="flex-1 text-center lg:text-left">
            <div class="inline-flex items-center gap-2 rounded-full bg-amber-100 dark:bg-amber-900/40 px-3 py-1 text-xs font-medium text-amber-700 dark:text-amber-300 mb-6">
              <span class="text-base">🎲</span>
              Classic Board Game
            </div>

            <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight text-neutral-900 dark:text-neutral-100 leading-tight">
              Mensch ärgere
              <span class="bg-gradient-to-r from-amber-500 to-red-500 bg-clip-text text-transparent">dich nicht</span>
            </h1>

            <p class="mt-5 text-lg text-neutral-600 dark:text-neutral-400 max-w-lg mx-auto lg:mx-0">
              The beloved German classic — now online. Create a game, invite your friends, and race your pieces home!
            </p>

            <!-- Resume session banner -->
            <div
              v-if="activeSession"
              class="mt-6 mx-auto lg:mx-0 max-w-lg flex items-center gap-3 rounded-xl bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 p-3"
            >
              <span class="text-2xl">🔄</span>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-green-800 dark:text-green-300">
                  {{ activeSession.gameId ? "Game in progress" : "Lobby waiting" }}
                </p>
                <p class="text-xs text-green-600 dark:text-green-400 truncate">
                  Playing as {{ activeSession.playerName }}
                </p>
              </div>
              <button
                class="rounded-lg bg-green-600 hover:bg-green-700 text-white text-sm font-semibold px-4 py-2 transition-colors"
                @click="resumeSession"
              >
                {{ activeSession.gameId ? "Continue" : "Return" }}
              </button>
              <button
                class="text-green-600 dark:text-green-400 hover:text-green-800 dark:hover:text-green-200 text-xs"
                @click="dismissSession"
                title="Dismiss"
              >
                ✕
              </button>
            </div>

            <div class="mt-8 flex flex-col sm:flex-row gap-3 justify-center lg:justify-start">
              <button
                class="inline-flex items-center justify-center gap-2 rounded-xl bg-amber-500 hover:bg-amber-600 active:bg-amber-700 text-white font-semibold px-7 py-3.5 text-base shadow-lg shadow-amber-500/25 hover:shadow-amber-500/40 transition-all duration-200"
                @click="showCreateDialog = true"
              >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                </svg>
                Create Game
              </button>
              <button
                class="inline-flex items-center justify-center gap-2 rounded-xl border-2 border-amber-300 dark:border-amber-700 text-amber-700 dark:text-amber-300 hover:bg-amber-100 dark:hover:bg-amber-900/30 font-semibold px-7 py-3.5 text-base transition-all duration-200"
                @click="showJoinDialog = true"
              >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                  <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z" />
                </svg>
                Join Game
              </button>
            </div>

            <!-- Stats pills -->
            <div class="mt-8 flex items-center gap-4 justify-center lg:justify-start">
              <div class="flex items-center gap-1.5 text-sm text-neutral-500 dark:text-neutral-400">
                <div class="h-2 w-2 rounded-full bg-green-500 animate-pulse"></div>
                <span>{{ lobbies.length }} {{ lobbies.length === 1 ? "game" : "games" }} open</span>
              </div>
              <div class="text-sm text-neutral-400 dark:text-neutral-500">•</div>
              <div class="text-sm text-neutral-500 dark:text-neutral-400">2–4 players</div>
            </div>
          </div>

          <!-- Right: Decorative Board -->
          <div class="board-wrapper relative">
            <div class="absolute -inset-4 rounded-3xl bg-gradient-to-br from-amber-200/50 to-orange-200/50 dark:from-amber-900/20 dark:to-orange-900/20 blur-2xl"></div>
            <div class="board-scaler relative rounded-2xl bg-amber-200 dark:bg-amber-900 p-3 shadow-2xl border border-amber-300/50 dark:border-amber-700/50">
              <div class="p-3 border-2 border-black/20 dark:border-white/10 rounded-xl bg-amber-100/50 dark:bg-amber-800/30">
                <TheBoard />
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Lobby Section -->
    <section class="max-w-3xl mx-auto px-4 pb-16">
      <LobbyList :lobbies="lobbies" :loading="loading" :error="error" @join="handleJoinFromList" @refresh="fetchLobbies" />
    </section>

    <!-- Dialogs -->
    <CreateLobbyDialog v-if="showCreateDialog" @create="handleCreate" @close="showCreateDialog = false" />
    <JoinLobbyDialog v-if="showJoinDialog" :lobbies="lobbies" @join="handleJoin" @close="showJoinDialog = false" />
    <AuthDialog v-if="showAuthDialog" @close="showAuthDialog = false" />
  </div>
</template>

<style scoped>
/*
 * transform: scale() does not affect layout size, so we use a wrapper
 * with explicit dimensions matching the scaled output, and position the
 * board absolutely inside with transform-origin: top left.
 */
.board-wrapper {
  --board-size: 500px;
  --board-scale: 0.55;
  width: calc(var(--board-size) * var(--board-scale));
  height: calc(var(--board-size) * var(--board-scale));
  flex-shrink: 0;
}

.board-scaler {
  width: var(--board-size);
  height: var(--board-size);
  transform: scale(var(--board-scale));
  transform-origin: top left;
}

@media (min-width: 640px) {
  .board-wrapper {
    --board-scale: 0.65;
  }
}

@media (min-width: 1024px) {
  .board-wrapper {
    --board-scale: 0.6;
  }
}
</style>
