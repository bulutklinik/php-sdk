# bulutklinik/sdk

Official Bulutklinik API SDK for PHP. Framework-agnostic, PSR-18/PSR-17 based
(bring your own HTTP client, auto-discovered), fully typed, PHP 8.2+.

Covers the patient flow: **auth, doctor search, slots, appointments, payments,
and health measures**. See [`DESIGN.md`](./DESIGN.md) for the full wire contract.

## Install

```bash
composer require bulutklinik/sdk
```

You also need any PSR-18 client + PSR-17 factories. They are auto-discovered via
`php-http/discovery`. If you don't have one yet:

```bash
composer require guzzlehttp/guzzle
```

## Quick start

```php
use Bulutklinik\Sdk\BulutklinikClient;
use Bulutklinik\Sdk\ClientConfig;
use Bulutklinik\Sdk\Environment;

$client = new BulutklinikClient(new ClientConfig(
    environment: Environment::Production, // Production | Test | Local
    clientId: getenv('BK_CLIENT_ID'),
    clientSecret: getenv('BK_CLIENT_SECRET'),
));

// 1) Log in (tokens are stored automatically)
$login = $client->auth->connect('patient@example.com', '•••••••', 'email');

if ($login->twoFactorRequired) {
    // Collect the SMS code, then:
    $client->auth->connectWithTwoFactor('123456', $login->twoFactorResponse);
}

// 2) Find a doctor
$result = $client->doctors->search(
    searchParams: ['withFreeText' => 'kardiyoloji'],
    orderParams: ['slot'],
    otherParams: ['isInterviewable'],
    currentPage: 1,
);

// 3) Slots, then 4) reserve ("YYYY-MM-DD HH:mm")
$doctorId = $result['foundDoctors'][0]['doctor_id'];
$slots = $client->slots->schedule($doctorId, 'interview');
$client->appointments->reserveInterview($doctorId, '2026-06-20 14:30');
```

## Services

| Group                  | Methods |
|------------------------|---------|
| `$client->auth`         | `connect`, `connectWithTwoFactor`, `register`, `refresh`, `disconnect` |
| `$client->doctors`      | `branches`, `locations`, `quickSearch`, `search`, `detail` |
| `$client->slots`        | `schedule` |
| `$client->appointments` | `reserveInterview`, `addPhysical`, `cancel` |
| `$client->payments`     | `checkDiscountCode`, `getCards`, `saveCard`, `pay`, `deleteCard` |
| `$client->measures`     | `addList`, `add`, `update`, `delete`, `last`, `list`, `graph`, `partnerHealthInformation` |

## Authentication & tokens

- `connect` / `connectWithTwoFactor` / `register` store the access + refresh
  tokens automatically.
- On a `401` (or `resultType 4`), the SDK silently refreshes once and retries.
- Provide a custom token store by implementing `Bulutklinik\Sdk\Token\TokenStore`
  and passing it via `ClientConfig(tokenStore: …)`.

## Payments (3-D Secure)

`payments.pay()` returns an array with `payment3DUrl` on a 3DS flow. Open that URL
in a browser; the bank → server callback completes the capture. The SDK never
opens or parses the URL.

## Health measures

```php
$client->measures->addList([
    ['type' => 'tension', 'date_time' => '2026-06-17 09:30', 'hypertension' => 120, 'hypotension' => 80],
    ['type' => 'glucose', 'date_time' => '2026-06-17 09:35', 'glucose' => 95, 'glucose_type' => 0],
]);

$client->measures->last();
$client->measures->list('glucose', 1, 0); // glucoseType 0=fasting, 1=postprandial
$client->measures->graph('tension', 2, 1); // period 2 = weekly
```

> The partner endpoint (`partnerHealthInformation`) uses a `partnerToken` from
> `ClientConfig`. The API currently matches the patient by `phoneNumber`; send
> both `identity` and `phoneNumber` for forward compatibility.

## Errors

All exceptions extend `Bulutklinik\Sdk\Exception\BulutklinikException`:

`TransportException` (network) · `ApiException` → `ValidationException` (422),
`AuthenticationException` (401 / logout), `AuthorizationException` (403),
`NotFoundException` (404), `RateLimitException` (429).
Details live on `$e->context` (`httpStatus`, `resultType`, `errorType`, `data`,
`method`, `path`, `retryAfter`).

```php
use Bulutklinik\Sdk\Exception\RateLimitException;
use Bulutklinik\Sdk\Exception\ValidationException;

try {
    $client->payments->pay(/* … */);
} catch (RateLimitException $e) {
    echo 'retry after ' . $e->context->retryAfter;
} catch (ValidationException $e) {
    var_dump($e->context->data);
}
```

## Development

```bash
composer install
composer cs:check   # PHP-CS-Fixer (PSR-12)
composer stan       # PHPStan level 6
composer test       # Pest
```

## License

MIT
