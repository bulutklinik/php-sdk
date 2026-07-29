<?php

declare(strict_types=1);

namespace Bulutklinik\Sdk\Resource\Partner;

use Bulutklinik\Sdk\Resource\AbstractResource;

/**
 * Health measurements on the partner surface.
 *
 * **Scope:** measurements are written into and read from **your own company**.
 * Values the patient entered in the Bulutklinik mobile app live in the consumer
 * tenant and are *not* visible here — that is a consequence of tenant isolation,
 * not a bug.
 *
 * Writes take the full patient shape (`name`, `surname`, `phoneNumber` required;
 * the patient is created inside your company if absent); reads and edits take the
 * lighter reference shape (`identityNumber` or `phoneNumber`).
 */
final class PartnerMeasuresResource extends AbstractResource
{
    /**
     * Most recent value of every measurement type.
     *
     * @param array<string, mixed> $patient
     */
    public function last(array $patient): mixed
    {
        return $this->http->request('POST', '/outher/lastMeasures', 'partner', ['patient' => $patient]);
    }

    /**
     * Paginated history of one type.
     *
     * @param array<string, mixed> $patient
     */
    public function list(array $patient, string $type, int|string|null $page = null, ?int $glucoseType = null): mixed
    {
        return $this->http->request('POST', "/outher/measuresList/{$type}", 'partner', [
            'patient' => $patient,
            'currentPage' => $page,
            'glucoseType' => $glucoseType,
        ]);
    }

    /**
     * Time-bucketed series. `$period`: 1=day, 2=week, 3=month, 4=year.
     *
     * @param array<string, mixed> $patient
     */
    public function graph(
        array $patient,
        string $type,
        int $period,
        int|string|null $page = null,
        ?int $glucoseType = null,
    ): mixed {
        return $this->http->request('POST', "/outher/measuresGraph/{$type}/{$period}", 'partner', [
            'patient' => $patient,
            'currentPage' => $page,
            'glucoseType' => $glucoseType,
        ]);
    }

    /**
     * Write several measurements of mixed types in one transaction. Max 200 rows.
     *
     * @param array<string, mixed>              $patient
     * @param list<array<string, mixed>>        $data    each row needs `type` plus that type's own fields
     */
    public function addList(array $patient, array $data): mixed
    {
        return $this->http->request('POST', '/outher/measures', 'partner', [
            'patient' => $patient,
            'data' => $data,
        ]);
    }

    /**
     * Write a single measurement.
     *
     * @param array<string, mixed> $patient
     * @param array<string, mixed> $fields  `date_time` plus the type's own fields
     */
    public function add(array $patient, string $type, array $fields): mixed
    {
        return $this->http->request('POST', "/outher/measure/{$type}", 'partner', ['patient' => $patient] + $fields);
    }

    /**
     * Update one measurement row. `$id` comes from `list()`.
     *
     * @param array<string, mixed> $patient
     * @param array<string, mixed> $fields
     */
    public function update(array $patient, string $type, int|string $id, array $fields): mixed
    {
        return $this->http->request('PUT', "/outher/measure/{$type}", 'partner', ['patient' => $patient, 'id' => $id] + $fields);
    }

    /**
     * Delete one measurement row.
     *
     * @param array<string, mixed> $patient
     */
    public function delete(array $patient, string $type, int|string $id): mixed
    {
        return $this->http->request('DELETE', "/outher/measure/{$type}", 'partner', [
            'patient' => $patient,
            'id' => $id,
        ]);
    }

    /**
     * Legacy teusan bulk submission.
     *
     * @deprecated Writes into the shared consumer tenant rather than your own
     * company, so the values are not readable through `last()` / `list()`. Prefer
     * `addList()`. Kept for existing teusan integrations.
     *
     * @param list<array<string, mixed>> $data
     */
    public function healthInformation(?string $identity, ?string $phoneNumber, array $data): mixed
    {
        return $this->http->request('POST', '/outher/healthInformation', 'partner', [
            'identity' => $identity,
            'phoneNumber' => $phoneNumber,
            'data' => $data,
        ]);
    }
}
