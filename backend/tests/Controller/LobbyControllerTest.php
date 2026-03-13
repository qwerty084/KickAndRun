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

        $client->request('POST', '/api/lobbies/' . $created['id'] . '/start');
        self::assertResponseStatusCodeSame(201);

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('gameSessionId', $data);
        self::assertSame('in_game', $data['lobby']['status']);
    }
}
