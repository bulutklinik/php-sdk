<?php

declare(strict_types=1);

/**
 * Live smoke test against the Bulutklinik test environment (apitest).
 * Read-only flow; each step is independent. Credentials default to the repo's
 * Postman collection (test account). Run: php scripts/live-check.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Bulutklinik\Sdk\BulutklinikClient;
use Bulutklinik\Sdk\ClientConfig;
use Bulutklinik\Sdk\Environment;
use Bulutklinik\Sdk\Exception\ApiException;

$client = new BulutklinikClient(new ClientConfig(
    environment: Environment::Test,
    clientId: getenv('BK_CLIENT_ID') ?: '96b630b3-f62a-4e67-b33c-b58802dca5af',
    clientSecret: getenv('BK_CLIENT_SECRET') ?: 'KPgmEavOSomEl8mQu1ZZMoyZaVXBSuuKxrrzMAkX',
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

$login = $step('auth.connect', fn () => $client->auth->connect(
    getenv('BK_USERNAME') ?: 'hackathon@bulutklinik.test',
    getenv('BK_PASSWORD') ?: 'Hackathon2026',
    'email',
));
echo '    twoFactorRequired=' . var_export($login?->twoFactorRequired, true)
    . ' accessTokenStored=' . var_export($client->tokenStore->getAccessToken() !== null, true) . "\n";

$branches = $step('doctors.branches', fn () => $client->doctors->branches());
echo '    branches=' . (is_array($branches) ? count($branches) : 'n/a') . "\n";

$locations = $step('doctors.locations', fn () => $client->doctors->locations());
echo '    locations=' . (is_array($locations) ? count($locations) : 'n/a') . "\n";

$step('doctors.quickSearch', fn () => $client->doctors->quickSearch('kardiyo', 'interview'));

$found = $step('doctors.search', fn () => $client->doctors->search(['withFreeText' => 'kardiyoloji'], ['slot'], ['isInterviewable'], 1, 10));
echo '    foundDoctorsCount=' . (is_array($found) ? var_export($found['foundDoctorsCount'] ?? 'n/a', true) : 'n/a') . "\n";

$doctorId = (int) (getenv('BK_DOCTOR_ID') ?: '8282');
$detail = $step('doctors.detail', fn () => $client->doctors->detail($doctorId));
echo '    detailKeys=' . (is_array($detail) ? count($detail) : 'n/a') . "\n";

$slots = $step('slots.schedule', fn () => $client->slots->schedule($doctorId, 'interview'));
echo '    slotDays=' . (is_array($slots) ? count($slots) : 'n/a') . "\n";

$last = $step('measures.last', fn () => $client->measures->last());
echo '    measuresLastKeys=' . (is_array($last) ? count($last) : 'n/a') . "\n";

$step('auth.disconnect', fn () => $client->auth->disconnect());

$passed = count(array_filter($results, fn ($r) => $r[1] === true));
echo "\nSUMMARY: {$passed}/" . count($results) . " steps OK\n";
foreach ($results as [$name, $ok]) {
    echo '  ' . ($ok ? 'OK ' : 'ERR') . " {$name}\n";
}
