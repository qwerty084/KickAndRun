import { ref } from "vue";

export interface Lobby {
  id: string;
  name: string;
  code: string;
  maxPlayers: number;
  status: string;
  hostPlayer: { id: string; name: string };
  players: { id: string; name: string }[];
  gameSessionId?: string;
}

const API_BASE = import.meta.env.VITE_API_BASE_URL ?? "/api";

export function useLobby() {
  const lobbies = ref<Lobby[]>([]);
  const loading = ref(false);
  const error = ref<string | null>(null);

  async function fetchLobbies() {
    loading.value = true;
    error.value = null;
    try {
      const res = await fetch(`${API_BASE}/lobbies`);
      if (!res.ok) throw new Error(`Failed to fetch lobbies: ${res.statusText}`);
      lobbies.value = await res.json();
    } catch (e) {
      error.value = e instanceof Error ? e.message : "Unknown error";
    } finally {
      loading.value = false;
    }
  }

  async function createLobby(name: string, hostName: string): Promise<Lobby | null> {
    loading.value = true;
    error.value = null;
    try {
      const res = await fetch(`${API_BASE}/lobbies`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ name, hostName }),
      });
      if (!res.ok) throw new Error(`Failed to create lobby: ${res.statusText}`);
      const lobby: Lobby = await res.json();
      await fetchLobbies();
      return lobby;
    } catch (e) {
      error.value = e instanceof Error ? e.message : "Unknown error";
      return null;
    } finally {
      loading.value = false;
    }
  }

  async function joinLobby(lobbyId: string, playerName: string): Promise<Lobby | null> {
    loading.value = true;
    error.value = null;
    try {
      const res = await fetch(`${API_BASE}/lobbies/${lobbyId}/join`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ playerName }),
      });
      if (!res.ok) throw new Error(`Failed to join lobby: ${res.statusText}`);
      return await res.json();
    } catch (e) {
      error.value = e instanceof Error ? e.message : "Unknown error";
      return null;
    } finally {
      loading.value = false;
    }
  }

  return { lobbies, loading, error, fetchLobbies, createLobby, joinLobby };
}
