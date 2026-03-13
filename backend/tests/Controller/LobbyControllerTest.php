<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class LobbyControllerTest extends WebTestCase
{
    public function testListLobbiesReturnsEmptyArray(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/lobbies');

        self::assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertIsArray($data);
    }

    public function testCreateLobby(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/lobbies', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['name' => 'Test Lobby', 'hostName' => 'Alice']));

        self::assertResponseStatusCodeSame(201);

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('Test Lobby', $data['name']);
        self::assertSame('Alice', $data['hostPlayer']['name']);
        self::assertSame('waiting', $data['status']);
        self::assertCount(1, $data['players']);
    }

    public function testCreateLobbyValidation(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/lobbies', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['name' => 'Test']));

        self::assertResponseStatusCodeSame(400);
    }

    public function testShowLobby(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/lobbies', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['name' => 'Show Lobby', 'hostName' => 'Bob']));
        $created = json_decode($client->getResponse()->getContent(), true);

        $client->request('GET', '/api/lobbies/' . $created['id']);
        self::assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('Show Lobby', $data['name']);
    }

    public function testShowLobbyNotFound(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/lobbies/00000000-0000-0000-0000-000000000000');

        self::assertResponseStatusCodeSame(404);
    }

    public function testJoinLobby(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/lobbies', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['name' => 'Join Lobby', 'hostName' => 'Alice']));
        $created = json_decode($client->getResponse()->getContent(), true);

        $client->request('POST', '/api/lobbies/' . $created['id'] . '/join', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['playerName' => 'Bob']));

        self::assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertCount(2, $data['players']);
    }

    public function testLeaveLobby(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/lobbies', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['name' => 'Leave Lobby', 'hostName' => 'Alice']));
        $created = json_decode($client->getResponse()->getContent(), true);

        $client->request('POST', '/api/lobbies/' . $created['id'] . '/join', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['playerName' => 'Bob']));
        $joined = json_decode($client->getResponse()->getContent(), true);
        $bobId = $joined['players'][1]['id'];

        $client->request('POST', '/api/lobbies/' . $created['id'] . '/leave', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['playerId' => $bobId]));

        self::assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertCount(1, $data['players']);
    }

    public function testDeleteLobby(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/lobbies', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['name' => 'Delete Lobby', 'hostName' => 'Alice']));
        $created = json_decode($client->getResponse()->getContent(), true);

        $client->request('DELETE', '/api/lobbies/' . $created['id']);
        self::assertResponseStatusCodeSame(204);

        $client->request('GET', '/api/lobbies/' . $created['id']);
        self::assertResponseStatusCodeSame(404);
    }

    public function testStartGame(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/lobbies', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['name' => 'Game Lobby', 'hostName' => 'Alice']));
        $created = json_decode($client->getResponse()->getContent(), true);

        // Must have at least 2 players
        $client->request('POST', '/api/lobbies/' . $created['id'] . '/join', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['playerName' => 'Bob']));

        $client->request('POST', '/api/lobbies/' . $created['id'] . '/start');
        self::assertResponseStatusCodeSame(201);

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('gameSessionId', $data);
        self::assertSame('in_game', $data['lobby']['status']);
        self::assertArrayHasKey('gameState', $data);

        // Verify lobby serialization includes gameSessionId
        self::assertArrayHasKey('gameSessionId', $data['lobby']);
        self::assertSame($data['gameSessionId'], $data['lobby']['gameSessionId']);
    }

    public function testGetLobbyGame(): void
    {
        $client = static::createClient();

        // Create lobby + join + start
        $client->request('POST', '/api/lobbies', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['name' => 'Get Game Lobby', 'hostName' => 'Alice']));
        $created = json_decode($client->getResponse()->getContent(), true);

        $client->request('POST', '/api/lobbies/' . $created['id'] . '/join', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['playerName' => 'Bob']));

        $client->request('POST', '/api/lobbies/' . $created['id'] . '/start');
        $startData = json_decode($client->getResponse()->getContent(), true);

        // GET /api/lobbies/{id}/game
        $client->request('GET', '/api/lobbies/' . $created['id'] . '/game');
        self::assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('gameSessionId', $data);
        self::assertSame($startData['gameSessionId'], $data['gameSessionId']);
        self::assertArrayHasKey('gameState', $data);
    }

    public function testGetLobbyGameNotStarted(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/lobbies', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['name' => 'Waiting Lobby', 'hostName' => 'Alice']));
        $created = json_decode($client->getResponse()->getContent(), true);

        $client->request('GET', '/api/lobbies/' . $created['id'] . '/game');
        self::assertResponseStatusCodeSame(409);
    }

    public function testGetLobbyGameNotFound(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/lobbies/00000000-0000-0000-0000-000000000000/game');
        self::assertResponseStatusCodeSame(404);
    }

    public function testShowLobbyIncludesGameSessionIdAfterStart(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/lobbies', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['name' => 'SessionId Lobby', 'hostName' => 'Alice']));
        $created = json_decode($client->getResponse()->getContent(), true);

        // Before start: no gameSessionId
        self::assertArrayNotHasKey('gameSessionId', $created);

        $client->request('POST', '/api/lobbies/' . $created['id'] . '/join', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['playerName' => 'Bob']));

        $client->request('POST', '/api/lobbies/' . $created['id'] . '/start');

        // After start: GET lobby should include gameSessionId
        $client->request('GET', '/api/lobbies/' . $created['id']);
        self::assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('gameSessionId', $data);
        self::assertNotEmpty($data['gameSessionId']);
    }

    private function createFinishedGame(): array
    {
        $client = static::createClient();

        $client->request('POST', '/api/lobbies', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['name' => 'Rematch Lobby', 'hostName' => 'Alice']));
        $lobby = json_decode($client->getResponse()->getContent(), true);
        $lobbyId = $lobby['id'];
        $hostPlayerId = $lobby['hostPlayer']['id'];

        $client->request('POST', "/api/lobbies/$lobbyId/join", [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['playerName' => 'Bob']));
        $updated = json_decode($client->getResponse()->getContent(), true);
        $player2Id = $updated['players'][1]['id'];

        $client->request('POST', "/api/lobbies/$lobbyId/start");
        $startData = json_decode($client->getResponse()->getContent(), true);
        $gameId = $startData['gameSessionId'];

        // Force-finish the game session via direct DB manipulation
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $gameSession = $em->getRepository(\App\Entity\GameSession::class)->find($gameId);
        $gameSession->setStatus(\App\Entity\GameSession::STATUS_FINISHED);
        $em->flush();

        return [
            'client' => $client,
            'lobbyId' => $lobbyId,
            'gameId' => $gameId,
            'hostPlayerId' => $hostPlayerId,
            'player2Id' => $player2Id,
        ];
    }

    public function testRematchResetsLobbyToWaiting(): void
    {
        $ctx = $this->createFinishedGame();

        $ctx['client']->request('POST', "/api/lobbies/{$ctx['lobbyId']}/rematch", [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['playerId' => $ctx['hostPlayerId']]));

        self::assertResponseIsSuccessful();
        $data = json_decode($ctx['client']->getResponse()->getContent(), true);
        self::assertSame('waiting', $data['status']);
    }

    public function testRematchRejectsNonMember(): void
    {
        $ctx = $this->createFinishedGame();

        $ctx['client']->request('POST', "/api/lobbies/{$ctx['lobbyId']}/rematch", [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['playerId' => '00000000-0000-0000-0000-000000000000']));

        self::assertResponseStatusCodeSame(403);
    }

    public function testRematchRejectsActiveGame(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/lobbies', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['name' => 'Active Lobby', 'hostName' => 'Alice']));
        $lobby = json_decode($client->getResponse()->getContent(), true);
        $lobbyId = $lobby['id'];
        $hostId = $lobby['hostPlayer']['id'];

        $client->request('POST', "/api/lobbies/$lobbyId/join", [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['playerName' => 'Bob']));

        $client->request('POST', "/api/lobbies/$lobbyId/start");

        $client->request('POST', "/api/lobbies/$lobbyId/rematch", [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['playerId' => $hostId]));

        self::assertResponseStatusCodeSame(409);
    }

    public function testRematchAllowsStartingNewGame(): void
    {
        $ctx = $this->createFinishedGame();

        // Rematch
        $ctx['client']->request('POST', "/api/lobbies/{$ctx['lobbyId']}/rematch", [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['playerId' => $ctx['hostPlayerId']]));
        self::assertResponseIsSuccessful();

        // Start a new game from the same lobby
        $ctx['client']->request('POST', "/api/lobbies/{$ctx['lobbyId']}/start");
        self::assertResponseStatusCodeSame(201);

        $data = json_decode($ctx['client']->getResponse()->getContent(), true);
        self::assertNotEquals($ctx['gameId'], $data['gameSessionId']);
    }

    public function testGameShowIncludesLobbyId(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/lobbies', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['name' => 'LobbyId Test', 'hostName' => 'Alice']));
        $lobby = json_decode($client->getResponse()->getContent(), true);
        $lobbyId = $lobby['id'];

        $client->request('POST', "/api/lobbies/$lobbyId/join", [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['playerName' => 'Bob']));

        $client->request('POST', "/api/lobbies/$lobbyId/start");
        $startData = json_decode($client->getResponse()->getContent(), true);
        $gameId = $startData['gameSessionId'];

        $client->request('GET', "/api/games/$gameId");
        self::assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('lobbyId', $data);
        self::assertSame($lobbyId, $data['lobbyId']);
    }
}
