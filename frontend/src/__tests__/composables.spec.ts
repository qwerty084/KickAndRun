import { describe, it, expect, vi, beforeEach } from "vitest";

// We test the API functions by importing the composables and mocking fetch
const API_BASE = "/api";

describe("useGame", () => {
  beforeEach(() => {
    vi.restoreAllMocks();
  });

  describe("getGame", () => {
    it("fetches game session and returns data on success", async () => {
      const mockResponse = { gameState: { players: ["green"] } };
      vi.stubGlobal(
        "fetch",
        vi.fn().mockResolvedValue({
          ok: true,
          json: () => Promise.resolve(mockResponse),
        }),
      );

      const { useGame } = await import("@/composables/useGame");
      const { getGame } = useGame();
      const result = await getGame("game-123");

      expect(fetch).toHaveBeenCalledWith(`${API_BASE}/games/game-123`, expect.anything());
      expect(result).toEqual(mockResponse);
    });

    it("returns null on fetch failure", async () => {
      vi.stubGlobal(
        "fetch",
        vi.fn().mockResolvedValue({
          ok: false,
          status: 404,
          json: () => Promise.resolve({ error: "Not found" }),
        }),
      );

      const { useGame } = await import("@/composables/useGame");
      const { getGame } = useGame();
      const result = await getGame("bad-id");

      expect(result).toBeNull();
    });
  });

  describe("rollDice", () => {
    it("sends POST with playerId and returns roll result", async () => {
      const mockResponse = {
        diceRoll: 6,
        validMoves: [{ pieceIndex: 0, from: "base", to: "path:0" }],
        phase: "moving",
        gameState: {},
      };
      vi.stubGlobal(
        "fetch",
        vi.fn().mockResolvedValue({
          ok: true,
          json: () => Promise.resolve(mockResponse),
        }),
      );

      const { useGame } = await import("@/composables/useGame");
      const { rollDice } = useGame();
      const result = await rollDice("game-123", "player-1");

      expect(fetch).toHaveBeenCalledWith(
        `${API_BASE}/games/game-123/roll`,
        expect.objectContaining({
          method: "POST",
          body: JSON.stringify({ playerId: "player-1" }),
        }),
      );
      expect(result).toEqual(mockResponse);
    });

    it("returns null on failure", async () => {
      vi.stubGlobal(
        "fetch",
        vi.fn().mockResolvedValue({
          ok: false,
          json: () => Promise.resolve({ error: "Not your turn" }),
        }),
      );

      const { useGame } = await import("@/composables/useGame");
      const { rollDice } = useGame();
      const result = await rollDice("game-123", "player-1");

      expect(result).toBeNull();
    });
  });

  describe("movePiece", () => {
    it("sends POST with playerId and pieceIndex", async () => {
      const mockResponse = {
        moved: { pieceIndex: 0, from: "base", to: "path:0" },
        kicked: null,
        extraTurn: false,
        winner: null,
        gameState: {},
      };
      vi.stubGlobal(
        "fetch",
        vi.fn().mockResolvedValue({
          ok: true,
          json: () => Promise.resolve(mockResponse),
        }),
      );

      const { useGame } = await import("@/composables/useGame");
      const { movePiece } = useGame();
      const result = await movePiece("game-123", "player-1", 2);

      expect(fetch).toHaveBeenCalledWith(
        `${API_BASE}/games/game-123/move`,
        expect.objectContaining({
          method: "POST",
          body: JSON.stringify({ playerId: "player-1", pieceIndex: 2 }),
        }),
      );
      expect(result).toEqual(mockResponse);
    });
  });

  describe("startGame", () => {
    it("sends POST to start game and returns result", async () => {
      const mockResponse = {
        gameSessionId: "session-456",
        lobby: {},
        gameState: {},
      };
      vi.stubGlobal(
        "fetch",
        vi.fn().mockResolvedValue({
          ok: true,
          json: () => Promise.resolve(mockResponse),
        }),
      );

      const { useGame } = await import("@/composables/useGame");
      const { startGame } = useGame();
      const result = await startGame("lobby-123");

      expect(fetch).toHaveBeenCalledWith(
        `${API_BASE}/lobbies/lobby-123/start`,
        expect.objectContaining({ method: "POST" }),
      );
      expect(result).toEqual(mockResponse);
    });
  });
});

describe("useLobby", () => {
  beforeEach(() => {
    vi.restoreAllMocks();
  });

  it("fetchLobbies populates lobbies array", async () => {
    const mockLobbies = [
      { id: "1", name: "Test", code: "ABC", maxPlayers: 4, status: "waiting", hostPlayer: { id: "h", name: "Host" }, players: [] },
    ];
    vi.stubGlobal(
      "fetch",
      vi.fn().mockResolvedValue({
        ok: true,
        json: () => Promise.resolve(mockLobbies),
      }),
    );

    const { useLobby } = await import("@/composables/useLobby");
    const { lobbies, fetchLobbies } = useLobby();
    await fetchLobbies();

    expect(lobbies.value).toEqual(mockLobbies);
  });

  it("createLobby sends POST with name and hostName", async () => {
    const mockLobby = { id: "new", name: "My Game", code: "XYZ", hostPlayer: { id: "h", name: "Alice" }, players: [] };
    vi.stubGlobal(
      "fetch",
      vi.fn().mockResolvedValue({
        ok: true,
        json: () => Promise.resolve(mockLobby),
      }),
    );

    const { useLobby } = await import("@/composables/useLobby");
    const { createLobby } = useLobby();
    const result = await createLobby("My Game", "Alice");

    expect(fetch).toHaveBeenCalledWith(
      `${API_BASE}/lobbies`,
      expect.objectContaining({
        method: "POST",
        body: JSON.stringify({ name: "My Game", hostName: "Alice" }),
      }),
    );
    expect(result).toEqual(mockLobby);
  });

  it("joinLobby sends POST with playerName", async () => {
    const mockLobby = { id: "lobby-1", players: [{ id: "p", name: "Bob" }] };
    vi.stubGlobal(
      "fetch",
      vi.fn().mockResolvedValue({
        ok: true,
        json: () => Promise.resolve(mockLobby),
      }),
    );

    const { useLobby } = await import("@/composables/useLobby");
    const { joinLobby } = useLobby();
    const result = await joinLobby("lobby-1", "Bob");

    expect(fetch).toHaveBeenCalledWith(
      `${API_BASE}/lobbies/lobby-1/join`,
      expect.objectContaining({
        method: "POST",
        body: JSON.stringify({ playerName: "Bob" }),
      }),
    );
    expect(result).toEqual(mockLobby);
  });

  it("sets error on fetch failure", async () => {
    vi.stubGlobal(
      "fetch",
      vi.fn().mockRejectedValue(new Error("Network error")),
    );

    const { useLobby } = await import("@/composables/useLobby");
    const { error, fetchLobbies } = useLobby();
    await fetchLobbies();

    expect(error.value).toBe("Network error");
  });
});
