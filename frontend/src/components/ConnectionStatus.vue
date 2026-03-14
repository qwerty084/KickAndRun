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
  <div class="inline-flex items-center gap-1.5 text-xs font-medium" role="status" aria-live="polite">
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

    <!-- Disconnected: red persistent -->
    <template v-else-if="status === 'disconnected'">
      <span class="w-2 h-2 rounded-full bg-red-500" aria-hidden="true"></span>
      <span class="text-red-600 dark:text-red-400">Disconnected</span>
    </template>

    <!-- Idle: nothing shown -->
  </div>
</template>
