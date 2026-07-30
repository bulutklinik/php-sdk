<?php

declare(strict_types=1);

namespace Bulutklinik\Sdk\Resource;

/**
 * Diet lists recorded for a patient **inside your own company**.
 * Lists written by other clinics are not visible here.
 *
 * `$patient` shape (read form): `['identityNumber' => ?string, 'phoneNumber' => ?string]`.
 * `identityNumber` is primary; `phoneNumber` is accepted only when it matches
 * exactly one patient.
 */
final class DietsResource extends AbstractResource
{
    /**
     * Paginated diet lists — `['foundDietsCount' => int, 'foundDiets' => array]`.
     *
     * @param array<string, mixed> $patient
     */
    public function list(array $patient, int|string|null $page = null): mixed
    {
        return $this->http->request('POST', '/outher/dietLists', 'partner', [
            'patient' => $patient,
            'currentPage' => $page,
        ]);
    }

    /**
     * Meal breakdown of one diet list. `$listId` comes from `list()`.
     *
     * @param array<string, mixed> $patient
     */
    public function detail(array $patient, int|string $listId): mixed
    {
        return $this->http->request('POST', '/outher/diet', 'partner', [
            'patient' => $patient,
            'listId' => $listId,
        ]);
    }
}
