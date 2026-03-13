<?php

namespace App\Controller;

use App\Entity\GameSession;
use App\Entity\Lobby;
use App\Entity\Player;
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

        $lobby->setStatus(Lobby::STATUS_IN_GAME);

        $gameSession = new GameSession($lobby);
        $gameSession->setStatus(GameSession::STATUS_ACTIVE);

        $this->em->persist($gameSession);
        $this->em->flush();

        $this->publishLobbyUpdate($lobby, 'game_started');

        return $this->json([
            'gameSessionId' => $gameSession->getId()->toRfc4122(),
            'lobby' => $this->serializeLobby($lobby),
        ], Response::HTTP_CREATED);
    }

    #[Route('/games/{id}', name: 'game_show', methods: ['GET'])]
    public function showGame(string $id): JsonResponse
    {
        $gameSession = $this->em->getRepository(GameSession::class)->find($id);

        if (!$gameSession) {
            return $this->json(['error' => 'Game session not found.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'id' => $gameSession->getId()->toRfc4122(),
            'status' => $gameSession->getStatus(),
            'currentTurn' => $gameSession->getCurrentTurn(),
            'gameState' => $gameSession->getGameState(),
            'lobby' => $this->serializeLobby($gameSession->getLobby()),
            'createdAt' => $gameSession->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $gameSession->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ]);
    }

    private function publishLobbyUpdate(Lobby $lobby, string $event): void
    {
        $this->hub->publish(new Update(
            'lobby/' . $lobby->getId()->toRfc4122(),
            json_encode([
                'event' => $event,
                'lobby' => $this->serializeLobby($lobby),
            ], JSON_THROW_ON_ERROR),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeLobby(Lobby $lobby): array
    {
        return [
            'id' => $lobby->getId()->toRfc4122(),
            'code' => $lobby->getCode(),
            'name' => $lobby->getName(),
            'hostPlayer' => [
                'id' => $lobby->getHostPlayer()->getId()->toRfc4122(),
                'name' => $lobby->getHostPlayer()->getName(),
            ],
            'players' => $lobby->getPlayers()->map(fn (Player $p) => [
                'id' => $p->getId()->toRfc4122(),
                'name' => $p->getName(),
            ])->toArray(),
            'maxPlayers' => $lobby->getMaxPlayers(),
            'status' => $lobby->getStatus(),
            'createdAt' => $lobby->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $lobby->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
