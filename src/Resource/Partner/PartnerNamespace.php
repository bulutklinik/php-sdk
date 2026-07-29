<?php

declare(strict_types=1);

namespace Bulutklinik\Sdk\Resource\Partner;

use Bulutklinik\Sdk\Http\HttpClient;

/**
 * The company-scoped partner surface (`/outher`), exposed as `$client->partner`.
 *
 * It is a **second persona**, not a replacement for the patient one:
 *
 * | | patient (`$client->…`) | partner (`$client->partner->…`) |
 * |---|---|---|
 * | Auth | patient login, access token | pre-issued partner token |
 * | Who the data belongs to | the logged-in patient, across every clinic | your own company only |
 * | How a patient is named | implicit (the session) | inline, per request |
 *
 * Requests here use the configured `partnerToken`; no patient login is involved
 * and the silent access-token refresh does not apply.
 *
 * Endpoints with no partner equivalent — patient login/registration, the card
 * vault, 3-D Secure payment, self-service AI, address CRUD — stay on the patient
 * surface by design.
 */
final class PartnerNamespace
{
    public readonly PartnerDoctorsResource $doctors;
    public readonly PartnerSlotsResource $slots;
    public readonly PartnerAppointmentsResource $appointments;
    public readonly PartnerDietsResource $diets;
    public readonly PartnerLaboratoryResource $laboratory;
    public readonly PartnerMeasuresResource $measures;

    public function __construct(HttpClient $http)
    {
        $this->doctors = new PartnerDoctorsResource($http);
        $this->slots = new PartnerSlotsResource($http);
        $this->appointments = new PartnerAppointmentsResource($http);
        $this->diets = new PartnerDietsResource($http);
        $this->laboratory = new PartnerLaboratoryResource($http);
        $this->measures = new PartnerMeasuresResource($http);
    }
}
