<?php

declare(strict_types=1);

namespace Bulutklinik\Sdk\Token;

/**
 * Optional extension: a store that also persists the refresh token.
 *
 * Implementing this is not required — a {@see TokenStore} written against spec
 * 1.0.x keeps working. When the injected store does not implement it, the SDK
 * holds the refresh token in memory for the client's lifetime; the only
 * consequence is that a process restart needs `auth->connect()` rather than
 * `auth->refresh()`.
 */
interface RefreshTokenStore extends TokenStore
{
    public function getRefreshToken(): ?string;

    public function setRefreshToken(?string $token): void;
}
