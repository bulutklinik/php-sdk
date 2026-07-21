<?php

declare(strict_types=1);

use Bulutklinik\Sdk\Token\InMemoryTokenStore;

it('diets->list omits the page segment when page is null', function () {
    [$client, $mock] = makeClient(
        fn () => jsonResponse(['resultType' => 0, 'data' => ['foundDietsCount' => 0, 'foundDiets' => []]]),
        new InMemoryTokenStore('abc'),
    );

    $res = $client->diets->list();

    expect($res)->toBe(['foundDietsCount' => 0, 'foundDiets' => []]);
    $req = $mock->requests[0];
    expect((string) $req->getUri())->toBe('https://apitest.bulutklinik.com/api/v3/patients/dietLists');
    expect($req->getMethod())->toBe('GET');
    expect($req->getHeaderLine('Authorization'))->toBe('Bearer abc');
});

it('diets->list appends the page segment when provided', function () {
    [$client, $mock] = makeClient(
        fn () => jsonResponse(['resultType' => 0, 'data' => ['foundDietsCount' => 0, 'foundDiets' => []]]),
        new InMemoryTokenStore('abc'),
    );

    $client->diets->list(2);

    expect((string) $mock->requests[0]->getUri())
        ->toBe('https://apitest.bulutklinik.com/api/v3/patients/dietLists/2');
});

it('diets->detail GETs /patients/diet/{listId} with a bearer token', function () {
    [$client, $mock] = makeClient(
        fn () => jsonResponse(['resultType' => 0, 'data' => [['time' => '08:00', 'meals' => []]]]),
        new InMemoryTokenStore('abc'),
    );

    $client->diets->detail(42);

    $req = $mock->requests[0];
    expect((string) $req->getUri())->toBe('https://apitest.bulutklinik.com/api/v3/patients/diet/42');
    expect($req->getMethod())->toBe('GET');
    expect($req->getHeaderLine('Authorization'))->toBe('Bearer abc');
});
