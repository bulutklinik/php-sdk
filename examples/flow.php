<?php

declare(strict_types=1);

/**
 * End-to-end example: login -> search -> slots -> reserve, plus health measures.
 * Provide credentials via env: BK_CLIENT_ID, BK_CLIENT_SECRET, BK_USERNAME,
 * BK_PASSWORD, BK_DOCTOR_ID. Run: php examples/flow.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Bulutklinik\Sdk\BulutklinikClient;
use Bulutklinik\Sdk\ClientConfig;
use Bulutklinik\Sdk\Environment;

$client = new BulutklinikClient(new ClientConfig(
    environment: Environment::Test,
    clientId: getenv('BK_CLIENT_ID') ?: '',
    clientSecret: getenv('BK_CLIENT_SECRET') ?: '',
));

$login = $client->auth->connect(
    getenv('BK_USERNAME') ?: '',
    getenv('BK_PASSWORD') ?: '',
    'email',
);

if ($login->twoFactorRequired) {
    echo "2FA required. Collect the SMS code, then call:\n";
    echo "  \$client->auth->connectWithTwoFactor(\$smsCode, \$login->twoFactorResponse);\n";

    return;
}

$search = $client->doctors->quickSearch('kardiyo', 'interview');
echo 'quickSearch: ' . json_encode($search, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

$doctorId = (int) (getenv('BK_DOCTOR_ID') ?: '8282');
$slots = $client->slots->schedule($doctorId, 'interview');
echo 'slots: ' . json_encode($slots, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

$client->measures->addList([
    ['type' => 'tension', 'date_time' => '2026-06-17 09:30', 'hypertension' => 120, 'hypotension' => 80],
    ['type' => 'pulse', 'date_time' => '2026-06-17 09:31', 'pulse' => 72],
]);
echo "measures submitted\n";

$client->auth->disconnect();
