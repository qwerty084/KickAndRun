<script setup lang="ts">
import { onMounted, onUnmounted, computed, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import TheBoard from "@/components/TheBoard.vue";
import GameLog from "@/components/GameLog.vue";
import ConnectionStatus from "@/components/ConnectionStatus.vue";
import ChatPanel from "@/components/ChatPanel.vue";
import { useGameStore } from "@/stores/game";
import { usePlayerSession } from "@/composables/usePlayerSession";
import { useSoundEffects } from "@/composables/useSoundEffects";
import { apiFetch } from "@/composables/apiFetch";
import { buildPieceMap } from "@/composables/boardLayout";
import type { ChatMessage } from "@/composables/useChat";
import type { PlayerColor } from "@/types/Game";
import type { GameEvent } from "@/stores/game";

const route = useRoute();
const router = useRouter();
const gameId = route.params.id as string;
const store = useGameStore();
const { loadSession, updateSession } = usePlayerSession();
const { muted: soundMuted, toggleMute } = useSoundEffects();
const isRematchLoading = ref(false);
const diceAnimating = ref(false);
const gameChatPanel = ref<InstanceType<typeof ChatPanel> | null>(null);

watch(
  () => store.lastDiceRoll,
  (newVal) => {
    if (newVal !== null) {
      diceAnimating.value = true;
      setTimeout(() => {
        diceAnimating.value = false;
      }, 400);
    }
  },
);

const colorLabels: Record<PlayerColor, string> = {
  green: "🟢 Green",
  yellow: "🟡 Yellow",
  red: "🔴 Red",
  black: "⚫ Black",
};

const pieceSummary = computed(() => {
  if (!store.gameState) return [];
  return store.gameState.players.map((color) => {
    const pieces = store.gameState!.pieces[color] ?? [];
    const inBase = pieces.filter((p) => p.position === "base").length;
    const onPath = pieces.filter((p) => p.position.startsWith("path:")).length;
    const inGoal = pieces.filter((p) => p.position.startsWith("goal:") || p.position === "finished").length;
    return { color, inBase, onPath, inGoal };
  });
});

const lastActionToast = ref<string | null>(null);
let toastTimeout: ReturnType<typeof setTimeout> | null = null;

watch(
  () => store.eventLog.length,
  () => {
    const events = store.eventLog;
    if (events.length === 0) return;
    const last = events[events.length - 1];
    lastActionToast.value = formatToast(last);
    if (toastTimeout) clearTimeout(toastTimeout);
    toastTimeout = setTimeout(() => {
      lastActionToast.value = null;
    }, 3000);
  },
);

function formatToast(event: GameEvent): string {
  const bot = event.isBot ? "🤖 " : "";
  const name = `${bot}${event.playerName}`;
  if (event.type === "dice_rolled") {
    return `${name} rolled a ${event.diceRoll ?? "?"}`;
  }
  if (event.type === "piece_moved") {
    let msg = `${name} moved piece`;
    if (event.kicked) msg += " — kicked! 💥";
    if (event.extraTurn) msg += " — extra turn!";
    if (event.winner) msg = `🏆 ${event.playerName} wins!`;
    return msg;
  }
  return `${name} — ${event.type}`;
}

async function requestRematch() {
  if (!store.lobbyId || !store.myPlayerId) return;
  isRematchLoading.value = true;
  try {
    const res = await apiFetch(`/lobbies/${store.lobbyId}/rematch`, {
      method: "POST",
      body: JSON.stringify({ playerId: store.myPlayerId }),
    });
    if (res.ok) {
      updateSession({ gameId: undefined });
      router.push({ name: "lobby", params: { id: store.lobbyId } });
    }
  } catch {
    // Silently fail — user can try again
  } finally {
    isRematchLoading.value = false;
  }
}

function handleFieldClick(position: string) {
  if (!store.isMyTurn || !store.myColor) return;

  // If clicking a piece that has valid moves, select it
  const pieceMap = store.gameState ? buildPieceMap(store.gameState) : new Map();
  const piece = pieceMap.get(position);

  if (piece && piece.color === store.myColor) {
    // Clicked own piece — select or move it
    if (store.validMoves.some((m) => m.pieceIndex === piece.pieceIndex)) {
      if (store.selectedPieceIndex === piece.pieceIndex) {
        // Double-click same piece — execute if only one move
        const moves = store.validMoves.filter((m) => m.pieceIndex === piece.pieceIndex);
        if (moves.length === 1) {
          store.move(piece.pieceIndex);
        }
      } else {
        store.selectPiece(piece.pieceIndex);
      }
    }
  } else if (store.selectedPieceIndex !== null) {
    // Clicked a target field — execute the move if valid
    const selectedMoves = store.validMoves.filter((m) => m.pieceIndex === store.selectedPieceIndex);
    if (selectedMoves.length === 1) {
      store.move(store.selectedPieceIndex);
    }
  }
}

onMounted(async () => {
  let playerId = route.query.playerId as string;
  let playerIndex = parseInt(route.query.playerIndex as string, 10);

  // Try restoring from localStorage if query params are missing (page refresh)
  if (!playerId || isNaN(playerIndex)) {
    const session = loadSession();
    if (session && session.gameId === gameId) {
      try {
        const res = await apiFetch(`/games/${gameId}/player/${session.playerId}`);
        if (res.ok) {
          const data = await res.json();
          playerId = session.playerId;
          playerIndex = data.playerIndex;
        }
      } catch {
        // Validation failed — continue as spectator
      }
    }
  }

  if (playerId && !isNaN(playerIndex)) {
    store.setMyPlayer(playerId, playerIndex);
    updateSession({ gameId });
  }

  await store.loadGame(gameId);
  store.subscribeMercure();

  store.onRematch((lobbyId) => {
    updateSession({ gameId: undefined });
    router.push({ name: "lobby", params: { id: lobbyId } });
  });

  store.onChatMessage((message) => {
    gameChatPanel.value?.addMessage(message as unknown as ChatMessage);
  });
});

onUnmounted(() => {
  store.unsubscribeMercure();
});
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
      <span class="text-sm text-neutral-500 dark:text-neutral-400">Game {{ gameId.substring(0, 8) }}…</span>
      <ConnectionStatus :status="store.connectionStatus" class="ml-auto" />
      <button
        class="text-lg hover:scale-110 hover:opacity-80 transition-all"
        :title="soundMuted ? 'Unmute sounds' : 'Mute sounds'"
        :aria-label="soundMuted ? 'Unmute sounds' : 'Mute sounds'"
        @click="toggleMute"
      >
        {{ soundMuted ? "🔇" : "🔊" }}
      </button>
    </header>

    <!-- Loading -->
    <div v-if="store.isLoading && !store.gameState" class="flex justify-center items-center h-64">
      <p class="text-neutral-500 dark:text-neutral-400 animate-pulse">Loading game…</p>
    </div>

    <!-- Error -->
    <div v-else-if="store.lastError && !store.gameState" class="flex flex-col items-center h-64 justify-center gap-4">
      <p class="text-red-600 dark:text-red-400">{{ store.lastError }}</p>
      <button class="text-sm text-amber-600 hover:underline" @click="store.loadGame(gameId)">Retry</button>
    </div>

    <!-- Game layout -->
    <main v-else class="flex flex-col lg:flex-row gap-4 px-4 pb-8 max-w-[1200px] mx-auto">
      <!-- Board column -->
      <div class="flex-1 min-w-0" role="status" aria-live="polite">
        <!-- Action toast -->
        <Transition name="toast">
          <div
            v-if="lastActionToast"
            class="mb-3 mx-auto text-center text-sm font-medium py-2 px-4 rounded-lg bg-white dark:bg-neutral-800 shadow-lg border border-neutral-200 dark:border-neutral-700"
            style="max-width: 700px"
          >
            {{ lastActionToast }}
          </div>
        </Transition>

        <!-- Bot thinking banner -->
        <div
          v-if="store.botThinking"
          class="mb-3 mx-auto text-center text-sm font-medium py-2 px-4 rounded-lg bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300 animate-pulse"
          style="max-width: 700px"
        >
          <span class="inline-block w-3 h-3 border-2 border-amber-500 border-t-transparent rounded-full animate-spin mr-1.5"></span>
          🤖 Bot is thinking...
        </div>

        <div class="bg-amber-200 dark:bg-amber-900 p-2 rounded border-[3px] border-red-600 mx-auto" style="max-width: 700px">
          <div class="p-4 h-full border-2 border-black dark:border-neutral-300 rounded-sm">
            <TheBoard
              :game-state="store.gameState"
              :valid-moves="store.validMoves"
              :selected-piece-index="store.selectedPieceIndex"
              :my-color="store.myColor"
              @field-click="handleFieldClick"
            />
          </div>
        </div>
      </div>

      <!-- HUD Panel -->
      <aside class="w-full lg:w-72 flex flex-col gap-4">
        <!-- Turn indicator -->
        <div class="rounded-xl bg-white dark:bg-neutral-800 shadow-md border border-neutral-200 dark:border-neutral-700 p-4">
          <h2 class="text-sm font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wide mb-2">Current Turn</h2>
          <div v-if="store.currentPlayer" class="flex items-center gap-2">
            <span class="text-xl">{{ colorLabels[store.currentPlayer] }}</span>
            <span v-if="store.isMyTurn" class="text-xs font-bold text-amber-600 dark:text-amber-400 bg-amber-100 dark:bg-amber-900/40 px-2 py-0.5 rounded-full">YOUR TURN</span>
          </div>
          <p v-if="store.phase === 'rolling'" class="text-xs text-neutral-500 mt-1">
            Waiting to roll… ({{ store.gameState?.rollAttemptsLeft ?? 0 }} {{ (store.gameState?.rollAttemptsLeft ?? 0) === 1 ? "attempt" : "attempts" }} left)
          </p>
          <p v-else-if="store.phase === 'moving'" class="text-xs text-neutral-500 mt-1">
            Choose a piece to move
          </p>
        </div>

        <!-- Dice + Roll -->
        <div class="rounded-xl bg-white dark:bg-neutral-800 shadow-md border border-neutral-200 dark:border-neutral-700 p-4 text-center">
          <div v-if="store.lastDiceRoll" :class="['text-6xl mb-3', { 'dice-roll': diceAnimating }]" aria-label="Dice result">
            {{ ["", "⚀", "⚁", "⚂", "⚃", "⚄", "⚅"][store.lastDiceRoll] }}
          </div>
          <div v-else class="text-6xl mb-3 text-neutral-300 dark:text-neutral-600">🎲</div>

          <button
            :disabled="!store.isMyTurn || store.phase !== 'rolling' || store.isLoading"
            class="w-full rounded-lg bg-amber-500 hover:bg-amber-600 active:bg-amber-700 text-white font-bold py-2.5 px-4 shadow-md hover:-translate-y-0.5 hover:shadow-lg transition-all disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:translate-y-0 disabled:hover:shadow-md"
            @click="store.roll()"
          >
            {{ store.isLoading ? "Rolling…" : "Roll Dice" }}
          </button>

          <!-- Valid moves summary -->
          <div v-if="store.validMoves.length > 0 && store.isMyTurn" class="mt-3 text-left">
            <p class="text-xs font-medium text-neutral-500 dark:text-neutral-400 mb-1">Valid moves ({{ store.validMoves.length }}):</p>
            <ul class="space-y-1">
              <li
                v-for="mv in store.validMoves"
                :key="mv.pieceIndex"
                class="text-xs px-2 py-1 rounded cursor-pointer transition-colors"
                :class="store.selectedPieceIndex === mv.pieceIndex ? 'bg-amber-200 dark:bg-amber-800 font-bold' : 'hover:bg-neutral-100 dark:hover:bg-neutral-700'"
                @click="store.selectPiece(mv.pieceIndex)"
              >
                Piece {{ mv.pieceIndex + 1 }}: {{ mv.from }} → {{ mv.to }}
              </li>
            </ul>
          </div>

          <div v-if="store.validMoves.length === 0 && store.phase === 'moving' && store.isMyTurn" class="mt-3 text-xs text-neutral-500">
            No valid moves — turn passes
          </div>
        </div>

        <!-- Players -->
        <div class="rounded-xl bg-white dark:bg-neutral-800 shadow-md border border-neutral-200 dark:border-neutral-700 p-4">
          <h2 class="text-sm font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wide mb-3">Players</h2>
          <div class="space-y-2">
            <div
              v-for="ps in pieceSummary"
              :key="ps.color"
              class="flex items-center justify-between p-2 rounded-lg transition-colors"
              :class="{ 'bg-amber-50 dark:bg-amber-900/30 ring-1 ring-amber-300 dark:ring-amber-700': store.currentPlayer === ps.color }"
            >
              <span class="text-sm font-medium">
                {{ colorLabels[ps.color] }}
                <span v-if="store.players[store.gameState?.players.indexOf(ps.color) ?? -1]?.isBot" class="text-xs">🤖</span>
                <span v-if="store.players[store.gameState?.players.indexOf(ps.color) ?? -1]" class="text-xs text-neutral-500 ml-1">
                  {{ store.players[store.gameState?.players.indexOf(ps.color) ?? -1]?.name }}
                </span>
              </span>
              <div class="flex gap-2 text-xs text-neutral-500">
                <span title="In base" :aria-label="ps.inBase + ' pieces in base'">🏠{{ ps.inBase }}</span>
                <span title="On path" :aria-label="ps.onPath + ' pieces on path'">🛤️{{ ps.onPath }}</span>
                <span title="In goal" :aria-label="ps.inGoal + ' pieces in goal'">🏁{{ ps.inGoal }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Game Log -->
        <div class="rounded-xl bg-white dark:bg-neutral-800 shadow-md border border-neutral-200 dark:border-neutral-700 p-4">
          <GameLog :events="store.eventLog" :bot-thinking="store.botThinking" />
        </div>

        <!-- Chat -->
        <ChatPanel
          v-if="store.myPlayerId"
          ref="gameChatPanel"
          context="game"
          :context-id="gameId"
          :my-player-id="store.myPlayerId"
          :collapsed="true"
        />

        <!-- Error display -->
        <div v-if="store.lastError" class="rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 p-3">
          <p class="text-sm text-red-600 dark:text-red-400">{{ store.lastError }}</p>
        </div>
      </aside>
    </main>

    <!-- Win dialog -->
    <div v-if="store.isFinished && store.winner" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
      <div class="bg-white dark:bg-neutral-800 rounded-2xl shadow-2xl p-8 text-center max-w-sm mx-4">
        <div class="text-6xl mb-4">🏆</div>
        <h2 class="text-2xl font-bold text-neutral-900 dark:text-neutral-100 mb-2">Game Over!</h2>
        <p class="text-lg text-neutral-600 dark:text-neutral-400 mb-6">
          {{ colorLabels[store.winner] }} wins!
        </p>
        <div class="flex flex-col gap-3">
          <button
            v-if="store.lobbyId && store.myPlayerId"
            :disabled="isRematchLoading"
            class="rounded-xl bg-amber-500 hover:bg-amber-600 text-white font-bold py-2.5 px-6 shadow-md hover:-translate-y-0.5 hover:shadow-lg transition-all disabled:opacity-50 disabled:hover:translate-y-0 disabled:hover:shadow-md"
            @click="requestRematch"
          >
            {{ isRematchLoading ? "Starting…" : "🔄 Rematch" }}
          </button>
          <button
            class="rounded-xl bg-neutral-200 dark:bg-neutral-700 hover:bg-neutral-300 dark:hover:bg-neutral-600 text-neutral-700 dark:text-neutral-300 font-bold py-2.5 px-6 shadow-md hover:-translate-y-0.5 transition-all"
            @click="router.push({ name: 'home' })"
          >
            Back to Home
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.toast-enter-active {
  transition: all 0.3s ease-out;
}
.toast-leave-active {
  transition: all 0.3s ease-in;
}
.toast-enter-from,
.toast-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}

.dice-roll {
  animation: dice-shake 0.4s ease-out;
}

@keyframes dice-shake {
  0% {
    transform: rotate(0deg) scale(1);
  }
  20% {
    transform: rotate(-15deg) scale(1.1);
  }
  40% {
    transform: rotate(15deg) scale(1.1);
  }
  60% {
    transform: rotate(-8deg) scale(1.05);
  }
  80% {
    transform: rotate(5deg) scale(1.02);
  }
  100% {
    transform: rotate(0deg) scale(1);
  }
}
</style>
