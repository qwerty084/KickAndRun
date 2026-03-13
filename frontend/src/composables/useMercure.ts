import { ref, type Ref } from "vue";

export type ConnectionStatus = "idle" | "connecting" | "connected" | "reconnecting" | "disconnected";

const MAX_RECONNECT_FAILURES = 3;

export interface UseMercureReturn {
  status: Ref<ConnectionStatus>;
  subscribe: (topic: string, onMessage: (payload: unknown) => void) => void;
  unsubscribe: () => void;
}

export function useMercure(): UseMercureReturn {
  const status = ref<ConnectionStatus>("idle");
  let eventSource: EventSource | null = null;
  let consecutiveErrors = 0;

  function subscribe(topic: string, onMessage: (payload: unknown) => void) {
    unsubscribe();
    consecutiveErrors = 0;
    status.value = "connecting";

    const mercureUrl = new URL("/.well-known/mercure", window.location.origin);
    mercureUrl.searchParams.set("topic", topic);

    eventSource = new EventSource(mercureUrl.toString());

    eventSource.onopen = () => {
      consecutiveErrors = 0;
      status.value = "connected";
    };

    eventSource.onmessage = (event) => {
      try {
        const payload = JSON.parse(event.data);
        onMessage(payload);
      } catch {
        // Ignore malformed events
      }
    };

    eventSource.onerror = () => {
      if (!eventSource) return;

      consecutiveErrors++;

      if (eventSource.readyState === EventSource.CLOSED) {
        status.value = "disconnected";
      } else if (consecutiveErrors >= MAX_RECONNECT_FAILURES) {
        status.value = "disconnected";
      } else {
        status.value = "reconnecting";
      }
    };
  }

  function unsubscribe() {
    if (eventSource) {
      eventSource.close();
      eventSource = null;
    }
    if (status.value !== "idle") {
      status.value = "idle";
    }
    consecutiveErrors = 0;
  }

  return { status, subscribe, unsubscribe };
}
