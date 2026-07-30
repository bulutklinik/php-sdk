<?php

declare(strict_types=1);

use Bulutklinik\Sdk\Token\InMemoryTokenStore;

it('seeds, sets and clears the partner token', function () {
    $store = new InMemoryTokenStore('a');
    expect($store->getToken())->toBe('a');

    $store->setToken('b');
    expect($store->getToken())->toBe('b');

    $store->clear();
    expect($store->getToken())->toBeNull();
});

it('defaults to null when unseeded', function () {
    expect((new InMemoryTokenStore())->getToken())->toBeNull();
});

it('accepts an explicit null unset', function () {
    $store = new InMemoryTokenStore('a');
    $store->setToken(null);
    expect($store->getToken())->toBeNull();
});
