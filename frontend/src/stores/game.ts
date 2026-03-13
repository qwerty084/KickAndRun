import { ref, computed } from "vue";
import { defineStore } from "pinia";
import type { GameState, ValidMove, PlayerColor } from "@/types/Game";
import { useGame } from "@/composables/useGame";
import { useMercure, type ConnectionStatus } from "@/composables/useMercure";

export interface GameEvent {
  type: "dice_rolled" | "piece_moved" | "game_started" | "turn_passed";
  playerColor: PlayerColor;
  playerName: string;
  isBot: boolean;
  diceRoll?: number;
  moved?: { pieceIndex: number; from: string; to: string };
  kicked?: boolean;
  extraTurn?: boolean;
  winner?: PlayerColor | null;
  timestamp: number;
}

export const useGameStore = defineStore("game", () => {
  const gameId = ref<string | null>(null);
  const gameState = ref<GameState | null>(null);
  const validMoves = ref<ValidMove[]>([]);
  const selectedPieceIndex = ref<number | null>(null);
  const myPlayerId = ref<string | null>(null);
  const myPlayerIndex = ref<number | null>(null);
  const players = ref<{ id: string; name: string; isBot?: boolean }[]>([]);
  const lastError = ref<string | null>(null);
  const isLoading = ref(false);
  const eventLog = ref<GameEvent[]>([]);
  const botThinking = ref(false);
  const lastMovedPiece = ref<{ color: PlayerColor; position: string } | null>(null);

  const { getGame, rollDice: apiRoll, movePiece: apiMove } = useGame();
  const mercure = useMercure();
  const connectionStatus = computed<ConnectionStatus>(() => mercure.status.value);

  // Computed
  const currentPlayer = computed<PlayerColor | null>(() => {
    if (!gameState.value) return null;
    return gameState.value.players[gameState.value.currentPlayerIndex];
  });

  const isMyTurn = computed(() => {
    if (myPlayerIndex.value === null || !gameState.value) return false;
    return gameState.value.currentPlayerIndex === myPlayerIndex.value;
  });

  const myColor = computed<PlayerColor | null>(() => {
    if (myPlayerIndex.value === null || !gameState.value) return null;
    return gameState.value.players[myPlayerIndex.value];
  });

  const phase = computed(() => gameState.value?.phase ?? null);
  const lastDiceRoll = computed(() => gameState.value?.lastDiceRoll ?? null);
  const winner = computed(() => gameState.value?.winner ?? null);
  const isFinished = computed(() => gameState.value?.phase === "finished");

  const validMoveTargets = computed(() => {
    return new Set(validMoves.value.map((m) => m.to));
  });

  const validMovesByPiece = computed(() => {
    const map = new Map<number, ValidMove>();
    for (const move of validMoves.value) {
      map.set(move.pieceIndex, move);
    }
    return map;
  });

  // Actions
  async function loadGame(id: string) {
    gameId.value = id;
    isLoading.value = true;
    lastError.value = null;
    try {
      const session = await getGame(id);
      if (session) {
        gameState.value = session.gameState;
      }
    } catch (e) {
      lastError.value = e instanceof Error ? e.message : "Failed to load game";
    } finally {
      isLoading.value = false;
    }
  }

  async function roll() {
    if (!gameId.value || !myPlayerId.value) return;
    isLoading.value = true;
    lastError.value = null;
    try {
      const result = await apiRoll(gameId.value, myPlayerId.value);
      if (result) {
        gameState.value = result.gameState;
        validMoves.value = result.validMoves;
        selectedPieceIndex.value = null;

        // If only one valid move, auto-select it
        if (result.validMoves.length === 1) {
          selectedPieceIndex.value = result.validMoves[0].pieceIndex;
        }
      }
    } catch (e) {
      lastError.value = e instanceof Error ? e.message : "Roll failed";
    } finally {
      isLoading.value = false;
    }
  }

  async function move(pieceIndex: number) {
    if (!gameId.value || !myPlayerId.value) return;
    isLoading.value = true;
    lastError.value = null;
    try {
      const result = await apiMove(gameId.value, myPlayerId.value, pieceIndex);
      if (result) {
        gameState.value = result.gameState;
        validMoves.value = [];
        selectedPieceIndex.value = null;
      }
    } catch (e) {
      lastError.value = e instanceof Error ? e.message : "Move failed";
    } finally {
      isLoading.value = false;
    }
  }

  function selectPiece(pieceIndex: number) {
    if (validMovesByPiece.value.has(pieceIndex)) {
      selectedPieceIndex.value = pieceIndex;
    }
  }

  function setMyPlayer(playerId: string, playerIndex: number) {
    myPlayerId.value = playerId;
    myPlayerIndex.value = playerIndex;
  }

  function setPlayers(playerList: { id: string; name: string; isBot?: boolean }[]) {
    players.value = playerList;
  }

  function addEvent(event: GameEvent) {
    eventLog.value.push(event);
    // Keep last 50 events
    if (eventLog.value.length > 50) {
      eventLog.value = eventLog.value.slice(-50);
    }
  }

  function resolvePlayerName(color: string): string {
    const colors = ["green", "yellow", "red", "black"];
    const idx = colors.indexOf(color);
    if (idx >= 0 && players.value[idx]) {
      return players.value[idx].name;
    }
    return color;
  }

  function isPlayerBot(color: string): boolean {
    const colors = ["green", "yellow", "red", "black"];
    const idx = colors.indexOf(color);
    if (idx >= 0 && players.value[idx]) {
      return players.value[idx].isBot ?? false;
    }
    return false;
  }

  // Mercure subscription
  function subscribeMercure() {
    if (!gameId.value) return;
    mercure.subscribe(`game/${gameId.value}`, handleMercureMessage);
  }

  function handleMercureMessage(payload: unknown) {
    const data = (payload as Record<string, unknown>).data as Record<string, unknown> | undefined;
    const eventType = (payload as Record<string, unknown>).event as string;

    if (data?.gameState) {
      gameState.value = data.gameState as GameState;
      // Clear local valid moves on opponent's update
      if (!isMyTurn.value) {
        validMoves.value = [];
        selectedPieceIndex.value = null;
      }
    }

    // Build event log entry
    const playerColor = data?.playerColor as PlayerColor;
    const playerName = (data?.playerName as string) ?? resolvePlayerName(playerColor);
    const isBot = (data?.isBot as boolean) ?? isPlayerBot(playerColor);

    if (eventType === "dice_rolled" && playerColor) {
      addEvent({
        type: "dice_rolled",
        playerColor,
        playerName,
        isBot,
        diceRoll: (data?.diceRoll as number) ?? undefined,
        timestamp: Date.now(),
      });
    }

    if (eventType === "piece_moved" && playerColor) {
      const moved = data?.moved as { pieceIndex: number; from: string; to: string } | undefined;
      addEvent({
        type: "piece_moved",
        playerColor,
        playerName,
        isBot,
        moved: moved ?? undefined,
        kicked: (data?.kicked as boolean) ?? false,
        extraTurn: (data?.extraTurn as boolean) ?? false,
        winner: (data?.winner as PlayerColor) ?? null,
        timestamp: Date.now(),
      });

      // Track last moved piece for highlight
      if (moved?.to) {
        lastMovedPiece.value = { color: playerColor, position: moved.to };
        setTimeout(() => {
          lastMovedPiece.value = null;
        }, 1500);
      }
    }

    // Update bot thinking state
    if (data?.gameState) {
      const gs = data.gameState as GameState;
      const nextPlayer = players.value[gs.currentPlayerIndex];
      botThinking.value = nextPlayer?.isBot === true && gs.phase !== "finished";
    }
  }

  function unsubscribeMercure() {
    mercure.unsubscribe();
  }

  function $reset() {
    unsubscribeMercure();
    gameId.value = null;
    gameState.value = null;
    validMoves.value = [];
    selectedPieceIndex.value = null;
    myPlayerId.value = null;
    myPlayerIndex.value = null;
    players.value = [];
    lastError.value = null;
    isLoading.value = false;
    eventLog.value = [];
    botThinking.value = false;
    lastMovedPiece.value = null;
  }

  return {
    // State
    gameId,
    gameState,
    validMoves,
    selectedPieceIndex,
    myPlayerId,
    myPlayerIndex,
    players,
    lastError,
    isLoading,
    eventLog,
    botThinking,
    lastMovedPiece,
    connectionStatus,
    // Computed
    currentPlayer,
    isMyTurn,
    myColor,
    phase,
    lastDiceRoll,
    winner,
    isFinished,
    validMoveTargets,
    validMovesByPiece,
    // Actions
    loadGame,
    roll,
    move,
    selectPiece,
    setMyPlayer,
    setPlayers,
    addEvent,
    subscribeMercure,
    unsubscribeMercure,
    $reset,
  };
});
