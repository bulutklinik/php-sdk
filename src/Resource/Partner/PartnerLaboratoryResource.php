<?php

declare(strict_types=1);

namespace Bulutklinik\Sdk\Resource\Partner;

use Bulutklinik\Sdk\Resource\AbstractResource;

/**
 * Laboratory catalogue and results on the partner surface.
 *
 * `catalog()` / `catalogDetail()` are global, static package definitions.
 * `results()` / `resultDetail()` are scoped to your own company and merge two
 * sources: the clinic's HBYS lab requests and TmcLab order groups. Results
 * recorded by other clinics are not visible.
 */
final class PartnerLaboratoryResource extends AbstractResource
{
    /** Orderable test packages. Static catalogue, no patient context. */
    public function catalog(): mixed
    {
        return $this->http->request('GET', '/outher/laboratoryCatalog', 'partner');
    }

    /**
     * One catalogue package. Prices are the plain list prices — the patient-side
     * discount pass does not apply on the partner surface.
     */
    public function catalogDetail(int|string $testId): mixed
    {
        return $this->http->request('GET', "/outher/laboratoryCatalog/{$testId}", 'partner');
    }

    /**
     * Paginated results — `['foundTestsCount' => int, 'foundTests' => array]`.
     * Each item's `id` is accepted verbatim by `resultDetail()` (a `-lab` suffix
     * marks a TmcLab group).
     *
     * @param array<string, mixed> $patient
     */
    public function results(array $patient, int|string|null $page = null): mixed
    {
        return $this->http->request('POST', '/outher/laboratoryResults', 'partner', [
            'patient' => $patient,
            'currentPage' => $page,
        ]);
    }

    /**
     * One result. Pass the `id` from `results()` unchanged.
     *
     * @param array<string, mixed> $patient
     */
    public function resultDetail(array $patient, int|string $testId): mixed
    {
        return $this->http->request('POST', '/outher/laboratoryResult', 'partner', [
            'patient' => $patient,
            'testId' => (string) $testId,
        ]);
    }
}
