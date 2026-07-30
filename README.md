# bulutklinik/sdk

Official Bulutklinik **partner** API SDK for PHP. Framework-agnostic,
PSR-18/PSR-17 based (bring your own HTTP client, auto-discovered), fully typed,
PHP 8.2+.

This is a single-persona SDK: every call runs on the company-scoped `/outher`
surface with the partner token issued for your integration. You act on the
patients of **your own company**, and the patient is named inline on each
request — there is no patient session. See [`DESIGN.md`](./DESIGN.md) for
the full wire contract.

> **1.1.0 restores `$client->auth`.** 1.0.x wrongly assumed the partner token
> could only be issued out of band; it is in fact minted by `connectApi` from your
> portal credentials, and it is refreshable. Existing 1.0.x code that passes
> `partnerToken:` keeps working. See [CHANGELOG.md](./CHANGELOG.md).

## Install

```bash
composer require bulutklinik/sdk
```

You also need a PSR-18 client and PSR-17 factories, e.g.:

```bash
composer require guzzlehttp/guzzle
```

## Quick start

```php
use Bulutklinik\Sdk\BulutklinikClient;
use Bulutklinik\Sdk\ClientConfig;
use Bulutklinik\Sdk\Environment;
use Bulutklinik\Sdk\ApiVersion;

$client = new BulutklinikClient(new ClientConfig(
    environment: Environment::Production,  // Production | Test | Local
    apiVersion: ApiVersion::V3,            // V3 (default) | V4
    clientId: getenv('BK_CLIENT_ID') ?: null,
    clientSecret: getenv('BK_CLIENT_SECRET') ?: null,
));

// 0) Log in. Tokens are stored and refreshed for you.
$client->auth->connect('svc@your-app.bulutklinik', 'your-portal-password');

// 1) Find a doctor you can book
$result = $client->doctors->search(['withFreeText' => 'kardiyoloji'], 1, ['slot']);
$doctorId = $result['foundDoctors'][0]['doctor_id'];

// 2) Free slots
$schedule = $client->slots->schedule($doctorId, '2026-08-01');
$slot = reset($schedule)[0];

// 3) Hold it for a patient — named inline, no session
$held = $client->appointments->reserveWithoutAgreement($slot['slotId'], $doctorId, [
    'name' => 'Ada',
    'surname' => 'Lovelace',
    'phoneNumber' => '+905551112233',
]);

// 4) Confirm before $held['reservationExpired'] passes
$client->appointments->create($held['hash'], $held['outherProcessId']);
```

## Services

31 endpoints across seven groups.

| Group                    | Methods |
|--------------------------|---------|
| `$client->auth`          | `connect`, `refresh`, `disconnect` |
| `$client->doctors`       | `search`, `branches`, `detail`, `locations` |
| `$client->slots`         | `schedule` |
| `$client->appointments`  | `reserve`, `reserveWithoutAgreement`, `instantReserve`, `create`, `createWithoutSlot`, `cancelWithoutSlot`, `list`, `info`, `checkDoctor` |
| `$client->measures`      | `last`, `list`, `graph`, `addList`, `add`, `update`, `delete`, `healthInformation` |
| `$client->laboratory`    | `catalog`, `catalogDetail`, `results`, `resultDetail` |
| `$client->diets`         | `list`, `detail` |

## Naming a patient

There is no session, so every patient-scoped call carries the patient in its
body — never in the URL, since a TCKN in a path segment would land in access
logs, proxy logs and error breadcrumbs.

**Reads** take a light reference. The server looks only inside your own company
and never creates anything:

```php
$client->measures->last(['identityNumber' => '12345678901']);
$client->diets->list(['phoneNumber' => '+905551112233']);
```

`identityNumber` is primary; `phoneNumber` is a fallback accepted only when it
matches exactly one patient (the column is not unique — family members share
numbers). A patient you have never treated resolves to "not found", with the same
message as "not yours" so the endpoint cannot be used to probe for TCKNs.

**Writes** take the descriptive shape, because the patient is created inside your
company if absent:

```php
$client->measures->addList(
    ['name' => 'Ada', 'surname' => 'Lovelace', 'phoneNumber' => '+905551112233'],
    [['type' => 'pulse', 'date_time' => '2026-06-17 09:31', 'pulse' => 72]],
);
```

## Booking

Two flows, depending on who collects the agreements and the payment:

```php
// (A) Hand off to the patient — returns a browser `url` for agreements + payment.
$held = $client->appointments->reserve($slotId, $doctorId, $user);
echo $held['url'];

// (B) You already collected them — returns a `hash` to confirm yourself.
$held = $client->appointments->reserveWithoutAgreement($slotId, $doctorId, $user);
$client->appointments->create($held['hash'], $outherProcessId);
```

**Payment is never taken through the API.** No partner endpoint produces a
financial record; the browser hand-off in (A) is where payment happens. The SDK
returns `url` verbatim and never opens or follows it.

`createWithoutSlot` books a free-form range outside the slot grid, for
integrations running their own calendar; `cancelWithoutSlot` reverses it — and
only it.

## Authentication

Your portal application issues a **client ID**, a **client secret** and a
project-specific **service identity**; the password is the one you set when
registering on the portal. `auth->connect()` exchanges them for an access token
and a refresh token:

```php
$client = new BulutklinikClient(new ClientConfig(clientId: '…', clientSecret: '…'));

$client->auth->connect(
    'svc@your-app.bulutklinik',
    'your-portal-password',
    loginMode: 'email', // default
);
```

