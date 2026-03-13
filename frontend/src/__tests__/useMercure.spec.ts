import { describe, it, expect, vi, beforeEach } from "vitest";
import { useMercure } from "@/composables/useMercure";

// Mock EventSource
class MockEventSource {
  static CONNECTING = 0;
  static OPEN = 1;
  static CLOSED = 2;

  readyState = MockEventSource.CONNECTING;
  onopen: (() => void) | null = null;
  onmessage: ((event: { data: string }) => void) | null = null;
  onerror: (() => void) | null = null;
  close = vi.fn(() => {
    this.readyState = MockEventSource.CLOSED;
  });

  constructor(public url: string) {
    MockEventSource.instances.push(this);
  }

  // Simulate connection open
  simulateOpen() {
    this.readyState = MockEventSource.OPEN;
    this.onopen?.();
  }

  // Simulate incoming message
  simulateMessage(data: unknown) {
    this.onmessage?.({ data: JSON.stringify(data) });
  }

  // Simulate error (still reconnecting)
  simulateError(closed = false) {
    if (closed) {
      this.readyState = MockEventSource.CLOSED;
    }
    this.onerror?.();
  }

  static instances: MockEventSource[] = [];
  static reset() {
    MockEventSource.instances = [];
  }
}

beforeEach(() => {
  MockEventSource.reset();
  vi.stubGlobal("EventSource", MockEventSource);
});

describe("useMercure", () => {
  it("starts with idle status", () => {
    const { status } = useMercure();
    expect(status.value).toBe("idle");
  });

  it("transitions to connecting on subscribe", () => {
    const { status, subscribe } = useMercure();
    subscribe("test/topic", vi.fn());
    expect(status.value).toBe("connecting");
  });

  it("creates EventSource with correct URL", () => {
    const { subscribe } = useMercure();
    subscribe("game/123", vi.fn());
    expect(MockEventSource.instances).toHaveLength(1);
    const url = new URL(MockEventSource.instances[0].url);
    expect(url.pathname).toBe("/.well-known/mercure");
    expect(url.searchParams.get("topic")).toBe("game/123");
  });

  it("transitions to connected on open", () => {
    const { status, subscribe } = useMercure();
    subscribe("test/topic", vi.fn());
    MockEventSource.instances[0].simulateOpen();
    expect(status.value).toBe("connected");
  });

  it("calls onMessage with parsed payload", () => {
    const handler = vi.fn();
    const { subscribe } = useMercure();
    subscribe("test/topic", handler);
    MockEventSource.instances[0].simulateOpen();
    MockEventSource.instances[0].simulateMessage({ event: "test", data: { foo: 1 } });
    expect(handler).toHaveBeenCalledWith({ event: "test", data: { foo: 1 } });
  });

  it("ignores malformed messages", () => {
    const handler = vi.fn();
    const { subscribe } = useMercure();
    subscribe("test/topic", handler);
    MockEventSource.instances[0].simulateOpen();
    // Send raw non-JSON
    MockEventSource.instances[0].onmessage?.({ data: "not json" });
    expect(handler).not.toHaveBeenCalled();
  });

  it("transitions to reconnecting on first error", () => {
    const { status, subscribe } = useMercure();
    subscribe("test/topic", vi.fn());
    MockEventSource.instances[0].simulateOpen();
    MockEventSource.instances[0].simulateError();
    expect(status.value).toBe("reconnecting");
  });

  it("transitions to disconnected after multiple errors", () => {
    const { status, subscribe } = useMercure();
    subscribe("test/topic", vi.fn());
    MockEventSource.instances[0].simulateOpen();
    MockEventSource.instances[0].simulateError();
    MockEventSource.instances[0].simulateError();
    MockEventSource.instances[0].simulateError();
    expect(status.value).toBe("disconnected");
  });

  it("resets error count on successful reconnect", () => {
    const { status, subscribe } = useMercure();
    subscribe("test/topic", vi.fn());
    MockEventSource.instances[0].simulateOpen();
    // Two errors (not enough for disconnected)
    MockEventSource.instances[0].simulateError();
    MockEventSource.instances[0].simulateError();
    expect(status.value).toBe("reconnecting");
    // Reconnect succeeds
    MockEventSource.instances[0].simulateOpen();
    expect(status.value).toBe("connected");
    // One more error — should still be reconnecting (counter reset)
    MockEventSource.instances[0].simulateError();
    expect(status.value).toBe("reconnecting");
  });

  it("transitions to disconnected when EventSource is CLOSED", () => {
    const { status, subscribe } = useMercure();
    subscribe("test/topic", vi.fn());
    MockEventSource.instances[0].simulateOpen();
    MockEventSource.instances[0].simulateError(true);
    expect(status.value).toBe("disconnected");
  });

  it("closes EventSource and resets to idle on unsubscribe", () => {
    const { status, subscribe, unsubscribe } = useMercure();
    subscribe("test/topic", vi.fn());
    MockEventSource.instances[0].simulateOpen();
    expect(status.value).toBe("connected");
    unsubscribe();
    expect(status.value).toBe("idle");
    expect(MockEventSource.instances[0].close).toHaveBeenCalled();
  });

  it("closes previous EventSource on re-subscribe", () => {
    const { subscribe } = useMercure();
    subscribe("topic/1", vi.fn());
    const first = MockEventSource.instances[0];
    subscribe("topic/2", vi.fn());
    expect(first.close).toHaveBeenCalled();
    expect(MockEventSource.instances).toHaveLength(2);
  });
});
