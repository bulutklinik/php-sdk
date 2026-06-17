<?php

declare(strict_types=1);

namespace Bulutklinik\Sdk\Resource;

use Bulutklinik\Sdk\Exception\BulutklinikException;
use Bulutklinik\Sdk\LoginResult;

/** Login, 2FA, token refresh, registration and logout. */
final class AuthResource extends AbstractResource
{
    /**
     * Log in. On success tokens are stored automatically and a result with
     * `twoFactorRequired === false` is returned. When 2FA is enabled, the result
     * carries `twoFactorResponse` — pass it (with the SMS code) to
     * {@see connectWithTwoFactor()}.
     */
    public function connect(
        string $apiUserName,
        ?string $apiUserPassword,
        string $loginMode,
        ?string $clientId = null,
        ?string $clientSecret = null,
        ?string $withPhoneNumber = null,
    ): LoginResult {
        $body = [
            'apiUserName' => $apiUserName,
            'apiUserPassword' => $apiUserPassword,
            'apiClientId' => $clientId ?? $this->http->clientId,
            'apiSecretKey' => $clientSecret ?? $this->http->clientSecret,
            'loginMode' => $loginMode,
        ];
        if ($withPhoneNumber !== null) {
            $body['withPhoneNumber'] = $withPhoneNumber;
        }

        return $this->finishLogin($this->http->request('POST', '/general/connectApi', 'public', $body));
    }

    /** Complete a 2FA login with the SMS code and the challenge blob. */
    public function connectWithTwoFactor(string $smsVerificationCode, string $response): void
    {
        $data = $this->http->request('POST', '/general/connectApiWithTwoFactor', 'public', [
            'smsVerificationCode' => $smsVerificationCode,
            'response' => $response,
        ]);
        $this->storeTokens($data);
    }

    /** Register a new patient (afterRegister auto-login). Stores tokens on success. */
    public function register(
        string $name,
        string $surname,
        string $apiUserName,
        string $phoneNumber,
        string $password,
        string $smsVerificationCode,
        string $response,
        int $acceptUserAgreement = 1,
        ?string $clientId = null,
        ?string $clientSecret = null,
    ): void {
        $data = $this->http->request('POST', '/patients/addNewPatient', 'public', [
            'name' => $name,
            'surname' => $surname,
            'apiUserName' => $apiUserName,
            'phoneNumber' => $phoneNumber,
            'password' => $password,
            'smsVerificationCode' => $smsVerificationCode,
            'response' => $response,
            'acceptUserAgreement' => $acceptUserAgreement,
            'apiClientId' => $clientId ?? $this->http->clientId,
            'apiSecretKey' => $clientSecret ?? $this->http->clientSecret,
        ]);
        $this->storeTokens($data);
    }

    /** Manually refresh the access token using the stored refresh token. */
    public function refresh(): void
    {
        $this->http->refresh();
    }

    /** Revoke the current tokens server-side and clear the local token store. */
    public function disconnect(): void
    {
        try {
            $this->http->request('POST', '/general/disconnectApi', 'bearer', []);
        } finally {
            $this->http->tokenStore->clear();
        }
    }

    private function finishLogin(mixed $data): LoginResult
    {
        if (\is_array($data) && isset($data['access_token']) && \is_string($data['access_token'])) {
            $this->storeTokens($data);

            return new LoginResult(false);
        }
        if (\is_array($data) && isset($data['response']) && \is_string($data['response'])) {
            return new LoginResult(true, $data['response']);
        }

        return new LoginResult(false);
    }

    private function storeTokens(mixed $data): void
    {
        if (!\is_array($data) || !isset($data['access_token']) || !\is_string($data['access_token'])) {
            throw new BulutklinikException('Login response did not contain an access token');
        }
        $refresh = isset($data['refresh_token']) && \is_string($data['refresh_token']) ? $data['refresh_token'] : null;
        $this->http->tokenStore->setTokens($data['access_token'], $refresh);
    }
}
