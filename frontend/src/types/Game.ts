export type PlayerColor = "green" | "yellow" | "red" | "black";

export interface PieceState {
  position: string; // "base", "path:N", "goal:N", "finished"
}

export interface GameState {
  players: PlayerColor[];
  currentPlayerIndex: number;
  pieces: Record<PlayerColor, PieceState[]>;
  lastDiceRoll: number | null;
  phase: "rolling" | "moving" | "finished";
  consecutiveSixes: number;
  turnNumber: number;
  winner: PlayerColor | null;
  rollAttemptsLeft: number;
}

export interface ValidMove {
  pieceIndex: number;
  from: string;
  to: string;
}

export interface RollResponse {
  diceRoll: number | null;
  validMoves: ValidMove[];
  phase: string;
  gameState: GameState;
}

export interface MoveResponse {
  moved: {
    pieceIndex: number;
    from: string;
    to: string;
  };
  kicked: {
    player: string;
    pieceIndex: number;
    from: string;
    to: string;
  } | null;
  extraTurn: boolean;
  winner: PlayerColor | null;
  gameState: GameState;
}

export interface GameSession {
  id: string;
  lobbyId: string;
  lobbyName?: string;
  status: string;
  currentTurn: number;
  gameState: GameState;
  createdAt: string;
  updatedAt: string;
}

export interface StartGameResponse {
  gameSessionId: string;
  lobby: Record<string, unknown>;
  gameState: GameState;
}
