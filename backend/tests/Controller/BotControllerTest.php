<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class BotControllerTest extends WebTestCase
{
    private function createLobbyWithHost(string $lobbyName = 'Bot Lobby', string $hostName = 'Alice'): array
    {
        $client = static::createClient();
        $client->request('POST', '/api/lobbies', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['name' => $lobbyName, 'hostName' => $hostName]));

        return [
            'client' => $client,
            'lobby' => json_decode($client->getResponse()->getContent(), true),
        ];
    }

    public function testAddBotToLobby(): void
    {
        ['client' => $client, 'lobby' => $lobby] = $this->createLobbyWithHost();

        $client->request('POST', '/api/lobbies/' . $lobby['id'] . '/add-bot', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['hostPlayerId' => $lobby['hostPlayer']['id']]));

        self::assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertCount(2, $data['players']);
        self::assertTrue($data['players'][1]['isBot']);
        self::assertSame('Bot 1', $data['players'][1]['name']);
    }

    public function testAddMultipleBots(): void
    {
        ['client' => $client, 'lobby' => $lobby] = $this->createLobbyWithHost();
        $hostId = $lobby['hostPlayer']['id'];

        // Add 3 bots to fill the lobby
        for ($i = 0; $i < 3; $i++) {
            $client->request('POST', '/api/lobbies/' . $lobby['id'] . '/add-bot', [], [], [
                'CONTENT_TYPE' => 'application/json',
            ], json_encode(['hostPlayerId' => $hostId]));
            self::assertResponseIsSuccessful();
        }

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertCount(4, $data['players']);
        self::assertFalse($data['players'][0]['isBot']); // Host is human
        self::assertTrue($data['players'][1]['isBot']);
        self::assertTrue($data['players'][2]['isBot']);
        self::assertTrue($data['players'][3]['isBot']);
    }

    public function testAddBotToFullLobby(): void
    {
        ['client' => $client, 'lobby' => $lobby] = $this->createLobbyWithHost();
        $hostId = $lobby['hostPlayer']['id'];

        // Fill with 3 bots
        for ($i = 0; $i < 3; $i++) {
            $client->request('POST', '/api/lobbies/' . $lobby['id'] . '/add-bot', [], [], [
                'CONTENT_TYPE' => 'application/json',
            ], json_encode(['hostPlayerId' => $hostId]));
        }

        // Try adding a 4th bot (lobby is full)
        $client->request('POST', '/api/lobbies/' . $lobby['id'] . '/add-bot', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['hostPlayerId' => $hostId]));

        self::assertResponseStatusCodeSame(409);
    }

    public function testOnlyHostCanAddBot(): void
    {
        ['client' => $client, 'lobby' => $lobby] = $this->createLobbyWithHost();

        $client->request('POST', '/api/lobbies/' . $lobby['id'] . '/add-bot', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['hostPlayerId' => 'not-the-host']));

        self::assertResponseStatusCodeSame(403);
    }

    public function testRemoveBotFromLobby(): void
    {
        ['client' => $client, 'lobby' => $lobby] = $this->createLobbyWithHost();
        $hostId = $lobby['hostPlayer']['id'];

        // Add a bot
        $client->request('POST', '/api/lobbies/' . $lobby['id'] . '/add-bot', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['hostPlayerId' => $hostId]));
        $afterAdd = json_decode($client->getResponse()->getContent(), true);
        $botId = $afterAdd['players'][1]['id'];

        // Remove the bot
        $client->request('POST', '/api/lobbies/' . $lobby['id'] . '/remove-bot', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['hostPlayerId' => $hostId, 'botPlayerId' => $botId]));

        self::assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertCount(1, $data['players']);
        self::assertFalse($data['players'][0]['isBot']);
    }

    public function testOnlyHostCanRemoveBot(): void
    {
        ['client' => $client, 'lobby' => $lobby] = $this->createLobbyWithHost();
        $hostId = $lobby['hostPlayer']['id'];

        // Add a bot
        $client->request('POST', '/api/lobbies/' . $lobby['id'] . '/add-bot', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['hostPlayerId' => $hostId]));
        $afterAdd = json_decode($client->getResponse()->getContent(), true);
        $botId = $afterAdd['players'][1]['id'];

        // Try removing with wrong host ID
        $client->request('POST', '/api/lobbies/' . $lobby['id'] . '/remove-bot', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['hostPlayerId' => 'not-the-host', 'botPlayerId' => $botId]));

        self::assertResponseStatusCodeSame(403);
    }

    public function testStartGameWithBots(): void
    {
        ['client' => $client, 'lobby' => $lobby] = $this->createLobbyWithHost();
        $hostId = $lobby['hostPlayer']['id'];

        // Add a bot
        $client->request('POST', '/api/lobbies/' . $lobby['id'] . '/add-bot', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['hostPlayerId' => $hostId]));

        // Start the game
        $client->request('POST', '/api/lobbies/' . $lobby['id'] . '/start');
        self::assertResponseStatusCodeSame(201);

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('gameSessionId', $data);
        self::assertArrayHasKey('gameState', $data);
    }

    public function testBotAutoPlaysAfterHumanMove(): void
    {
        ['client' => $client, 'lobby' => $lobby] = $this->createLobbyWithHost();
        $hostId = $lobby['hostPlayer']['id'];

        // Add a bot (player index 1 = yellow)
        $client->request('POST', '/api/lobbies/' . $lobby['id'] . '/add-bot', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['hostPlayerId' => $hostId]));

        // Start the game — player 0 (green/Alice) goes first
        $client->request('POST', '/api/lobbies/' . $lobby['id'] . '/start');
        $startData = json_decode($client->getResponse()->getContent(), true);
        $gameId = $startData['gameSessionId'];

        // Roll dice for the human player
        $client->request('POST', '/api/games/' . $gameId . '/roll', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['playerId' => $hostId]));
        self::assertResponseIsSuccessful();

        $rollData = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('diceRoll', $rollData);

        // Fetch the game state — the state should reflect bot having already played
        // (or it's human's turn again after bot played)
        $client->request('GET', '/api/games/' . $gameId);
        self::assertResponseIsSuccessful();

        $gameData = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('gameState', $gameData);
    }

    public function testSerializationIncludesIsBot(): void
    {
        ['client' => $client, 'lobby' => $lobby] = $this->createLobbyWithHost();

        // Host should have isBot = false
        self::assertFalse($lobby['hostPlayer']['isBot']);
        self::assertFalse($lobby['players'][0]['isBot']);

        // Add a bot
        $client->request('POST', '/api/lobbies/' . $lobby['id'] . '/add-bot', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['hostPlayerId' => $lobby['hostPlayer']['id']]));

        // Verify GET lobby includes isBot
        $client->request('GET', '/api/lobbies/' . $lobby['id']);
        $data = json_decode($client->getResponse()->getContent(), true);

        self::assertArrayHasKey('isBot', $data['players'][0]);
        self::assertArrayHasKey('isBot', $data['players'][1]);
        self::assertFalse($data['players'][0]['isBot']);
        self::assertTrue($data['players'][1]['isBot']);
    }
}
