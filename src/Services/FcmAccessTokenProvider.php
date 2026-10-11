<?php

namespace Usamamuneerchaudhary\Notifier\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Exchanges a Google service account for a short lived OAuth2 token for the FCM HTTP v1 API.
 */
class FcmAccessTokenProvider
{
    public const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    public const DEFAULT_TOKEN_URI = 'https://oauth2.googleapis.com/token';

    /**
     * @param  array{client_email?: string, private_key?: string, token_uri?: string}  $serviceAccount
     */
    public function token(array $serviceAccount): string
    {
        $clientEmail = (string) ($serviceAccount['client_email'] ?? '');
        $privateKey = (string) ($serviceAccount['private_key'] ?? '');

        if ($clientEmail === '' || $privateKey === '') {
            throw new RuntimeException('The Firebase service account needs a client_email and private_key.');
        }

        return Cache::remember(
            'notifier:fcm_access_token:'.md5($clientEmail),
            now()->addMinutes(55),
            fn (): string => $this->requestToken($clientEmail, $privateKey, (string) ($serviceAccount['token_uri'] ?? self::DEFAULT_TOKEN_URI))
        );
    }

    protected function requestToken(string $clientEmail, string $privateKey, string $tokenUri): string
    {
        $now = time();

        $jwt = $this->signJwt([
            'iss' => $clientEmail,
            'scope' => self::SCOPE,
            'aud' => $tokenUri,
            'iat' => $now,
            'exp' => $now + 3600,
        ], $privateKey);

        $response = Http::asForm()->connectTimeout(3)->timeout(10)->post($tokenUri, [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        $accessToken = $response->json('access_token');

        if (! $response->successful() || ! is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException('Could not obtain a Firebase access token: '.$response->body());
        }

        return $accessToken;
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    protected function signJwt(array $claims, string $privateKey): string
    {
        $segments = [
            $this->base64Url((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
            $this->base64Url((string) json_encode($claims)),
        ];

        $signingInput = implode('.', $segments);

        if (! openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Could not sign the Firebase token request. Check the service account private_key.');
        }

        return $signingInput.'.'.$this->base64Url($signature);
    }

    protected function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
