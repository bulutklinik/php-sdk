<?php

declare(strict_types=1);

namespace Bulutklinik\Sdk\Token;

/**
 * Pluggable token persistence. The default is in-memory; provide a custom
 * implementation to persist tokens to a file, cache, database or session.
 */
interface TokenStore
{
    public function getAccessToken(): ?string;

    public function getRefreshToken(): ?string;

    public function setTokens(string $accessToken, ?string $refreshToken): void;

    public function clear(): void;
}
