<?php

declare(strict_types=1);

namespace Bulutklinik\Sdk\Resource;

/** Branches, locations, quick/filtered doctor search and doctor detail. */
final class DoctorsResource extends AbstractResource
{
    /** @return array<array-key, mixed> */
    public function branches(): array
    {
        return $this->asArray($this->http->request('GET', '/patients/allBranches', 'bearer'));
    }

    /** @return array<array-key, mixed> */
    public function locations(): array
    {
        return $this->asArray($this->http->request('GET', '/patients/allLocations', 'bearer'));
    }

    /** @return array<array-key, mixed> */
    public function quickSearch(string $searchText, ?string $listType = null, ?string $location = null): array
    {
        return $this->asArray($this->http->request('POST', '/patients/quickSearch', 'bearer', [
            'searchText' => $searchText,
            'listType' => $listType,
            'location' => $location,
        ]));
    }

    /**
     * @param array<string, mixed> $searchParams
     * @param list<string>         $orderParams
     * @param list<string>         $otherParams
     *
     * @return array<array-key, mixed>
     */
    public function search(
        array $searchParams = [],
        array $orderParams = [],
        array $otherParams = [],
        int $currentPage = 1,
        int $perPageLimit = 20,
    ): array {
        return $this->asArray($this->http->request('POST', '/patients/filteredSearch', 'bearer', [
            'searchParams' => $searchParams,
            'orderParams' => $orderParams,
            'otherParams' => $otherParams,
            'currentPage' => $currentPage,
            'perPageLimit' => $perPageLimit,
        ]));
    }

    /** @return array<array-key, mixed> */
    public function detail(int|string $id, int|string|null $corporate = null): array
    {
        $path = $corporate !== null
            ? "/patients/doctorDetail/{$id}/{$corporate}"
            : "/patients/doctorDetail/{$id}";

        return $this->asArray($this->http->request('GET', $path, 'bearer'));
    }
}
