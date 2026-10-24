<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApifySettingsController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->role === 'admin', 403);
        $data = $request->validate([
            'enabled' => ['required', 'boolean'], 'automation_enabled' => ['required', 'boolean'],
            'daily_runs' => ['required', 'integer', 'between:1,10'],
            'min_interval_minutes' => ['required', 'integer', 'between:1,10080'],
        ]);
        $data['automation_enabled'] = (bool) $data['enabled'] && (bool) $data['automation_enabled'];
        DB::table('discovery_provider_settings')->where('id', 1)->update($data + ['updated_at' => now()]);

        return back()->with('success', 'Pengaturan Apify tersimpan. Run baru dapat memakai kredit provider sesuai batas penggunaan.');
    }
}
