<?php

namespace App\Game;

use App\Game\Exception\InvalidMoveException;
use App\Game\Exception\NotYourTurnException;

class GameEngine
{
    private const int PATH_SIZE = 40;
    private const int PIECES_PER_PLAYER = 4;
    private const int MAX_CONSECUTIVE_SIXES = 3;
    private const int BASE_ROLL_ATTEMPTS = 3;

    /**
     * @param list<PlayerColor> $playerColors
     */
    public function initializeGame(array $playerColors): GameState
    {
        $pieces = [];
        foreach ($playerColors as $color) {
            $pieces[$color->value] = array_fill(0, self::PIECES_PER_PLAYER, PiecePosition::base());
        }

        return new GameState(
            players: $playerColors,
            currentPlayerIndex: 0,
            pieces: $pieces,
            lastDiceRoll: null,
            phase: GameState::PHASE_ROLLING,
            consecutiveSixes: 0,
            turnNumber: 1,
            winner: null,
            rollAttemptsLeft: 0,
        );
    }

    public function rollDice(GameState $state, PlayerColor $player, ?int $forcedRoll = null): GameState
    {
        if ($state->phase === GameState::PHASE_FINISHED) {
            throw new InvalidMoveException('Game is already finished.');
        }

        if ($state->currentPlayer() !== $player) {
            throw new NotYourTurnException();
        }

        if ($state->phase !== GameState::PHASE_ROLLING) {
            throw new InvalidMoveException('You must move a piece first.');
        }

        $newState = $state->clone();
        $roll = $forcedRoll ?? random_int(1, 6);
        $newState->lastDiceRoll = $roll;

        $allInBase = $this->allPiecesInBase($newState, $player);

        // If all pieces are in base and didn't roll a 6, handle 3-attempt rule
        if ($allInBase && $roll !== 6) {
            if ($newState->rollAttemptsLeft === 0) {
                // First roll attempt — set remaining to 2
                $newState->rollAttemptsLeft = self::BASE_ROLL_ATTEMPTS - 1;
            } else {
                $newState->rollAttemptsLeft--;
            }

            if ($newState->rollAttemptsLeft <= 0) {
                // Out of attempts, pass turn
                $newState->advanceToNextPlayer();
            } else {
                // Can try again
                $newState->phase = GameState::PHASE_ROLLING;
                $newState->lastDiceRoll = $roll;
            }

            return $newState;
        }

        // Check valid moves
        $validMoves = $this->getValidMoves($newState, $player, $roll);
        if (empty($validMoves)) {
            // No valid moves: pass turn (no extra turn even on 6)
            $newState->advanceToNextPlayer();
            return $newState;
        }

        $newState->phase = GameState::PHASE_MOVING;
        $newState->rollAttemptsLeft = 0;

        return $newState;
    }

    /**
     * @return list<array{pieceIndex: int, from: string, to: string}>
     */
    public function getValidMoves(GameState $state, PlayerColor $player, int $roll): array
    {
        $pieces = $state->piecesOf($player);
        $moves = [];
        $hasBaseExitMove = false;

        foreach ($pieces as $index => $position) {
            $target = $this->calculateTarget($player, $position, $roll);
            if ($target === null) {
                continue;
            }

            // Cannot land on own piece
            if ($this->isOccupiedByOwn($state, $player, $target)) {
                continue;
            }

            $move = [
                'pieceIndex' => $index,
                'from' => $position->toString(),
                'to' => $target->toString(),
            ];

            if ($position->isBase()) {
                $hasBaseExitMove = true;
            }

            $moves[] = $move;
        }

        // Must exit base if possible (rule 5): filter out non-base moves if base exit exists
        if ($hasBaseExitMove && $roll === 6) {
            $moves = array_values(array_filter(
                $moves,
                fn(array $m) => PiecePosition::fromString($m['from'])->isBase(),
            ));
        }

        return $moves;
    }

