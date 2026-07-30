<?php

declare(strict_types=1);

namespace Bulutklinik\Sdk\Token;

/** In-memory token store (default). The token lives for the lifetime of the object. */
final class InMemoryTokenStore implements TokenStore
{
    public function __construct(private ?string $token = null)
    {
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(?string $token): void
    {
        $this->token = $token;
    }

    public function clear(): void
    {
        $this->token = null;
    }
}
