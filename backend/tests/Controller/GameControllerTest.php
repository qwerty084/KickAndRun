<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class GameControllerTest extends WebTestCase
{
    private function createLobbyAndStartGame(): array
    {
        $client = static::createClient();

        // Create lobby
        $client->request('POST', '/api/lobbies', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'name' => 'Test Game',
            'hostName' => 'Player1',
        ]));
        $lobby = json_decode($client->getResponse()->getContent(), true);
        $lobbyId = $lobby['id'];
        $player1Id = $lobby['hostPlayer']['id'];

        // Join second player
        $client->request('POST', "/api/lobbies/$lobbyId/join", [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'playerName' => 'Player2',
        ]));
        $updated = json_decode($client->getResponse()->getContent(), true);
        $player2Id = $updated['players'][1]['id'];

        // Start game
        $client->request('POST', "/api/lobbies/$lobbyId/start");
        $this->assertResponseStatusCodeSame(201);
        $startData = json_decode($client->getResponse()->getContent(), true);
        $gameId = $startData['gameSessionId'];

        return [
            'client' => $client,
            'gameId' => $gameId,
            'player1Id' => $player1Id,
            'player2Id' => $player2Id,
        ];
    }

    public function testStartGameInitializesState(): void
    {
        $ctx = $this->createLobbyAndStartGame();

        $ctx['client']->request('GET', "/api/games/{$ctx['gameId']}");
        $this->assertResponseIsSuccessful();

        $data = json_decode($ctx['client']->getResponse()->getContent(), true);
        $this->assertSame('active', $data['status']);
        $this->assertNotEmpty($data['gameState']);
        $this->assertSame('rolling', $data['gameState']['phase']);
        $this->assertCount(2, $data['gameState']['players']);
    }

    public function testRollDice(): void
    {
        $ctx = $this->createLobbyAndStartGame();

        $ctx['client']->request('POST', "/api/games/{$ctx['gameId']}/roll", [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'playerId' => $ctx['player1Id'],
        ]));

        $this->assertResponseIsSuccessful();
        $data = json_decode($ctx['client']->getResponse()->getContent(), true);
        $this->assertArrayHasKey('diceRoll', $data);
        $this->assertArrayHasKey('gameState', $data);
    }

    public function testWrongPlayerCannotRoll(): void
    {
        $ctx = $this->createLobbyAndStartGame();

        // Player 2 (yellow) tries to roll when it's Player 1's (green) turn
        $ctx['client']->request('POST', "/api/games/{$ctx['gameId']}/roll", [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'playerId' => $ctx['player2Id'],
        ]));

        $this->assertResponseStatusCodeSame(409);
    }

    public function testMoveRequiresPriorRoll(): void
    {
        $ctx = $this->createLobbyAndStartGame();

        // Try to move without rolling
        $ctx['client']->request('POST', "/api/games/{$ctx['gameId']}/move", [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'playerId' => $ctx['player1Id'],
            'pieceIndex' => 0,
        ]));

        $this->assertResponseStatusCodeSame(400);
    }

    public function testGameNotFound(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/games/00000000-0000-0000-0000-000000000000');
        $this->assertResponseStatusCodeSame(404);
    }

    public function testRollWithInvalidPlayer(): void
    {
        $ctx = $this->createLobbyAndStartGame();

        $ctx['client']->request('POST', "/api/games/{$ctx['gameId']}/roll", [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'playerId' => '00000000-0000-0000-0000-000000000000',
        ]));

        $this->assertResponseStatusCodeSame(403);
    }

    public function testRollMissingPlayerId(): void
    {
        $ctx = $this->createLobbyAndStartGame();

        $ctx['client']->request('POST', "/api/games/{$ctx['gameId']}/roll", [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([]));

        $this->assertResponseStatusCodeSame(400);
    }

    public function testMinPlayersRequired(): void
    {
        $client = static::createClient();

        // Create lobby with only 1 player
        $client->request('POST', '/api/lobbies', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'name' => 'Solo Lobby',
            'hostName' => 'Lonely',
        ]));
        $lobby = json_decode($client->getResponse()->getContent(), true);

        $client->request('POST', "/api/lobbies/{$lobby['id']}/start");
        $this->assertResponseStatusCodeSame(400);
    }
}
