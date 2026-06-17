<?php

declare(strict_types=1);

namespace Bulutklinik\Sdk;

/**
 * Result of {@see Resource\AuthResource::connect()}. When `twoFactorRequired` is
 * true, pass `twoFactorResponse` (with the SMS code) to `connectWithTwoFactor()`.
 */
final class LoginResult
{
    public function __construct(
        public readonly bool $twoFactorRequired,
        public readonly ?string $twoFactorResponse = null,
    ) {
    }
}
