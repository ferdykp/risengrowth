<?php

namespace App\Http\Controllers;

use App\Models\SocialAccount;
use App\Services\Discovery\RunThreadsDiscovery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class DiscoveryRunController extends Controller
{
    public function __invoke(
        Request $request,
        RunThreadsDiscovery $discovery,
    ): RedirectResponse {
        abort_unless($request->user()?->role === 'admin', 403);

        $data = $request->validate([
            'keyword_id' => ['required', 'integer', 'min:1'],
        ]);

        $keyword = DB::table('search_keywords')
            ->where('id', $data['keyword_id'])
            ->where('platform', 'threads')
            ->where('enabled', true)
            ->whereNull('deleted_at')
            ->first();

        if (! $keyword) {
            throw ValidationException::withMessages([
                'keyword_id' => 'Keyword tidak aktif atau tidak ditemukan.',
            ]);
        }

        $account = SocialAccount::query()
            ->where('platform', 'threads')
            ->where('status', 'connected')
            ->orderByDesc('last_verified_at')
            ->first();

        if (! $account || ! $account->isConnected()) {
            throw ValidationException::withMessages([
                'keyword_id' =>
                'Hubungkan akun Threads terlebih dahulu.',
            ]);
        }

        // Shared Workspace: cegah dua admin menjalankan
        // keyword yang sama pada saat bersamaan.
        $lock = Cache::lock(
            'threads-discovery:' . $account->id . ':' . $keyword->id,
            90,
        );

        if (! $lock->get()) {
            return redirect()
                ->route('discovery.index')
                ->withErrors([
                    'discovery' =>
                    'Pencarian keyword ini sedang dijalankan.',
                ]);
        }

        try {
            $result = $discovery->execute($account, $keyword);

            return redirect()
                ->route('discovery.index')
                ->with(
                    'success',
                    'Pencarian selesai. '
                        . $result['fetched'] . ' postingan diperiksa, '
                        . $result['added'] . ' lead baru ditambahkan.'
                );
        } catch (Throwable $exception) {
            Log::warning('Threads discovery failed', [
                'keyword_id' => $keyword->id,
                'account_id' => $account->id,
                'exception_type' => get_class($exception),
            ]);

            return redirect()
                ->route('discovery.index')
                ->withErrors([
                    'discovery' =>
                    'Pencarian gagal. Periksa izin '
                        . 'threads_keyword_search dan status token.',
                ]);
        } finally {
            $lock->release();
        }
    }
}
