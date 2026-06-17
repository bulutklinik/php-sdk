<?php

declare(strict_types=1);

namespace Bulutklinik\Sdk\Resource;

use Bulutklinik\Sdk\Http\HttpClient;

abstract class AbstractResource
{
    public function __construct(protected readonly HttpClient $http)
    {
    }

    /**
     * Normalize a decoded `data` payload to an array.
     *
     * @return array<array-key, mixed>
     */
    final protected function asArray(mixed $value): array
    {
        return \is_array($value) ? $value : [];
    }
}
