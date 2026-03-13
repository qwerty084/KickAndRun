<?php

namespace App\Controller;

use App\Entity\GameSession;
use App\Entity\Player;
use App\Game\BotService;
use App\Game\Exception\InvalidMoveException;
use App\Game\Exception\NotYourTurnException;
use App\Game\GameEngine;
use App\Game\GameState;
use App\Game\PlayerColor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class GameController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly GameEngine $engine,
        private readonly HubInterface $hub,
        private readonly BotService $botService,
    ) {
    }

    #[Route('/games/{id}', name: 'game_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $gameSession = $this->em->getRepository(GameSession::class)->find($id);

        if (!$gameSession) {
            return $this->json(['error' => 'Game session not found.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeGameSession($gameSession));
    }

    #[Route('/games/{id}/roll', name: 'game_roll', methods: ['POST'])]
    public function roll(string $id, Request $request): JsonResponse
    {
        $gameSession = $this->em->getRepository(GameSession::class)->find($id);

        if (!$gameSession) {
            return $this->json(['error' => 'Game session not found.'], Response::HTTP_NOT_FOUND);
        }

        if ($gameSession->getStatus() !== GameSession::STATUS_ACTIVE) {
            return $this->json(['error' => 'Game is not active.'], Response::HTTP_CONFLICT);
        }

        $data = $request->toArray();
        $playerId = $data['playerId'] ?? null;

        if (!$playerId) {
            return $this->json(['error' => 'Field "playerId" is required.'], Response::HTTP_BAD_REQUEST);
        }

        $state = GameState::fromArray($gameSession->getGameState());
        $playerColor = $this->resolvePlayerColor($gameSession, $playerId);

        if ($playerColor === null) {
            return $this->json(['error' => 'Player is not in this game.'], Response::HTTP_FORBIDDEN);
        }

        try {
            $newState = $this->engine->rollDice($state, $playerColor);
        } catch (NotYourTurnException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_CONFLICT);
        } catch (InvalidMoveException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $validMoves = [];
        if ($newState->phase === GameState::PHASE_MOVING && $newState->lastDiceRoll !== null) {
            $validMoves = $this->engine->getValidMoves($newState, $playerColor, $newState->lastDiceRoll);
        }

        $this->persistState($gameSession, $newState);

        $playerInfo = $this->resolvePlayerInfo($gameSession, $playerId);

        $response = [
            'diceRoll' => $newState->lastDiceRoll,
            'validMoves' => $validMoves,
            'phase' => $newState->phase,
            'playerColor' => $playerColor->value,
            'playerName' => $playerInfo['name'] ?? null,
            'isBot' => $playerInfo['isBot'] ?? false,
            'gameState' => $newState->toArray(),
        ];

        $this->publishGameUpdate($gameSession, 'dice_rolled', $response);

        // If next action belongs to a bot (turn passed after no valid moves), trigger bot turns
        $latestState = GameState::fromArray($gameSession->getGameState());
        if ($latestState->phase !== GameState::PHASE_FINISHED && $this->botService->isCurrentPlayerBot($gameSession)) {
            $this->botService->playBotTurns($gameSession);
        }

        // Return fresh state so the caller sees the post-bot state
        $response['gameState'] = $gameSession->getGameState();

        return $this->json($response);
    }

    #[Route('/games/{id}/move', name: 'game_move', methods: ['POST'])]
    public function move(string $id, Request $request): JsonResponse
    {
        $gameSession = $this->em->getRepository(GameSession::class)->find($id);

        if (!$gameSession) {
            return $this->json(['error' => 'Game session not found.'], Response::HTTP_NOT_FOUND);
        }

        if ($gameSession->getStatus() !== GameSession::STATUS_ACTIVE) {
            return $this->json(['error' => 'Game is not active.'], Response::HTTP_CONFLICT);
        }

        $data = $request->toArray();
        $playerId = $data['playerId'] ?? null;
        $pieceIndex = $data['pieceIndex'] ?? null;

        if (!$playerId || $pieceIndex === null) {
            return $this->json(
                ['error' => 'Fields "playerId" and "pieceIndex" are required.'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $state = GameState::fromArray($gameSession->getGameState());
        $playerColor = $this->resolvePlayerColor($gameSession, $playerId);

        if ($playerColor === null) {
            return $this->json(['error' => 'Player is not in this game.'], Response::HTTP_FORBIDDEN);
        }

        try {
            $result = $this->engine->movePiece($state, $playerColor, (int) $pieceIndex);
        } catch (NotYourTurnException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_CONFLICT);
        } catch (InvalidMoveException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        if ($result->winner !== null) {
            $gameSession->setStatus(GameSession::STATUS_FINISHED);
        }

        $this->persistState($gameSession, $result->newState);

        $playerInfo = $this->resolvePlayerInfo($gameSession, $playerId);

        $response = [
            'moved' => [
                'pieceIndex' => $result->pieceIndex,
                'from' => $result->from->toString(),
                'to' => $result->to->toString(),
            ],
            'kicked' => $result->kicked,
            'extraTurn' => $result->extraTurn,
            'winner' => $result->winner?->value,
            'playerColor' => $playerColor->value,
            'playerName' => $playerInfo['name'] ?? null,
            'isBot' => $playerInfo['isBot'] ?? false,
            'gameState' => $result->newState->toArray(),
        ];

        $this->publishGameUpdate($gameSession, 'piece_moved', $response);

        // If next player is a bot, auto-play their turns
        $latestState = GameState::fromArray($gameSession->getGameState());
        if ($latestState->phase !== GameState::PHASE_FINISHED && $this->botService->isCurrentPlayerBot($gameSession)) {
            $this->botService->playBotTurns($gameSession);
        }

        // Return fresh state so the caller sees the post-bot state
        $response['gameState'] = $gameSession->getGameState();

        return $this->json($response);
    }

    private function resolvePlayerColor(GameSession $gameSession, string $playerId): ?PlayerColor
    {
        $players = $gameSession->getLobby()->getPlayers()->toArray();
        $colors = PlayerColor::inOrder();

        foreach ($players as $index => $player) {
            /** @var Player $player */
            if ($player->getId()->toRfc4122() === $playerId && isset($colors[$index])) {
                return $colors[$index];
            }
        }

        return null;
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

    /**
     * @return array<string, mixed>
     */
    private function serializeGameSession(GameSession $gameSession): array
    {
        return [
            'id' => $gameSession->getId()->toRfc4122(),
            'status' => $gameSession->getStatus(),
            'currentTurn' => $gameSession->getCurrentTurn(),
            'gameState' => $gameSession->getGameState(),
            'players' => $this->serializePlayers($gameSession),
            'createdAt' => $gameSession->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $gameSession->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return list<array{id: string, name: string, isBot: bool, color: string}>
     */
    private function serializePlayers(GameSession $gameSession): array
    {
        $players = $gameSession->getLobby()->getPlayers()->toArray();
        $colors = PlayerColor::inOrder();
        $result = [];

        foreach ($players as $index => $player) {
            /** @var Player $player */
            $result[] = [
                'id' => $player->getId()->toRfc4122(),
                'name' => $player->getName(),
                'isBot' => $player->isBot(),
                'color' => isset($colors[$index]) ? $colors[$index]->value : 'unknown',
            ];
        }

        return $result;
    }

    /**
     * @return array{name: string, isBot: bool, color: string}|null
     */
    private function resolvePlayerInfo(GameSession $gameSession, string $playerId): ?array
    {
        $players = $gameSession->getLobby()->getPlayers()->toArray();
        $colors = PlayerColor::inOrder();

        foreach ($players as $index => $player) {
            /** @var Player $player */
            if ($player->getId()->toRfc4122() === $playerId && isset($colors[$index])) {
                return [
                    'name' => $player->getName(),
                    'isBot' => $player->isBot(),
                    'color' => $colors[$index]->value,
                ];
            }
        }

        return null;
    }
}
