<?php

declare(strict_types=1);

namespace Bulutklinik\Sdk\Resource;

/**
 * The patient's saved addresses. Required by {@see LaboratoryResource::order()}
 * (which needs an `addressId`). `add`/`update` take a `cityId` (from
 * {@see DoctorsResource::locations()}) and a `districtId` (from `GET /getConfig` —
 * `cities[].districts[]`, reachable via the client's `request()` escape hatch).
 */
final class AddressesResource extends AbstractResource
{
    /**
     * List the patient's saved addresses (default first). Each item's `id` is the
     * `addressId` used by `update`, `delete` and `laboratory.order`.
     */
    public function list(): mixed
    {
        return $this->http->request('GET', '/patients/userAddress', 'bearer');
    }

    /**
     * Add an address. Success → `{ addressId }`. The first address is always the default.
     *
     * @return array{addressId: int|string}|array<string, mixed>
     */
    public function add(
        string $title,
        int|string $cityId,
        int|string $districtId,
        string $address,
        string $locationLat,
        string $locationLng,
        ?string $description = null,
        ?int $isDefault = null,
    ): array {
        $body = [
            'title' => $title,
            'cityId' => $cityId,
            'districtId' => $districtId,
            'address' => $address,
            'locationLat' => $locationLat,
            'locationLng' => $locationLng,
        ];
        if ($description !== null) {
            $body['description'] = $description;
        }
        if ($isDefault !== null) {
            $body['isDefault'] = $isDefault;
        }

        return $this->asArray($this->http->request('POST', '/patients/userAddress', 'bearer', $body));
    }

    /**
     * Update an address by `id`. Send only `$id` + `$isDefault` to flip the default
     * flag, or the other fields to edit it.
     *
     * @param array<string, mixed> $fields Optional keys: title, description, cityId,
     *                                     districtId, address, locationLat, locationLng, isDefault.
     */
    public function update(int|string $id, array $fields = []): mixed
    {
        return $this->http->request('PUT', '/patients/userAddress', 'bearer', ['id' => $id] + $fields);
    }

    /**
     * Delete an address by `id` (sent in the body). The default address cannot be
     * deleted — reassign the default via {@see update()} first; an address already
     * used on an order cannot be deleted either.
     */
    public function delete(int|string $id): mixed
    {
        return $this->http->request('DELETE', '/patients/userAddress', 'bearer', ['id' => $id]);
    }
}
