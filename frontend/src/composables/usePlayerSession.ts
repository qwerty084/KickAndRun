const STORAGE_KEY = "kickandrun_session";
const EXPIRY_MS = 24 * 60 * 60 * 1000; // 24 hours

export interface PlayerSession {
  playerId: string;
  playerName: string;
  lobbyId?: string;
  gameId?: string;
  savedAt: number;
}

export function usePlayerSession() {
  function saveSession(data: Omit<PlayerSession, "savedAt">) {
    const session: PlayerSession = { ...data, savedAt: Date.now() };
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(session));
    } catch {
      // localStorage unavailable (SSR, private browsing quota)
    }
  }

  function loadSession(): PlayerSession | null {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      if (!raw) return null;
      const session: PlayerSession = JSON.parse(raw);
      if (Date.now() - session.savedAt > EXPIRY_MS) {
        clearSession();
        return null;
      }
      return session;
    } catch {
      return null;
    }
  }

  function clearSession() {
    try {
      localStorage.removeItem(STORAGE_KEY);
    } catch {
      // Ignore
    }
  }

  function updateSession(updates: Partial<Omit<PlayerSession, "savedAt">>) {
    const current = loadSession();
    if (current) {
      saveSession({ ...current, ...updates });
    }
  }

  return { saveSession, loadSession, clearSession, updateSession };
}
