@extends('layouts.workspace')

@section('title', 'Eksplorasi')
@section('page_heading', 'Eksplorasi')

@section('content')

    @php
        /*
    |--------------------------------------------------------------------------
    | Discovery Workspace State
    |--------------------------------------------------------------------------
    */

        $isAdmin = auth()->user()?->role === 'admin';

        /*
    |--------------------------------------------------------------------------
    | Connected Threads Account
    |--------------------------------------------------------------------------
    |
    | data_get() digunakan karena DiscoveryController dapat mengembalikan
    | stdClass dari Query Builder, bukan Eloquent Model.
    |
    | Dengan ini, properti yang belum dipilih oleh query tidak menyebabkan
    | Undefined property error.
    |
    */

        $connectedAccount = $accounts->first(function ($account) {
            $platform = data_get($account, 'platform');
            $status = data_get($account, 'status');
            $expiresAt = data_get($account, 'token_expires_at');

            if ($platform !== 'threads' || $status !== 'connected') {
                return false;
            }

            if (blank($expiresAt)) {
                return true;
            }

            try {
                return \Illuminate\Support\Carbon::parse($expiresAt)->isFuture();
            } catch (\Throwable) {
                return false;
            }
        });

        /*
    |--------------------------------------------------------------------------
    | Enabled Threads Keywords
    |--------------------------------------------------------------------------
    */

        $enabledKeywords = $keywords
            ->filter(function ($keyword) {
                return data_get($keyword, 'platform') === 'threads' && (bool) data_get($keyword, 'enabled', false);
            })
            ->values();

        /*
    |--------------------------------------------------------------------------
    | Discovery Availability
    |--------------------------------------------------------------------------
    */

        $canRunDiscovery = $isAdmin && $enabledKeywords->isNotEmpty();

        /*
    |--------------------------------------------------------------------------
    | Status Appearance
    |--------------------------------------------------------------------------
    */

        $statusLabels = [
            'queued' => 'Antrean',
            'running' => 'Berjalan',
            'completed' => 'Selesai',
            'failed' => 'Gagal',
            'partial' => 'Sebagian',
        ];

        $statusClasses = [
            'queued' => 'bg-slate-100 text-slate-600',
            'running' => 'bg-blue-50 text-blue-700',
            'completed' => 'bg-emerald-50 text-emerald-700',
            'failed' => 'bg-red-50 text-red-700',
            'partial' => 'bg-amber-50 text-amber-700',
        ];

        $statistics = [
            [
                'label' => 'Total proses',
                'value' => $totalRuns, 'key' => 'totalRuns',
                'note' => 'Seluruh pencarian',
                'icon' => 'manage_search',
            ],
            [
                'label' => 'Proses selesai',
                'value' => $completedRuns, 'key' => 'completedRuns',
                'note' => 'Pencarian berhasil',
                'icon' => 'task_alt',
            ],
            [
                'label' => 'Postingan ditemukan',
                'value' => $totalDiscovered, 'key' => 'totalDiscovered',
                'note' => 'Postingan unik',
                'icon' => 'forum',
            ],
            [
                'label' => 'Keyword aktif',
                'value' => $activeKeywords, 'key' => 'activeKeywords',
                'note' => 'Keyword dipantau',
                'icon' => 'tag',
            ],
        ];
    @endphp

    <div class="rg-discovery">

        {{-- ================================================================ --}}
        {{-- PAGE HEADER                                                      --}}
        {{-- ================================================================ --}}

        <header class="mb-8 flex flex-col gap-6 lg:items-start">

            <div class="min-w-0">

                <p class="mb-2 text-[11px] font-bold uppercase tracking-[.18em] text-slate-500">
                    RISENGROWTH / DISCOVERY
                </p>

                <h1 class="text-[30px] font-bold tracking-[-.045em] text-slate-950 md:text-[38px]">
                    Eksplorasi Prospek
                </h1>

                <p class="mt-2 max-w-xl text-sm leading-relaxed text-slate-500">
                    Temukan percakapan yang relevan dengan layanan RisenDev.
                </p>

            </div>

            {{-- DISCOVERY FORM --}}

            @if ($isAdmin)

                <form id="discovery-run-form" method="POST" action="{{ route('discovery.run') }}"
                    class="flex w-full flex-col gap-3 sm:flex-row sm:flex-wrap">
                    @csrf
                    <div>
                        <label for="run-source" class="rg-field-label">Sumber discovery</label>
                        <select id="run-source" name="source" class="w-full rounded-xl border border-slate-200 p-3 text-sm">
                            <option value="apify" @selected(old('source', 'apify') === 'apify')>Threads via Apify</option>
                            <option value="threads" @selected(old('source') === 'threads')>Threads API resmi</option>
                            <option value="python" @selected(old('source') === 'python')>Public web / API (Python)</option>
                        </select>
                    </div>

                    <div class="w-full sm:w-auto">

                        <label for="run-keyword" class="rg-field-label">
                            Pilih keyword pencarian
                        </label>

                        <select id="run-keyword" name="keyword_id" required @disabled(!$canRunDiscovery)
                            class="w-full min-w-[220px] rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-900 outline-none transition focus:border-slate-400 focus:ring-2 focus:ring-slate-100 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400">
                            <option value="">
                                Pilih keyword
                            </option>

                            @foreach ($enabledKeywords as $keyword)
                                <option value="{{ data_get($keyword, 'id') }}" @selected(old('keyword_id') == data_get($keyword, 'id'))>
                                    {{ data_get($keyword, 'phrase') }}
                                    ({{ strtoupper(data_get($keyword, 'search_type', 'RECENT')) }})
                                </option>
                            @endforeach

                        </select>

                    </div>

                    <div>
                        <label for="existing-apify-run" class="rg-field-label">ID run lama (opsional)</label>
                        <input id="existing-apify-run" name="existing_run_id" maxlength="64" pattern="[a-zA-Z0-9]+" placeholder="Kosongkan untuk pencarian baru" class="w-full rounded-xl border border-slate-200 p-3 text-sm">
                    </div>
                    <button data-unavailable="{{ $canRunDiscovery ? 'false' : 'true' }}" id="discovery-run-button" type="submit" @disabled(!$canRunDiscovery)
                        class="inline-flex min-h-[46px] items-center justify-center gap-2 whitespace-nowrap rounded-xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2 disabled:cursor-not-allowed disabled:bg-slate-200 disabled:text-slate-500">
                        <span class="material-symbols-outlined text-[19px]">
                            search
                        </span>

                        <span data-run-label>Jalankan Pencarian</span>

                    </button>

                </form>

            @endif

        </header>
        <div data-storage-key="risengrowth-discovery-{{ auth()->id() }}" id="discovery-live-status" role="status" aria-live="polite" hidden class="mb-5 rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-800"></div>
        <button type="button" id="discovery-stop-tracking" hidden class="mb-3 text-xs text-blue-700 underline">Hentikan pemantauan permintaan (tidak membatalkan job)</button>
        <p class="mb-5 text-xs text-slate-500" id="discovery-live-health">Status dan riwayat diperbarui otomatis setiap 4 detik.</p>


        <section class="rg-panel mb-6 rounded-xl border border-slate-200 bg-white p-5 space-y-3">
            <h2 class="text-lg font-bold">Threads via Apify</h2>
            <p class="text-sm text-slate-600">{{ $apifyConfigured ? 'Token server tersedia' : 'APIFY_TOKEN belum diisi di server' }} · Provider {{ $apifySettings->enabled ? 'aktif' : 'nonaktif' }} · Otomatis {{ $apifySettings->automation_enabled ? 'aktif' : 'nonaktif' }}{{ $apifyPaused ? ' · Dijeda karena kuota/rate limit' : '' }}</p>
            <p class="text-xs text-slate-600">Maksimal 10 postingan per run. Batas start {{ $apifySettings->daily_runs }}/hari UTC; sudah dicoba {{ $apifyStartsToday }}. Batas biaya per run USD {{ config('services.apify.probe_max_charge_usd') }}; budget bulanan aplikasi USD {{ config('services.apify.monthly_budget_usd') }}.</p>
            <p class="text-xs text-slate-500">Run baru memakai kredit Apify. Untuk hasil yang sudah ada, masukkan ID run lama dan pilih keyword yang sama. Impor tidak memulai Actor baru. Saldo kredit gratis harus diperiksa di billing Apify.</p>
            @if($apifyUsage)<p class="text-xs text-slate-500">Pemakaian akun terakhir dilaporkan USD {{ $apifyUsage['usage_usd'] }} / limit akun USD {{ $apifyUsage['limit_usd'] }} pada {{ $apifyUsage['checked_at'] }}. Ini bukan saldo kredit gratis real-time.</p>@endif
            @if($isAdmin)
                <form method="POST" action="{{ route('discovery.apify.settings') }}" class="rg-provider-settings">
                    @csrf @method('PATCH')
                    <label class="rg-provider-toggle text-sm"><input type="hidden" name="enabled" value="0"><input type="checkbox" name="enabled" value="1" @checked($apifySettings->enabled)> Aktifkan Apify</label>
                    <label class="rg-provider-toggle text-sm"><input type="hidden" name="automation_enabled" value="0"><input type="checkbox" name="automation_enabled" value="1" @checked($apifySettings->automation_enabled)> Cari otomatis</label>
                    <label class="rg-provider-number">Batas start/hari UTC<input type="number" name="daily_runs" min="1" max="10" required value="{{ $apifySettings->daily_runs }}" class="mt-1 block w-28 rounded-lg border border-slate-200 p-2"></label>
                    <label class="rg-provider-number">Interval minimum (menit)<input type="number" name="min_interval_minutes" min="360" max="10080" required value="{{ $apifySettings->min_interval_minutes }}" class="mt-1 block w-36 rounded-lg border border-slate-200 p-2"></label>
                    <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm text-white">Simpan pengaturan</button>
                </form>
            @endif
            <details class="text-xs text-slate-600"><summary class="cursor-pointer font-semibold">Jadwal keyword aktif</summary>
                @foreach($enabledKeywords as $keyword)<p class="mt-2">{{ $keyword->phrase }} · {{ max($apifySettings->min_interval_minutes, $keyword->interval_minutes ?? 360) }} menit · Jatuh tempo {{ $nextScans[$keyword->id]->format('d M Y H:i') }} ({{ config('app.timezone') }}). Eksekusi mengikuti kuota harian, worker, dan status otomatis.</p>@endforeach
            </details>
            <a href="{{ route('leads.index', ['source' => 'apify']) }}" class="inline-block text-sm font-semibold text-blue-700 underline">Buka lead hasil Apify →</a>
        </section>

        {{-- ================================================================ --}}
        {{-- FLASH MESSAGES                                                   --}}
        {{-- ================================================================ --}}




        {{-- ================================================================ --}}
        {{-- DISCOVERY AVAILABILITY                                            --}}
        {{-- ================================================================ --}}

        @if (!$isAdmin)
            <div class="mb-6 flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4">

                <span class="material-symbols-outlined text-[20px] text-slate-500">
                    info
                </span>

                <div>

                    <p class="text-sm font-semibold text-slate-800">
                        Pencarian dikelola Administrator
                    </p>

                    <p class="mt-1 text-xs leading-relaxed text-slate-500">
                        Kamu tetap dapat melihat hasil discovery dan riwayat pencarian tim.
                    </p>

                </div>

            </div>
        @elseif (!$connectedAccount && !$apifySettings->enabled)
            <div
                class="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-xl border border-amber-200 bg-amber-50 p-4">

                <div class="flex items-start gap-3">

                    <span class="material-symbols-outlined text-[20px] text-amber-700">
                        link_off
                    </span>

                    <div>

                        <p class="text-sm font-semibold text-amber-900">
                            Akun Threads belum tersedia
                        </p>

                        <p class="mt-1 text-xs leading-relaxed text-amber-800">
                            Untuk mencari postingan Threads, buka Settings lalu Connect Threads. Akun harus memberikan izin threads_keyword_search. Pilihan Public web / API hanya mencari sumber web yang dikonfigurasi, bukan Threads.
                        </p>

                    </div>

                </div>

                <a href="{{ route('settings.index') }}"
                    class="inline-flex items-center gap-1 text-sm font-semibold text-amber-900 hover:underline">
                    Buka Settings

                    <span class="material-symbols-outlined text-[17px]">
                        arrow_forward
                    </span>
                </a>

            </div>
        @elseif ($enabledKeywords->isEmpty())
            <div
                class="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-xl border border-amber-200 bg-amber-50 p-4">

                <div class="flex items-start gap-3">

                    <span class="material-symbols-outlined text-[20px] text-amber-700">
                        warning
                    </span>

                    <div>

                        <p class="text-sm font-semibold text-amber-900">
                            Belum ada keyword aktif
                        </p>

                        <p class="mt-1 text-xs text-amber-800">
                            Tambahkan keyword Threads sebelum menjalankan pencarian.
                        </p>

                    </div>

                </div>

                <a href="{{ route('keywords.index') }}" class="text-sm font-semibold text-amber-900 hover:underline">
                    Kelola Keyword →
                </a>

            </div>
        @else
            <div class="mb-6 flex flex-wrap items-center gap-2 text-xs text-slate-500">

                <span class="material-symbols-outlined text-[17px] text-emerald-600">
                    check_circle
                </span>

                <span>
                    @if ($apifySettings->enabled)
                        Apify {{ $apifyConfigured ? 'aktif · token tersedia' : 'aktif · token belum tersedia' }}
                    @else
                        Threads API terhubung · Akun
                        <span class="font-semibold text-slate-700">{{ data_get($connectedAccount, 'username') }}</span>
                    @endif
                </span>

                <span class="hidden text-slate-300 sm:inline">·</span>

                <span>
                    {{ $enabledKeywords->count() }} keyword aktif
                </span>

                <span class="hidden text-slate-300 sm:inline">·</span>

                <span>
                    Apify: maksimal 10 postingan per pencarian
                </span>

            </div>
        @endif


        {{-- ================================================================ --}}
        {{-- STATISTICS                                                       --}}
        {{-- ================================================================ --}}

        <section class="mb-6 grid grid-cols-2 gap-4 xl:grid-cols-4">

            @foreach ($statistics as $stat)
                <article class="rg-panel min-h-36 rounded-2xl border border-slate-200 bg-white p-5">

                    <div class="flex items-start justify-between gap-3">

                        <p class="text-xs font-semibold text-slate-500">
                            {{ $stat['label'] }}
                        </p>

                        <span class="material-symbols-outlined text-[20px] text-slate-400">
                            {{ $stat['icon'] }}
                        </span>

                    </div>

                    <p data-discovery-metric="{{ $stat['key'] }}" class="mt-5 text-3xl font-bold tabular-nums tracking-tight text-slate-950">
                        {{ number_format((int) $stat['value'], 0, ',', '.') }}
                    </p>

                    <p class="mt-3 text-xs text-slate-500">
                        {{ $stat['note'] }}
                    </p>

                </article>
            @endforeach

        </section>


        {{-- ================================================================ --}}
        {{-- ACCOUNTS AND KEYWORDS                                             --}}
        {{-- ================================================================ --}}

        <div class="mb-6 grid grid-cols-1 gap-5 xl:grid-cols-12">


            {{-- CONNECTED ACCOUNTS --}}

            <section class="rg-panel rounded-2xl border border-slate-200 bg-white p-6 xl:col-span-5">

                <div class="mb-5 flex items-center justify-between gap-3">

                    <div>

                        <h2 class="text-lg font-bold text-slate-950">
                            Akun terhubung
                        </h2>

                        <p class="mt-1 text-xs text-slate-500">
                            Koneksi media sosial workspace.
                        </p>

                    </div>

                    <span class="material-symbols-outlined text-slate-400">
                        link
                    </span>

                </div>


                @forelse ($accounts as $account)
                    @php
                        $platform = data_get($account, 'platform', '');
                        $username = data_get($account, 'username');
                        $status = data_get($account, 'status');
                        $expiresAt = data_get($account, 'token_expires_at');
                        $verifiedAt = data_get($account, 'last_verified_at');

                        $accountActive = $status === 'connected';

                        if ($accountActive && filled($expiresAt)) {
                            try {
                                $accountActive = \Illuminate\Support\Carbon::parse($expiresAt)->isFuture();
                            } catch (\Throwable) {
                                $accountActive = false;
                            }
                        }
                    @endphp

                    <div class="flex items-center justify-between gap-3 border-t border-slate-100 py-4">

                        <div class="min-w-0">

                            <p class="truncate text-sm font-semibold text-slate-900">

                                {{ ucfirst($platform) }}

                                @if (filled($username))
                                    <span class="font-normal text-slate-400">
                                        {{ '@' . $username }}
                                    </span>
                                @endif

                            </p>

                            <p class="mt-1 text-xs text-slate-500">

                                Terakhir diverifikasi:

                                @if ($verifiedAt)
                                    {{ \Illuminate\Support\Carbon::parse($verifiedAt)->diffForHumans() }}
                                @else
                                    Belum pernah
                                @endif

                            </p>

                            @if (filled($expiresAt))
                                <p class="mt-1 text-xs text-slate-400">

                                    Token berlaku sampai:

                                    {{ \Illuminate\Support\Carbon::parse($expiresAt)->format('d M Y, H:i') }}

                                </p>
                            @endif

                        </div>

                        <span
                            class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold
                            {{ $accountActive ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">

                            {{ $accountActive ? 'Terhubung' : 'Tidak aktif' }}

                        </span>

                    </div>

                @empty

                    <div class="py-10 text-center">

                        <span class="material-symbols-outlined text-4xl text-slate-300">
                            cable
                        </span>

                        <h3 class="mt-2 font-semibold text-slate-900">
                            Belum ada akun
                        </h3>

                        <p class="mt-2 text-sm text-slate-500">
                            Hubungkan akun Threads melalui Settings.
                        </p>

                        <a href="{{ route('settings.index') }}"
                            class="mt-4 inline-flex rounded-lg border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">
                            Buka Settings
                        </a>

                    </div>
                @endforelse

            </section>


            {{-- KEYWORDS --}}

            <section class="rg-panel rounded-2xl border border-slate-200 bg-white p-6 xl:col-span-7">

                <div class="mb-5 flex items-center justify-between gap-4">

                    <div>

                        <h2 class="text-lg font-bold text-slate-950">
                            Keyword pencarian
                        </h2>

                        <p class="mt-1 text-xs text-slate-500">
                            Kata kunci untuk mencari prospek.
                        </p>

                    </div>

                    <a href="{{ route('keywords.index') }}"
                        class="shrink-0 text-xs font-semibold text-blue-700 hover:underline">
                        Kelola keyword →
                    </a>

                </div>


                @forelse ($keywords->take(6) as $keyword)
                    @php
                        $keywordEnabled = (bool) data_get($keyword, 'enabled', false);
                    @endphp

                    <div class="flex items-center justify-between gap-3 border-t border-slate-100 py-3">

                        <div class="min-w-0">

                            <p class="truncate text-sm font-semibold text-slate-900">
                                {{ data_get($keyword, 'phrase') }}
                            </p>

                            <p class="mt-0.5 text-xs text-slate-500">

                                {{ ucfirst(str_replace('_', ' ', data_get($keyword, 'category', '-'))) }}

                                <span class="mx-1">·</span>

                                {{ ucfirst(data_get($keyword, 'platform', '')) }}

                            </p>

                        </div>

                        <span
                            class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold
                            {{ $keywordEnabled ? 'bg-blue-50 text-blue-700' : 'bg-slate-100 text-slate-500' }}">

                            {{ $keywordEnabled ? 'Aktif' : 'Nonaktif' }}

                        </span>

                    </div>

                @empty

                    <div class="py-10 text-center">

                        <span class="material-symbols-outlined text-4xl text-slate-300">
                            manage_search
                        </span>

                        <h3 class="mt-2 font-semibold text-slate-900">
                            Belum ada keyword
                        </h3>

                        <p class="mt-2 text-sm text-slate-500">
                            Tambahkan keyword untuk memulai discovery.
                        </p>

                    </div>
                @endforelse

            </section>

        </div>


        {{-- ================================================================ --}}
        {{-- DISCOVERY HISTORY                                                 --}}
        {{-- ================================================================ --}}

        <section class="rg-panel rounded-2xl border border-slate-200 bg-white p-6">

            <div class="mb-5 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">

                <div>

                    <h2 class="text-lg font-bold text-slate-950">
                        Riwayat pencarian
                    </h2>

                    <p class="mt-1 text-xs text-slate-500">
                        Riwayat proses dan hasil pencarian Threads.
                    </p>

                </div>


                {{-- HISTORY FILTER --}}

                <form method="GET" action="{{ route('discovery.index') }}" class="flex flex-wrap items-center gap-2">
                    <input type="hidden" name="prefer_indonesian" value="{{ (bool) ($filters['prefer_indonesian'] ?? true) ? '1' : '0' }}">

                    <label for="discovery-status" class="sr-only">
                        Status
                    </label>

                    <select id="discovery-status" name="status"
                        class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">

                        <option value="">
                            Semua status
                        </option>

                        @foreach ($statusLabels as $key => $label)
                            <option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>
                                {{ $label }}
                            </option>
                        @endforeach

                    </select>


                    <label for="discovery-keyword" class="sr-only">
                        Keyword
                    </label>

                    <select id="discovery-keyword" name="keyword"
                        class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">

                        <option value="">
                            Semua keyword
                        </option>

                        @foreach ($keywords as $keyword)
                            <option value="{{ data_get($keyword, 'id') }}" @selected((string) ($filters['keyword'] ?? '') === (string) data_get($keyword, 'id'))>
                                {{ data_get($keyword, 'phrase') }}
                            </option>
                        @endforeach

                    </select>


                    <button type="submit"
                        class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800">
                        Terapkan
                    </button>

                    <a href="{{ route('discovery.index') }}"
                        class="px-2 py-2 text-sm text-slate-500 transition hover:text-slate-900">
                        Reset
                    </a>

                </form>

            </div>


            {{-- HISTORY TABLE --}}

            <div class="overflow-x-auto">

                <table class="w-full min-w-[720px] text-sm">

                    <thead>

                        <tr class="border-y border-slate-100 text-left text-xs text-slate-500">

                            <th class="py-3 pr-4 font-semibold">
                                Keyword
                            </th>

                            <th class="py-3 pr-4 font-semibold">
                                Platform
                            </th>

                            <th class="py-3 pr-4 font-semibold">
                                Status
                            </th>

                            <th class="py-3 pr-4 font-semibold">
                                Diambil
                            </th>

                            <th class="py-3 pr-4 font-semibold">
                                Ditambahkan
                            </th>

                            <th class="py-3 font-semibold">
                                Waktu
                            </th>

                        </tr>

                    </thead>


                    <tbody id="discovery-run-rows">
                        @include('discovery.partials.run-rows', ['runs' => $runs])
                    </tbody>

                </table>

            </div>


            {{-- PAGINATION --}}

            @if ($runs->hasPages())
                <div class="mt-5 border-t border-slate-100 pt-5">
                    {{ $runs->links() }}
                </div>
            @endif

        </section>

        <section class="rg-panel mt-6 rounded-xl border border-slate-200 bg-white p-5 space-y-4">
            <h2 class="text-lg font-bold">Review postingan ditemukan</h2>
            <p class="text-xs text-slate-500">Prioritas Indonesia memakai perkiraan kata dalam teks, bukan lokasi akun. Tidak menyaring hasil dari negara lain. Untuk pencarian, coba keyword spesifik seperti “butuh website”, “cari jasa website”, atau “website toko”.</p>
            <p class="text-sm text-slate-600">Sumber Python opsional: {{ $activeSources->join(', ') ?: 'Tidak ada' }}. Scan berhasil terakhir: {{ $lastSuccessfulScan ?? 'Belum ada' }}.</p>
            <p class="text-xs text-slate-500">Threads via Apify memakai token server. Opsi Threads API resmi memerlukan akun dan izin Meta. Hasil public web berasal dari sumber yang dikonfigurasi admin.</p>
            <form method="GET" action="{{ route('discovery.index') }}" class="flex flex-wrap gap-3">
                <label class="flex items-center gap-2 text-sm text-slate-600"><input type="hidden" name="prefer_indonesian" value="0"><input type="checkbox" name="prefer_indonesian" value="1" @checked((bool) ($filters['prefer_indonesian'] ?? true))> Utamakan bahasa Indonesia</label>
                <label class="text-sm">Kualifikasi
                    <select name="qualification" class="rounded-lg border border-slate-200 p-2">
                        <option value="">Semua postingan</option>
                        @foreach(['qualified' => 'Qualified', 'uncertain' => 'Perlu review', 'rejected' => 'Ditolak'] as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['qualification'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm text-white">Filter</button>
            </form>
            <div id="discovery-post-reviews" class="rg-review-list">@include('discovery.partials.post-reviews')</div>
        </section>
    </div>

@endsection

@push('scripts')
<script src="{{ asset('discovery-live.js').'?v='.filemtime(public_path('discovery-live.js')) }}" defer></script>
@endpush
