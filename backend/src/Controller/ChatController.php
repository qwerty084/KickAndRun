<?php

namespace App\Controller;

use App\Entity\ChatMessage;
use App\Entity\Player;
use App\Repository\ChatMessageRepository;
use App\Repository\LobbyRepository;
use App\Repository\GameSessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/api/chat')]
class ChatController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ChatMessageRepository $chatMessageRepository,
        private readonly LobbyRepository $lobbyRepository,
        private readonly GameSessionRepository $gameSessionRepository,
        private readonly HubInterface $hub,
    ) {
    }

    #[Route('/lobby/{lobbyId}/messages', name: 'chat_lobby_history', methods: ['GET'])]
    public function lobbyHistory(string $lobbyId): JsonResponse
    {
        $lobby = $this->lobbyRepository->find(Uuid::fromRfc4122($lobbyId));
        if (!$lobby) {
            return $this->json(['error' => 'Lobby not found.'], Response::HTTP_NOT_FOUND);
        }

        $messages = $this->chatMessageRepository->findByContext('lobby', $lobby->getId());

        return $this->json(array_map($this->serializeMessage(...), $messages));
    }

    #[Route('/lobby/{lobbyId}/messages', name: 'chat_lobby_send', methods: ['POST'])]
    public function lobbySend(string $lobbyId, Request $request): JsonResponse
    {
        $lobby = $this->lobbyRepository->find(Uuid::fromRfc4122($lobbyId));
        if (!$lobby) {
            return $this->json(['error' => 'Lobby not found.'], Response::HTTP_NOT_FOUND);
        }

        $data = $request->toArray();
        $playerId = $data['playerId'] ?? '';
        $content = trim($data['content'] ?? '');

        $validation = $this->validateMessage($playerId, $content);
        if ($validation) {
            return $validation;
        }

        $player = $this->em->getRepository(Player::class)->find(Uuid::fromRfc4122($playerId));
        if (!$player) {
            return $this->json(['error' => 'Player not found.'], Response::HTTP_NOT_FOUND);
        }

        // Verify player is in this lobby
        $inLobby = false;
        foreach ($lobby->getPlayers() as $p) {
            if ($p->getId()->equals($player->getId())) {
                $inLobby = true;
                break;
            }
        }
        if (!$inLobby) {
            return $this->json(['error' => 'Player is not in this lobby.'], Response::HTTP_FORBIDDEN);
        }

        $message = new ChatMessage($player, $content, 'lobby', $lobby->getId());
        $this->em->persist($message);
        $this->em->flush();

        $serialized = $this->serializeMessage($message);

        $this->hub->publish(new Update(
            'lobby/' . $lobby->getId()->toRfc4122(),
            json_encode([
                'event' => 'chat_message',
                'message' => $serialized,
            ], JSON_THROW_ON_ERROR),
        ));

        return $this->json($serialized, Response::HTTP_CREATED);
    }

    #[Route('/game/{gameId}/messages', name: 'chat_game_history', methods: ['GET'])]
    public function gameHistory(string $gameId): JsonResponse
    {
        $game = $this->gameSessionRepository->find(Uuid::fromRfc4122($gameId));
        if (!$game) {
            return $this->json(['error' => 'Game not found.'], Response::HTTP_NOT_FOUND);
        }

        $messages = $this->chatMessageRepository->findByContext('game', $game->getId());

        return $this->json(array_map($this->serializeMessage(...), $messages));
    }

    #[Route('/game/{gameId}/messages', name: 'chat_game_send', methods: ['POST'])]
    public function gameSend(string $gameId, Request $request): JsonResponse
    {
        $game = $this->gameSessionRepository->find(Uuid::fromRfc4122($gameId));
        if (!$game) {
            return $this->json(['error' => 'Game not found.'], Response::HTTP_NOT_FOUND);
        }

        $data = $request->toArray();
        $playerId = $data['playerId'] ?? '';
        $content = trim($data['content'] ?? '');

        $validation = $this->validateMessage($playerId, $content);
        if ($validation) {
            return $validation;
        }

        $player = $this->em->getRepository(Player::class)->find(Uuid::fromRfc4122($playerId));
        if (!$player) {
            return $this->json(['error' => 'Player not found.'], Response::HTTP_NOT_FOUND);
        }

        // Verify player is in this game's lobby
        $lobby = $game->getLobby();
        $inLobby = false;
        foreach ($lobby->getPlayers() as $p) {
            if ($p->getId()->equals($player->getId())) {
                $inLobby = true;
                break;
            }
        }
        if (!$inLobby) {
            return $this->json(['error' => 'Player is not in this game.'], Response::HTTP_FORBIDDEN);
        }

        $message = new ChatMessage($player, $content, 'game', $game->getId());
        $this->em->persist($message);
        $this->em->flush();

        $serialized = $this->serializeMessage($message);

        $this->hub->publish(new Update(
            'game/' . $game->getId()->toRfc4122(),
            json_encode([
                'event' => 'chat_message',
                'message' => $serialized,
            ], JSON_THROW_ON_ERROR),
        ));

        return $this->json($serialized, Response::HTTP_CREATED);
    }

    private function validateMessage(string $playerId, string $content): ?JsonResponse
    {
        if (!$playerId) {
            return $this->json(['error' => 'playerId is required.'], Response::HTTP_BAD_REQUEST);
        }

        if ($content === '') {
            return $this->json(['error' => 'Message content is required.'], Response::HTTP_BAD_REQUEST);
        }

        if (mb_strlen($content) > 500) {
            return $this->json(['error' => 'Message must be 500 characters or fewer.'], Response::HTTP_BAD_REQUEST);
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function serializeMessage(ChatMessage $message): array
    {
        return [
            'id' => $message->getId()->toRfc4122(),
            'content' => htmlspecialchars($message->getContent(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            'player' => [
                'id' => $message->getPlayer()->getId()->toRfc4122(),
                'name' => $message->getPlayer()->getName(),
            ],
            'createdAt' => $message->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
