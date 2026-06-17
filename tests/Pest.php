<?php

declare(strict_types=1);

use Bulutklinik\Sdk\BulutklinikClient;
use Bulutklinik\Sdk\ClientConfig;
use Bulutklinik\Sdk\Environment;
use Bulutklinik\Sdk\Tests\MockClient;
use Bulutklinik\Sdk\Token\InMemoryTokenStore;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

/**
 * Build a client wired to a mock PSR-18 client (test environment).
 *
 * @param callable(RequestInterface): Response $handler
 *
 * @return array{0: BulutklinikClient, 1: MockClient, 2: InMemoryTokenStore}
 */
function makeClient(
    callable $handler,
    ?InMemoryTokenStore $store = null,
    ?string $clientId = null,
    ?string $clientSecret = null,
    ?string $partnerToken = null,
): array {
    $mock = new MockClient($handler);
    $factory = new HttpFactory();
    $store ??= new InMemoryTokenStore();

    $client = new BulutklinikClient(new ClientConfig(
        environment: Environment::Test,
        clientId: $clientId,
        clientSecret: $clientSecret,
        partnerToken: $partnerToken,
        tokenStore: $store,
        httpClient: $mock,
        requestFactory: $factory,
        streamFactory: $factory,
    ));

    return [$client, $mock, $store];
}

/**
 * @param array<string, mixed> $body
 * @param array<string, string> $headers
 */
function jsonResponse(array $body, int $status = 200, array $headers = []): Response
{
    return new Response(
        $status,
        array_merge(['Content-Type' => 'application/json'], $headers),
        json_encode($body, JSON_THROW_ON_ERROR),
    );
}
