<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ChatControllerTest extends WebTestCase
{
    private function createLobbyWithPlayer(object $client): array
    {
        $client->request('POST', '/api/lobbies', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['name' => 'Chat Test', 'hostName' => 'ChatPlayer']));

        $lobby = json_decode($client->getResponse()->getContent(), true);

        return [
            'lobbyId' => $lobby['id'],
            'playerId' => $lobby['hostPlayer']['id'],
        ];
    }

    private function createGameWithPlayers(object $client): array
    {
        // Create lobby
        $client->request('POST', '/api/lobbies', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['name' => 'Chat Game', 'hostName' => 'Host']));
        $lobby = json_decode($client->getResponse()->getContent(), true);
        $lobbyId = $lobby['id'];
        $hostId = $lobby['hostPlayer']['id'];

        // Add a bot so we have 2 players
        $client->request('POST', "/api/lobbies/{$lobbyId}/add-bot", [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['hostPlayerId' => $hostId]));

        // Start game
        $client->request('POST', "/api/lobbies/{$lobbyId}/start");
        $game = json_decode($client->getResponse()->getContent(), true);

        return [
            'lobbyId' => $lobbyId,
            'gameId' => $game['gameSessionId'],
            'playerId' => $hostId,
        ];
    }

    public function testSendLobbyChatMessage(): void
    {
        $client = static::createClient();
        $ctx = $this->createLobbyWithPlayer($client);

        $client->request('POST', "/api/chat/lobby/{$ctx['lobbyId']}/messages", [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['playerId' => $ctx['playerId'], 'content' => 'Hello lobby!']));

        self::assertResponseStatusCodeSame(201);
        $msg = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('Hello lobby!', $msg['content']);
        self::assertSame($ctx['playerId'], $msg['player']['id']);
        self::assertArrayHasKey('createdAt', $msg);
    }

    public function testGetLobbyChatHistory(): void
    {
        $client = static::createClient();
        $ctx = $this->createLobbyWithPlayer($client);

        // Send two messages
        $client->request('POST', "/api/chat/lobby/{$ctx['lobbyId']}/messages", [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['playerId' => $ctx['playerId'], 'content' => 'First']));
        $client->request('POST', "/api/chat/lobby/{$ctx['lobbyId']}/messages", [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['playerId' => $ctx['playerId'], 'content' => 'Second']));

        // Fetch history
        $client->request('GET', "/api/chat/lobby/{$ctx['lobbyId']}/messages");
        self::assertResponseIsSuccessful();

        $messages = json_decode($client->getResponse()->getContent(), true);
        self::assertCount(2, $messages);
        self::assertSame('First', $messages[0]['content']);
        self::assertSame('Second', $messages[1]['content']);
    }

    public function testSendGameChatMessage(): void
    {
        $client = static::createClient();
        $ctx = $this->createGameWithPlayers($client);

        $client->request('POST', "/api/chat/game/{$ctx['gameId']}/messages", [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['playerId' => $ctx['playerId'], 'content' => 'Hello game!']));

        self::assertResponseStatusCodeSame(201);
        $msg = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('Hello game!', $msg['content']);
    }

    public function testGetGameChatHistory(): void
    {
        $client = static::createClient();
        $ctx = $this->createGameWithPlayers($client);

        $client->request('POST', "/api/chat/game/{$ctx['gameId']}/messages", [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['playerId' => $ctx['playerId'], 'content' => 'GG']));

        $client->request('GET', "/api/chat/game/{$ctx['gameId']}/messages");
        self::assertResponseIsSuccessful();

        $messages = json_decode($client->getResponse()->getContent(), true);
        self::assertCount(1, $messages);
        self::assertSame('GG', $messages[0]['content']);
    }

    public function testEmptyMessageRejected(): void
    {
        $client = static::createClient();
        $ctx = $this->createLobbyWithPlayer($client);

        $client->request('POST', "/api/chat/lobby/{$ctx['lobbyId']}/messages", [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['playerId' => $ctx['playerId'], 'content' => '']));

        self::assertResponseStatusCodeSame(400);
    }

    public function testMessageTooLongRejected(): void
    {
        $client = static::createClient();
        $ctx = $this->createLobbyWithPlayer($client);

        $client->request('POST', "/api/chat/lobby/{$ctx['lobbyId']}/messages", [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['playerId' => $ctx['playerId'], 'content' => str_repeat('a', 501)]));

        self::assertResponseStatusCodeSame(400);
    }

    public function testNonMemberRejected(): void
    {
        $client = static::createClient();
        $ctx = $this->createLobbyWithPlayer($client);

        // Create another lobby to get a different player
        $client->request('POST', '/api/lobbies', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['name' => 'Other Lobby', 'hostName' => 'Outsider']));
        $other = json_decode($client->getResponse()->getContent(), true);

        $client->request('POST', "/api/chat/lobby/{$ctx['lobbyId']}/messages", [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['playerId' => $other['hostPlayer']['id'], 'content' => 'Intruder!']));

        self::assertResponseStatusCodeSame(403);
    }

    public function testHtmlIsEscaped(): void
    {
        $client = static::createClient();
        $ctx = $this->createLobbyWithPlayer($client);

        $client->request('POST', "/api/chat/lobby/{$ctx['lobbyId']}/messages", [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['playerId' => $ctx['playerId'], 'content' => '<script>alert("xss")</script>']));

        self::assertResponseStatusCodeSame(201);
        $msg = json_decode($client->getResponse()->getContent(), true);
        self::assertStringNotContainsString('<script>', $msg['content']);
        self::assertStringContainsString('&lt;script&gt;', $msg['content']);
    }
}
