<?php

declare(strict_types=1);

namespace Bulutklinik\Sdk\Token;

/** In-memory token store (default). Tokens live for the lifetime of the object. */
final class InMemoryTokenStore implements RefreshTokenStore
{
    public function __construct(
        private ?string $token = null,
        private ?string $refreshToken = null,
    ) {
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(?string $token): void
    {
        $this->token = $token;
    }

    public function getRefreshToken(): ?string
    {
        return $this->refreshToken;
    }

    public function setRefreshToken(?string $token): void
    {
        $this->refreshToken = $token;
    }

    public function clear(): void
    {
        $this->token = null;
        $this->refreshToken = null;
    }
}
