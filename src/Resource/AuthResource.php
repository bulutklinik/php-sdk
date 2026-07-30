<?php

declare(strict_types=1);

namespace Bulutklinik\Sdk\Resource;

use Bulutklinik\Sdk\LoginResult;

/**
 * Token lifecycle.
 *
 * The Developer Platform issues a **client id**, a **client secret** and a
 * project-specific **service identity** per approved application; the password is
 * the one set when registering on the portal. `connect()` exchanges those for an
 * access + refresh token pair, which every other method on the client then uses.
 *
 * Neither `connect()` nor `refresh()` is partner-authenticated: they are the two
 * public endpoints that *produce* the credential.
 */
final class AuthResource extends AbstractResource
{
    /**
     * Log in and store the resulting tokens.
     *
     * `$clientId` / `$clientSecret` fall back to the values the client was
     * constructed with, so you usually only pass the service identity and
     * password.
     *
     * If the account has SMS 2FA enabled the API returns a challenge instead of a
     * token pair; the result carries `twoFactorRequired` rather than throwing.
     * Partner service identities do not normally have 2FA on.
     */
    public function connect(
        string $apiUserName,
        string $apiUserPassword,
        ?string $clientId = null,
        ?string $clientSecret = null,
        string $loginMode = 'email',
    ): LoginResult {
        $id = $clientId ?? $this->http->clientId;
        $secret = $clientSecret ?? $this->http->clientSecret;

        if ($id === null || $secret === null) {
            throw new \InvalidArgumentException(
                'clientId and clientSecret are required — pass them to connect() or to ClientConfig.',
            );
        }

        $data = $this->http->request('POST', '/general/connectApi', 'public', [
            'apiClientId' => $id,
            'apiSecretKey' => $secret,
            'apiUserName' => $apiUserName,
            'apiUserPassword' => $apiUserPassword,
            'loginMode' => $loginMode,
        ]);

        if (\is_array($data) && isset($data['access_token']) && \is_string($data['access_token'])) {
            $refresh = isset($data['refresh_token']) && \is_string($data['refresh_token'])
                ? $data['refresh_token']
                : null;
            $this->http->setTokens($data['access_token'], $refresh);

            $policy = $data['password_policy'] ?? null;

            return new LoginResult(false, null, \is_array($policy) ? $policy : null);
        }

        $challenge = \is_array($data) && isset($data['response']) && \is_string($data['response'])
            ? $data['response']
            : null;

        return new LoginResult(true, $challenge);
    }

    /**
     * Exchange the stored refresh token for a new pair. Both tokens rotate.
     *
     * The transport already does this automatically on a `401` / `resultType 4`,
     * so calling it by hand is only useful to refresh ahead of time.
     */
    public function refresh(): void
    {
        $this->http->refresh();
    }

    /**
     * Revoke the access token and all of its refresh tokens, then clear the store.
     *
     * Sent with an empty body on purpose: the endpoint also accepts a device-token
     * cleanup whose `device` mapping has no default branch server-side, and there
     * is no partner use for it.
     */
    public function disconnect(): void
    {
        $this->http->request('POST', '/general/disconnectApi', 'partner', []);
        $this->http->clearTokens();
    }
}
