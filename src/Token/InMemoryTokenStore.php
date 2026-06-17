<?php

declare(strict_types=1);

namespace Bulutklinik\Sdk\Token;

/** In-memory token store (default). Tokens live for the lifetime of the object. */
final class InMemoryTokenStore implements TokenStore
{
    public function __construct(
        private ?string $accessToken = null,
        private ?string $refreshToken = null,
    ) {
    }

    public function getAccessToken(): ?string
    {
        return $this->accessToken;
    }

    public function getRefreshToken(): ?string
    {
        return $this->refreshToken;
    }

    public function setTokens(string $accessToken, ?string $refreshToken): void
    {
        $this->accessToken = $accessToken;
        $this->refreshToken = $refreshToken;
    }

    public function clear(): void
    {
        $this->accessToken = null;
        $this->refreshToken = null;
    }
}
