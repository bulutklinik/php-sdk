<?php

declare(strict_types=1);

/**
 * Live smoke test against the Bulutklinik test environment (apitest).
 *
 * Read-only flow; each step is independent. Needs a partner token issued for a
 * test company with the `apiouther` scope:
 *
 *   BK_PARTNER_TOKEN=... php scripts/live-check.php
 *
 * Unlike the patient surface there is no shared test credential — the token is
 * per-integration. Steps that touch a patient need one that exists inside the
 * token's own company; set BK_PATIENT_TCKN or BK_PATIENT_PHONE to run them.
 */

require __DIR__ . '/../vendor/autoload.php';

use Bulutklinik\Sdk\ApiVersion;
use Bulutklinik\Sdk\BulutklinikClient;
use Bulutklinik\Sdk\ClientConfig;
use Bulutklinik\Sdk\Environment;
use Bulutklinik\Sdk\Exception\ApiException;

$partnerToken = getenv('BK_PARTNER_TOKEN') ?: '';
if ($partnerToken === '') {
    fwrite(STDERR, "BK_PARTNER_TOKEN is required.\n");
    exit(2);
}

$client = new BulutklinikClient(new ClientConfig(
    environment: Environment::Test,
    apiVersion: ApiVersion::from(getenv('BK_API_VERSION') ?: 'v3'),
    partnerToken: $partnerToken,
));

$results = [];
$step = function (string $name, callable $fn) use (&$results): mixed {
    try {
        $r = $fn();
        echo "OK  {$name}\n";
        $results[] = [$name, true];

        return $r;
    } catch (\Throwable $e) {
        $detail = $e instanceof ApiException
            ? sprintf(
                ' [http=%d resultType=%s errorType=%s]',
                $e->context->httpStatus,
                var_export($e->context->resultType, true),
                var_export($e->context->errorType, true),
            )
            : '';
        echo sprintf("ERR %s: %s - %s%s\n", $name, $e::class, $e->getMessage(), $detail);
        $results[] = [$name, false];

        return null;
    }
};

// --- Scope-only steps: prove the token and base URL without any patient.
$branches = $step('doctors.branches', fn () => $client->doctors->branches());
echo '    branches=' . (is_array($branches) ? count($branches) : 'n/a') . "\n";

$locations = $step('doctors.locations', fn () => $client->doctors->locations());
echo '    locations=' . (is_array($locations) ? count($locations) : 'n/a') . "\n";

$catalog = $step('laboratory.catalog', fn () => $client->laboratory->catalog());
echo '    catalog=' . (is_array($catalog) ? count($catalog) : 'n/a') . "\n";

$found = $step('doctors.search', fn () => $client->doctors->search(['withFreeText' => 'kardiyoloji'], 1, ['slot']));
echo '    foundDoctorsCount=' . (is_array($found) ? var_export($found['foundDoctorsCount'] ?? 'n/a', true) : 'n/a') . "\n";

$doctorId = (int) (getenv('BK_DOCTOR_ID') ?: '8282');
$detail = $step('doctors.detail', fn () => $client->doctors->detail($doctorId));
echo '    detailKeys=' . (is_array($detail) ? count($detail) : 'n/a') . "\n";

$step('appointments.checkDoctor', fn () => $client->appointments->checkDoctor($doctorId, 0));

$slots = $step('slots.schedule', fn () => $client->slots->schedule($doctorId));
echo '    slotDays=' . (is_array($slots) ? count($slots) : 'n/a') . "\n";

// --- Patient-scoped steps. A TCKN that works on the patient surface will not
//     necessarily resolve here: the patient must exist in the token's company.
$patient = null;
if ($tckn = getenv('BK_PATIENT_TCKN')) {
    $patient = ['identityNumber' => $tckn];
} elseif ($phone = getenv('BK_PATIENT_PHONE')) {
    $patient = ['phoneNumber' => $phone];
}

if ($patient !== null) {
    $last = $step('measures.last', fn () => $client->measures->last($patient));
    echo '    measuresLastKeys=' . (is_array($last) ? count($last) : 'n/a') . "\n";

    $step('diets.list', fn () => $client->diets->list($patient));
    $step('laboratory.results', fn () => $client->laboratory->results($patient));
} else {
    echo "--  skipped patient-scoped steps (set BK_PATIENT_TCKN or BK_PATIENT_PHONE)\n";
}

$passed = count(array_filter($results, fn ($r) => $r[1] === true));
echo "\nSUMMARY: {$passed}/" . count($results) . " steps OK\n";
foreach ($results as [$name, $ok]) {
    echo '  ' . ($ok ? 'OK ' : 'ERR') . " {$name}\n";
}
