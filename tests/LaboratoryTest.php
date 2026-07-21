<?php

declare(strict_types=1);

use Bulutklinik\Sdk\Token\InMemoryTokenStore;

it('laboratory->results omits the page segment when page is null', function () {
    [$client, $mock] = makeClient(
        fn () => jsonResponse(['resultType' => 0, 'data' => ['foundTestsCount' => 0, 'foundTests' => []]]),
        new InMemoryTokenStore('abc'),
    );

    $res = $client->laboratory->results();

    expect($res)->toBe(['foundTestsCount' => 0, 'foundTests' => []]);
    $req = $mock->requests[0];
    expect((string) $req->getUri())->toBe('https://apitest.bulutklinik.com/api/v3/patients/userLabTestList');
    expect($req->getMethod())->toBe('GET');
    expect($req->getHeaderLine('Authorization'))->toBe('Bearer abc');
});

it('laboratory->results appends the page segment when provided', function () {
    [$client, $mock] = makeClient(
        fn () => jsonResponse(['resultType' => 0, 'data' => ['foundTestsCount' => 0, 'foundTests' => []]]),
        new InMemoryTokenStore('abc'),
    );

    $client->laboratory->results(3);

    expect((string) $mock->requests[0]->getUri())
        ->toBe('https://apitest.bulutklinik.com/api/v3/patients/userLabTestList/3');
});

it('laboratory->resultDetail interpolates a string testId verbatim (plain and -lab)', function () {
    [$client, $mock] = makeClient(
        fn () => jsonResponse(['resultType' => 0, 'data' => ['id' => '123', 'test_name' => 'Hemogram']]),
        new InMemoryTokenStore('abc'),
    );

    $client->laboratory->resultDetail('123');
    $client->laboratory->resultDetail('4821-lab');

    expect((string) $mock->requests[0]->getUri())
        ->toBe('https://apitest.bulutklinik.com/api/v3/patients/userLabTestDetail/123');
    expect($mock->requests[0]->getMethod())->toBe('GET');
    expect((string) $mock->requests[1]->getUri())
        ->toBe('https://apitest.bulutklinik.com/api/v3/patients/userLabTestDetail/4821-lab');
});

it('laboratory->catalog GETs /patients/allLaboratoryTests with a bearer token', function () {
    [$client, $mock] = makeClient(
        fn () => jsonResponse(['resultType' => 0, 'data' => ['test_groups' => []]]),
        new InMemoryTokenStore('abc'),
    );

    $client->laboratory->catalog();

    $req = $mock->requests[0];
    expect((string) $req->getUri())->toBe('https://apitest.bulutklinik.com/api/v3/patients/allLaboratoryTests');
    expect($req->getMethod())->toBe('GET');
    expect($req->getHeaderLine('Authorization'))->toBe('Bearer abc');
});

it('laboratory->catalogDetail GETs one group by id', function () {
    [$client, $mock] = makeClient(
        fn () => jsonResponse(['resultType' => 0, 'data' => ['id' => 7, 'name' => 'Grup']]),
        new InMemoryTokenStore('abc'),
    );

    $client->laboratory->catalogDetail(7);

    expect((string) $mock->requests[0]->getUri())
        ->toBe('https://apitest.bulutklinik.com/api/v3/patients/laboratoryTestDetail/7');
});

it('laboratory->order POSTs the required body to /patients/addNewLaboratoryTest', function () {
    [$client, $mock] = makeClient(
        fn () => jsonResponse(['resultType' => 0, 'data' => ['preOrderId' => 55]]),
        new InMemoryTokenStore('abc'),
    );

    $res = $client->laboratory->order(101, 202, 303);

    expect($res)->toBe(['preOrderId' => 55]);
    $req = $mock->requests[0];
    expect((string) $req->getUri())->toBe('https://apitest.bulutklinik.com/api/v3/patients/addNewLaboratoryTest');
    expect($req->getMethod())->toBe('POST');
    expect($req->getHeaderLine('Authorization'))->toBe('Bearer abc');
    expect(json_decode((string) $req->getBody(), true))
        ->toBe(['testId' => 101, 'addressId' => 202, 'laboratoryId' => 303]);
});
