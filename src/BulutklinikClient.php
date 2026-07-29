<?php

declare(strict_types=1);

namespace Bulutklinik\Sdk;

use Bulutklinik\Sdk\Http\HttpClient;
use Bulutklinik\Sdk\Resource\AddressesResource;
use Bulutklinik\Sdk\Resource\AppointmentsResource;
use Bulutklinik\Sdk\Resource\AuthResource;
use Bulutklinik\Sdk\Resource\DietsResource;
use Bulutklinik\Sdk\Resource\DoctorsResource;
use Bulutklinik\Sdk\Resource\LaboratoryResource;
use Bulutklinik\Sdk\Resource\MealsResource;
use Bulutklinik\Sdk\Resource\MeasuresResource;
use Bulutklinik\Sdk\Resource\Partner\PartnerNamespace;
use Bulutklinik\Sdk\Resource\PaymentsResource;
use Bulutklinik\Sdk\Resource\SkinResource;
use Bulutklinik\Sdk\Resource\SlotsResource;
use Bulutklinik\Sdk\Token\TokenStore;

/**
 * The Bulutklinik API client. Construct once and reuse; service groups are
 * exposed as readonly properties.
 *
 * @example
 * $client = new BulutklinikClient(new ClientConfig(
 *     environment: Environment::Test,
 *     clientId: '…',
 *     clientSecret: '…',
 * ));
 * $client->auth->connect('patient@example.com', '•••', 'email');
 * $result = $client->doctors->quickSearch('kardiyo');
 */
final class BulutklinikClient
{
    public readonly AuthResource $auth;
    public readonly DoctorsResource $doctors;
    public readonly SlotsResource $slots;
    public readonly AppointmentsResource $appointments;
    public readonly PaymentsResource $payments;
    public readonly MeasuresResource $measures;
    public readonly SkinResource $skin;
    public readonly MealsResource $meals;
    public readonly LaboratoryResource $laboratory;
    public readonly DietsResource $diets;
    public readonly AddressesResource $addresses;
    /**
     * The company-scoped partner surface (`/outher`). Uses the configured
     * `partnerToken` instead of a patient login; data is limited to your own
     * company and the patient is named inline on each call.
     */
    public readonly PartnerNamespace $partner;
    public readonly TokenStore $tokenStore;

    private readonly HttpClient $http;

    public function __construct(?ClientConfig $config = null)
    {
        $config ??= new ClientConfig();
        $this->http = new HttpClient($config);
        $this->tokenStore = $this->http->tokenStore;

        $this->auth = new AuthResource($this->http);
        $this->doctors = new DoctorsResource($this->http);
        $this->slots = new SlotsResource($this->http);
        $this->appointments = new AppointmentsResource($this->http);
        $this->payments = new PaymentsResource($this->http);
        $this->measures = new MeasuresResource($this->http);
        $this->skin = new SkinResource($this->http);
        $this->meals = new MealsResource($this->http);
        $this->laboratory = new LaboratoryResource($this->http);
        $this->diets = new DietsResource($this->http);
        $this->addresses = new AddressesResource($this->http);
        $this->partner = new PartnerNamespace($this->http);
    }

    /**
     * Escape hatch: call any Bulutklinik API endpoint that does not yet have a
     * typed resource method. The request goes through the same shared transport
     * as the resource methods, so default headers, the chosen `$auth` mode
     * (`bearer` by default), silent token refresh + retry, envelope unwrapping
     * and the typed error hierarchy all still apply. Returns the unwrapped
     * `data` payload. Prefer a typed resource method when one exists.
     *
     * @param string                     $method `GET` | `POST` | `PUT` | `DELETE`
     * @param string                     $path   relative to the base URL, e.g. `/patients/allBranches`
     * @param string                     $auth   `public` | `bearer` | `partner` (default `bearer`)
     * @param array<string, mixed>|null  $body   optional JSON payload (omitted on `GET`)
     * @param string|null                $lang   optional per-request `lang` override
     *
     * @example
     * $branches = $client->request('GET', '/patients/allBranches');
     * $created = $client->request('POST', '/patients/someNewEndpoint', 'bearer', ['foo' => 'bar']);
     */
    public function request(string $method, string $path, string $auth = 'bearer', ?array $body = null, ?string $lang = null): mixed
    {
        return $this->http->request($method, $path, $auth, $body, $lang);
    }
}
