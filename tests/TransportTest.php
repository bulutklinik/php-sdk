<?php

declare(strict_types=1);

use Bulutklinik\Sdk\ApiVersion;
use Bulutklinik\Sdk\BulutklinikClient;
use Bulutklinik\Sdk\ClientConfig;
use Bulutklinik\Sdk\Exception\AuthenticationException;
use Bulutklinik\Sdk\Exception\AuthorizationException;
use Bulutklinik\Sdk\Exception\NotFoundException;
use Bulutklinik\Sdk\Exception\RateLimitException;
use Bulutklinik\Sdk\Exception\TransportException;
use Bulutklinik\Sdk\Exception\ValidationException;
use Bulutklinik\Sdk\Token\InMemoryTokenStore;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestInterface;

const REF = ['identityNumber' => '12345678901'];

it('unwraps data on success and sends the partner token + lang header', function () {
    [$client, $mock] = makeClient(
        fn () => jsonResponse(['resultType' => 0, 'data' => ['foundDoctorsCount' => 0, 'foundDoctors' => []]]),
    );

    $res = $client->doctors->search(['withFreeText' => 'kardiyoloji'], 1, ['slot']);

    expect($res)->toBe(['foundDoctorsCount' => 0, 'foundDoctors' => []]);
    $req = $mock->requests[0];
    expect((string) $req->getUri())->toBe(TEST_BASE . '/outher/search');
    expect($req->getHeaderLine('Authorization'))->toBe('Bearer PT');
    expect($req->getHeaderLine('lang'))->toBe('tr');
    expect(bodyOf($req))->toBe([
        'searchParams' => ['withFreeText' => 'kardiyoloji'],
        'orderParams' => ['slot'],
        'currentPage' => 1,
    ]);
});

it('targets v4 when asked, without changing any path', function () {
    [$client, $mock] = makeClient(
        fn () => jsonResponse(['resultType' => 0, 'data' => null]),
        apiVersion: ApiVersion::V4,
    );

    $client->doctors->branches();

    expect((string) $mock->requests[0]->getUri())
        ->toBe('https://apitest.bulutklinik.com/api/v4/outher/branches');
});

it('refuses to dispatch without a token instead of sending an anonymous request', function () {
    $dispatched = 0;
    [$client, $mock] = makeClient(function () use (&$dispatched) {
        ++$dispatched;

        return jsonResponse(['resultType' => 0, 'data' => null]);
    }, new InMemoryTokenStore());

    $caught = null;
    try {
        $client->doctors->branches();
    } catch (AuthenticationException $e) {
        $caught = $e;
    }

    expect($caught)->toBeInstanceOf(AuthenticationException::class);
    expect($dispatched)->toBe(0);
    expect($mock->requests)->toBe([]);
});

it('rejects partnerToken and tokenStore together', function () {
    new ClientConfig(partnerToken: 'PT', tokenStore: new InMemoryTokenStore('OTHER'));
})->throws(InvalidArgumentException::class, 'not both');

it('seeds the default store from partnerToken', function () {
    $client = new BulutklinikClient(new ClientConfig(partnerToken: 'PT'));

    expect($client->tokenStore->getToken())->toBe('PT');
});

it('reads the token from the store on every call, so rotation takes effect', function () {
    $store = new InMemoryTokenStore('first');
    [$client, $mock] = makeClient(fn () => jsonResponse(['resultType' => 0, 'data' => null]), $store);

    $client->doctors->branches();
    $store->setToken('second');
    $client->doctors->branches();

    expect(array_map(fn (RequestInterface $r) => $r->getHeaderLine('Authorization'), $mock->requests))
        ->toBe(['Bearer first', 'Bearer second']);
});

it('request() escape hatch defaults to the partner token', function () {
    [$client, $mock] = makeClient(fn () => jsonResponse(['resultType' => 0, 'data' => ['ok' => true]]));

    $res = $client->request('GET', '/outher/customEndpoint');

    expect($res)->toBe(['ok' => true]);
    $req = $mock->requests[0];
    expect((string) $req->getUri())->toBe(TEST_BASE . '/outher/customEndpoint');
    expect($req->getMethod())->toBe('GET');
    expect($req->getHeaderLine('Authorization'))->toBe('Bearer PT');
});

it('request() can still reach a public endpoint', function () {
    [$client, $mock] = makeClient(fn () => jsonResponse(['resultType' => 0, 'data' => ['id' => 7]]));

    $res = $client->request('POST', '/general/somePublicEndpoint', 'public', ['foo' => 'bar']);

    expect($res)->toBe(['id' => 7]);
    $req = $mock->requests[0];
    expect($req->getMethod())->toBe('POST');
    expect($req->getHeaderLine('Authorization'))->toBe('');
    expect(bodyOf($req))->toBe(['foo' => 'bar']);
});

it('maps 422 to ValidationException', function () {
    [$client] = makeClient(
        fn () => jsonResponse(['resultType' => 1, 'errorType' => 'validation', 'errorMessage' => 'bad'], 422),
    );
    $client->doctors->branches();
})->throws(ValidationException::class);

it('maps 403 to AuthorizationException — wrong scope or no company on the token', function () {
    [$client] = makeClient(fn () => jsonResponse(['resultType' => 1], 403));
    $client->doctors->branches();
})->throws(AuthorizationException::class);

it('maps a numeric errorType 404 (live-found) to NotFoundException', function () {
    [$client] = makeClient(
        fn () => jsonResponse(['resultType' => 1, 'errorType' => 1, 'errorMessage' => 'Bilinmeyen bir hata.'], 404),
    );
    $client->doctors->branches();
})->throws(NotFoundException::class);

it('maps 429 to RateLimitException with retryAfter', function () {
    [$client] = makeClient(fn () => jsonResponse(['resultType' => 1], 429, ['Retry-After' => '30']));

    $caught = null;
    try {
        $client->doctors->branches();
    } catch (RateLimitException $e) {
        $caught = $e;
    }

    expect($caught)->toBeInstanceOf(RateLimitException::class);
    expect($caught->context->retryAfter)->toBe(30);
});

it('surfaces an expired token (resultType 4) without retrying', function () {
    $attempts = 0;
    $store = new InMemoryTokenStore('expired');
    [$client] = makeClient(function () use (&$attempts) {
        ++$attempts;

        return jsonResponse(['resultType' => 4, 'errorMessage' => 'You must log in.'], 401);
    }, $store);

    $caught = null;
    try {
        $client->measures->last(REF);
    } catch (AuthenticationException $e) {
        $caught = $e;
    }

    expect($caught)->toBeInstanceOf(AuthenticationException::class);
    expect($caught->getMessage())->toContain('cannot refresh it');
    expect($attempts)->toBe(1);
    // An expired token is kept: the caller may want to inspect it while
    // installing the replacement. Only a revoked one is cleared.
    expect($store->getToken())->toBe('expired');
});

it('clears the store and throws on logout (resultType 2)', function () {
    $store = new InMemoryTokenStore('revoked');
    [$client] = makeClient(fn () => jsonResponse(['resultType' => 2, 'errorMessage' => 'logged out']), $store);

    $caught = null;
    try {
        $client->measures->last(REF);
    } catch (AuthenticationException $e) {
        $caught = $e;
    }

    expect($caught)->toBeInstanceOf(AuthenticationException::class);
    expect($store->getToken())->toBeNull();
});

it('wraps network failures in TransportException', function () {
    [$client] = makeClient(function (): never {
        throw new class ('boom') extends RuntimeException implements ClientExceptionInterface {};
    });
    $client->doctors->branches();
})->throws(TransportException::class);
