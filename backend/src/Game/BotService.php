<?php

namespace App\Game;

use App\Entity\GameSession;
use App\Entity\Player;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Handles automatic bot turns: rolls dice and picks a random valid move.
 * Loops until it's a human player's turn (or the game ends).
 */
class BotService
{
    public function __construct(
        private readonly GameEngine $engine,
        private readonly EntityManagerInterface $em,
        private readonly HubInterface $hub,
    ) {
    }

    /**
     * Play all consecutive bot turns starting from the current player.
     * Returns the final game state after all bot turns complete.
     *
     * @return array<string, mixed> Accumulated events for Mercure broadcast
     */
    public function playBotTurns(GameSession $gameSession): array
    {
        $events = [];
        $maxIterations = 200; // Safety limit to prevent infinite loops
        $iterations = 0;

        while ($iterations < $maxIterations) {
            $iterations++;

            $state = GameState::fromArray($gameSession->getGameState());

            if ($state->phase === GameState::PHASE_FINISHED) {
                break;
            }

            $currentColor = $state->currentPlayer();
            $currentPlayer = $this->getPlayerByColor($gameSession, $state->currentPlayerIndex);

            if ($currentPlayer === null || !$currentPlayer->isBot()) {
                break;
            }

            // Bot rolls dice
            $state = $this->engine->rollDice($state, $currentColor);
            $this->persistState($gameSession, $state);

            $rollEvent = [
                'event' => 'dice_rolled',
                'data' => [
                    'diceRoll' => $state->lastActualRoll ?? $state->lastDiceRoll,
                    'phase' => $state->phase,
                    'isBot' => true,
                    'playerColor' => $currentColor->value,
                    'playerName' => $currentPlayer->getName(),
                    'gameState' => $state->toArray(),
                ],
            ];
            $events[] = $rollEvent;
            $this->publishGameUpdate($gameSession, 'dice_rolled', $rollEvent['data']);

            if ($state->phase === GameState::PHASE_MOVING && $state->lastDiceRoll !== null) {
                $validMoves = $this->engine->getValidMoves($state, $currentColor, $state->lastDiceRoll);

                if (count($validMoves) > 0) {
                    // Pick a random move
                    $chosenMove = $validMoves[array_rand($validMoves)];
                    $result = $this->engine->movePiece($state, $currentColor, $chosenMove['pieceIndex']);
                    $state = $result->newState;

                    if ($result->winner !== null) {
                        $gameSession->setStatus(GameSession::STATUS_FINISHED);
                    }

                    $this->persistState($gameSession, $state);

                    $moveEvent = [
                        'event' => 'piece_moved',
                        'data' => [
                            'moved' => [
                                'pieceIndex' => $result->pieceIndex,
                                'from' => $result->from->toString(),
                                'to' => $result->to->toString(),
                            ],
                            'kicked' => $result->kicked,
                            'extraTurn' => $result->extraTurn,
                            'winner' => $result->winner?->value,
                            'isBot' => true,
                            'playerColor' => $currentColor->value,
                            'playerName' => $currentPlayer->getName(),
                            'gameState' => $state->toArray(),
                        ],
                    ];
                    $events[] = $moveEvent;
                    $this->publishGameUpdate($gameSession, 'piece_moved', $moveEvent['data']);
                }
            }

            // If game is finished, stop
            if ($state->phase === GameState::PHASE_FINISHED) {
                break;
            }
        }

        return $events;
    }

    /**
     * Check if the current player in the game is a bot.
     */
    public function isCurrentPlayerBot(GameSession $gameSession): bool
    {
        $state = GameState::fromArray($gameSession->getGameState());
        $player = $this->getPlayerByColor($gameSession, $state->currentPlayerIndex);

        return $player !== null && $player->isBot();
    }

    private function getPlayerByColor(GameSession $gameSession, int $playerIndex): ?Player
    {
        $players = $gameSession->getLobby()->getPlayers()->toArray();

        return $players[$playerIndex] ?? null;
    }

    private function persistState(GameSession $gameSession, GameState $state): void
    {
        $gameSession->setGameState($state->toArray());
        $gameSession->setCurrentTurn($state->turnNumber);
        $this->em->flush();
    }

    private function publishGameUpdate(GameSession $gameSession, string $event, mixed $data): void
    {
        $this->hub->publish(new Update(
            'game/' . $gameSession->getId()->toRfc4122(),
            json_encode([
                'event' => $event,
                'data' => $data,
            ], JSON_THROW_ON_ERROR),
        ));
    }
}
