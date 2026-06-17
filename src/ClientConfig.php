<?php

declare(strict_types=1);

namespace Bulutklinik\Sdk;

use Bulutklinik\Sdk\Token\TokenStore;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Immutable client configuration. All transport pieces (PSR-18 client, PSR-17
 * factories) are optional and auto-discovered via php-http/discovery when null.
 * Request timeouts are a property of the injected PSR-18 client.
 */
final class ClientConfig
{
    public function __construct(
        public readonly Environment $environment = Environment::Production,
        public readonly ?string $baseUrl = null,
        public readonly string $lang = 'tr',
        public readonly ?string $clientId = null,
        public readonly ?string $clientSecret = null,
        public readonly ?string $partnerToken = null,
        public readonly ?TokenStore $tokenStore = null,
        public readonly ?ClientInterface $httpClient = null,
        public readonly ?RequestFactoryInterface $requestFactory = null,
        public readonly ?StreamFactoryInterface $streamFactory = null,
    ) {
    }

    public function resolveBaseUrl(): string
    {
        return rtrim($this->baseUrl ?? $this->environment->baseUrl(), '/');
    }
}
