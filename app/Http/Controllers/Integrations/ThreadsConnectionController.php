<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Services\Social\Threads\ThreadsClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ThreadsConnectionController extends Controller
{
    public function connect(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->role === 'admin', 403);

        $config = config('services.threads');

        if (
            blank($config['app_id'])
            || blank($config['app_secret'])
            || blank($config['redirect_uri'])
        ) {
            return redirect()
                ->route('settings.index')
                ->withErrors([
                    'threads' => 'Konfigurasi Threads belum lengkap.',
                ]);
        }

        $state = Str::random(64);

        $request->session()->put('threads_oauth', [
            'state' => $state,
            'user_id' => $request->user()->id,
            'created_at' => now()->timestamp,
        ]);

        $query = http_build_query([
            'client_id' => $config['app_id'],
            'redirect_uri' => $config['redirect_uri'],
            'scope' => implode(',', $config['scopes']),
            'response_type' => 'code',
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);

        return redirect()->away(
            'https://threads.net/oauth/authorize?' . $query
        );
    }

    public function callback(
        Request $request,
        ThreadsClient $threads
    ): RedirectResponse {
        $oauth = $request->session()->pull('threads_oauth');

        if (! is_array($oauth)) {
            return $this->error('Sesi OAuth tidak ditemukan. Ulangi koneksi.');
        }

        if (
            ! $request->user()
            || $request->user()->role !== 'admin'
            || (int) $oauth['user_id'] !== $request->user()->id
            || now()->timestamp - (int) $oauth['created_at'] > 600
            || ! hash_equals(
                (string) $oauth['state'],
                (string) $request->query('state', '')
            )
        ) {
            return $this->error(
                'Validasi keamanan OAuth gagal. Silakan ulangi.'
            );
        }

        if ($request->filled('error')) {
            return $this->error(
                'Otorisasi Threads dibatalkan atau ditolak.'
            );
        }

        $code = $request->query('code');

        if (! is_string($code) || blank($code)) {
            return $this->error(
                'Authorization code tidak ditemukan.'
            );
        }

        try {
            $shortLived = $threads->exchangeCode($code);

            $longLived = $threads->getLongLivedToken(
                $shortLived['access_token']
            );

            $token = $longLived['access_token'];

            $profile = $threads->getProfile($token);

            if (
                (string) $profile['id']
                !== (string) $shortLived['user_id']
            ) {
                throw new \RuntimeException(
                    'Identitas pemilik token tidak cocok.'
                );
            }

            $account = SocialAccount::query()
                ->firstOrNew([
                    'platform' => 'threads',
                    'external_account_id' => (string) $profile['id'],
                ]);

            $account->user_id = $request->user()->id;
            $account->username = $profile['username'] ?? null;
            $account->status = 'connected';
            $account->granted_scopes = config(
                'services.threads.scopes'
            );
            $account->token_expires_at = now()->addSeconds(
                (int) $longLived['expires_in']
            );
            $account->last_verified_at = now();

            $account->setAccessToken($token);
            $account->save();

            return redirect()
                ->route('settings.index')
                ->with(
                    'success',
                    'Akun Threads berhasil dihubungkan.'
                );
        } catch (Throwable $exception) {
            // Jangan pernah memasukkan token atau code ke log.
            Log::warning('Threads OAuth connection failed', [
                'exception_type' => get_class($exception),
                'user_id' => $request->user()->id,
            ]);

            return $this->error(
                'Koneksi Threads gagal. Periksa permission Meta dan konfigurasi OAuth.'
            );
        }
    }

    public function disconnect(
        Request $request,
        SocialAccount $account
    ): RedirectResponse {
        abort_unless($request->user()?->role === 'admin', 403);

        abort_unless($account->platform === 'threads', 404);

        $account->update([
            'status' => 'disconnected',
            'access_token_encrypted' => null,
            'refresh_token_encrypted' => null,
            'token_expires_at' => null,
            'granted_scopes' => null,
            'last_verified_at' => null,
        ]);

        return redirect()
            ->route('settings.index')
            ->with(
                'success',
                'Koneksi Threads telah diputus dari RisenGrowth.'
            );
    }

    private function error(string $message): RedirectResponse
    {
        return redirect()
            ->route('settings.index')
            ->withErrors(['threads' => $message]);
    }
}
