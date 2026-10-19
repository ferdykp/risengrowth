<?php

namespace App\Http\Controllers;

use App\Jobs\RunPythonDiscoveryJob;
use App\Models\SocialAccount;
use App\Services\Discovery\RunThreadsDiscovery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class DiscoveryRunController extends Controller
{
    public function __invoke(
        Request $request,
        RunThreadsDiscovery $discovery,
    ): RedirectResponse {

        // STEP 1: Request telah mencapai controller.
        Log::info('Discovery request received', [
            'user_id' => $request->user()?->id,
            'keyword_id' => $request->input('keyword_id'),
        ]);

        // STEP 2: Hanya Admin yang boleh menjalankan discovery.
        abort_unless(
            $request->user()?->role === 'admin',
            403
        );

        $data = $request->validate([
            'source' => ['nullable', Rule::in(['python', 'threads'])],
            'keyword_id' => [
                'required',
                'integer',
                'exists:search_keywords,id',
            ],
        ]);

        // STEP 3: Ambil keyword aktif dari shared workspace.
        $keyword = DB::table('search_keywords')
            ->where('id', $data['keyword_id'])
            ->where('platform', 'threads')
            ->where('enabled', true)
            ->whereNull('deleted_at')
            ->first();

        if (! $keyword) {
            Log::notice('Discovery keyword unavailable', [
                'keyword_id' => $data['keyword_id'],
            ]);

            throw ValidationException::withMessages([
                'keyword_id' => 'Keyword tidak ditemukan atau belum aktif.',
            ]);
        }

        Log::info('Discovery keyword validated', [
            'keyword_id' => $keyword->id,
            'search_type' => $keyword->search_type,
        ]);

        if (($data['source'] ?? 'threads') === 'python') {
            RunPythonDiscoveryJob::dispatch($keyword->id);

            return redirect()->route('discovery.index')->with('success', 'Discovery masuk antrean. Periksa riwayat setelah worker berjalan.');
        }

        // STEP 4: Pilih akun Threads dari shared workspace.
        $account = SocialAccount::query()
            ->where('platform', 'threads')
            ->where('status', 'connected')
            ->where(function ($query) {
                $query
                    ->whereNull('token_expires_at')
                    ->orWhere(
                        'token_expires_at',
                        '>',
                        now()
                    );
            })
            ->orderByDesc('last_verified_at')
            ->first();

        if (! $account || ! $account->isConnected()) {
            Log::warning('Discovery Threads account unavailable', [
                'keyword_id' => $keyword->id,
            ]);

            throw ValidationException::withMessages([
                'keyword_id' => 'Akun Threads tidak aktif. Hubungkan ulang melalui Settings.',
            ]);
        }

        Log::info('Discovery Threads account validated', [
            'account_id' => $account->id,
            'keyword_id' => $keyword->id,
        ]);

        // STEP 5: Prevent concurrent discovery for same keyword.
        $lockKey = sprintf(
            'threads-discovery:%d:%d',
            $account->id,
            $keyword->id
        );

        $lock = Cache::lock($lockKey, 90);

        if (! $lock->get()) {
            Log::notice('Discovery lock unavailable', [
                'account_id' => $account->id,
                'keyword_id' => $keyword->id,
            ]);

            return redirect()
                ->route('discovery.index')
                ->withErrors([
                    'discovery' => 'Pencarian keyword ini sedang diproses. Coba lagi sebentar.',
                ]);
        }

        try {
            Log::info('Discovery execution started', [
                'account_id' => $account->id,
                'keyword_id' => $keyword->id,
            ]);

            // STEP 6: Actual Threads API discovery.
            $result = $discovery->execute(
                $account,
                $keyword
            );

            Log::info('Discovery execution completed', [
                'account_id' => $account->id,
                'keyword_id' => $keyword->id,
                'fetched_count' => $result['fetched'],
                'added_count' => $result['added'],
            ]);

            return redirect()
                ->route('discovery.index')
                ->with(
                    'success',
                    sprintf(
                        'Pencarian selesai. %d postingan ditemukan dan %d lead baru ditambahkan.',
                        $result['fetched'],
                        $result['added']
                    )
                );
        } catch (Throwable $exception) {

            // Tidak mencatat access token, secret,
            // authorization code, atau response body Meta.
            Log::error('Threads discovery execution failed', [
                'account_id' => $account->id,
                'keyword_id' => $keyword->id,
                'exception_type' => get_class($exception),
            ]);

            return redirect()
                ->route('discovery.index')
                ->withErrors([
                    'discovery' => 'Pencarian gagal diproses. Periksa riwayat dan log Discovery di server.',
                ]);
        } finally {
            $lock->release();
        }
    }
}
