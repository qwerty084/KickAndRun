<script setup lang="ts">
import { onMounted, onUnmounted, ref, nextTick } from "vue";

interface Props {
  title: string;
  message: string;
  confirmText?: string;
  cancelText?: string;
  destructive?: boolean;
}

withDefaults(defineProps<Props>(), {
  confirmText: "Confirm",
  cancelText: "Cancel",
  destructive: false,
});

const emit = defineEmits<{ confirm: []; cancel: [] }>();
const cancelButton = ref<HTMLButtonElement | null>(null);

function handleEscape(e: KeyboardEvent) {
  if (e.key === "Escape") emit("cancel");
}

onMounted(() => {
  document.addEventListener("keydown", handleEscape);
  nextTick(() => cancelButton.value?.focus());
});

onUnmounted(() => {
  document.removeEventListener("keydown", handleEscape);
});
</script>

<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="$emit('cancel')">
      <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" aria-hidden="true"></div>

      <div
        role="dialog"
        aria-modal="true"
        class="relative w-full max-w-sm rounded-2xl bg-white dark:bg-neutral-800 shadow-2xl border border-neutral-200 dark:border-neutral-700 p-6"
      >
        <h2 class="text-lg font-bold text-neutral-900 dark:text-neutral-100 mb-2">{{ title }}</h2>
        <p class="text-sm text-neutral-600 dark:text-neutral-400 mb-6">{{ message }}</p>

        <div class="flex gap-3">
          <button
            ref="cancelButton"
            class="flex-1 rounded-xl border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-700 text-neutral-700 dark:text-neutral-300 font-medium py-2.5 text-sm hover:bg-neutral-50 dark:hover:bg-neutral-600 hover:-translate-y-0.5 transition-all duration-200"
            @click="$emit('cancel')"
          >
            {{ cancelText }}
          </button>
          <button
            class="flex-1 rounded-xl text-white font-semibold py-2.5 text-sm hover:-translate-y-0.5 hover:shadow-lg transition-all duration-200"
            :class="
              destructive
                ? 'bg-red-500 hover:bg-red-600 active:bg-red-700 shadow-lg shadow-red-500/25'
                : 'bg-amber-500 hover:bg-amber-600 active:bg-amber-700 shadow-lg shadow-amber-500/25'
            "
            @click="$emit('confirm')"
          >
            {{ confirmText }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
