<?php

namespace App\Services\Social\Threads;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ThreadsClient
{
    private function api(): PendingRequest
    {
        return Http::acceptJson()
            ->timeout(20)
            ->connectTimeout(10);
    }

    private function baseUrl(): string
    {
        return rtrim(
            config('services.threads.api_base_url'),
            '/'
        );
    }

    public function exchangeCode(string $code): array
    {
        $response = $this->api()
            ->asForm()
            ->post(
                $this->baseUrl() . '/oauth/access_token',
                [
                    'client_id' => config(
                        'services.threads.app_id'
                    ),
                    'client_secret' => config(
                        'services.threads.app_secret'
                    ),
                    'grant_type' => 'authorization_code',
                    'redirect_uri' => config(
                        'services.threads.redirect_uri'
                    ),
                    'code' => $code,
                ]
            );

        $response->throw();

        $data = $response->json();

        if (
            ! is_array($data)
            || ! filled($data['access_token'] ?? null)
            || ! filled($data['user_id'] ?? null)
        ) {
            throw new RuntimeException(
                'Respons token Threads tidak lengkap.'
            );
        }

        return $data;
    }

    public function getLongLivedToken(
        string $shortLivedToken
    ): array {
        $response = $this->api()
            ->get(
                $this->baseUrl() . '/access_token',
                [
                    'grant_type' => 'th_exchange_token',
                    'client_secret' => config(
                        'services.threads.app_secret'
                    ),
                    'access_token' => $shortLivedToken,
                ]
            );

        $response->throw();

        $data = $response->json();

        if (
            ! is_array($data)
            || ! filled($data['access_token'] ?? null)
            || ! is_numeric($data['expires_in'] ?? null)
        ) {
            throw new RuntimeException(
                'Respons long-lived token tidak valid.'
            );
        }

        return $data;
    }

    public function getProfile(string $token): array
    {
        $response = $this->api()
            ->withToken($token)
            ->get(
                $this->baseUrl() . '/me',
                [
                    'fields' => 'id,username',
                ]
            );

        $response->throw();

        $data = $response->json();

        if (
            ! is_array($data)
            || ! filled($data['id'] ?? null)
        ) {
            throw new RuntimeException(
                'Profil akun Threads tidak valid.'
            );
        }

        return $data;
    }

    public function refreshToken(string $token): array
    {
        $response = $this->api()
            ->get(
                $this->baseUrl() . '/refresh_access_token',
                [
                    'grant_type' => 'th_refresh_token',
                    'access_token' => $token,
                ]
            );

        $response->throw();

        $data = $response->json();

        if (
            ! is_array($data)
            || ! filled($data['access_token'] ?? null)
            || ! is_numeric($data['expires_in'] ?? null)
        ) {
            throw new RuntimeException(
                'Respons refresh token tidak valid.'
            );
        }

        return $data;
    }
}
