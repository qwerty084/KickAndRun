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
}
