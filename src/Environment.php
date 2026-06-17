<?php

declare(strict_types=1);

namespace Bulutklinik\Sdk;

enum Environment: string
{
    case Production = 'production';
    case Test = 'test';
    case Local = 'local';

    public function baseUrl(): string
    {
        return match ($this) {
            self::Production => 'https://api.bulutklinik.com/api/v3',
            self::Test => 'https://apitest.bulutklinik.com/api/v3',
            self::Local => 'https://api-bulutklinik.test/api/v3',
        };
    }
}
