import { describe, it, expect, beforeEach, vi } from "vitest";
import { setActivePinia, createPinia } from "pinia";
import { useGameStore } from "@/stores/game";
import type { GameState, PlayerColor } from "@/types/Game";

function makeGameState(overrides: Partial<GameState> = {}): GameState {
  return {
    players: ["green", "yellow"] as PlayerColor[],
    currentPlayerIndex: 0,
    pieces: {
      green: [
        { position: "base" },
        { position: "base" },
        { position: "base" },
        { position: "base" },
      ],
      yellow: [
        { position: "base" },
        { position: "base" },
        { position: "base" },
        { position: "base" },
      ],
      red: [],
      black: [],
    },
    lastDiceRoll: null,
    phase: "rolling",
    consecutiveSixes: 0,
    turnNumber: 1,
    winner: null,
    rollAttemptsLeft: 3,
    ...overrides,
  };
}

describe("useGameStore", () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    vi.restoreAllMocks();
  });

  describe("initial state", () => {
    it("starts with null values", () => {
      const store = useGameStore();
      expect(store.gameId).toBeNull();
      expect(store.gameState).toBeNull();
      expect(store.validMoves).toEqual([]);
      expect(store.selectedPieceIndex).toBeNull();
      expect(store.myPlayerId).toBeNull();
      expect(store.myPlayerIndex).toBeNull();
      expect(store.lastError).toBeNull();
      expect(store.isLoading).toBe(false);
    });
  });

  describe("computed properties", () => {
    it("currentPlayer returns current player color", () => {
      const store = useGameStore();
      store.gameState = makeGameState({ currentPlayerIndex: 0 });
      expect(store.currentPlayer).toBe("green");

      store.gameState = makeGameState({ currentPlayerIndex: 1 });
      expect(store.currentPlayer).toBe("yellow");
    });

    it("currentPlayer returns null when no game state", () => {
      const store = useGameStore();
      expect(store.currentPlayer).toBeNull();
    });

    it("isMyTurn returns true when it is my turn", () => {
      const store = useGameStore();
      store.gameState = makeGameState({ currentPlayerIndex: 0 });
      store.myPlayerIndex = 0;
      expect(store.isMyTurn).toBe(true);
    });

    it("isMyTurn returns false when not my turn", () => {
      const store = useGameStore();
      store.gameState = makeGameState({ currentPlayerIndex: 1 });
      store.myPlayerIndex = 0;
      expect(store.isMyTurn).toBe(false);
    });

    it("isMyTurn returns false when myPlayerIndex is null", () => {
      const store = useGameStore();
      store.gameState = makeGameState({ currentPlayerIndex: 0 });
      expect(store.isMyTurn).toBe(false);
    });

    it("myColor returns the correct color for my index", () => {
      const store = useGameStore();
      store.gameState = makeGameState();
      store.myPlayerIndex = 1;
      expect(store.myColor).toBe("yellow");
    });

    it("myColor returns null when no game or player index", () => {
      const store = useGameStore();
      expect(store.myColor).toBeNull();
    });

    it("phase returns current phase", () => {
      const store = useGameStore();
      store.gameState = makeGameState({ phase: "moving" });
      expect(store.phase).toBe("moving");
    });

    it("phase returns null when no game state", () => {
      const store = useGameStore();
      expect(store.phase).toBeNull();
    });

    it("lastDiceRoll returns the last roll value", () => {
      const store = useGameStore();
      store.gameState = makeGameState({ lastDiceRoll: 6 });
      expect(store.lastDiceRoll).toBe(6);
    });

    it("winner returns null when no winner", () => {
      const store = useGameStore();
      store.gameState = makeGameState();
      expect(store.winner).toBeNull();
    });

    it("winner returns winner color when game is finished", () => {
      const store = useGameStore();
      store.gameState = makeGameState({ winner: "green", phase: "finished" });
      expect(store.winner).toBe("green");
    });

    it("isFinished returns true when phase is finished", () => {
      const store = useGameStore();
      store.gameState = makeGameState({ phase: "finished" });
      expect(store.isFinished).toBe(true);
    });

    it("isFinished returns false when phase is not finished", () => {
      const store = useGameStore();
      store.gameState = makeGameState({ phase: "rolling" });
      expect(store.isFinished).toBe(false);
    });

    it("validMoveTargets is a Set of move targets", () => {
      const store = useGameStore();
      store.validMoves = [
        { pieceIndex: 0, from: "base", to: "path:0" },
        { pieceIndex: 1, from: "path:5", to: "path:11" },
      ];
      expect(store.validMoveTargets).toEqual(new Set(["path:0", "path:11"]));
    });

    it("validMovesByPiece maps piece index to move", () => {
      const store = useGameStore();
      store.validMoves = [
        { pieceIndex: 0, from: "base", to: "path:0" },
        { pieceIndex: 2, from: "path:5", to: "path:11" },
      ];
      expect(store.validMovesByPiece.has(0)).toBe(true);
      expect(store.validMovesByPiece.has(2)).toBe(true);
      expect(store.validMovesByPiece.has(1)).toBe(false);
    });
  });

  describe("selectPiece", () => {
    it("selects a piece that has valid moves", () => {
      const store = useGameStore();
      store.validMoves = [{ pieceIndex: 2, from: "path:5", to: "path:11" }];
      store.selectPiece(2);
      expect(store.selectedPieceIndex).toBe(2);
    });

    it("does not select a piece without valid moves", () => {
      const store = useGameStore();
      store.validMoves = [{ pieceIndex: 0, from: "base", to: "path:0" }];
      store.selectPiece(2);
      expect(store.selectedPieceIndex).toBeNull();
    });
  });

  describe("setMyPlayer", () => {
    it("sets player ID and index", () => {
      const store = useGameStore();
      store.setMyPlayer("player-123", 1);
      expect(store.myPlayerId).toBe("player-123");
      expect(store.myPlayerIndex).toBe(1);
    });
  });

  describe("$reset", () => {
    it("resets all state to defaults", () => {
      const store = useGameStore();
      store.gameState = makeGameState();
      store.myPlayerId = "p1";
      store.myPlayerIndex = 0;
      store.validMoves = [{ pieceIndex: 0, from: "base", to: "path:0" }];
      store.selectedPieceIndex = 0;
      store.lastError = "some error";

      store.$reset();

      expect(store.gameId).toBeNull();
      expect(store.gameState).toBeNull();
      expect(store.validMoves).toEqual([]);
      expect(store.selectedPieceIndex).toBeNull();
      expect(store.myPlayerId).toBeNull();
      expect(store.myPlayerIndex).toBeNull();
      expect(store.lastError).toBeNull();
      expect(store.isLoading).toBe(false);
    });
  });

  describe("loadGame", () => {
    it("fetches game state on success", async () => {
      const mockState = makeGameState();
      vi.stubGlobal(
        "fetch",
        vi.fn().mockResolvedValue({
          ok: true,
          json: () => Promise.resolve({ gameState: mockState }),
        }),
      );

      const store = useGameStore();
      await store.loadGame("game-123");

      expect(store.gameId).toBe("game-123");
      expect(store.gameState).toEqual(mockState);
      expect(store.isLoading).toBe(false);
      expect(store.lastError).toBeNull();
    });

    it("handles fetch failure gracefully", async () => {
      vi.stubGlobal(
        "fetch",
        vi.fn().mockResolvedValue({
          ok: false,
          status: 404,
          statusText: "Not Found",
          json: () => Promise.resolve({ error: "Not found" }),
        }),
      );

      const store = useGameStore();
      await store.loadGame("bad-id");

      // useGame returns null on failure; store sees no session
      expect(store.gameState).toBeNull();
      expect(store.isLoading).toBe(false);
    });
  });

  describe("roll", () => {
    it("updates game state and valid moves on successful roll", async () => {
      const newState = makeGameState({ lastDiceRoll: 6, phase: "moving" });
      vi.stubGlobal(
        "fetch",
        vi.fn().mockResolvedValue({
          ok: true,
          json: () =>
            Promise.resolve({
              diceRoll: 6,
              validMoves: [{ pieceIndex: 0, from: "base", to: "path:0" }],
              phase: "moving",
              gameState: newState,
            }),
        }),
      );

      const store = useGameStore();
      store.gameId = "game-123";
      store.myPlayerId = "player-1";

      await store.roll();

      expect(store.gameState).toEqual(newState);
      expect(store.validMoves).toHaveLength(1);
      expect(store.isLoading).toBe(false);
    });

    it("auto-selects when only one valid move", async () => {
      const newState = makeGameState({ lastDiceRoll: 6, phase: "moving" });
      vi.stubGlobal(
        "fetch",
        vi.fn().mockResolvedValue({
          ok: true,
          json: () =>
            Promise.resolve({
              diceRoll: 6,
              validMoves: [{ pieceIndex: 2, from: "path:5", to: "path:11" }],
              phase: "moving",
              gameState: newState,
            }),
        }),
      );

      const store = useGameStore();
      store.gameId = "game-123";
      store.myPlayerId = "player-1";

      await store.roll();

      expect(store.selectedPieceIndex).toBe(2);
    });

    it("does nothing when gameId or playerId is missing", async () => {
      const mockFetch = vi.fn();
      vi.stubGlobal("fetch", mockFetch);

      const store = useGameStore();
      await store.roll();

      expect(mockFetch).not.toHaveBeenCalled();
    });
  });

  describe("move", () => {
    it("updates game state and clears moves on successful move", async () => {
      const newState = makeGameState({ currentPlayerIndex: 1, phase: "rolling" });
      vi.stubGlobal(
        "fetch",
        vi.fn().mockResolvedValue({
          ok: true,
          json: () =>
            Promise.resolve({
              moved: { pieceIndex: 0, from: "base", to: "path:0" },
              kicked: null,
              extraTurn: false,
              winner: null,
              gameState: newState,
            }),
        }),
      );

      const store = useGameStore();
      store.gameId = "game-123";
      store.myPlayerId = "player-1";
      store.validMoves = [{ pieceIndex: 0, from: "base", to: "path:0" }];
      store.selectedPieceIndex = 0;

      await store.move(0);

      expect(store.gameState).toEqual(newState);
      expect(store.validMoves).toEqual([]);
      expect(store.selectedPieceIndex).toBeNull();
    });

    it("handles network error gracefully", async () => {
      vi.stubGlobal(
        "fetch",
        vi.fn().mockRejectedValue(new Error("Network error")),
      );

      const store = useGameStore();
      store.gameId = "game-123";
      store.myPlayerId = "player-1";

      await store.move(0);

      // Error is caught within useGame composable; store sees null result
      expect(store.isLoading).toBe(false);
    });
  });
});
