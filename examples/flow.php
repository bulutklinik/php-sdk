<?php

declare(strict_types=1);

/**
 * End-to-end partner example: check a doctor -> slots -> reserve, plus a
 * health-measures read/write round trip.
 *
 * Provide credentials via env: BK_PARTNER_TOKEN, BK_DOCTOR_ID, BK_PATIENT_PHONE,
 * BK_PATIENT_TCKN. Run: php examples/flow.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Bulutklinik\Sdk\BulutklinikClient;
use Bulutklinik\Sdk\ClientConfig;
use Bulutklinik\Sdk\Environment;

$client = new BulutklinikClient(new ClientConfig(
    environment: Environment::Test,
    partnerToken: getenv('BK_PARTNER_TOKEN') ?: null,
));

// 1. Discovery. Needs no patient data, so it is the fastest way to prove the
//    token and base URL are right.
$branches = $client->doctors->branches();
echo 'branches: ' . count($branches) . "\n";

$doctorId = (int) (getenv('BK_DOCTOR_ID') ?: '8282');
$bookable = $client->appointments->checkDoctor($doctorId, 0);
echo 'bookable through this integration: ' . json_encode($bookable, JSON_UNESCAPED_UNICODE) . "\n";

// 2. Availability. `slotId` from here feeds the reservation.
$schedule = $client->slots->schedule($doctorId, '2026-08-01');
echo 'slots: ' . json_encode($schedule, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

// 3. Booking. The patient is named inline — there is no session.
//
//    `reserve()` returns a `url` to hand to the patient for agreements and
//    payment. `reserveWithoutAgreement()` returns a `hash` for you to confirm
//    yourself, as below.
$user = [
    'name' => 'Ada',
    'surname' => 'Lovelace',
    'phoneNumber' => getenv('BK_PATIENT_PHONE') ?: '+90 5551112233',
    'identityNumber' => getenv('BK_PATIENT_TCKN') ?: null,
];

$firstDay = is_array($schedule) ? (reset($schedule) ?: []) : [];
if ($firstDay !== []) {
    $held = $client->appointments->reserveWithoutAgreement($firstDay[0]['slotId'], $doctorId, $user);
    echo 'held until ' . ($held['reservationExpired'] ?? '?') . "\n";
    // `outherProcessId` arrives alongside `hash` in the same response:
    // $client->appointments->create($held['hash'], $outherProcessId);
}

// 4. Measurements. Writes create the patient in your company if absent; reads
//    only ever look inside your company.
$client->measures->addList($user, [
    ['type' => 'tension', 'date_time' => '2026-06-17 09:30', 'hypertension' => 120, 'hypotension' => 80],
    ['type' => 'pulse', 'date_time' => '2026-06-17 09:31', 'pulse' => 72],
]);
echo "measures submitted\n";

$latest = $client->measures->last(['phoneNumber' => $user['phoneNumber']]);
echo 'latest: ' . json_encode($latest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
