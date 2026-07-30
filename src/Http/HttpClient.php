<?php

declare(strict_types=1);

namespace Bulutklinik\Sdk\Http;

use Bulutklinik\Sdk\ClientConfig;
use Bulutklinik\Sdk\Exception\ApiErrorContext;
use Bulutklinik\Sdk\Exception\ApiException;
use Bulutklinik\Sdk\Exception\AuthenticationException;
use Bulutklinik\Sdk\Exception\TransportException;
use Bulutklinik\Sdk\Token\RefreshTokenStore;
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
 * On a `401` / `resultType 4` it refreshes once and retries the original request;
 * the error surfaces only when there is no refresh token or the refresh fails.
 */
final class HttpClient
{
    public readonly TokenStore $tokenStore;
    public readonly ?string $clientId;
    public readonly ?string $clientSecret;

    private readonly string $baseUrl;
    private readonly string $lang;
    /** Used only when the injected store cannot persist the refresh token. */
    private ?string $fallbackRefreshToken = null;
    private readonly ClientInterface $httpClient;
    private readonly RequestFactoryInterface $requestFactory;
    private readonly StreamFactoryInterface $streamFactory;

    public function __construct(ClientConfig $config)
    {
        $this->baseUrl = $config->resolveBaseUrl();
        $this->lang = $config->lang;
        $this->clientId = $config->clientId;
        $this->clientSecret = $config->clientSecret;
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
        return $this->send($method, $path, $auth, $body, $lang, false);
    }

    /** Persist a freshly minted token pair. */
    public function setTokens(string $accessToken, ?string $refreshToken): void
    {
        $this->tokenStore->setToken($accessToken);
        if ($this->tokenStore instanceof RefreshTokenStore) {
            $this->tokenStore->setRefreshToken($refreshToken);
        } else {
            $this->fallbackRefreshToken = $refreshToken;
        }
    }

    public function getRefreshToken(): ?string
    {
        return $this->tokenStore instanceof RefreshTokenStore
            ? $this->tokenStore->getRefreshToken()
            : $this->fallbackRefreshToken;
    }

    public function clearTokens(): void
    {
        $this->fallbackRefreshToken = null;
        $this->tokenStore->clear();
    }

    /** Force a refresh using the stored refresh token. Throws on failure. */
    public function refresh(): void
    {
        if (!$this->tryRefresh()) {
            throw new AuthenticationException(
                'Token refresh failed',
                new ApiErrorContext(httpStatus: 401, method: 'POST', path: '/general/refreshApi'),
            );
        }
    }

    /**
     * @param array<string, mixed>|null $body
     */
    private function send(string $method, string $path, string $auth, ?array $body, ?string $lang, bool $isRetry): mixed
    {
        [$status, $envelope, $response] = $this->dispatch($method, $path, $auth, $body, $lang);

        if ($status >= 200 && $status < 300 && ($envelope['resultType'] ?? null) === 0) {
            return $envelope['data'] ?? null;
        }

        $expired = $status === 401 || ($envelope['resultType'] ?? null) === 4;
        if ($auth === 'partner' && $expired && !$isRetry && $this->tryRefresh()) {
            return $this->send($method, $path, $auth, $body, $lang, true);
        }

        // A revoked session is worth forgetting; a merely expired access token is
        // not, since the caller may want to inspect it.
        if (($envelope['resultType'] ?? null) === 2) {
            $this->clearTokens();
        }

        throw $this->toException($method, $path, $status, $envelope, $response);
    }

    private function tryRefresh(): bool
    {
        $refreshToken = $this->getRefreshToken();
        if ($refreshToken === null || $refreshToken === '' || $this->clientId === null || $this->clientSecret === null) {
            return false;
        }

        try {
            [$status, $envelope] = $this->dispatch('POST', '/general/refreshApi', 'public', [
                'refreshToken' => $refreshToken,
                'clientId' => $this->clientId,
                'clientSecretKey' => $this->clientSecret,
            ], null);
        } catch (TransportException) {
            return false;
        }

        $data = $envelope['data'] ?? null;
        if ($status < 200 || $status >= 300 || ($envelope['resultType'] ?? null) !== 0
            || !\is_array($data) || !isset($data['access_token']) || !\is_string($data['access_token'])) {
            $this->clearTokens();

            return false;
        }

        $newRefresh = isset($data['refresh_token']) && \is_string($data['refresh_token'])
            ? $data['refresh_token']
            : $refreshToken;
        $this->setTokens($data['access_token'], $newRefresh);

        return true;
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
                    'No access token available. Call auth->connect(), or construct the client with partnerToken.',
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
