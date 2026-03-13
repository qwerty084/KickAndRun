<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class HealthControllerTest extends WebTestCase
{
    public function testHealthEndpointReturnsOk(): void
    {
        $client = static::createClient();
        $client->request('GET', '/health');

        self::assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('ok', $data['status']);
        self::assertArrayHasKey('timestamp', $data);
    }
}