    public function movePiece(GameState $state, PlayerColor $player, int $pieceIndex): MoveResult
    {
        if ($state->phase === GameState::PHASE_FINISHED) {
            throw new InvalidMoveException('Game is already finished.');
        }

        if ($state->currentPlayer() !== $player) {
            throw new NotYourTurnException();
        }

        if ($state->phase !== GameState::PHASE_MOVING) {
            throw new InvalidMoveException('You must roll the dice first.');
        }

        if ($state->lastDiceRoll === null) {
            throw new InvalidMoveException('No dice roll available.');
        }

        $roll = $state->lastDiceRoll;
        $validMoves = $this->getValidMoves($state, $player, $roll);

        // Find the requested move
        $selectedMove = null;
        foreach ($validMoves as $move) {
            if ($move['pieceIndex'] === $pieceIndex) {
                $selectedMove = $move;
                break;
            }
        }

        if ($selectedMove === null) {
            throw new InvalidMoveException("Piece $pieceIndex cannot be moved.");
        }

        $newState = $state->clone();
        $from = PiecePosition::fromString($selectedMove['from']);
        $to = PiecePosition::fromString($selectedMove['to']);

        // Execute move
        $newState->setPiece($player, $pieceIndex, $to);

        // Check for kick
        $kicked = null;
        if ($to->isPath()) {
            $kicked = $this->checkAndApplyKick($newState, $player, $to);
        }

        // Check for three consecutive sixes
        $isSix = $roll === 6;
        $extraTurn = false;

        if ($isSix) {
            $newState->consecutiveSixes++;
            if ($newState->consecutiveSixes >= self::MAX_CONSECUTIVE_SIXES) {
                // Forfeit: send the piece that just moved back to base
                $newState->setPiece($player, $pieceIndex, PiecePosition::base());
                $newState->advanceToNextPlayer();
            } else {
                // Extra turn
                $extraTurn = true;
                $newState->lastDiceRoll = null;
                $newState->phase = GameState::PHASE_ROLLING;
            }
        } else {
            $newState->advanceToNextPlayer();
        }

        // Check winner
        $winner = $this->checkWinner($newState);
        if ($winner !== null) {
            $newState->winner = $winner;
            $newState->phase = GameState::PHASE_FINISHED;
        }

        return new MoveResult(
            newState: $newState,
            pieceIndex: $pieceIndex,
            from: $from,
            to: $to,
            kicked: $kicked,
            extraTurn: $extraTurn,
            winner: $winner,
        );
    }

    public function checkWinner(GameState $state): ?PlayerColor
    {
        foreach ($state->players as $color) {
            $allDone = true;
            foreach ($state->piecesOf($color) as $position) {
                if (!$position->isGoal() && !$position->isFinished()) {
                    $allDone = false;
                    break;
                }
            }
            if ($allDone) {
                return $color;
            }
        }

        return null;
    }

    private function calculateTarget(PlayerColor $player, PiecePosition $position, int $roll): ?PiecePosition
    {
        if ($position->isBase()) {
            // Can only exit on a 6
            if ($roll !== 6) {
                return null;
            }
            return PiecePosition::path($player->entryPosition());
        }

        if ($position->isPath()) {
            return $this->calculatePathTarget($player, $position->index, $roll);
        }

        if ($position->isGoal()) {
            $newGoalIndex = $position->index + $roll;
            if ($newGoalIndex > 3) {
                return null; // Overshoot
            }
            return PiecePosition::goal($newGoalIndex);
        }

        // Finished pieces can't move
        return null;
    }

    private function calculatePathTarget(PlayerColor $player, int $currentPathIndex, int $roll): ?PiecePosition
    {
        $entry = $player->entryPosition();

        // Steps already traveled from entry point (clockwise = decrementing indices)
        $stepsFromEntry = ($entry - $currentPathIndex + self::PATH_SIZE) % self::PATH_SIZE;
        $totalSteps = $stepsFromEntry + $roll;

        if ($totalSteps < self::PATH_SIZE) {
            // Still on the walking path (clockwise = decrement)
            return PiecePosition::path(($currentPathIndex - $roll + self::PATH_SIZE) % self::PATH_SIZE);
        }

        // Entering or moving within goal zone
        $goalIndex = $totalSteps - self::PATH_SIZE;
        if ($goalIndex > 3) {
            return null; // Overshoot goal
        }

        return PiecePosition::goal($goalIndex);
    }

    private function isOccupiedByOwn(GameState $state, PlayerColor $player, PiecePosition $target): bool
    {
        foreach ($state->piecesOf($player) as $position) {
            if ($position->equals($target)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{player: string, pieceIndex: int, from: string, to: string}|null
     */
    private function checkAndApplyKick(GameState $state, PlayerColor $movingPlayer, PiecePosition $landingPosition): ?array
    {
        foreach ($state->players as $color) {
            if ($color === $movingPlayer) {
                continue;
            }

            foreach ($state->piecesOf($color) as $index => $position) {
                if ($position->equals($landingPosition)) {
                    $from = $position->toString();
                    $state->setPiece($color, $index, PiecePosition::base());
                    return [
                        'player' => $color->value,
                        'pieceIndex' => $index,
                        'from' => $from,
                        'to' => 'base',
                    ];
                }
            }
        }

        return null;
    }

    private function allPiecesInBase(GameState $state, PlayerColor $player): bool
    {
        foreach ($state->piecesOf($player) as $position) {
            if (!$position->isBase()) {
                return false;
            }
        }

        return true;
    }
}
