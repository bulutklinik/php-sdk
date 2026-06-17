<?php

declare(strict_types=1);

use Bulutklinik\Sdk\Token\InMemoryTokenStore;

it('connect stores tokens and fills client id/secret from config', function () {
    $store = new InMemoryTokenStore();
    [$client, $mock] = makeClient(
        fn () => jsonResponse(['resultType' => 0, 'data' => ['access_token' => 't', 'refresh_token' => 'r', 'password_policy' => []]]),
        $store,
        'c',
        's',
    );

    $result = $client->auth->connect('u', 'p', 'email');

    expect($result->twoFactorRequired)->toBeFalse();
    expect($store->getAccessToken())->toBe('t');
    expect($store->getRefreshToken())->toBe('r');

    $body = json_decode((string) $mock->requests[0]->getBody(), true);
    expect($body['apiClientId'])->toBe('c');
    expect($body['apiSecretKey'])->toBe('s');
    expect($body['loginMode'])->toBe('email');
});

it('connect surfaces a 2FA challenge instead of throwing', function () {
    [$client] = makeClient(
        fn () => jsonResponse(['resultType' => 0, 'data' => ['response' => 'BLOB']]),
        new InMemoryTokenStore(),
        'c',
        's',
    );

    $result = $client->auth->connect('u', 'p', 'email');

    expect($result->twoFactorRequired)->toBeTrue();
    expect($result->twoFactorResponse)->toBe('BLOB');
});

it('disconnect clears the store even when the request fails', function () {
    $store = new InMemoryTokenStore('a', 'r');
    [$client] = makeClient(fn () => jsonResponse(['resultType' => 1, 'errorMessage' => 'fail'], 500), $store);

    $threw = false;
    try {
        $client->auth->disconnect();
    } catch (\Throwable) {
        $threw = true;
    }

    expect($threw)->toBeTrue();
    expect($store->getAccessToken())->toBeNull();
});
