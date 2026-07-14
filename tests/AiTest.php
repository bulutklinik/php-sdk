<?php

declare(strict_types=1);

use Bulutklinik\Sdk\Token\InMemoryTokenStore;

it('skin->analyze posts images to /patients/imageCheck with a bearer token', function () {
    [$client, $mock] = makeClient(
        fn () => jsonResponse(['resultType' => 0, 'data' => ['status' => [
            ['id' => 1, 'label' => 'nevus', 'comment' => '…', 'case_detail' => 'blob'],
        ]]]),
        new InMemoryTokenStore('abc'),
    );

    $res = $client->skin->analyze([['image' => 'BASE64', 'branch_id' => 42]]);

    expect($res['status'][0]['label'])->toBe('nevus');
    $req = $mock->requests[0];
    expect((string) $req->getUri())->toBe('https://apitest.bulutklinik.com/api/v3/patients/imageCheck');
    expect($req->getMethod())->toBe('POST');
    expect($req->getHeaderLine('Authorization'))->toBe('Bearer abc');
    expect(json_decode((string) $req->getBody(), true))
        ->toBe(['images' => [['image' => 'BASE64', 'branch_id' => 42]]]);
});

it('meals->analyze maps parameters to the snake_case body', function () {
    [$client, $mock] = makeClient(
        fn () => jsonResponse(['resultType' => 0, 'data' => ['status' => ['comment' => '{}']]]),
        new InMemoryTokenStore('abc'),
    );

    $client->meals->analyze(
        image: 'BASE64',
        portionSize: 'custom',
        mealType: 'lunch',
        portionGrams: 300,
        note: 'az yağlı',
    );

    $req = $mock->requests[0];
    expect((string) $req->getUri())->toBe('https://apitest.bulutklinik.com/api/v3/patients/imageAnalyzeMeal');
    expect(json_decode((string) $req->getBody(), true))->toBe([
        'image' => 'BASE64',
        'portion_size' => 'custom',
        'meal_type' => 'lunch',
        'portion_grams' => 300,
        'note' => 'az yağlı',
    ]);
});

it('meals->analyze omits optional fields when not provided', function () {
    [$client, $mock] = makeClient(
        fn () => jsonResponse(['resultType' => 0, 'data' => ['status' => ['comment' => '{}']]]),
        new InMemoryTokenStore('abc'),
    );

    $client->meals->analyze(image: 'BASE64', portionSize: 'medium', mealType: 'snack');

    expect(json_decode((string) $mock->requests[0]->getBody(), true))->toBe([
        'image' => 'BASE64',
        'portion_size' => 'medium',
        'meal_type' => 'snack',
    ]);
});
