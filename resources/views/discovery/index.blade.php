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

        $canRunDiscovery = $isAdmin && $connectedAccount !== null && $enabledKeywords->isNotEmpty();

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
                'value' => $totalRuns,
                'note' => 'Seluruh pencarian',
                'icon' => 'manage_search',
            ],
            [
                'label' => 'Proses selesai',
                'value' => $completedRuns,
                'note' => 'Pencarian berhasil',
                'icon' => 'task_alt',
            ],
            [
                'label' => 'Postingan ditemukan',
                'value' => $totalDiscovered,
                'note' => 'Postingan unik',
                'icon' => 'forum',
            ],
            [
                'label' => 'Keyword aktif',
                'value' => $activeKeywords,
                'note' => 'Keyword dipantau',
                'icon' => 'tag',
            ],
        ];
    @endphp

    <div class="mx-auto max-w-[1500px] px-5 pb-12 pt-9 lg:px-10">

        {{-- ================================================================ --}}
        {{-- PAGE HEADER                                                      --}}
        {{-- ================================================================ --}}

        <header class="mb-8 flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">

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
                    class="flex w-full flex-col gap-3 sm:flex-row lg:w-auto">
                    @csrf

                    <div class="w-full sm:w-auto">

                        <label for="run-keyword" class="sr-only">
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

                    <button id="discovery-run-button" type="submit" @disabled(!$canRunDiscovery)
                        class="inline-flex min-h-[46px] items-center justify-center gap-2 whitespace-nowrap rounded-xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2 disabled:cursor-not-allowed disabled:bg-slate-200 disabled:text-slate-500">
                        <span class="material-symbols-outlined text-[19px]">
                            search
                        </span>

                        <span>Jalankan Pencarian</span>

                    </button>

                </form>

            @endif

        </header>


        {{-- ================================================================ --}}
        {{-- FLASH MESSAGES                                                   --}}
        {{-- ================================================================ --}}

        @if (session('success'))
            <div role="status"
                class="mb-5 flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4">

                <span class="material-symbols-outlined text-[20px] text-emerald-700">
                    check_circle
                </span>

                <div class="min-w-0">

                    <p class="text-sm font-semibold text-emerald-900">
                        Pencarian berhasil
                    </p>

                    <p class="mt-1 text-sm leading-relaxed text-emerald-800">
                        {{ session('success') }}
                    </p>

                </div>

            </div>
        @endif


        @if ($errors->any())

            <div role="alert" class="mb-5 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-5 py-4">

                <span class="material-symbols-outlined text-[20px] text-red-700">
                    error
                </span>

                <div class="min-w-0">

                    <p class="text-sm font-semibold text-red-900">
                        Pencarian tidak dapat dijalankan
                    </p>

                    <ul class="mt-2 list-inside list-disc space-y-1 text-sm leading-relaxed text-red-700">

                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach

                    </ul>

                </div>

            </div>

        @endif


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
        @elseif (!$connectedAccount)
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
                            Hubungkan akun Threads atau periksa masa berlaku token melalui Settings.
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
                    Siap mencari menggunakan akun
                    <span class="font-semibold text-slate-700">
                        {{ '@' . data_get($connectedAccount, 'username', 'Threads') }}
                    </span>
                </span>

                <span class="hidden text-slate-300 sm:inline">·</span>

                <span>
                    {{ $enabledKeywords->count() }} keyword aktif
                </span>

                <span class="hidden text-slate-300 sm:inline">·</span>

                <span>
                    Maksimal 25 hasil per pencarian
                </span>

            </div>
        @endif


        {{-- ================================================================ --}}
        {{-- STATISTICS                                                       --}}
        {{-- ================================================================ --}}

        <section class="mb-6 grid grid-cols-2 gap-4 xl:grid-cols-4">

            @foreach ($statistics as $stat)
                <article class="min-h-36 rounded-2xl border border-slate-200 bg-white p-5">

                    <div class="flex items-start justify-between gap-3">

                        <p class="text-xs font-semibold text-slate-500">
                            {{ $stat['label'] }}
                        </p>

                        <span class="material-symbols-outlined text-[20px] text-slate-400">
                            {{ $stat['icon'] }}
                        </span>

                    </div>

                    <p class="mt-5 text-3xl font-bold tabular-nums tracking-tight text-slate-950">
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

            <section class="rounded-2xl border border-slate-200 bg-white p-6 xl:col-span-5">

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

            <section class="rounded-2xl border border-slate-200 bg-white p-6 xl:col-span-7">

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

        <section class="rounded-2xl border border-slate-200 bg-white p-6">

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


                    <tbody>

                        @forelse ($runs as $run)
                            @php
                                $runStatus = data_get($run, 'status', 'queued');

                                $statusLabel = $statusLabels[$runStatus] ?? ucfirst($runStatus);

                                $statusClass = $statusClasses[$runStatus] ?? 'bg-slate-100 text-slate-600';
                            @endphp

                            <tr class="border-b border-slate-100">

                                <td class="py-4 pr-4">

                                    <p class="font-semibold text-slate-900">
                                        {{ data_get($run, 'keyword_phrase', '-') }}
                                    </p>

                                    @if (filled(data_get($run, 'error_summary')))
                                        <p class="mt-1 max-w-xs break-words text-xs leading-relaxed text-red-700">
                                            {{ data_get($run, 'error_summary') }}
                                        </p>
                                    @endif

                                </td>


                                <td class="py-4 pr-4 text-slate-600">
                                    {{ ucfirst(data_get($run, 'platform', '-')) }}
                                </td>


                                <td class="py-4 pr-4">

                                    <span
                                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">
                                        {{ $statusLabel }}
                                    </span>

                                </td>


                                <td class="py-4 pr-4 tabular-nums text-slate-700">
                                    {{ number_format((int) data_get($run, 'fetched_count', 0), 0, ',', '.') }}
                                </td>


                                <td class="py-4 pr-4 tabular-nums text-slate-700">
                                    {{ number_format((int) data_get($run, 'added_count', 0), 0, ',', '.') }}
                                </td>


                                <td class="whitespace-nowrap py-4 text-slate-500">

                                    @if (data_get($run, 'created_at'))
                                        {{ \Illuminate\Support\Carbon::parse(data_get($run, 'created_at'))->format('d M Y, H:i') }}
                                    @else
                                        -
                                    @endif

                                </td>

                            </tr>

                        @empty

                            <tr>

                                <td colspan="6" class="py-16 text-center">

                                    <span class="material-symbols-outlined text-4xl text-slate-300">
                                        history
                                    </span>

                                    <p class="mt-2 font-semibold text-slate-900">
                                        Belum ada riwayat pencarian
                                    </p>

                                    <p class="mt-1 text-sm text-slate-500">
                                        Pilih keyword lalu jalankan pencarian pertama.
                                    </p>

                                </td>

                            </tr>
                        @endforelse

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

    </div>

@endsection
