<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AuthControllerTest extends WebTestCase
{
    public function testRegisterSuccess(): void
    {
        $client = static::createClient();
        $username = 'testuser_' . bin2hex(random_bytes(4));

        $client->request('POST', '/api/auth/register', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['username' => $username, 'password' => 'secret123']));

        self::assertResponseStatusCodeSame(201);

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame($username, $data['user']['username']);
        self::assertArrayHasKey('token', $data);
        self::assertNotEmpty($data['token']);
    }

    public function testRegisterDuplicateUsername(): void
    {
        $client = static::createClient();
        $username = 'dupuser_' . bin2hex(random_bytes(4));

        $client->request('POST', '/api/auth/register', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['username' => $username, 'password' => 'secret123']));
        self::assertResponseStatusCodeSame(201);

        $client->request('POST', '/api/auth/register', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['username' => $username, 'password' => 'secret456']));
        self::assertResponseStatusCodeSame(409);

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertStringContainsString('already taken', $data['error']);
    }

    public function testRegisterUsernameTooShort(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/auth/register', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['username' => 'ab', 'password' => 'secret123']));

        self::assertResponseStatusCodeSame(400);
    }

    public function testRegisterPasswordTooShort(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/auth/register', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['username' => 'validuser', 'password' => '12345']));

        self::assertResponseStatusCodeSame(400);
    }

    public function testLoginSuccess(): void
    {
        $client = static::createClient();
        $username = 'loginuser_' . bin2hex(random_bytes(4));

        // Register first
        $client->request('POST', '/api/auth/register', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['username' => $username, 'password' => 'secret123']));
        self::assertResponseStatusCodeSame(201);

        // Login
        $client->request('POST', '/api/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['username' => $username, 'password' => 'secret123']));
        self::assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame($username, $data['user']['username']);
        self::assertArrayHasKey('token', $data);
    }

    public function testLoginWrongPassword(): void
    {
        $client = static::createClient();
        $username = 'wrongpw_' . bin2hex(random_bytes(4));

        $client->request('POST', '/api/auth/register', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['username' => $username, 'password' => 'secret123']));
        self::assertResponseStatusCodeSame(201);

        $client->request('POST', '/api/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['username' => $username, 'password' => 'wrongpassword']));
        self::assertResponseStatusCodeSame(401);
    }

    public function testLoginNonexistentUser(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['username' => 'doesnotexist', 'password' => 'secret123']));
        self::assertResponseStatusCodeSame(401);
    }

    public function testMeWithToken(): void
    {
        $client = static::createClient();
        $username = 'meuser_' . bin2hex(random_bytes(4));

        // Register to get a token
        $client->request('POST', '/api/auth/register', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['username' => $username, 'password' => 'secret123']));
        $data = json_decode($client->getResponse()->getContent(), true);
        $token = $data['token'];

        // Use token to get /me
        $client->request('GET', '/api/auth/me', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);
        self::assertResponseIsSuccessful();

        $meData = json_decode($client->getResponse()->getContent(), true);
        self::assertSame($username, $meData['username']);
    }

    public function testMeWithoutToken(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/auth/me');
        self::assertResponseStatusCodeSame(401);
    }

    public function testExistingEndpointsStillWorkWithoutAuth(): void
    {
        $client = static::createClient();

        // Health check
        $client->request('GET', '/health');
        self::assertResponseIsSuccessful();

        // List lobbies
        $client->request('GET', '/api/lobbies');
        self::assertResponseIsSuccessful();

        // Create lobby (anonymous)
        $client->request('POST', '/api/lobbies', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['name' => 'Auth Test Lobby', 'hostName' => 'Guest']));
        self::assertResponseStatusCodeSame(201);
    }
}
