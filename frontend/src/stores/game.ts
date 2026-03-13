import { ref, computed } from "vue";
import { defineStore } from "pinia";
import type { GameState, ValidMove, PlayerColor } from "@/types/Game";
import { useGame } from "@/composables/useGame";

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

  // EventSource for Mercure
  let eventSource: EventSource | null = null;

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
    unsubscribeMercure();

    const mercureUrl = new URL("/.well-known/mercure", window.location.origin);
    mercureUrl.searchParams.set("topic", `game/${gameId.value}`);

    eventSource = new EventSource(mercureUrl.toString());
    eventSource.onmessage = (event) => {
      try {
        const payload = JSON.parse(event.data);
        const eventType: string = payload.event;
        const data = payload.data;

        if (data?.gameState) {
          gameState.value = data.gameState;
          // Clear local valid moves on opponent's update
          if (!isMyTurn.value) {
            validMoves.value = [];
            selectedPieceIndex.value = null;
          }
        }

        // Build event log entry
        const playerColor = data?.playerColor as PlayerColor;
        const playerName = data?.playerName ?? resolvePlayerName(playerColor);
        const isBot = data?.isBot ?? isPlayerBot(playerColor);

        if (eventType === "dice_rolled" && playerColor) {
          addEvent({
            type: "dice_rolled",
            playerColor,
            playerName,
            isBot,
            diceRoll: data.diceRoll ?? undefined,
            timestamp: Date.now(),
          });
        }

        if (eventType === "piece_moved" && playerColor) {
          addEvent({
            type: "piece_moved",
            playerColor,
            playerName,
            isBot,
            moved: data.moved ?? undefined,
            kicked: data.kicked ?? false,
            extraTurn: data.extraTurn ?? false,
            winner: data.winner ?? null,
            timestamp: Date.now(),
          });

          // Track last moved piece for highlight
          if (data.moved?.to) {
            lastMovedPiece.value = { color: playerColor, position: data.moved.to };
            setTimeout(() => {
              lastMovedPiece.value = null;
            }, 1500);
          }
        }

        // Update bot thinking state
        if (data?.gameState) {
          const nextPlayerIdx = data.gameState.currentPlayerIndex;
          const nextPlayer = players.value[nextPlayerIdx];
          botThinking.value = nextPlayer?.isBot === true && data.gameState.phase !== "finished";
        }
      } catch {
        // Ignore malformed events
      }
    };
    eventSource.onerror = () => {
      // EventSource auto-reconnects; no action needed
    };
  }

  function unsubscribeMercure() {
    if (eventSource) {
      eventSource.close();
      eventSource = null;
    }
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
