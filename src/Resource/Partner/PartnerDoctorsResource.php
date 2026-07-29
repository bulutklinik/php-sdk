<?php

declare(strict_types=1);

namespace Bulutklinik\Sdk\Resource\Partner;

use Bulutklinik\Sdk\Resource\AbstractResource;

/**
 * Doctor discovery on the partner surface.
 *
 * Results are scoped to the doctors enabled for your integration (the server
 * filters on your partner slug), so a doctor returned here is one you can
 * actually book.
 */
final class PartnerDoctorsResource extends AbstractResource
{
    /**
     * Filtered doctor search.
     *
     * @param array<string, mixed> $searchParams
     * @param list<string>         $orderParams  any of `name`, `order`, `slot`
     */
    public function search(array $searchParams, int $currentPage = 1, array $orderParams = []): mixed
    {
        return $this->http->request('POST', '/outher/search', 'partner', [
            'searchParams' => $searchParams,
            'orderParams' => $orderParams,
            'currentPage' => $currentPage,
        ]);
    }

    /**
     * Branches available through your integration.
     *
     * @return array<array-key, mixed>
     */
    public function branches(): array
    {
        return $this->asArray($this->http->request('GET', '/outher/branches', 'partner'));
    }

    /** Detail of a single doctor. */
    public function detail(int|string $doctorId): mixed
    {
        return $this->http->request('GET', "/outher/doctorInfos/{$doctorId}", 'partner');
    }

    /**
     * City list. Global catalogue — not scoped to your company.
     *
     * @return array<array-key, mixed>
     */
    public function locations(): array
    {
        return $this->asArray($this->http->request('GET', '/outher/locations', 'partner'));
    }
}
