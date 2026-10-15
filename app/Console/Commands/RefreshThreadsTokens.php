<?php

namespace App\Console\Commands;

use App\Models\SocialAccount;
use App\Services\Social\Threads\ThreadsClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class RefreshThreadsTokens extends Command
{
    protected $signature = 'threads:refresh-tokens';

    protected $description =
    'Refresh long-lived Threads tokens before expiration';

    public function handle(ThreadsClient $threads): int
    {
        $accounts = SocialAccount::query()
            ->where('platform', 'threads')
            ->where('status', 'connected')
            ->whereNotNull('token_expires_at')
            ->where('token_expires_at', '>', now()->addDay())
            ->where('token_expires_at', '<=', now()->addDays(14))
            ->get();

        foreach ($accounts as $account) {
            try {
                $token = $account->getAccessToken();

                if (blank($token)) {
                    $account->update([
                        'status' => 'disconnected',
                    ]);

                    continue;
                }

                $result = $threads->refreshToken($token);

                $account->setAccessToken(
                    $result['access_token']
                );

                $account->token_expires_at =
                    now()->addSeconds(
                        (int) $result['expires_in']
                    );

                $account->last_verified_at = now();
                $account->save();

                $this->info(
                    'Threads account #' . $account->id . ' refreshed.'
                );
            } catch (Throwable $exception) {
                Log::warning('Threads token refresh failed', [
                    'account_id' => $account->id,
                    'exception_type' => get_class($exception),
                ]);

                $this->warn(
                    'Failed to refresh Threads account #' . $account->id
                );
            }
        }

        return self::SUCCESS;
    }
}
