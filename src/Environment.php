<?php

declare(strict_types=1);

namespace Bulutklinik\Sdk;

enum Environment: string
{
    case Production = 'production';
    case Test = 'test';
    case Local = 'local';

    /** API root for this environment. The base URL is `<root>/<apiVersion>`. */
    public function apiRoot(): string
    {
        return match ($this) {
            self::Production => 'https://api.bulutklinik.com/api',
            self::Test => 'https://apitest.bulutklinik.com/api',
            self::Local => 'https://api-bulutklinik.test/api',
        };
    }

    public function baseUrl(ApiVersion $apiVersion = ApiVersion::V3): string
    {
        return $this->apiRoot() . '/' . $apiVersion->value;
    }
}
