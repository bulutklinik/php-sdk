<?php

declare(strict_types=1);

use Bulutklinik\Sdk\Token\InMemoryTokenStore;

const PARTNER_BASE = 'https://apitest.bulutklinik.com/api/v3';

/**
 * A client with BOTH a patient access token and a partner token configured.
 * Partner calls must ignore the patient one.
 *
 * @return array{0: \Bulutklinik\Sdk\BulutklinikClient, 1: \Bulutklinik\Sdk\Tests\MockClient}
 */
function partnerClient(): array
{
    [$client, $mock] = makeClient(
        fn () => jsonResponse(['resultType' => 0, 'data' => null]),
        new InMemoryTokenStore('PATIENT'),
        partnerToken: 'PT',
    );

    return [$client, $mock];
}

function bodyOfRequest(\Psr\Http\Message\RequestInterface $r): array
{
    return json_decode((string) $r->getBody(), true, 512, JSON_THROW_ON_ERROR) ?? [];
}

it('sends the partner token, never the patient access token', function () {
    [$client, $mock] = partnerClient();

    $client->partner->doctors->branches();
    $client->partner->measures->last(['identityNumber' => '12345678901']);

    foreach ($mock->requests as $request) {
        expect($request->getHeaderLine('Authorization'))->toBe('Bearer PT');
    }
});

it('leaves the patient surface on the patient token', function () {
    [$client, $mock] = partnerClient();

    $client->doctors->branches();

    expect($mock->requests[0]->getHeaderLine('Authorization'))->toBe('Bearer PATIENT');
    expect((string) $mock->requests[0]->getUri())->toBe(PARTNER_BASE.'/patients/allBranches');
});

it('builds discovery paths', function () {
    [$client, $mock] = partnerClient();

    $client->partner->doctors->locations();
    $client->partner->doctors->detail(42);
    $client->partner->laboratory->catalog();
    $client->partner->laboratory->catalogDetail(18246);
    $client->partner->slots->schedule(7, '2026-08-01');

    $urls = array_map(fn ($r) => (string) $r->getUri(), $mock->requests);

    expect($urls)->toBe([
        PARTNER_BASE.'/outher/locations',
        PARTNER_BASE.'/outher/doctorInfos/42',
        PARTNER_BASE.'/outher/laboratoryCatalog',
        PARTNER_BASE.'/outher/laboratoryCatalog/18246',
        PARTNER_BASE.'/outher/doctorSlots',
    ]);
});

it('carries the patient reference in the body, not the path', function () {
    [$client, $mock] = partnerClient();
    $patient = ['identityNumber' => '12345678901'];

    $client->partner->diets->list($patient, 2);
    $client->partner->measures->list($patient, 'glucose', 1, 0);
    $client->partner->laboratory->results($patient);

    // The identity number must never leak into a URL — it would land in access
    // logs, proxy logs and error breadcrumbs.
    foreach ($mock->requests as $request) {
        expect((string) $request->getUri())->not->toContain('12345678901');
    }

    expect((string) $mock->requests[0]->getUri())->toBe(PARTNER_BASE.'/outher/dietLists');
    expect(bodyOfRequest($mock->requests[0]))->toBe(['patient' => $patient, 'currentPage' => 2]);

    expect((string) $mock->requests[1]->getUri())->toBe(PARTNER_BASE.'/outher/measuresList/glucose');
    expect(bodyOfRequest($mock->requests[1]))
        ->toBe(['patient' => $patient, 'currentPage' => 1, 'glucoseType' => 0]);
});

it('passes a lab result id through unchanged, suffix and all', function () {
    [$client, $mock] = partnerClient();
    $patient = ['identityNumber' => '12345678901'];

    $client->partner->laboratory->resultDetail($patient, '1234-lab');
    expect(bodyOfRequest($mock->requests[0])['testId'])->toBe('1234-lab');

    $client->partner->laboratory->resultDetail($patient, 1234);
    expect(bodyOfRequest($mock->requests[1])['testId'])->toBe('1234');
});

it('uses the right verb and path for the measure write endpoints', function () {
    [$client, $mock] = partnerClient();
    $writePatient = ['name' => 'Ada', 'surname' => 'Lovelace', 'phoneNumber' => '+905551112233'];
    $ref = ['identityNumber' => '12345678901'];

    $client->partner->measures->addList($writePatient, [
        ['type' => 'pulse', 'date_time' => '2026-06-17 09:00', 'pulse' => 72],
    ]);
    $client->partner->measures->add($writePatient, 'tension', [
        'date_time' => '2026-06-17 09:00', 'hypertension' => 120, 'hypotension' => 80,
    ]);
    $client->partner->measures->update($ref, 'tension', 9, [
        'date_time' => '2026-06-17 10:00', 'hypertension' => 125, 'hypotension' => 85,
    ]);
    $client->partner->measures->delete($ref, 'tension', 9);

    $seen = array_map(fn ($r) => [$r->getMethod(), (string) $r->getUri()], $mock->requests);

    expect($seen)->toBe([
        ['POST', PARTNER_BASE.'/outher/measures'],
        ['POST', PARTNER_BASE.'/outher/measure/tension'],
        ['PUT', PARTNER_BASE.'/outher/measure/tension'],
        ['DELETE', PARTNER_BASE.'/outher/measure/tension'],
    ]);

    // Measure fields are flattened alongside `patient`, matching the server shape.
    expect(bodyOfRequest($mock->requests[1]))->toBe([
        'patient' => $writePatient,
        'date_time' => '2026-06-17 09:00',
        'hypertension' => 120,
        'hypotension' => 80,
    ]);
    expect(bodyOfRequest($mock->requests[3]))->toBe(['patient' => $ref, 'id' => 9]);
});

it('books through the partner appointment lifecycle', function () {
    [$client, $mock] = partnerClient();
    $user = ['name' => 'Ada', 'surname' => 'Lovelace', 'phoneNumber' => '+905551112233'];

    $client->partner->appointments->reserve(1, 2, $user);
    $client->partner->appointments->create('h', 5);
    $client->partner->appointments->list('+905551112233');
    $client->partner->appointments->cancelWithoutSlot(['hash' => 'h', 'outherProcessId' => 5]);

    $seen = array_map(fn ($r) => [$r->getMethod(), (string) $r->getUri()], $mock->requests);

    expect($seen)->toBe([
        ['POST', PARTNER_BASE.'/outher/reservation'],
        ['POST', PARTNER_BASE.'/outher/appointment'],
        ['POST', PARTNER_BASE.'/outher/appointments'],
        ['DELETE', PARTNER_BASE.'/outher/appointmentWithoutSlot'],
    ]);
    expect(bodyOfRequest($mock->requests[0]))->toBe(['slotId' => 1, 'doctorId' => 2, 'user' => $user]);
});
