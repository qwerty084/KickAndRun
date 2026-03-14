import { ref } from "vue";
import { apiFetch } from "@/composables/apiFetch";

export interface ChatMessage {
  id: string;
  content: string;
  player: { id: string; name: string };
  createdAt: string;
}

export function useChat() {
  const messages = ref<ChatMessage[]>([]);
  const loading = ref(false);
  const error = ref<string | null>(null);

  async function fetchMessages(context: "lobby" | "game", contextId: string) {
    loading.value = true;
    error.value = null;
    try {
      const res = await apiFetch(`/chat/${context}/${contextId}/messages`);
      if (!res.ok) throw new Error("Failed to load messages");
      messages.value = await res.json();
    } catch (e) {
      error.value = e instanceof Error ? e.message : "Unknown error";
    } finally {
      loading.value = false;
    }
  }

  async function sendMessage(
    context: "lobby" | "game",
    contextId: string,
    playerId: string,
    content: string,
  ): Promise<boolean> {
    error.value = null;
    try {
      const res = await apiFetch(`/chat/${context}/${contextId}/messages`, {
        method: "POST",
        body: JSON.stringify({ playerId, content }),
      });
      if (!res.ok) {
        const data = await res.json().catch(() => ({}));
        error.value = data.error || "Failed to send message";
        return false;
      }
      return true;
    } catch (e) {
      error.value = e instanceof Error ? e.message : "Unknown error";
      return false;
    }
  }

  function addMessage(msg: ChatMessage) {
    // Avoid duplicates
    if (!messages.value.some((m) => m.id === msg.id)) {
      messages.value.push(msg);
    }
  }

  return { messages, loading, error, fetchMessages, sendMessage, addMessage };
}
