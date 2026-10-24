<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\ApifySettingsController;
use App\Http\Controllers\CommentGeneratorController;
use App\Http\Controllers\CommentSuggestionController;
use App\Http\Controllers\DiscoveredPostReviewController;
use App\Http\Controllers\DiscoveryController;
use App\Http\Controllers\DiscoveryRunController;
use App\Http\Controllers\InitialPasswordController;
use App\Http\Controllers\Integrations\ThreadsConnectionController;
use App\Http\Controllers\InternalAuthController;
use App\Http\Controllers\KeywordController;
use App\Http\Controllers\LeadDetailController;
use App\Http\Controllers\LeadInboxController;
use App\Http\Controllers\LeadStatusController;
use App\Http\Controllers\LocalQualificationController;
use App\Http\Controllers\OverviewController;
use App\Http\Controllers\ReviewQueueController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [InternalAuthController::class, 'show'])->name('login');
    Route::post('/login', [InternalAuthController::class, 'login'])->middleware('throttle:auth-login')->name('login.submit');
    Route::get('/forgot-password', [InternalAuthController::class, 'forgot'])->name('password.request');
    Route::post('/forgot-password', [InternalAuthController::class, 'sendResetLink'])->middleware('throttle:password-email')->name('password.email');
    Route::get('/reset-password/{token}', [InternalAuthController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [InternalAuthController::class, 'resetPassword'])->middleware('throttle:password-reset')->name('password.update');
});

Route::middleware(['auth', 'active.account'])->group(function () {
    Route::post('/logout', [InternalAuthController::class, 'logout'])->name('logout');
    Route::get('/account/initial-password', [InitialPasswordController::class, 'edit'])->name('account.initial-password.edit');
    Route::put('/account/initial-password', [InitialPasswordController::class, 'update'])->middleware('throttle:password-change')->name('account.initial-password.update');
});

Route::middleware(['auth', 'active.account', 'initial.password'])->group(function () {
    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->middleware('throttle:account-mutations')->name('users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->middleware('throttle:account-mutations')->name('users.update');
        Route::patch('/users/{user}/toggle', [UserController::class, 'toggle'])->middleware('throttle:account-mutations')->name('users.toggle');
        Route::put('/users/{user}/password', [UserController::class, 'resetPassword'])->middleware('throttle:password-change')->name('users.password');
    });
    Route::put('/account/password', [InternalAuthController::class, 'updatePassword'])->middleware('throttle:password-change')->name('account.password.update');

    Route::get('/', OverviewController::class)->name('overview');
    Route::get('/leads', LeadInboxController::class)->name('leads.index');
    Route::patch('/leads/{lead}/status', LeadStatusController::class)->middleware('throttle:account-mutations')->name('leads.status');
    Route::post('/discovery/posts/{post}/local-analysis', LocalQualificationController::class)->middleware('throttle:ai-generation')->name('discovery.posts.local');
    Route::patch('/discovery/posts/{post}', DiscoveredPostReviewController::class)->middleware('throttle:account-mutations')->name('discovery.posts.review');
    Route::get('/leads/{lead}', LeadDetailController::class)->name('leads.show');
    Route::get('/discovery', DiscoveryController::class)->name('discovery.index');

    Route::get('/keywords', [KeywordController::class, 'index'])->name('keywords.index');
    Route::post('/keywords', [KeywordController::class, 'store'])->middleware('throttle:account-mutations')->name('keywords.store');
    Route::put('/keywords/{keyword}', [KeywordController::class, 'update'])->middleware('throttle:account-mutations')->name('keywords.update');
    Route::patch('/keywords/{keyword}/toggle', [KeywordController::class, 'toggle'])->middleware('throttle:account-mutations')->name('keywords.toggle');
    Route::delete('/keywords/{keyword}', [KeywordController::class, 'destroy'])->middleware('throttle:account-mutations')->name('keywords.destroy');
    Route::post('/keywords/{keyword}/restore', [KeywordController::class, 'restore'])->middleware('throttle:account-mutations')->name('keywords.restore');

    Route::get('/comments', [CommentGeneratorController::class, 'index'])->name('comments.index');
    Route::post('/comments', [CommentGeneratorController::class, 'store'])->middleware('throttle:account-mutations')->name('comments.store');
    Route::post('/comments/suggestions', [CommentSuggestionController::class, 'store'])->middleware('throttle:ai-generation')->name('comments.suggestions.store');
    Route::patch('/comments/suggestions/{suggestion}', [CommentSuggestionController::class, 'update'])->whereNumber('suggestion')->middleware('throttle:account-mutations')->name('comments.suggestions.update');
    Route::post('/comments/generate', [CommentGeneratorController::class, 'generate'])->middleware('throttle:ai-generation')->name('comments.generate');

    Route::get('/reviews', [ReviewQueueController::class, 'index'])->name('reviews.index');
    Route::patch('/reviews/{draft}', [ReviewQueueController::class, 'update'])->middleware('throttle:account-mutations')->name('reviews.update');
    Route::post('/reviews/{draft}/submit', [ReviewQueueController::class, 'submit'])->middleware('throttle:account-mutations')->name('reviews.submit');
    Route::post('/reviews/{draft}/decision', [ReviewQueueController::class, 'decide'])->middleware('throttle:account-mutations')->name('reviews.decide');

    Route::get('/activity', ActivityController::class)->name('activity.index');
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::patch('/settings/profile', [SettingsController::class, 'profile'])->middleware('throttle:account-mutations')->name('settings.profile');
    Route::patch('/settings/preferences', [SettingsController::class, 'preferences'])->middleware('throttle:account-mutations')->name('settings.preferences');

    Route::patch('/discovery/apify-settings', ApifySettingsController::class)
        ->middleware(['admin', 'throttle:account-mutations'])->name('discovery.apify.settings');

    Route::post('/discovery/run', DiscoveryRunController::class)
        ->middleware(['admin', 'throttle:6,1'])
        ->name('discovery.run');

    Route::middleware('admin')->group(function () {
        Route::post(
            '/auth/threads/connect',
            [ThreadsConnectionController::class, 'connect']
        )
            ->middleware('throttle:6,1')
            ->name('threads.connect');

        Route::get(
            '/auth/threads/callback',
            [ThreadsConnectionController::class, 'callback']
        )
            ->name('threads.callback');

        Route::delete(
            '/auth/threads/{account}',
            [ThreadsConnectionController::class, 'disconnect']
        )
            ->middleware('throttle:6,1')
            ->name('threads.disconnect');
    });
});
