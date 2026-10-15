<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use App\Models\Lead;
use App\Models\CommentDraft;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Navigation metrics belong to the shared shell, not individual pages.
        View::composer('layouts.workspace', function ($view): void {
            $view->with('workspaceNewLeadsCount', Lead::query()->where('status', 'new')->count());
            $view->with('workspacePendingReviewsCount', CommentDraft::query()
                ->where('author_id', auth()->id())
                ->where('status', 'pending_review')
                ->count());
        });

        RateLimiter::for('auth-login', fn (Request $request) => [
            Limit::perMinute(5)->by('login:'.strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(30)->by('login-ip:'.$request->ip()),
        ]);
        RateLimiter::for('password-email', fn (Request $request) => [
            Limit::perMinute(3)->by('reset:'.strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perHour(15)->by('reset-ip:'.$request->ip()),
        ]);
        RateLimiter::for('password-reset', fn (Request $request) => [
            Limit::perMinute(5)->by('password-reset-email:'.strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perHour(20)->by('password-reset-ip:'.$request->ip()),
        ]);
        RateLimiter::for('password-change', fn (Request $request) =>
            Limit::perMinute(5)->by('password-user:'.($request->user()?->getAuthIdentifier() ?? $request->ip()))
        );
        RateLimiter::for('ai-generation', fn (Request $request) => [
            Limit::perMinute(6)->by('ai-minute:'.($request->user()?->getAuthIdentifier() ?? $request->ip())),
            Limit::perHour(60)->by('ai-hour:'.($request->user()?->getAuthIdentifier() ?? $request->ip())),
        ]);
        RateLimiter::for('account-mutations', fn (Request $request) =>
            Limit::perMinute(30)->by('write:'.($request->user()?->getAuthIdentifier() ?? $request->ip()))
        );
    }
}
