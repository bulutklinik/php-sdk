# Changelog

All notable changes to `bulutklinik/sdk` are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0]

The SDK becomes **partner-only**. Everything that required a patient login is
gone; the company-scoped `/outher` surface that shipped under `$client->partner`
in 0.6.0 is now the client root. See `DESIGN.md` §12 for the full migration.

### Changed — BREAKING

- **`$client->partner-><group>` → `$client-><group>`.** The six partner groups
  (`doctors`, `slots`, `appointments`, `measures`, `laboratory`, `diets`) moved to
  the root. Their paths, bodies and behaviour are unchanged — this is a rename.
  Resource classes lost the `Partner` prefix and moved from
  `Bulutklinik\Sdk\Resource\Partner\` up to `Bulutklinik\Sdk\Resource\`;
  `PartnerNamespace` is gone.
- **`TokenStore` now holds one partner token**: `getToken()` / `setToken()` /
  `clear()` replace `getAccessToken()` / `getRefreshToken()` / `setTokens()`.
  `InMemoryTokenStore` takes the token as its single constructor argument.
- **`partnerToken` is now the client's credential** and is required for every
  call. Passing both `partnerToken:` and `tokenStore:` to `ClientConfig` throws
  `InvalidArgumentException` rather than silently picking one.
- **No silent refresh.** A `401` / `resultType 4` throws `AuthenticationException`
  with no retry — a partner token is issued out of band and cannot be renewed
  from here. Install a newly issued token in the token store instead.
- **A missing token fails before dispatch** with `AuthenticationException`, rather
  than sending an anonymous request that returns an opaque `401`.
- **Escape hatch `$auth` defaults to `'partner'`**; the `'bearer'` mode no longer
  exists. `'public'` remains, for unauthenticated endpoints outside the surface.
- `Environment::baseUrl()` now takes an `ApiVersion` and a new
  `Environment::apiRoot()` returns the version-less root.
- `measures->partnerHealthInformation()` → `measures->healthInformation()`.
- `doctors->search()` signature is now `(array $searchParams, int $currentPage = 1,
  array $orderParams = [])` — `otherParams` and `perPageLimit` are gone, and
  `orderParams` no longer accepts `point`.

### Added

- **`ApiVersion` enum** (`V3` / `V4`) and an `apiVersion:` option on
  `ClientConfig`. Every path is version-agnostic, so targeting v4 is
  configuration, not a code change. Default stays `V3`.
- `ClientConfig::resolveTokenStore()`.

### Removed

- `$client->auth` (all 11 methods), `$client->payments` (5), `$client->skin`,
  `$client->meals`, `$client->addresses` (4) — no company-scoped equivalent exists.
- The patient-persona `doctors` / `slots` / `appointments` / `measures` /
  `laboratory` / `diets` that lived at the root in 0.6.0.
- `clientId` / `clientSecret` on `ClientConfig`.
- The `LoginResult` class.

## [0.6.0]

### Added

- `$client->auth->confirmRegistrationEmail(...)` — the **required** e-mail-branch middle
  step of registration (`POST /patients/emailConfirmationRegister`). A headerless SDK
  caller always gets `confirmationType: "email"` from `verifyRegistration`; confirm the
  e-mailed code here to receive the SMS blob that `register()` consumes (without it,
  `register()` returns 501).
- Social sign-up: `$client->auth->verifyRegistrationSocial(...)` +
  `$client->auth->registerSocial(...)` (both public; `registerSocial` does not
  auto-login — call `connect(...)` with loginMode `social` after).
- Password reset: `$client->auth->forgotPassword(...)` + `$client->auth->resetPassword(...)`.
- `$client->appointments->list(page?)` (`GET /patients/userAppointments`) — the source of the
  `event_id` that `cancel()` requires — and `$client->appointments->reservations()`.
- New `$client->addresses` group (`list`/`add`/`update`/`delete`) over `/patients/userAddress`,
  required by `laboratory->order()` (which needs an `addressId`).

## [0.5.0]

### Added

- `$client->auth->verifyRegistration(...)` — step 1 of registration
  (`POST /patients/verifyAddingNewPatient`): sends the verification code and returns
  the encrypted `response` blob to pass to `register()`. Uses the configured
  **partner** token (`auth:apiusers`, not public) and requires a browser-minted
  CAPTCHA token (`$recaptchaV2` or `$captcha`).

## [0.4.0]

### Added

- `$client->laboratory` — the patient's lab results, the orderable test catalog, and
  test pre-ordering: `results($page = null)` (`GET /patients/userLabTestList/{page?}`),
  `resultDetail($testId)` (`GET /patients/userLabTestDetail/{testId}`, string id),
  `catalog()` (`GET /patients/allLaboratoryTests`),
  `catalogDetail($id)` (`GET /patients/laboratoryTestDetail/{id}`) and
  `order($testId, $addressId, $laboratoryId)` (`POST /patients/addNewLaboratoryTest`).
- `$client->diets` — the patient's dietitian-written diet lists:
  `list($page = null)` (`GET /patients/dietLists/{page?}`) and
  `detail($listId)` (`GET /patients/diet/{listId}`).

## [0.3.0]

### Added

- `$client->skin->analyze($images)` — "Cildimde Neyim Var" AI skin-lesion analysis
  (`POST /patients/imageCheck`). Returns per-image lesion `label`, a Turkish AI
  `comment`, `confidence`, `possible_icd` and an opaque `case_detail` blob (which can
  be forwarded as a payment's `caseDetail`).
- `$client->meals->analyze(...)` — AI meal-photo calorie/nutrition estimation
  (`POST /patients/imageAnalyzeMeal`).

## [0.2.0]

### Added

- `$client->request(...)` escape hatch for calling any endpoint not yet covered by a
  typed resource method (DESIGN.md §7.2).

## [0.1.0]

### Added

- Initial release: `auth`, `doctors`, `slots`, `appointments`, `payments`, `measures`
  service groups over a shared transport with silent token refresh.
