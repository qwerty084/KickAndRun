<?php

namespace App\Controller;

use App\Entity\GameSession;
use App\Entity\Lobby;
use App\Entity\Player;
use App\Game\BotService;
use App\Game\GameEngine;
use App\Game\PlayerColor;
use App\Repository\LobbyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class LobbyController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LobbyRepository $lobbyRepository,
        private readonly HubInterface $hub,
        private readonly GameEngine $engine,
        private readonly BotService $botService,
    ) {
    }

    #[Route('/lobbies', name: 'lobby_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $lobbies = $this->lobbyRepository->findOpenLobbies();

        return $this->json(array_map($this->serializeLobby(...), $lobbies));
    }

    #[Route('/lobbies', name: 'lobby_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = $request->toArray();

        $lobbyName = $data['name'] ?? null;
        $hostName = $data['hostName'] ?? null;

        if (!$lobbyName || !$hostName) {
            return $this->json(
                ['error' => 'Fields "name" and "hostName" are required.'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $host = new Player($hostName);
        $lobby = new Lobby($lobbyName, $host);

        $this->em->persist($host);
        $this->em->persist($lobby);
        $this->em->flush();

        return $this->json($this->serializeLobby($lobby), Response::HTTP_CREATED);
    }

    #[Route('/lobbies/{id}', name: 'lobby_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $lobby = $this->lobbyRepository->find($id);

        if (!$lobby) {
            return $this->json(['error' => 'Lobby not found.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeLobby($lobby));
    }

    #[Route('/lobbies/{id}/join', name: 'lobby_join', methods: ['POST'])]
    public function join(string $id, Request $request): JsonResponse
    {
        $lobby = $this->lobbyRepository->find($id);

        if (!$lobby) {
            return $this->json(['error' => 'Lobby not found.'], Response::HTTP_NOT_FOUND);
        }

        if ($lobby->getStatus() !== Lobby::STATUS_WAITING) {
            return $this->json(['error' => 'Lobby is not accepting players.'], Response::HTTP_CONFLICT);
        }

        if ($lobby->isFull()) {
            return $this->json(['error' => 'Lobby is full.'], Response::HTTP_CONFLICT);
        }

        $data = $request->toArray();
        $playerName = $data['playerName'] ?? null;

        if (!$playerName) {
            return $this->json(
                ['error' => 'Field "playerName" is required.'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $player = new Player($playerName);
        $lobby->addPlayer($player);

        $this->em->persist($player);
        $this->em->flush();

        $this->publishLobbyUpdate($lobby, 'player_joined');

        return $this->json($this->serializeLobby($lobby));
    }

    #[Route('/lobbies/{id}/leave', name: 'lobby_leave', methods: ['POST'])]
    public function leave(string $id, Request $request): JsonResponse
    {
        $lobby = $this->lobbyRepository->find($id);

        if (!$lobby) {
            return $this->json(['error' => 'Lobby not found.'], Response::HTTP_NOT_FOUND);
        }

        $data = $request->toArray();
        $playerId = $data['playerId'] ?? null;

        if (!$playerId) {
            return $this->json(
                ['error' => 'Field "playerId" is required.'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $player = $this->em->getRepository(Player::class)->find($playerId);

        if (!$player) {
            return $this->json(['error' => 'Player not found.'], Response::HTTP_NOT_FOUND);
        }

        if ($player->getId()->equals($lobby->getHostPlayer()->getId())) {
            return $this->json(['error' => 'Host cannot leave the lobby.'], Response::HTTP_CONFLICT);
        }

        $lobby->removePlayer($player);
        $this->em->flush();

        $this->publishLobbyUpdate($lobby, 'player_left');

        return $this->json($this->serializeLobby($lobby));
    }

    #[Route('/lobbies/{id}/add-bot', name: 'lobby_add_bot', methods: ['POST'])]
    public function addBot(string $id, Request $request): JsonResponse
    {
        $lobby = $this->lobbyRepository->find($id);

        if (!$lobby) {
            return $this->json(['error' => 'Lobby not found.'], Response::HTTP_NOT_FOUND);
        }

        if ($lobby->getStatus() !== Lobby::STATUS_WAITING) {
            return $this->json(['error' => 'Lobby is not accepting players.'], Response::HTTP_CONFLICT);
        }

        if ($lobby->isFull()) {
            return $this->json(['error' => 'Lobby is full.'], Response::HTTP_CONFLICT);
        }

        $data = $request->toArray();
        $hostPlayerId = $data['hostPlayerId'] ?? null;

        if (!$hostPlayerId || $hostPlayerId !== $lobby->getHostPlayer()->getId()->toRfc4122()) {
            return $this->json(['error' => 'Only the host can add bots.'], Response::HTTP_FORBIDDEN);
        }

        $botNumber = 1;
        foreach ($lobby->getPlayers() as $player) {
            if ($player->isBot()) {
                $botNumber++;
            }
        }

        $bot = new Player('Bot ' . $botNumber, true);
        $lobby->addPlayer($bot);

        $this->em->persist($bot);
        $this->em->flush();

        $this->publishLobbyUpdate($lobby, 'player_joined');

        return $this->json($this->serializeLobby($lobby));
    }

    #[Route('/lobbies/{id}/remove-bot', name: 'lobby_remove_bot', methods: ['POST'])]
    public function removeBot(string $id, Request $request): JsonResponse
    {
        $lobby = $this->lobbyRepository->find($id);

        if (!$lobby) {
            return $this->json(['error' => 'Lobby not found.'], Response::HTTP_NOT_FOUND);
        }

        if ($lobby->getStatus() !== Lobby::STATUS_WAITING) {
            return $this->json(['error' => 'Cannot modify players after game started.'], Response::HTTP_CONFLICT);
        }

        $data = $request->toArray();
        $hostPlayerId = $data['hostPlayerId'] ?? null;
        $botPlayerId = $data['botPlayerId'] ?? null;

        if (!$hostPlayerId || $hostPlayerId !== $lobby->getHostPlayer()->getId()->toRfc4122()) {
            return $this->json(['error' => 'Only the host can remove bots.'], Response::HTTP_FORBIDDEN);
        }

        if (!$botPlayerId) {
            return $this->json(['error' => 'Field "botPlayerId" is required.'], Response::HTTP_BAD_REQUEST);
        }

        $botPlayer = $this->em->getRepository(Player::class)->find($botPlayerId);

        if (!$botPlayer || !$botPlayer->isBot()) {
            return $this->json(['error' => 'Bot player not found.'], Response::HTTP_NOT_FOUND);
        }

        $lobby->removePlayer($botPlayer);
        $this->em->remove($botPlayer);
        $this->em->flush();

        $this->publishLobbyUpdate($lobby, 'player_left');

        return $this->json($this->serializeLobby($lobby));
    }

    #[Route('/lobbies/{id}', name: 'lobby_delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $lobby = $this->lobbyRepository->find($id);

        if (!$lobby) {
            return $this->json(['error' => 'Lobby not found.'], Response::HTTP_NOT_FOUND);
        }

        $this->em->remove($lobby);
        $this->em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/lobbies/{id}/game', name: 'lobby_game', methods: ['GET'])]
    public function game(string $id): JsonResponse
    {
        $lobby = $this->lobbyRepository->find($id);

        if (!$lobby) {
            return $this->json(['error' => 'Lobby not found.'], Response::HTTP_NOT_FOUND);
        }

        if ($lobby->getStatus() === Lobby::STATUS_WAITING) {
            return $this->json(['error' => 'Game has not started yet.'], Response::HTTP_CONFLICT);
        }

        $gameSession = $lobby->getLatestGameSession();

        if (!$gameSession) {
            return $this->json(['error' => 'Game session not found.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'gameSessionId' => $gameSession->getId()->toRfc4122(),
            'gameState' => $gameSession->getGameState(),
        ]);
    }

    #[Route('/lobbies/{id}/start', name: 'lobby_start_game', methods: ['POST'])]
    public function startGame(string $id): JsonResponse
    {
        $lobby = $this->lobbyRepository->find($id);

        if (!$lobby) {
            return $this->json(['error' => 'Lobby not found.'], Response::HTTP_NOT_FOUND);
        }

        if ($lobby->getStatus() !== Lobby::STATUS_WAITING) {
            return $this->json(['error' => 'Game has already started or finished.'], Response::HTTP_CONFLICT);
        }

        if ($lobby->getPlayers()->count() < 2) {
            return $this->json(['error' => 'At least 2 players are required.'], Response::HTTP_BAD_REQUEST);
        }

        $lobby->setStatus(Lobby::STATUS_IN_GAME);

        // Assign colors based on join order
        $playerCount = $lobby->getPlayers()->count();
        $colors = array_slice(PlayerColor::inOrder(), 0, $playerCount);
        $initialState = $this->engine->initializeGame($colors);

        $gameSession = new GameSession($lobby);
        $gameSession->setStatus(GameSession::STATUS_ACTIVE);
        $gameSession->setGameState($initialState->toArray());

        $this->em->persist($gameSession);
        $this->em->flush();
        $this->em->refresh($lobby);

        $this->publishLobbyUpdate($lobby, 'game_started', [
            'gameSessionId' => $gameSession->getId()->toRfc4122(),
        ]);

        // If the first player is a bot, auto-play their turns
        if ($this->botService->isCurrentPlayerBot($gameSession)) {
            $this->botService->playBotTurns($gameSession);
        }

        return $this->json([
            'gameSessionId' => $gameSession->getId()->toRfc4122(),
            'lobby' => $this->serializeLobby($lobby),
            'gameState' => $gameSession->getGameState(),
        ], Response::HTTP_CREATED);
    }

    #[Route('/lobbies/{id}/rematch', name: 'lobby_rematch', methods: ['POST'])]
    public function rematch(string $id, Request $request): JsonResponse
    {
        $lobby = $this->lobbyRepository->find($id);

        if (!$lobby) {
            return $this->json(['error' => 'Lobby not found.'], Response::HTTP_NOT_FOUND);
        }

        $gameSession = $lobby->getLatestGameSession();

        if (!$gameSession || $gameSession->getStatus() !== GameSession::STATUS_FINISHED) {
            return $this->json(
                ['error' => 'No finished game to rematch.'],
                Response::HTTP_CONFLICT,
            );
        }

        $data = $request->toArray();
        $playerId = $data['playerId'] ?? null;

        if (!$playerId) {
            return $this->json(
                ['error' => 'Field "playerId" is required.'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $isMember = false;
        foreach ($lobby->getPlayers() as $player) {
            if ($player->getId()->toRfc4122() === $playerId) {
                $isMember = true;
                break;
            }
        }

        if (!$isMember) {
            return $this->json(['error' => 'Player is not in this lobby.'], Response::HTTP_FORBIDDEN);
        }

        $lobby->setStatus(Lobby::STATUS_WAITING);
        $this->em->flush();

        // Notify game subscribers to redirect to lobby
        $this->hub->publish(new Update(
            'game/' . $gameSession->getId()->toRfc4122(),
            json_encode([
                'event' => 'rematch_initiated',
                'data' => [
                    'lobbyId' => $lobby->getId()->toRfc4122(),
                ],
            ], JSON_THROW_ON_ERROR),
        ));

        $this->publishLobbyUpdate($lobby, 'rematch_initiated');

        return $this->json($this->serializeLobby($lobby));
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function publishLobbyUpdate(Lobby $lobby, string $event, array $extra = []): void
    {
        $this->hub->publish(new Update(
            'lobby/' . $lobby->getId()->toRfc4122(),
            json_encode(array_merge([
                'event' => $event,
                'lobby' => $this->serializeLobby($lobby),
            ], $extra), JSON_THROW_ON_ERROR),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeLobby(Lobby $lobby): array
    {
        $data = [
            'id' => $lobby->getId()->toRfc4122(),
            'code' => $lobby->getCode(),
            'name' => $lobby->getName(),
            'hostPlayer' => [
                'id' => $lobby->getHostPlayer()->getId()->toRfc4122(),
                'name' => $lobby->getHostPlayer()->getName(),
                'isBot' => $lobby->getHostPlayer()->isBot(),
            ],
            'players' => $lobby->getPlayers()->map(fn (Player $p) => [
                'id' => $p->getId()->toRfc4122(),
                'name' => $p->getName(),
                'isBot' => $p->isBot(),
            ])->toArray(),
            'maxPlayers' => $lobby->getMaxPlayers(),
            'status' => $lobby->getStatus(),
            'createdAt' => $lobby->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $lobby->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];

        $gameSession = $lobby->getLatestGameSession();
        if ($gameSession) {
            $data['gameSessionId'] = $gameSession->getId()->toRfc4122();
        }

        return $data;
    }
}
