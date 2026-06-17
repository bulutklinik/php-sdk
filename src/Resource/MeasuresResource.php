<?php

declare(strict_types=1);

namespace Bulutklinik\Sdk\Resource;

/** Health measurements: CRUD, latest, history list, graph and partner submission. */
final class MeasuresResource extends AbstractResource
{
    /**
     * Submit multiple measurements of any types in one call (primary entrypoint).
     *
     * @param list<array<string, mixed>> $records
     */
    public function addList(array $records): mixed
    {
        return $this->http->request('POST', '/patients/addNewUserMeasures', 'bearer', ['data' => $records]);
    }

    /**
     * Submit a single measurement of one type.
     *
     * @param array<string, mixed> $fields date_time + the type's own fields
     */
    public function add(string $type, array $fields): mixed
    {
        return $this->http->request('POST', "/patients/addNewUserMeasures/{$type}", 'bearer', $fields);
    }

    /**
     * @param array<string, mixed> $input id + date_time + the type's own fields
     */
    public function update(string $type, array $input): mixed
    {
        return $this->http->request('PUT', "/patients/updateUserMeasures/{$type}", 'bearer', $input);
    }

    public function delete(string $type, int|string $id): mixed
    {
        return $this->http->request('DELETE', "/patients/deleteUserMeasures/{$type}", 'bearer', ['id' => $id]);
    }

    /**
     * Latest value of each measurement type.
     *
     * @return array<array-key, mixed>
     */
    public function last(): array
    {
        return $this->asArray($this->http->request('GET', '/patients/measuresList', 'bearer'));
    }

    /** Paginated history for one type. `glucoseType` (0/1) applies only to glucose. */
    public function list(string $type, int|string $page, ?int $glucoseType = null): mixed
    {
        $path = $glucoseType !== null
            ? "/patients/userMeasuresList/{$type}/{$page}/{$glucoseType}"
            : "/patients/userMeasuresList/{$type}/{$page}";

        return $this->http->request('GET', $path, 'bearer');
    }

    /** Grouped graph data. `period`: 1=day, 2=week, 3=month, 4=year. */
    public function graph(string $type, int $period, int|string $page, ?int $glucoseType = null): mixed
    {
        $path = $glucoseType !== null
            ? "/patients/userMeasuresGraph/{$type}/{$period}/{$page}/{$glucoseType}"
            : "/patients/userMeasuresGraph/{$type}/{$period}/{$page}";

        return $this->http->request('GET', $path, 'bearer');
    }

    /**
     * Partner (teusan) submission — uses the configured partner token.
     *
     * @param list<array<string, mixed>> $data
     */
    public function partnerHealthInformation(?string $identity, ?string $phoneNumber, array $data): mixed
    {
        return $this->http->request('POST', '/outher/healthInformation', 'partner', [
            'identity' => $identity,
            'phoneNumber' => $phoneNumber,
            'data' => $data,
        ]);
    }
}
