<?php

declare(strict_types=1);

namespace Bulutklinik\Sdk;

/**
 * Result of `auth->connect()`.
 *
 * When `$twoFactorRequired` is true no tokens were stored and
 * `$twoFactorResponse` carries the server's challenge blob.
 */
final class LoginResult
{
    /**
     * @param array<string, mixed>|null $passwordPolicy
     */
    public function __construct(
        public readonly bool $twoFactorRequired,
        public readonly ?string $twoFactorResponse = null,
        public readonly ?array $passwordPolicy = null,
    ) {
    }
}
