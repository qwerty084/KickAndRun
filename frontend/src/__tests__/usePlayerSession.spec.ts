import { describe, it, expect, beforeEach, vi } from "vitest";
import { usePlayerSession } from "@/composables/usePlayerSession";

// Mock localStorage
const store: Record<string, string> = {};
const localStorageMock = {
  getItem: vi.fn((key: string) => store[key] ?? null),
  setItem: vi.fn((key: string, value: string) => {
    store[key] = value;
  }),
  removeItem: vi.fn((key: string) => {
    delete store[key];
  }),
};

beforeEach(() => {
  Object.keys(store).forEach((key) => delete store[key]);
  vi.stubGlobal("localStorage", localStorageMock);
  localStorageMock.getItem.mockClear();
  localStorageMock.setItem.mockClear();
  localStorageMock.removeItem.mockClear();
});

describe("usePlayerSession", () => {
  it("returns null when no session stored", () => {
    const { loadSession } = usePlayerSession();
    expect(loadSession()).toBeNull();
  });

  it("saves and loads a session", () => {
    const { saveSession, loadSession } = usePlayerSession();
    saveSession({ playerId: "p1", playerName: "Alice", lobbyId: "lobby-1" });
    const session = loadSession();
    expect(session).not.toBeNull();
    expect(session!.playerId).toBe("p1");
    expect(session!.playerName).toBe("Alice");
    expect(session!.lobbyId).toBe("lobby-1");
    expect(session!.savedAt).toBeGreaterThan(0);
  });

  it("clears session", () => {
    const { saveSession, clearSession, loadSession } = usePlayerSession();
    saveSession({ playerId: "p1", playerName: "Alice" });
    clearSession();
    expect(loadSession()).toBeNull();
  });

  it("updates session partially", () => {
    const { saveSession, updateSession, loadSession } = usePlayerSession();
    saveSession({ playerId: "p1", playerName: "Alice", lobbyId: "lobby-1" });
    updateSession({ gameId: "game-1" });
    const session = loadSession();
    expect(session!.gameId).toBe("game-1");
    expect(session!.lobbyId).toBe("lobby-1");
    expect(session!.playerId).toBe("p1");
  });

  it("expires sessions older than 24 hours", () => {
    const { saveSession, loadSession } = usePlayerSession();
    saveSession({ playerId: "p1", playerName: "Alice" });

    // Manually set savedAt to 25 hours ago
    const raw = JSON.parse(store["kickandrun_session"]);
    raw.savedAt = Date.now() - 25 * 60 * 60 * 1000;
    store["kickandrun_session"] = JSON.stringify(raw);

    expect(loadSession()).toBeNull();
    // Should also clear the stale entry
    expect(localStorageMock.removeItem).toHaveBeenCalledWith("kickandrun_session");
  });

  it("handles malformed JSON gracefully", () => {
    store["kickandrun_session"] = "not json";
    const { loadSession } = usePlayerSession();
    expect(loadSession()).toBeNull();
  });

  it("updateSession does nothing if no existing session", () => {
    const { updateSession, loadSession } = usePlayerSession();
    updateSession({ gameId: "game-1" });
    expect(loadSession()).toBeNull();
  });

  it("saves session with gameId", () => {
    const { saveSession, loadSession } = usePlayerSession();
    saveSession({ playerId: "p1", playerName: "Alice", gameId: "game-1" });
    const session = loadSession();
    expect(session!.gameId).toBe("game-1");
  });
});
