<?php

declare(strict_types=1);

use Bulutklinik\Sdk\BulutklinikClient;
use Bulutklinik\Sdk\ClientConfig;
use Bulutklinik\Sdk\Environment;
use Bulutklinik\Sdk\Exception\AuthenticationException;
use Bulutklinik\Sdk\Tests\MockClient;
use Bulutklinik\Sdk\Token\InMemoryTokenStore;
use Bulutklinik\Sdk\Token\TokenStore;
use GuzzleHttp\Psr7\HttpFactory;
use Psr\Http\Message\RequestInterface;

const TOKENS = ['access_token' => 'AT', 'refresh_token' => 'RT'];
const AUTH_REF = ['identityNumber' => '12345678901'];

/**
 * A client carrying portal client credentials.
 *
 * @param callable(RequestInterface): \GuzzleHttp\Psr7\Response $handler
 *
 * @return array{0: BulutklinikClient, 1: MockClient}
 */
function authClient(callable $handler, ?TokenStore $store = null): array
{
    $mock = new MockClient($handler);
    $factory = new HttpFactory();

    $client = new BulutklinikClient(new ClientConfig(
        environment: Environment::Test,
        clientId: 'cid',
        clientSecret: 'csecret',
        tokenStore: $store ?? new InMemoryTokenStore(),
        httpClient: $mock,
        requestFactory: $factory,
        streamFactory: $factory,
    ));

    return [$client, $mock];
}

it('connect posts the portal credentials and stores both tokens', function () {
    [$client, $mock] = authClient(fn () => jsonResponse(['resultType' => 0, 'data' => TOKENS]));

    $result = $client->auth->connect('svc@app.bulutklinik', 'hunter2');

    expect($result->twoFactorRequired)->toBeFalse();
    expect((string) $mock->requests[0]->getUri())->toBe(TEST_BASE . '/general/connectApi');
    // The login call is public — it is what produces the credential.
    expect($mock->requests[0]->getHeaderLine('Authorization'))->toBe('');
    expect(bodyOf($mock->requests[0]))->toBe([
        'apiClientId' => 'cid',
        'apiSecretKey' => 'csecret',
        'apiUserName' => 'svc@app.bulutklinik',
        'apiUserPassword' => 'hunter2',
        'loginMode' => 'email',
    ]);
    expect($client->tokenStore->getToken())->toBe('AT');
});

it('surfaces the 2FA challenge as a result, not an exception', function () {
    [$client] = authClient(fn () => jsonResponse(['resultType' => 0, 'data' => ['response' => 'BLOB']]));

    $result = $client->auth->connect('svc', 'p');

    expect($result->twoFactorRequired)->toBeTrue();
    expect($result->twoFactorResponse)->toBe('BLOB');
    expect($client->tokenStore->getToken())->toBeNull();
});

it('refuses to connect without client credentials', function () {
    [$client] = makeClient(fn () => jsonResponse(['resultType' => 0, 'data' => TOKENS]));
    $client->auth->connect('svc', 'p');
})->throws(InvalidArgumentException::class, 'clientId and clientSecret are required');

it('refreshes once on 401 then retries with the new token', function () {
    $dataCalls = 0;
    $store = new InMemoryTokenStore('AT', 'RT');
    [$client, $mock] = authClient(function (RequestInterface $req) use (&$dataCalls) {
        if (str_contains((string) $req->getUri(), '/general/refreshApi')) {
            return jsonResponse(['resultType' => 0, 'data' => ['access_token' => 'AT2', 'refresh_token' => 'RT2']]);
        }
        ++$dataCalls;

        return $dataCalls === 1
            ? jsonResponse(['resultType' => 4], 401)
            : jsonResponse(['resultType' => 0, 'data' => ['ok' => true]]);
    }, $store);

    expect($client->measures->last(AUTH_REF))->toBe(['ok' => true]);
    expect($store->getToken())->toBe('AT2');
    expect($store->getRefreshToken())->toBe('RT2');
    expect(bodyOf($mock->requests[1]))->toBe([
        'refreshToken' => 'RT',
        'clientId' => 'cid',
        'clientSecretKey' => 'csecret',
    ]);
});

it('retries at most once and clears the store when the refresh fails', function () {
    $refreshCalls = 0;
    $store = new InMemoryTokenStore('AT', 'RT');
    [$client] = authClient(function (RequestInterface $req) use (&$refreshCalls) {
        if (str_contains((string) $req->getUri(), '/general/refreshApi')) {
            ++$refreshCalls;

            return jsonResponse(['resultType' => 1], 401);
        }

        return jsonResponse(['resultType' => 4], 401);
    }, $store);

    $caught = null;
    try {
        $client->measures->last(AUTH_REF);
    } catch (AuthenticationException $e) {
        $caught = $e;
    }

    expect($caught)->toBeInstanceOf(AuthenticationException::class);
    expect($refreshCalls)->toBe(1);
    expect($store->getToken())->toBeNull();
});

it('does not attempt a refresh when no refresh token is held', function () {
    $refreshCalls = 0;
    $store = new InMemoryTokenStore('AT');
    [$client] = authClient(function (RequestInterface $req) use (&$refreshCalls) {
        if (str_contains((string) $req->getUri(), '/general/refreshApi')) {
            ++$refreshCalls;
        }

        return jsonResponse(['resultType' => 4], 401);
    }, $store);

    $caught = null;
    try {
        $client->doctors->branches();
    } catch (AuthenticationException $e) {
        $caught = $e;
    }

    expect($caught?->getMessage())->toContain('could not be refreshed');
    expect($refreshCalls)->toBe(0);
});

it('keeps the refresh token in memory when the store cannot persist it', function () {
    // A store written against spec 1.0.x: access token only.
    $legacy = new class () implements TokenStore {
        public ?string $token = null;

        public function getToken(): ?string
        {
            return $this->token;
        }

        public function setToken(?string $token): void
        {
            $this->token = $token;
        }

        public function clear(): void
        {
            $this->token = null;
        }
    };

    $dataCalls = 0;
    [$client] = authClient(function (RequestInterface $req) use (&$dataCalls) {
        $uri = (string) $req->getUri();
        if (str_contains($uri, '/general/connectApi')) {
            return jsonResponse(['resultType' => 0, 'data' => TOKENS]);
        }
        if (str_contains($uri, '/general/refreshApi')) {
            return jsonResponse(['resultType' => 0, 'data' => ['access_token' => 'AT2']]);
        }
        ++$dataCalls;

        return $dataCalls === 1
            ? jsonResponse(['resultType' => 4], 401)
            : jsonResponse(['resultType' => 0, 'data' => ['ok' => true]]);
    }, $legacy);

    $client->auth->connect('svc', 'p');

    expect($client->measures->last(AUTH_REF))->toBe(['ok' => true]);
    expect($legacy->token)->toBe('AT2');
});

it('disconnect revokes with an empty body and clears the store', function () {
    $store = new InMemoryTokenStore('AT', 'RT');
    [$client, $mock] = authClient(fn () => jsonResponse(['resultType' => 0, 'data' => null]), $store);

    $client->auth->disconnect();

    expect((string) $mock->requests[0]->getUri())->toBe(TEST_BASE . '/general/disconnectApi');
    expect($mock->requests[0]->getHeaderLine('Authorization'))->toBe('Bearer AT');
    // The device-cleanup fields are deliberately not sent: the server's `device`
    // mapping has no default branch.
    expect(bodyOf($mock->requests[0]))->toBe([]);
    expect($store->getToken())->toBeNull();
    expect($store->getRefreshToken())->toBeNull();
});
