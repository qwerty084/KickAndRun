import { describe, it, expect } from "vitest";
import type { GameState, PlayerColor } from "@/types/Game";
import { buildPieceMap, moveTargetToFieldId, fieldIdToPosition } from "@/composables/boardLayout";

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

describe("buildPieceMap", () => {
  it("maps pieces in base to base field IDs", () => {
    const state = makeGameState();
    const map = buildPieceMap(state);

    expect(map.get("base:green:0")).toEqual({ color: "green", pieceIndex: 0 });
    expect(map.get("base:green:1")).toEqual({ color: "green", pieceIndex: 1 });
    expect(map.get("base:green:2")).toEqual({ color: "green", pieceIndex: 2 });
    expect(map.get("base:green:3")).toEqual({ color: "green", pieceIndex: 3 });
    expect(map.get("base:yellow:0")).toEqual({ color: "yellow", pieceIndex: 0 });
  });

  it("maps pieces on path to path field IDs", () => {
    const state = makeGameState({
      pieces: {
        green: [
          { position: "path:5" },
          { position: "path:12" },
          { position: "base" },
          { position: "base" },
        ],
        yellow: [{ position: "path:20" }, { position: "base" }, { position: "base" }, { position: "base" }],
        red: [],
        black: [],
      },
    });

    const map = buildPieceMap(state);

    expect(map.get("path:5")).toEqual({ color: "green", pieceIndex: 0 });
    expect(map.get("path:12")).toEqual({ color: "green", pieceIndex: 1 });
    expect(map.get("path:20")).toEqual({ color: "yellow", pieceIndex: 0 });
    expect(map.get("base:green:0")).toEqual({ color: "green", pieceIndex: 2 });
  });

  it("maps pieces in goal to goal field IDs with color qualifier", () => {
    const state = makeGameState({
      pieces: {
        green: [
          { position: "goal:0" },
          { position: "goal:1" },
          { position: "goal:2" },
          { position: "goal:3" },
        ],
        yellow: [{ position: "base" }, { position: "base" }, { position: "base" }, { position: "base" }],
        red: [],
        black: [],
      },
    });

    const map = buildPieceMap(state);

    expect(map.get("goal:green:0")).toEqual({ color: "green", pieceIndex: 0 });
    expect(map.get("goal:green:1")).toEqual({ color: "green", pieceIndex: 1 });
    expect(map.get("goal:green:2")).toEqual({ color: "green", pieceIndex: 2 });
    expect(map.get("goal:green:3")).toEqual({ color: "green", pieceIndex: 3 });
  });

  it("skips finished pieces", () => {
    const state = makeGameState({
      pieces: {
        green: [
          { position: "finished" },
          { position: "path:10" },
          { position: "base" },
          { position: "base" },
        ],
        yellow: [{ position: "base" }, { position: "base" }, { position: "base" }, { position: "base" }],
        red: [],
        black: [],
      },
    });

    const map = buildPieceMap(state);

    // Finished pieces shouldn't be on any field
    expect(map.get("path:10")).toEqual({ color: "green", pieceIndex: 1 });
    expect(map.get("base:green:0")).toEqual({ color: "green", pieceIndex: 2 });
  });

  it("handles mixed positions for multiple players", () => {
    const state = makeGameState({
      players: ["green", "yellow", "red", "black"],
      pieces: {
        green: [{ position: "path:0" }, { position: "goal:3" }, { position: "base" }, { position: "base" }],
        yellow: [{ position: "path:15" }, { position: "base" }, { position: "base" }, { position: "base" }],
        red: [{ position: "path:25" }, { position: "goal:1" }, { position: "base" }, { position: "base" }],
        black: [{ position: "base" }, { position: "base" }, { position: "base" }, { position: "base" }],
      },
    });

    const map = buildPieceMap(state);

    expect(map.get("path:0")?.color).toBe("green");
    expect(map.get("goal:green:3")?.color).toBe("green");
    expect(map.get("path:15")?.color).toBe("yellow");
    expect(map.get("path:25")?.color).toBe("red");
    expect(map.get("goal:red:1")?.color).toBe("red");
    expect(map.get("base:black:0")?.color).toBe("black");
  });

  it("returns empty map for empty state", () => {
    const state = makeGameState({
      players: [],
      pieces: { green: [], yellow: [], red: [], black: [] },
    });

    const map = buildPieceMap(state);
    expect(map.size).toBe(0);
  });
});

describe("moveTargetToFieldId", () => {
  it("returns path positions unchanged", () => {
    expect(moveTargetToFieldId("path:5", "green")).toBe("path:5");
    expect(moveTargetToFieldId("path:39", "yellow")).toBe("path:39");
    expect(moveTargetToFieldId("path:0", "red")).toBe("path:0");
  });

  it("qualifies goal positions with player color", () => {
    expect(moveTargetToFieldId("goal:0", "green")).toBe("goal:green:0");
    expect(moveTargetToFieldId("goal:3", "yellow")).toBe("goal:yellow:3");
    expect(moveTargetToFieldId("goal:2", "red")).toBe("goal:red:2");
    expect(moveTargetToFieldId("goal:1", "black")).toBe("goal:black:1");
  });
});

describe("fieldIdToPosition", () => {
  it("returns path field IDs unchanged", () => {
    expect(fieldIdToPosition("path:5")).toBe("path:5");
    expect(fieldIdToPosition("path:39")).toBe("path:39");
  });

  it("strips color from goal field IDs", () => {
    expect(fieldIdToPosition("goal:green:0")).toBe("goal:0");
    expect(fieldIdToPosition("goal:yellow:3")).toBe("goal:3");
    expect(fieldIdToPosition("goal:red:2")).toBe("goal:2");
    expect(fieldIdToPosition("goal:black:1")).toBe("goal:1");
  });
});
