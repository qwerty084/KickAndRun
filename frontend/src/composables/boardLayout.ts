import type { PlayerColor, GameState } from "@/types/Game";

/**
 * Maps backend piece positions to visual board field IDs.
 *
 * Field ID formats:
 *   "path:N"            — walking path (0-39)
 *   "goal:COLOR:N"      — goal zone (0-3 per player)
 *   "base:COLOR:N"      — base slots (0-3 per player)
 *
 * Backend position formats:
 *   "path:N"            — same as field ID
 *   "goal:N"            — per-player, needs color qualification
 *   "base"              — per-player, needs color + piece index
 *   "finished"          — piece is done (no field ID)
 */

export interface PieceOnBoard {
  color: PlayerColor;
  pieceIndex: number;
}

/**
 * Build a map of fieldId → piece for rendering pieces on the board.
 */
export function buildPieceMap(state: GameState): Map<string, PieceOnBoard> {
  const map = new Map<string, PieceOnBoard>();

  for (const color of state.players) {
    const pieces = state.pieces[color];
    if (!pieces) continue;

    // Track base slot assignment (multiple pieces can be in base)
    let baseSlot = 0;

    for (let i = 0; i < pieces.length; i++) {
      const pos = pieces[i].position;

      if (pos === "base") {
        map.set(`base:${color}:${baseSlot}`, { color, pieceIndex: i });
        baseSlot++;
      } else if (pos === "finished") {
        // Finished pieces stay on their goal position visually (goal:3)
        // Actually in the game, finished pieces are in goal — we'll show them at the last open goal slot
        // For simplicity, treat finished as goal:3
        // But multiple pieces can be finished — assign sequential goal slots
      } else if (pos.startsWith("goal:")) {
        const goalIndex = pos.split(":")[1];
        map.set(`goal:${color}:${goalIndex}`, { color, pieceIndex: i });
      } else if (pos.startsWith("path:")) {
        map.set(pos, { color, pieceIndex: i });
      }
    }
  }

  return map;
}

/**
 * Given a set of valid move targets (backend position strings like "path:5"),
 * convert to field IDs for highlighting.
 */
export function moveTargetToFieldId(target: string, playerColor: PlayerColor): string {
  if (target.startsWith("goal:")) {
    const goalIndex = target.split(":")[1];
    return `goal:${playerColor}:${goalIndex}`;
  }
  return target; // "path:N" is the same
}

/**
 * Convert a field ID to backend position format for a given player color.
 * Used when clicking on a target to determine which move to execute.
 */
export function fieldIdToPosition(fieldId: string): string {
  if (fieldId.startsWith("goal:")) {
    // "goal:green:2" → "goal:2"
    const parts = fieldId.split(":");
    return `goal:${parts[2]}`;
  }
  return fieldId; // "path:N" stays as-is
}
