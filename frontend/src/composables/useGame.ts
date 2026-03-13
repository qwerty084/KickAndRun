import { ref } from "vue";
import type {
  GameSession,
  RollResponse,
  MoveResponse,
  StartGameResponse,
} from "@/types/Game";

const API_BASE = import.meta.env.VITE_API_BASE_URL ?? "/api";

export function useGame() {
  const loading = ref(false);
  const error = ref<string | null>(null);

  async function getGame(gameId: string): Promise<GameSession | null> {
    loading.value = true;
    error.value = null;
    try {
      const res = await fetch(`${API_BASE}/games/${gameId}`);
      if (!res.ok) throw new Error(`Failed to fetch game: ${res.statusText}`);
      return await res.json();
    } catch (e) {
      error.value = e instanceof Error ? e.message : "Unknown error";
      return null;
    } finally {
      loading.value = false;
    }
  }

  async function rollDice(gameId: string, playerId: string): Promise<RollResponse | null> {
    loading.value = true;
    error.value = null;
    try {
      const res = await fetch(`${API_BASE}/games/${gameId}/roll`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ playerId }),
      });
      if (!res.ok) {
        const data = await res.json().catch(() => ({}));
        throw new Error(data.error || `Roll failed: ${res.statusText}`);
      }
      return await res.json();
    } catch (e) {
      error.value = e instanceof Error ? e.message : "Unknown error";
      return null;
    } finally {
      loading.value = false;
    }
  }

  async function movePiece(
    gameId: string,
    playerId: string,
    pieceIndex: number,
  ): Promise<MoveResponse | null> {
    loading.value = true;
    error.value = null;
    try {
      const res = await fetch(`${API_BASE}/games/${gameId}/move`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ playerId, pieceIndex }),
      });
      if (!res.ok) {
        const data = await res.json().catch(() => ({}));
        throw new Error(data.error || `Move failed: ${res.statusText}`);
      }
      return await res.json();
    } catch (e) {
      error.value = e instanceof Error ? e.message : "Unknown error";
      return null;
    } finally {
      loading.value = false;
    }
  }

  async function startGame(lobbyId: string): Promise<StartGameResponse | null> {
    loading.value = true;
    error.value = null;
    try {
      const res = await fetch(`${API_BASE}/lobbies/${lobbyId}/start`, {
        method: "POST",
      });
      if (!res.ok) {
        const data = await res.json().catch(() => ({}));
        throw new Error(data.error || `Start failed: ${res.statusText}`);
      }
      return await res.json();
    } catch (e) {
      error.value = e instanceof Error ? e.message : "Unknown error";
      return null;
    } finally {
      loading.value = false;
    }
  }

  return { loading, error, getGame, rollDice, movePiece, startGame };
}
