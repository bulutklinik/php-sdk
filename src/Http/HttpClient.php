<?php

declare(strict_types=1);

namespace Bulutklinik\Sdk\Http;

use Bulutklinik\Sdk\ClientConfig;
use Bulutklinik\Sdk\Exception\ApiErrorContext;
use Bulutklinik\Sdk\Exception\ApiException;
use Bulutklinik\Sdk\Exception\AuthenticationException;
use Bulutklinik\Sdk\Exception\TransportException;
use Bulutklinik\Sdk\Token\TokenStore;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Low-level transport: builds PSR-7 requests, unwraps the response envelope and
 * maps failures to typed exceptions.
 *
 * There is no silent refresh: a partner token is issued out of band and cannot
 * be renewed from here, so an expired one (`401` / `resultType 4`) surfaces as an
 * `AuthenticationException` instead of being retried.
 */
final class HttpClient
{
    public readonly TokenStore $tokenStore;

    private readonly string $baseUrl;
    private readonly string $lang;
    private readonly ClientInterface $httpClient;
    private readonly RequestFactoryInterface $requestFactory;
    private readonly StreamFactoryInterface $streamFactory;

    public function __construct(ClientConfig $config)
    {
        $this->baseUrl = $config->resolveBaseUrl();
        $this->lang = $config->lang;
        $this->tokenStore = $config->resolveTokenStore();
        $this->httpClient = $config->httpClient ?? Psr18ClientDiscovery::find();
        $this->requestFactory = $config->requestFactory ?? Psr17FactoryDiscovery::findRequestFactory();
        $this->streamFactory = $config->streamFactory ?? Psr17FactoryDiscovery::findStreamFactory();
    }

    /**
     * @param array<string, mixed>|null $body
     */
    public function request(string $method, string $path, string $auth, ?array $body = null, ?string $lang = null): mixed
    {
        [$status, $envelope, $response] = $this->dispatch($method, $path, $auth, $body, $lang);

        if ($status >= 200 && $status < 300 && ($envelope['resultType'] ?? null) === 0) {
            return $envelope['data'] ?? null;
        }

        // A revoked token is worth forgetting; an expired one is not, since the
        // caller may want to inspect it while installing a replacement.
        if (($envelope['resultType'] ?? null) === 2) {
            $this->tokenStore->clear();
        }

        throw $this->toException($method, $path, $status, $envelope, $response);
    }

    /**
     * @param array<string, mixed>|null $body
     *
     * @return array{0: int, 1: array<string, mixed>, 2: ResponseInterface}
     */
    private function dispatch(string $method, string $path, string $auth, ?array $body, ?string $lang): array
    {
        $request = $this->requestFactory->createRequest($method, $this->baseUrl . $path)
            ->withHeader('Accept', 'application/json')
            ->withHeader('lang', $lang ?? $this->lang);

        if ($body !== null && $method !== 'GET') {
            $json = json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->streamFactory->createStream($json));
        }

        if ($auth === 'partner') {
            $token = $this->tokenStore->getToken();
            if ($token === null || $token === '') {
                // Dispatching anyway would only come back as an opaque 401.
                throw new AuthenticationException(
                    'No partner token configured.',
                    new ApiErrorContext(httpStatus: 0, method: $method, path: $path),
                );
            }
            $request = $request->withHeader('Authorization', 'Bearer ' . $token);
        }

        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new TransportException(
                sprintf('Network error on %s %s: %s', $method, $path, $e->getMessage()),
                0,
                $e,
            );
        }

        return [$response->getStatusCode(), $this->decode((string) $response->getBody()), $response];
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $contents): array
    {
        if ($contents === '') {
            return [];
        }
        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return ['errorMessage' => $contents];
        }

        return \is_array($decoded) ? $decoded : ['data' => $decoded];
    }

    /**
     * @param array<string, mixed> $envelope
     */
    private function toException(string $method, string $path, int $status, array $envelope, ResponseInterface $response): ApiException
    {
        $errorType = $envelope['errorType'] ?? null;
        if (!\is_string($errorType) && !\is_int($errorType)) {
            $errorType = null;
        }

        $retryAfter = $response->getHeaderLine('Retry-After');
        $errorMessage = $envelope['errorMessage'] ?? null;

        $context = new ApiErrorContext(
            httpStatus: $status,
            resultType: isset($envelope['resultType']) ? (int) $envelope['resultType'] : null,
            errorType: $errorType,
            data: $envelope['data'] ?? null,
            method: $method,
            path: $path,
            retryAfter: $retryAfter !== '' ? (int) $retryAfter : null,
        );

        $message = \is_string($errorMessage) && $errorMessage !== ''
            ? $errorMessage
            : sprintf('Bulutklinik API request failed: %s %s (HTTP %d)', $method, $path, $status);

        return ApiException::fromContext($context, $message);
    }
}
