<?php

namespace App\Http\Controllers;

use App\Models\UserGrowthSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use App\Models\SocialAccount;

class SettingsController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $settings = UserGrowthSetting::query()->firstOrNew(['user_id' => $user->id], [
            'discovery_enabled' => false,
            'discovery_interval_minutes' => 60,
            'notify_on_new_leads' => true,
        ]);

        $accounts = $user->role === 'admin'
            ? SocialAccount::query()
            ->where('platform', 'threads')
            ->orderByDesc('created_at')
            ->get()
            : collect();

        $threadsConfigured =
            filled(config('services.threads.app_id'))
            && filled(config('services.threads.app_secret'))
            && filled(config('services.threads.redirect_uri'));

        return view('settings.index', [
            'user' => $user,
            'settings' => $settings,
            'accounts' => $accounts,
            'groqConfigured' => filled(config('services.groq.key')),
            'groqModel' => config('services.groq.model'),
            'threadsConfigured' => $threadsConfigured,
            'isAdmin' => $user->role === 'admin',
        ]);
    }

    public function profile(Request $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:191', Rule::unique('users', 'email')->ignore($user->id)],
        ]);
        $user->update($validated);

        return back()->with('success', 'Profil berhasil diperbarui.');
    }

    public function preferences(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'discovery_interval_minutes' => ['required', 'integer', Rule::in([30, 60, 180, 360, 720, 1440])],
            'notify_on_new_leads' => ['required', 'boolean'],
        ]);

        // Discovery can't be enabled here until OAuth and scheduled discovery exist.
        UserGrowthSetting::query()->updateOrCreate(
            ['user_id' => $request->user()->id],
            [...$validated, 'discovery_enabled' => false],
        );

        return back()->with('success', 'Preferensi tersimpan. Penjadwalan otomatis belum diaktifkan.');
    }
}
