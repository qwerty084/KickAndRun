import { ref, computed } from "vue";
import { defineStore } from "pinia";
import type { GameState, ValidMove, PlayerColor } from "@/types/Game";
import { useGame } from "@/composables/useGame";

export const useGameStore = defineStore("game", () => {
  const gameId = ref<string | null>(null);
  const gameState = ref<GameState | null>(null);
  const validMoves = ref<ValidMove[]>([]);
  const selectedPieceIndex = ref<number | null>(null);
  const myPlayerId = ref<string | null>(null);
  const myPlayerIndex = ref<number | null>(null);
  const players = ref<{ id: string; name: string }[]>([]);
  const lastError = ref<string | null>(null);
  const isLoading = ref(false);

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

  function setPlayers(playerList: { id: string; name: string }[]) {
    players.value = playerList;
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
        if (payload.data?.gameState) {
          gameState.value = payload.data.gameState;
          // Clear local valid moves on opponent's update
          if (!isMyTurn.value) {
            validMoves.value = [];
            selectedPieceIndex.value = null;
          }
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
    subscribeMercure,
    unsubscribeMercure,
    $reset,
  };
});
