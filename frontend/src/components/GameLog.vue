<script setup lang="ts">
import { ref, watch, nextTick } from "vue";
import type { GameEvent } from "@/stores/game";

interface Props {
  events: GameEvent[];
  botThinking?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  botThinking: false,
});

const logContainer = ref<HTMLElement | null>(null);

watch(
  () => props.events.length,
  async () => {
    await nextTick();
    if (logContainer.value) {
      logContainer.value.scrollTop = logContainer.value.scrollHeight;
    }
  },
);

const colorClasses: Record<string, string> = {
  green: "text-green-700 dark:text-green-400",
  yellow: "text-amber-600 dark:text-amber-400",
  red: "text-red-600 dark:text-red-400",
  black: "text-neutral-800 dark:text-neutral-300",
};

function formatEvent(event: GameEvent): string {
  const bot = event.isBot ? "🤖 " : "";
  const name = `${bot}${event.playerName}`;

  if (event.type === "dice_rolled") {
    return `${name} rolled a ${event.diceRoll ?? "?"}`;
  }

  if (event.type === "piece_moved") {
    let msg = `${name} moved piece`;
    if (event.kicked) {
      msg += " and kicked an opponent! 💥";
    }
    if (event.extraTurn) {
      msg += " — extra turn!";
    }
    if (event.winner) {
      msg += ` 🏆 ${event.playerName} wins!`;
    }
    return msg;
  }

  return `${name} — ${event.type}`;
}
</script>

<template>
  <div class="flex flex-col h-full">
    <h3 class="text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wide mb-2">
      Game Log
    </h3>
    <div ref="logContainer" class="flex-1 overflow-y-auto space-y-1 min-h-0 max-h-48 pr-1 scrollbar-thin">
      <p v-if="events.length === 0" class="text-xs text-neutral-400 dark:text-neutral-500 italic">
        No actions yet...
      </p>
      <div
        v-for="(event, index) in events"
        :key="index"
        class="text-xs leading-relaxed py-0.5 px-1.5 rounded transition-colors"
        :class="[colorClasses[event.playerColor] ?? 'text-neutral-600', event.isBot ? 'bg-neutral-50 dark:bg-neutral-800/50' : '']"
      >
        {{ formatEvent(event) }}
      </div>
      <div
        v-if="botThinking"
        class="text-xs text-neutral-500 dark:text-neutral-400 flex items-center gap-1.5 py-0.5 px-1.5 animate-pulse"
      >
        <span class="inline-block w-3 h-3 border-2 border-amber-500 border-t-transparent rounded-full animate-spin"></span>
        🤖 Bot is thinking...
      </div>
    </div>
  </div>
</template>
