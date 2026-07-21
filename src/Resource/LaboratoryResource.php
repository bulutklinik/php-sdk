<?php

declare(strict_types=1);

namespace Bulutklinik\Sdk\Resource;

/** Laboratory: the patient's own results, the orderable test catalog, and test pre-ordering. */
final class LaboratoryResource extends AbstractResource
{
    /**
     * The patient's completed/in-progress lab results (paginated). `$page`
     * defaults to 1 server-side when omitted.
     */
    public function results(int|string|null $page = null): mixed
    {
        $path = $page !== null
            ? "/patients/userLabTestList/{$page}"
            : '/patients/userLabTestList';

        return $this->http->request('GET', $path, 'bearer');
    }

    /**
     * One result's detail. `$testId` is a **string**: a plain id (`"123"`) or a
     * TMC-lab id with a `-lab` suffix (`"123-lab"`) — pass it verbatim from a
     * `results` item.
     */
    public function resultDetail(string $testId): mixed
    {
        return $this->http->request('GET', "/patients/userLabTestDetail/{$testId}", 'bearer');
    }

    /** The orderable test-group catalog. */
    public function catalog(): mixed
    {
        return $this->http->request('GET', '/patients/allLaboratoryTests', 'bearer');
    }

    /** One catalog group by id. */
    public function catalogDetail(int|string $id): mixed
    {
        return $this->http->request('GET', "/patients/laboratoryTestDetail/{$id}", 'bearer');
    }

    /** Pre-order a lab test. Success → `data: { preOrderId }`. */
    public function order(int|string $testId, int|string $addressId, int|string $laboratoryId): mixed
    {
        return $this->http->request('POST', '/patients/addNewLaboratoryTest', 'bearer', [
            'testId' => $testId,
            'addressId' => $addressId,
            'laboratoryId' => $laboratoryId,
        ]);
    }
}
