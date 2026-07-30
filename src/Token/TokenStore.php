<?php

declare(strict_types=1);

namespace Bulutklinik\Sdk\Token;

/**
 * Pluggable source for the partner token.
 *
 * The token is read on **every** request, so pointing this at a file, cache,
 * database or secret manager lets a long-running process pick up a newly issued
 * token without being rebuilt.
 */
interface TokenStore
{
    public function getToken(): ?string;

    public function setToken(?string $token): void;

    public function clear(): void;
}
