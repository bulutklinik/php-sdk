<?php

declare(strict_types=1);

use Psr\Http\Message\RequestInterface;

const PATIENT_REF = ['identityNumber' => '12345678901'];
const WRITE_PATIENT = ['name' => 'Ada', 'surname' => 'Lovelace', 'phoneNumber' => '+905551112233'];

/** @return array{0: \Bulutklinik\Sdk\BulutklinikClient, 1: \Bulutklinik\Sdk\Tests\MockClient} */
function partnerClient(): array
{
    [$client, $mock] = makeClient(fn () => jsonResponse(['resultType' => 0, 'data' => null]));

    return [$client, $mock];
}

/** @return list<string> */
function urls(\Bulutklinik\Sdk\Tests\MockClient $mock): array
{
    return array_map(fn (RequestInterface $r) => (string) $r->getUri(), $mock->requests);
}

/** @return list<array{0: string, 1: string}> */
function verbsAndUrls(\Bulutklinik\Sdk\Tests\MockClient $mock): array
{
    return array_map(fn (RequestInterface $r) => [$r->getMethod(), (string) $r->getUri()], $mock->requests);
}

it('builds the discovery paths', function () {
    [$client, $mock] = partnerClient();

    $client->doctors->branches();
    $client->doctors->locations();
    $client->doctors->detail(42);
    $client->laboratory->catalog();
    $client->laboratory->catalogDetail(18246);
    $client->slots->schedule(7, '2026-08-01');

    expect(urls($mock))->toBe([
        TEST_BASE . '/outher/branches',
        TEST_BASE . '/outher/locations',
        TEST_BASE . '/outher/doctorInfos/42',
        TEST_BASE . '/outher/laboratoryCatalog',
        TEST_BASE . '/outher/laboratoryCatalog/18246',
        TEST_BASE . '/outher/doctorSlots',
    ]);
});

it('carries the patient reference in the body, not the path', function () {
    [$client, $mock] = partnerClient();

    $client->diets->list(PATIENT_REF, 2);
    $client->measures->list(PATIENT_REF, 'glucose', 1, 0);
    $client->laboratory->results(PATIENT_REF);

    // The identity number must never leak into a URL — it would land in access
    // logs, proxy logs and error breadcrumbs.
    foreach (urls($mock) as $url) {
        expect($url)->not->toContain('12345678901');
    }

    expect(urls($mock)[0])->toBe(TEST_BASE . '/outher/dietLists');
    expect(bodyOf($mock->requests[0]))->toBe(['patient' => PATIENT_REF, 'currentPage' => 2]);

    expect(urls($mock)[1])->toBe(TEST_BASE . '/outher/measuresList/glucose');
    expect(bodyOf($mock->requests[1]))
        ->toBe(['patient' => PATIENT_REF, 'currentPage' => 1, 'glucoseType' => 0]);

    expect(urls($mock)[2])->toBe(TEST_BASE . '/outher/laboratoryResults');
});

it('builds the measures graph path from type and period', function () {
    [$client, $mock] = partnerClient();

    $client->measures->graph(['phoneNumber' => '+905551112233'], 'weight', 3);

    expect(urls($mock)[0])->toBe(TEST_BASE . '/outher/measuresGraph/weight/3');
});

it('passes a lab result id through unchanged, suffix and all', function () {
    [$client, $mock] = partnerClient();

    $client->laboratory->resultDetail(PATIENT_REF, '1234-lab');
    $client->laboratory->resultDetail(PATIENT_REF, 1234);

    expect(bodyOf($mock->requests[0])['testId'])->toBe('1234-lab');
    expect(bodyOf($mock->requests[1])['testId'])->toBe('1234');
});

it('uses the right verb and path for the measure write endpoints', function () {
    [$client, $mock] = partnerClient();

    $client->measures->addList(WRITE_PATIENT, [
        ['type' => 'pulse', 'date_time' => '2026-06-17 09:00', 'pulse' => 72],
    ]);
    $client->measures->add(WRITE_PATIENT, 'tension', [
        'date_time' => '2026-06-17 09:00',
        'hypertension' => 120,
        'hypotension' => 80,
    ]);
    $client->measures->update(PATIENT_REF, 'tension', 9, [
        'date_time' => '2026-06-17 10:00',
        'hypertension' => 125,
    ]);
    $client->measures->delete(PATIENT_REF, 'tension', 9);

    expect(verbsAndUrls($mock))->toBe([
        ['POST', TEST_BASE . '/outher/measures'],
        ['POST', TEST_BASE . '/outher/measure/tension'],
        ['PUT', TEST_BASE . '/outher/measure/tension'],
        ['DELETE', TEST_BASE . '/outher/measure/tension'],
    ]);

    // Measure fields are flattened alongside `patient`, matching the server shape.
    expect(bodyOf($mock->requests[1]))->toBe([
        'patient' => WRITE_PATIENT,
        'date_time' => '2026-06-17 09:00',
        'hypertension' => 120,
        'hypotension' => 80,
    ]);
    expect(bodyOf($mock->requests[3]))->toBe(['patient' => PATIENT_REF, 'id' => 9]);
});

it('books through the appointment lifecycle', function () {
    [$client, $mock] = partnerClient();

    $client->appointments->checkDoctor(2, 0);
    $client->appointments->reserveWithoutAgreement(1, 2, WRITE_PATIENT);
    $client->appointments->create('h', 5);
    $client->appointments->list('+905551112233');
    $client->appointments->cancelWithoutSlot(['hash' => 'h', 'outherProcessId' => 5]);

    expect(verbsAndUrls($mock))->toBe([
        ['POST', TEST_BASE . '/outher/checkDoctor'],
        ['POST', TEST_BASE . '/outher/reservationWithoutAgreement'],
        ['POST', TEST_BASE . '/outher/appointment'],
        ['POST', TEST_BASE . '/outher/appointments'],
        ['DELETE', TEST_BASE . '/outher/appointmentWithoutSlot'],
    ]);
    expect(bodyOf($mock->requests[1]))->toBe(['slotId' => 1, 'doctorId' => 2, 'user' => WRITE_PATIENT]);
});

it('sends the hand-off reservation and the instant one to their own paths', function () {
    [$client, $mock] = partnerClient();

    $client->appointments->reserve(1, 2, WRITE_PATIENT);
    $client->appointments->instantReserve(WRITE_PATIENT);
    $client->appointments->createWithoutSlot(2, '2026-08-01 09:00', '2026-08-01 09:30', WRITE_PATIENT);

    expect(urls($mock))->toBe([
        TEST_BASE . '/outher/reservation',
        TEST_BASE . '/outher/instantReservation',
        TEST_BASE . '/outher/appointmentWithoutSlot',
    ]);
    expect(bodyOf($mock->requests[1]))->toBe(['user' => WRITE_PATIENT]);
});

it('keeps the legacy teusan contract flat', function () {
    [$client, $mock] = partnerClient();

    $client->measures->healthInformation('12345678901', '+905551112233', [
        ['type' => 'pulse', 'date_time' => '2026-06-17 09:00', 'pulse' => 72],
    ]);

    expect(urls($mock)[0])->toBe(TEST_BASE . '/outher/healthInformation');
    // No `patient` wrapper here — this endpoint predates that contract.
    expect(bodyOf($mock->requests[0]))->toBe([
        'identity' => '12345678901',
        'phoneNumber' => '+905551112233',
        'data' => [['type' => 'pulse', 'date_time' => '2026-06-17 09:00', 'pulse' => 72]],
    ]);
});
