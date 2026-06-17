<?php

declare(strict_types=1);

namespace Bulutklinik\Sdk;

use Bulutklinik\Sdk\Http\HttpClient;
use Bulutklinik\Sdk\Resource\AppointmentsResource;
use Bulutklinik\Sdk\Resource\AuthResource;
use Bulutklinik\Sdk\Resource\DoctorsResource;
use Bulutklinik\Sdk\Resource\MeasuresResource;
use Bulutklinik\Sdk\Resource\PaymentsResource;
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
    }
}