The granted scope comes from the credentials, not the request — a partner
application is provisioned with `apiouther`, which is what makes `/outher`
reachable. Already holding a token? Pass `partnerToken:` and skip the login.

### Refresh

Access tokens last ~30 days, refresh tokens ~130. You do not normally call
`refresh()` yourself: on a `401` / `resultType 4` the SDK refreshes once and
retries the original request.

```php
$client->auth->refresh();     // only useful to refresh ahead of time
$client->auth->disconnect();  // revokes both tokens and clears the store
```

If the refresh fails — or there is no refresh token because you supplied a bare
`partnerToken` — the call throws `AuthenticationException` and you should
`auth->connect()` again.

### Token storage

Tokens are read from a token store on **every** request, so a long-running
process can rotate them without being rebuilt. Implement
`Bulutklinik\Sdk\Token\RefreshTokenStore` to persist both:

```php
use Bulutklinik\Sdk\Token\RefreshTokenStore;

final class VaultTokenStore implements RefreshTokenStore
{
    public function getToken(): ?string { /* … */ }
    public function setToken(?string $token): void { /* … */ }
    public function getRefreshToken(): ?string { /* … */ }
    public function setRefreshToken(?string $token): void { /* … */ }
    public function clear(): void { /* … */ }
}
```

The two refresh methods are **optional**. A plain `TokenStore` — the 1.0.x shape,
access token only — still works; the SDK then keeps the refresh token in memory,
so a process restart needs `auth->connect()` rather than a refresh.

An `AuthorizationException` (403) means the credential itself is wrong: either
the granted scope does not include `apiouther`, or the account has no company.
The company boundary comes from the token, never from request input.

## Health measures

```php
$ref = ['identityNumber' => '12345678901'];

// Write several measurements at once (max 200 per call, one transaction)
$client->measures->addList($patient, [
    ['type' => 'tension', 'date_time' => '2026-06-17 09:30', 'hypertension' => 120, 'hypotension' => 80],
    ['type' => 'glucose', 'date_time' => '2026-06-17 09:35', 'glucose' => 95, 'glucose_type' => 0],
]);

$client->measures->last($ref);
$client->measures->list($ref, 'glucose', 1, 0);  // glucoseType 0=fasting, 1=postprandial
$client->measures->graph($ref, 'tension', 2);     // period 2 = weekly
```

> Measurements are written to **your own company**. A value you write does not
> appear in the patient's Bulutklinik mobile app, and values they entered there
> are not visible to you. That is tenant isolation working as intended.

`measures->healthInformation()` is the legacy `teusan` bulk endpoint, kept for
existing integrations: it needs the `teusan` scope instead of `apiouther`, takes
a flat `identity` + `phoneNumber` instead of `patient`, and writes into the
shared consumer tenant. Its patient matching is an **OR**, and it is loose: the lookup is
`identity OR phoneNumber` against the *global* user table and takes the first
row, so a phone number alone can resolve someone whose TCKN differs from the one
you sent. Send both, but do not assume they are checked as a pair — the
`apiouther` reads above do the opposite, scoping to your company and failing
closed on ambiguity. Prefer `addList()` for anything new.

## Laboratory & diets

```php
$ref = ['identityNumber' => '12345678901'];

// Global, static catalogue — no patient context
$catalog = $client->laboratory->catalog();
$group = $client->laboratory->catalogDetail(7);

// Results for a patient in your company. Ids may carry a "-lab" suffix; pass them back verbatim.
$results = $client->laboratory->results($ref);            // or ->results($ref, 2)
$detail = $client->laboratory->resultDetail($ref, '4821-lab');

// Diet lists written by a dietitian. Page size is fixed to 20 server-side.
$diets = $client->diets->list($ref);
$plan = $client->diets->detail($ref, $diets['foundDiets'][0]['list_id']);
```

Ordering a lab test is not available to partners — it creates a financial record.

## Escape hatch

Not every endpoint has a typed method. `$client->request()` reuses the same
transport, so headers, envelope unwrapping and typed exceptions all still apply:

```php
$data = $client->request('GET', '/outher/somethingNew');

// `public` reaches unauthenticated endpoints outside the partner surface,
// e.g. the city/district catalogue that feeds address forms.
$config = $client->request('GET', '/general/getConfig', 'public');
```

## Errors

All exceptions extend `Bulutklinik\Sdk\Exception\BulutklinikException`:

`TransportException` (network) · `ApiException` → `ValidationException` (422),
`AuthenticationException` (401 / revoked / expired), `AuthorizationException`
(403), `NotFoundException` (404), `RateLimitException` (429).
Every `ApiException` carries a `->context` with `httpStatus`, `resultType`,
`errorType`, `data`, `method`, `path`, `retryAfter`.

```php
use Bulutklinik\Sdk\Exception\RateLimitException;
use Bulutklinik\Sdk\Exception\ValidationException;

try {
    $client->measures->last($ref);
} catch (RateLimitException $e) {
    echo 'retry after ' . $e->context->retryAfter;
} catch (ValidationException $e) {
    var_dump($e->context->data);
}
```

Note that `/outher` reports most business-rule failures as HTTP **`501`** with
`resultType 1` — "patient not found in your company", "slot no longer free",
"doctor not bookable through your integration". It is not a server crash; read
the message.

## Development

```bash
composer install
vendor/bin/pest
vendor/bin/phpstan analyse
vendor/bin/php-cs-fixer fix --dry-run --diff
```

## License

MIT
