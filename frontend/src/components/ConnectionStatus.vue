<script setup lang="ts">
import type { ConnectionStatus } from "@/composables/useMercure";

interface Props {
  status: ConnectionStatus;
}

withDefaults(defineProps<Props>(), {
  status: "idle",
});
</script>

<template>
  <!-- Disconnected: prominent warning banner -->
  <div
    v-if="status === 'disconnected'"
    role="status"
    aria-live="polite"
    class="flex items-center justify-center gap-2 text-sm font-semibold py-2 px-4 rounded-lg bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-300 border border-red-300 dark:border-red-700"
  >
    <span class="w-2.5 h-2.5 rounded-full bg-red-500 animate-pulse" aria-hidden="true"></span>
    Disconnected — trying to reconnect…
  </div>

  <!-- Non-disconnected states: compact inline indicator -->
  <div v-else class="inline-flex items-center gap-1.5 text-xs font-medium" role="status" aria-live="polite">
    <!-- Connected: subtle green dot -->
    <template v-if="status === 'connected'">
      <span class="w-2 h-2 rounded-full bg-green-500" aria-hidden="true"></span>
      <span class="text-green-600 dark:text-green-400">Connected</span>
    </template>

    <!-- Connecting / Reconnecting: amber pulse -->
    <template v-else-if="status === 'connecting' || status === 'reconnecting'">
      <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse" aria-hidden="true"></span>
      <span class="text-amber-600 dark:text-amber-400">
        {{ status === "reconnecting" ? "Reconnecting…" : "Connecting…" }}
      </span>
    </template>

    <!-- Idle: nothing shown -->
  </div>
</template>
