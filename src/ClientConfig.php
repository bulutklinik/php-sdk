<?php

declare(strict_types=1);

namespace Bulutklinik\Sdk;

use Bulutklinik\Sdk\Token\InMemoryTokenStore;
use Bulutklinik\Sdk\Token\TokenStore;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Immutable client configuration. All transport pieces (PSR-18 client, PSR-17
 * factories) are optional and auto-discovered via php-http/discovery when null.
 * Request timeouts are a property of the injected PSR-18 client.
 *
 * Pass `$partnerToken` **or** `$tokenStore`, not both — either the literal or the
 * store is the source of truth for the credential, and guessing which one the
 * caller meant is how credential bugs get shipped.
 */
final class ClientConfig
{
    public function __construct(
        public readonly Environment $environment = Environment::Production,
        public readonly ApiVersion $apiVersion = ApiVersion::V3,
        public readonly ?string $baseUrl = null,
        public readonly string $lang = 'tr',
        public readonly ?string $partnerToken = null,
        public readonly ?TokenStore $tokenStore = null,
        public readonly ?ClientInterface $httpClient = null,
        public readonly ?RequestFactoryInterface $requestFactory = null,
        public readonly ?StreamFactoryInterface $streamFactory = null,
    ) {
        if ($partnerToken !== null && $tokenStore !== null) {
            throw new \InvalidArgumentException(
                'Pass either $partnerToken or $tokenStore, not both. '
                . 'Seed your own store with the token if you need custom persistence.',
            );
        }
    }

    public function resolveBaseUrl(): string
    {
        return rtrim($this->baseUrl ?? $this->environment->baseUrl($this->apiVersion), '/');
    }

    /** The configured store, or an in-memory one seeded with `$partnerToken`. */
    public function resolveTokenStore(): TokenStore
    {
        return $this->tokenStore ?? new InMemoryTokenStore($this->partnerToken);
    }
}
