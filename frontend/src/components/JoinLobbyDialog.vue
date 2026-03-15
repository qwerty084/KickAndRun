<script setup lang="ts">
import type { Lobby } from "@/composables/useLobby";
import { ref, watch, onMounted, onUnmounted, nextTick } from "vue";

interface Props {
  lobbies: Lobby[];
  loading?: boolean;
}

withDefaults(defineProps<Props>(), {
  loading: false,
});
const emit = defineEmits<{ join: [lobbyId: string, playerName: string]; close: [] }>();

const selectedLobby = ref<Lobby | null>(null);
const playerName = ref("");
const submitted = ref(false);
const dialogPanel = ref<HTMLDivElement | null>(null);
const playerNameInput = ref<HTMLInputElement | null>(null);

function openJoin(lobby: Lobby) {
  selectedLobby.value = lobby;
  playerName.value = "";
  submitted.value = false;
}

watch(selectedLobby, (val) => {
  if (val) {
    nextTick(() => playerNameInput.value?.focus());
  }
});

function handleSubmit() {
  submitted.value = true;
  if (!playerName.value.trim() || !selectedLobby.value) return;
  emit("join", selectedLobby.value.id, playerName.value.trim());
}

function handleEscape(e: KeyboardEvent) {
  if (e.key === "Escape") {
    if (selectedLobby.value) {
      selectedLobby.value = null;
    } else {
      emit("close");
    }
  }
}

onMounted(() => {
  document.addEventListener("keydown", handleEscape);
  nextTick(() => dialogPanel.value?.focus());
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
        ref="dialogPanel"
        role="dialog"
        aria-modal="true"
        tabindex="-1"
        class="relative w-full max-w-md rounded-2xl bg-white dark:bg-neutral-800 shadow-2xl border border-neutral-200 dark:border-neutral-700 p-6 outline-none"
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

        <template v-if="!selectedLobby">
          <h2 class="text-xl font-bold text-neutral-900 dark:text-neutral-100 mb-1">Join a Game</h2>
          <p class="text-sm text-neutral-500 dark:text-neutral-400 mb-6">Pick a lobby to join.</p>

          <div v-if="lobbies.length === 0" class="text-center py-8">
            <p class="text-neutral-400 text-sm">No open games available.</p>
          </div>

          <div v-else class="space-y-2 max-h-64 overflow-y-auto">
            <button
              v-for="lobby in lobbies"
              :key="lobby.id"
              class="w-full text-left rounded-xl border border-neutral-200 dark:border-neutral-600 p-3 hover:bg-amber-50 dark:hover:bg-neutral-700 hover:-translate-y-0.5 transition-all duration-200"
              :disabled="lobby.players.length >= lobby.maxPlayers"
              :class="{ 'opacity-50 cursor-not-allowed': lobby.players.length >= lobby.maxPlayers }"
              @click="openJoin(lobby)"
            >
              <div class="flex items-center justify-between">
                <span class="font-medium text-neutral-900 dark:text-neutral-100">{{ lobby.name }}</span>
                <span class="text-xs text-neutral-400">{{ lobby.players.length }}/{{ lobby.maxPlayers }}</span>
              </div>
              <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">Host: {{ lobby.hostPlayer.name }}</p>
            </button>
          </div>
        </template>

        <template v-else>
          <h2 class="text-xl font-bold text-neutral-900 dark:text-neutral-100 mb-1">
            Join "{{ selectedLobby.name }}"
          </h2>
          <p class="text-sm text-neutral-500 dark:text-neutral-400 mb-6">Enter your name to join the game.</p>

          <form @submit.prevent="handleSubmit" class="space-y-4">
            <div>
              <label for="player-name" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1.5">
                Your Name
              </label>
              <input
                id="player-name"
                ref="playerNameInput"
                v-model="playerName"
                type="text"
                placeholder="e.g. Max"
                maxlength="30"
                class="w-full rounded-xl border border-neutral-300 dark:border-neutral-600 bg-neutral-50 dark:bg-neutral-700 px-4 py-2.5 text-sm text-neutral-900 dark:text-neutral-100 placeholder-neutral-400 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 outline-none transition-all"
                :class="{ 'border-red-400 dark:border-red-500': submitted && !playerName.trim() }"
              />
              <p v-if="submitted && !playerName.trim()" class="mt-1 text-xs text-red-500">
                Please enter your name.
              </p>
            </div>

            <div class="flex gap-3 pt-2">
              <button
                type="button"
                class="flex-1 rounded-xl border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-700 text-neutral-700 dark:text-neutral-300 font-medium py-2.5 text-sm hover:bg-neutral-50 dark:hover:bg-neutral-600 hover:-translate-y-0.5 transition-all duration-200"
                @click="selectedLobby = null"
              >
                Back
              </button>
              <button
                type="submit"
                :disabled="loading"
                class="flex-1 rounded-xl bg-amber-500 hover:bg-amber-600 active:bg-amber-700 text-white font-semibold py-2.5 text-sm hover:-translate-y-0.5 hover:shadow-lg transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:translate-y-0 disabled:hover:shadow-none"
              >
                <span v-if="loading" class="inline-flex items-center gap-1.5">
                  <span class="inline-block w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                  Joining…
                </span>
                <span v-else>Join</span>
              </button>
            </div>
          </form>
        </template>
      </div>
    </div>
  </Teleport>
</template>
