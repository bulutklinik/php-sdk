<?php

declare(strict_types=1);

namespace Bulutklinik\Sdk;

use Bulutklinik\Sdk\Http\HttpClient;
use Bulutklinik\Sdk\Resource\AppointmentsResource;
use Bulutklinik\Sdk\Resource\DietsResource;
use Bulutklinik\Sdk\Resource\DoctorsResource;
use Bulutklinik\Sdk\Resource\LaboratoryResource;
use Bulutklinik\Sdk\Resource\MeasuresResource;
use Bulutklinik\Sdk\Resource\SlotsResource;
use Bulutklinik\Sdk\Token\TokenStore;

/**
 * The Bulutklinik partner API client. Construct once and reuse; service groups
 * are exposed as readonly properties.
 *
 * Every call runs on the company-scoped `/outher` surface with the partner token
 * issued for your integration: you act on the patients of **your own company**,
 * and the patient is named inline on each request — there is no login and no
 * session.
 *
 * @example
 * $client = new BulutklinikClient(new ClientConfig(
 *     environment: Environment::Test,
 *     partnerToken: getenv('BK_PARTNER_TOKEN') ?: null,
 * ));
 * $branches = $client->doctors->branches();
 * $latest = $client->measures->last(['identityNumber' => '12345678901']);
 */
final class BulutklinikClient
{
    /** Doctor discovery: search, branches, detail, city list. */
    public readonly DoctorsResource $doctors;
    /** Doctor availability (materialized slots). */
    public readonly SlotsResource $slots;
    /** Reserve, confirm, free-form booking, cancel, list, lookup. */
    public readonly AppointmentsResource $appointments;
    /** Health measurements for a named patient, read and write. */
    public readonly MeasuresResource $measures;
    /** Lab results for a named patient + the orderable test catalog. */
    public readonly LaboratoryResource $laboratory;
    /** Diet lists written by a dietitian, for a named patient. */
    public readonly DietsResource $diets;
    /**
     * The active token store. Write a newly issued partner token here to rotate
     * the credential without rebuilding the client.
     */
    public readonly TokenStore $tokenStore;

    private readonly HttpClient $http;

    public function __construct(?ClientConfig $config = null)
    {
        $config ??= new ClientConfig();
        $this->http = new HttpClient($config);
        $this->tokenStore = $this->http->tokenStore;

        $this->doctors = new DoctorsResource($this->http);
        $this->slots = new SlotsResource($this->http);
        $this->appointments = new AppointmentsResource($this->http);
        $this->measures = new MeasuresResource($this->http);
        $this->laboratory = new LaboratoryResource($this->http);
        $this->diets = new DietsResource($this->http);
    }

    /**
     * Escape hatch: call any Bulutklinik API endpoint that does not yet have a
     * typed resource method. The request goes through the same shared transport
     * as the resource methods, so default headers, the chosen `$auth` mode
     * (`partner` by default), envelope unwrapping and the typed error hierarchy
     * all still apply. Returns the unwrapped `data` payload. Prefer a typed
     * resource method when one exists.
     *
     * @param string                    $method `GET` | `POST` | `PUT` | `DELETE`
     * @param string                    $path   relative to the base URL, e.g. `/outher/branches`
     * @param string                    $auth   `partner` (default) | `public`
     * @param array<string, mixed>|null $body   optional JSON payload (omitted on `GET`)
     * @param string|null               $lang   optional per-request `lang` override
     *
     * @example
     * $branches = $client->request('GET', '/outher/branches');
     * // `public` reaches unauthenticated endpoints outside the partner surface
     * $config = $client->request('GET', '/general/getConfig', 'public');
     */
    public function request(string $method, string $path, string $auth = 'partner', ?array $body = null, ?string $lang = null): mixed
    {
        return $this->http->request($method, $path, $auth, $body, $lang);
    }
}
