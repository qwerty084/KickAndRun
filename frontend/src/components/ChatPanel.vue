<script setup lang="ts">
import { ref, nextTick, watch, onMounted } from "vue";
import { useChat } from "@/composables/useChat";

interface Props {
  context: "lobby" | "game";
  contextId: string;
  myPlayerId: string;
  collapsed?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  collapsed: false,
});

const emit = defineEmits<{ "update:collapsed": [value: boolean] }>();

const { messages, fetchMessages, sendMessage, addMessage } = useChat();
const inputText = ref("");
const sending = ref(false);
const messagesContainer = ref<HTMLElement | null>(null);
const isOpen = ref(!props.collapsed);

onMounted(() => {
  fetchMessages(props.context, props.contextId);
});

watch(
  () => messages.value.length,
  async () => {
    await nextTick();
    scrollToBottom();
  },
);

function scrollToBottom() {
  if (messagesContainer.value) {
    messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
  }
}

async function handleSend() {
  const text = inputText.value.trim();
  if (!text || sending.value) return;

  sending.value = true;
  const success = await sendMessage(props.context, props.contextId, props.myPlayerId, text);
  if (success) {
    inputText.value = "";
  }
  sending.value = false;
}

function toggle() {
  isOpen.value = !isOpen.value;
  emit("update:collapsed", !isOpen.value);
}

function formatTime(dateStr: string): string {
  try {
    return new Date(dateStr).toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
  } catch {
    return "";
  }
}

defineExpose({ addMessage });
</script>

<template>
  <div class="flex flex-col bg-white dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700 overflow-hidden">
    <!-- Header -->
    <button
      class="flex items-center justify-between px-4 py-2.5 bg-neutral-50 dark:bg-neutral-750 hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors text-left"
      @click="toggle"
    >
      <span class="text-sm font-semibold text-neutral-700 dark:text-neutral-300">
        💬 Chat
        <span v-if="messages.length" class="text-xs font-normal text-neutral-400 ml-1">({{ messages.length }})</span>
      </span>
      <svg
        xmlns="http://www.w3.org/2000/svg"
        class="h-4 w-4 text-neutral-400 transition-transform"
        :class="{ 'rotate-180': isOpen }"
        viewBox="0 0 20 20"
        fill="currentColor"
      >
        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
      </svg>
    </button>

    <!-- Body -->
    <div v-show="isOpen" class="flex flex-col">
      <!-- Messages -->
      <div
        ref="messagesContainer"
        class="h-48 overflow-y-auto px-3 py-2 space-y-1.5 text-sm"
      >
        <div v-if="messages.length === 0" class="text-center text-neutral-400 dark:text-neutral-500 text-xs py-6">
          No messages yet. Say hi! 👋
        </div>
        <div
          v-for="msg in messages"
          :key="msg.id"
          class="flex gap-2"
          :class="msg.player.id === myPlayerId ? 'justify-end' : ''"
        >
          <div
            class="max-w-[80%] rounded-lg px-2.5 py-1.5"
            :class="
              msg.player.id === myPlayerId
                ? 'bg-amber-100 dark:bg-amber-900/30 text-amber-900 dark:text-amber-200'
                : 'bg-neutral-100 dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200'
            "
          >
            <p v-if="msg.player.id !== myPlayerId" class="text-xs font-semibold text-neutral-500 dark:text-neutral-400 mb-0.5">
              {{ msg.player.name }}
            </p>
            <p class="break-words">{{ msg.content }}</p>
            <p class="text-[10px] text-neutral-400 dark:text-neutral-500 text-right mt-0.5">
              {{ formatTime(msg.createdAt) }}
            </p>
          </div>
        </div>
      </div>

      <!-- Input -->
      <form
        class="flex gap-2 px-3 py-2 border-t border-neutral-200 dark:border-neutral-700"
        @submit.prevent="handleSend"
      >
        <input
          v-model="inputText"
          type="text"
          maxlength="500"
          placeholder="Type a message..."
          class="flex-1 rounded-lg border border-neutral-300 dark:border-neutral-600 bg-neutral-50 dark:bg-neutral-700 px-3 py-1.5 text-sm text-neutral-900 dark:text-neutral-100 placeholder-neutral-400 focus:border-amber-500 focus:ring-1 focus:ring-amber-500/30 outline-none"
        />
        <button
          type="submit"
          :disabled="!inputText.trim() || sending"
          class="rounded-lg bg-amber-500 hover:bg-amber-600 text-white px-3 py-1.5 text-sm font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
        >
          Send
        </button>
      </form>
    </div>
  </div>
</template>
