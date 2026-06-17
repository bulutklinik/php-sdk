<?php

declare(strict_types=1);

use Bulutklinik\Sdk\Token\InMemoryTokenStore;

it('seeds, sets and clears tokens', function () {
    $store = new InMemoryTokenStore('a', 'r');
    expect($store->getAccessToken())->toBe('a');
    expect($store->getRefreshToken())->toBe('r');

    $store->setTokens('a2', 'r2');
    expect($store->getAccessToken())->toBe('a2');
    expect($store->getRefreshToken())->toBe('r2');

    $store->clear();
    expect($store->getAccessToken())->toBeNull();
    expect($store->getRefreshToken())->toBeNull();
});

it('defaults to null when unseeded', function () {
    $store = new InMemoryTokenStore();
    expect($store->getAccessToken())->toBeNull();
    expect($store->getRefreshToken())->toBeNull();
});
