<?php

declare(strict_types=1);

use Bulutklinik\Sdk\Exception\AuthenticationException;
use Bulutklinik\Sdk\Exception\NotFoundException;
use Bulutklinik\Sdk\Exception\RateLimitException;
use Bulutklinik\Sdk\Exception\TransportException;
use Bulutklinik\Sdk\Exception\ValidationException;
use Bulutklinik\Sdk\Token\InMemoryTokenStore;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestInterface;

it('unwraps data on success and sends bearer + lang headers', function () {
    [$client, $mock] = makeClient(
        fn () => jsonResponse(['resultType' => 0, 'data' => ['searchedDoctors' => []]]),
        new InMemoryTokenStore('abc'),
    );

    $res = $client->doctors->quickSearch('kardiyo');

    expect($res)->toBe(['searchedDoctors' => []]);
    $req = $mock->requests[0];
    expect((string) $req->getUri())->toBe('https://apitest.bulutklinik.com/api/v3/patients/quickSearch');
    expect($req->getHeaderLine('Authorization'))->toBe('Bearer abc');
    expect($req->getHeaderLine('lang'))->toBe('tr');
    expect(json_decode((string) $req->getBody(), true))
        ->toBe(['searchText' => 'kardiyo', 'listType' => null, 'location' => null]);
});

it('maps 422 to ValidationException', function () {
    [$client] = makeClient(
        fn () => jsonResponse(['resultType' => 1, 'errorType' => 'validation', 'errorMessage' => 'bad'], 422),
        new InMemoryTokenStore('a'),
    );
    $client->doctors->branches();
})->throws(ValidationException::class);

it('maps a numeric errorType 404 (live-found) to NotFoundException', function () {
    [$client] = makeClient(
        fn () => jsonResponse(['resultType' => 1, 'errorType' => 1, 'errorMessage' => 'Bilinmeyen bir hata.'], 404),
        new InMemoryTokenStore('a'),
    );
    $client->doctors->quickSearch('kardiyo');
})->throws(NotFoundException::class);

it('maps 429 to RateLimitException with retryAfter', function () {
    [$client] = makeClient(
        fn () => jsonResponse(['resultType' => 1], 429, ['Retry-After' => '30']),
        new InMemoryTokenStore('a'),
    );

    $caught = null;
    try {
        $client->doctors->branches();
    } catch (RateLimitException $e) {
        $caught = $e;
    }

    expect($caught)->toBeInstanceOf(RateLimitException::class);
    expect($caught->context->retryAfter)->toBe(30);
});

it('refreshes once on 401 then retries with the new token', function () {
    $dataCalls = 0;
    $store = new InMemoryTokenStore('old', 'r');
    [$client, $mock] = makeClient(function (RequestInterface $req) use (&$dataCalls) {
        if (str_contains((string) $req->getUri(), '/general/refreshApi')) {
            return jsonResponse(['resultType' => 0, 'data' => ['access_token' => 'new', 'refresh_token' => 'newr']]);
        }
        ++$dataCalls;

        return $dataCalls === 1
            ? jsonResponse(['resultType' => 4], 401)
            : jsonResponse(['resultType' => 0, 'data' => ['ok' => true]]);
    }, $store, 'c', 's');

    $res = $client->measures->last();

    expect($res)->toBe(['ok' => true]);
    expect($store->getAccessToken())->toBe('new');
    $last = $mock->requests[count($mock->requests) - 1];
    expect($last->getHeaderLine('Authorization'))->toBe('Bearer new');
});

it('clears the store and throws on logout (resultType 2)', function () {
    $store = new InMemoryTokenStore('a', 'r');
    [$client] = makeClient(fn () => jsonResponse(['resultType' => 2, 'errorMessage' => 'logged out']), $store);

    $caught = null;
    try {
        $client->measures->last();
    } catch (AuthenticationException $e) {
        $caught = $e;
    }

    expect($caught)->toBeInstanceOf(AuthenticationException::class);
    expect($store->getAccessToken())->toBeNull();
});

it('wraps network failures in TransportException', function () {
    [$client] = makeClient(function (): never {
        throw new class ('boom') extends RuntimeException implements ClientExceptionInterface {};
    }, new InMemoryTokenStore('a'));
    $client->doctors->branches();
})->throws(TransportException::class);

it('builds the measure list path and uses the partner token', function () {
    [$client, $mock] = makeClient(
        fn () => jsonResponse(['resultType' => 0, 'data' => null]),
        new InMemoryTokenStore('a'),
        partnerToken: 'PT',
    );

    $client->measures->list('glucose', 1, 0);
    expect((string) $mock->requests[0]->getUri())
        ->toBe('https://apitest.bulutklinik.com/api/v3/patients/userMeasuresList/glucose/1/0');

    $client->measures->partnerHealthInformation(null, '5551112233', [
        ['type' => 'pulse', 'date_time' => '2026-06-17 09:00', 'pulse' => 72],
    ]);
    $last = $mock->requests[count($mock->requests) - 1];
    expect($last->getHeaderLine('Authorization'))->toBe('Bearer PT');
});
