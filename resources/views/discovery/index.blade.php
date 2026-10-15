@extends('layouts.workspace')

@section('title', 'Eksplorasi')
@section('page_heading', 'Eksplorasi')

@section('content')

    @php
        $isAdmin = auth()->user()?->role === 'admin';

        $connectedAccount = $accounts->first(function ($account) {
            if ($account->platform !== 'threads' || $account->status !== 'connected') {
                return false;
            }

            if (empty($account->token_expires_at)) {
                return true;
            }

            return \Illuminate\Support\Carbon::parse($account->token_expires_at)->isFuture();
        });

        $enabledKeywords = $keywords->filter(
            fn($keyword) => $keyword->platform === 'threads' && (bool) $keyword->enabled,
        );

        $canRunDiscovery = $isAdmin && $connectedAccount !== null && $enabledKeywords->isNotEmpty();
    @endphp

    <div class="mx-auto max-w-[1500px] px-5 pb-12 pt-9 lg:px-10">

        {{-- PAGE HEADER --}}
        <header class="mb-8 flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">

            <div>
                <p class="mb-2 text-[11px] font-bold uppercase tracking-[.18em] text-slate-500">
                    RISENGROWTH / DISCOVERY
                </p>

                <h1 class="text-[30px] font-bold tracking-[-.045em] text-slate-950 md:text-[38px]">
                    Eksplorasi Prospek
                </h1>

                <p class="mt-2 max-w-xl text-sm text-slate-500">
                    Temukan percakapan yang relevan dengan layanan RisenDev.
                </p>
            </div>

            {{-- SEARCH FORM --}}
            <form method="POST" action="{{ route('discovery.run') }}"
                class="flex w-full flex-col gap-3 sm:flex-row lg:w-auto">
                @csrf

                <label for="run-keyword" class="sr-only">
                    Pilih keyword
                </label>

                <select id="run-keyword" name="keyword_id" required @disabled(!$canRunDiscovery)
                    class="min-w-[220px] rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-slate-400 focus:ring-2 focus:ring-slate-100 disabled:cursor-not-allowed disabled:bg-slate-100">
                    <option value="">Pilih keyword</option>

                    @foreach ($enabledKeywords as $keyword)
                        <option value="{{ $keyword->id }}" @selected(old('keyword_id') == $keyword->id)>
                            {{ $keyword->phrase }}
                            ({{ strtoupper($keyword->search_type) }})
                        </option>
                    @endforeach
                </select>

                <button type="submit" @disabled(!$canRunDiscovery)
                    class="inline-flex items-center justify-center rounded-xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:bg-slate-200 disabled:text-slate-500">
                    <span class="material-symbols-outlined mr-2 text-[19px]">
                        search
                    </span>
                    Jalankan Pencarian
                </button>
            </form>
        </header>

        {{-- FLASH MESSAGES --}}
        @if (session('success'))
            <div role="status"
                class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div role="alert" class="mb-5 rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-700">
                <p class="font-semibold">
                    Pencarian tidak dapat dijalankan
                </p>

                <ul class="mt-2 list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- DISCOVERY AVAILABILITY --}}
        @if (!$isAdmin)
            <div class="mb-6 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                Pencarian manual dikelola Administrator.
                Kamu tetap bisa melihat hasil dan riwayat tim.
            </div>
        @elseif (!$connectedAccount)
            <div
                class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4">
                <p class="text-sm text-amber-800">
                    Belum ada akun Threads aktif atau token koneksi telah kedaluwarsa.
                </p>

                <a href="{{ route('settings.index') }}" class="text-sm font-semibold text-amber-900 underline">
                    Buka Settings
                </a>
            </div>
        @elseif ($enabledKeywords->isEmpty())
            <div
                class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4">
                <p class="text-sm text-amber-800">
                    Belum ada keyword Threads yang aktif.
                </p>

                <a href="{{ route('keywords.index') }}" class="text-sm font-semibold text-amber-900 underline">
                    Kelola Keyword
                </a>
            </div>
        @endif

        {{-- TEMPORARY DEBUG PANEL --}}
        @if ($isAdmin && config('app.debug'))
            <section class="mb-6 rounded-xl border border-dashed border-slate-300 bg-slate-50 p-5">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-slate-600">
                        bug_report
                    </span>

                    <h2 class="text-sm font-semibold text-slate-900">
                        Discovery Diagnostics
                    </h2>
                </div>

                <p class="mt-1 text-xs text-slate-500">
                    Hanya ditampilkan ketika APP_DEBUG=true.
                </p>

                <div class="mt-4 grid grid-cols-2 gap-3 text-sm md:grid-cols-4">
                    <div>
                        <p class="text-xs text-slate-500">Admin</p>
                        <p class="font-semibold">
                            {{ $isAdmin ? 'YES' : 'NO' }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs text-slate-500">Threads Connected</p>
                        <p class="font-semibold">
                            {{ $connectedAccount ? 'YES' : 'NO' }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs text-slate-500">Enabled Keywords</p>
                        <p class="font-semibold">
                            {{ $enabledKeywords->count() }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs text-slate-500">Can Run</p>
                        <p class="font-semibold">
                            {{ $canRunDiscovery ? 'YES' : 'NO' }}
                        </p>
                    </div>
                </div>
            </section>
        @endif

        {{-- STATISTICS --}}
        <section class="mb-6 grid grid-cols-2 gap-4 xl:grid-cols-4">

            @foreach ([
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
        ] as $stat)
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
                        {{ number_format($stat['value'], 0, ',', '.') }}
                    </p>

                    <p class="mt-3 text-xs text-slate-500">
                        {{ $stat['note'] }}
                    </p>
                </article>
            @endforeach
        </section>

        {{-- ACCOUNTS AND KEYWORDS --}}
        <div class="mb-6 grid grid-cols-1 gap-5 xl:grid-cols-12">

            {{-- CONNECTED ACCOUNTS --}}
            <section class="rounded-2xl border border-slate-200 bg-white p-6 xl:col-span-5">

                <div class="mb-5 flex items-center justify-between">
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
                    <div class="flex items-center justify-between gap-3 border-t border-slate-100 py-4">

                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-slate-900">
                                {{ ucfirst($account->platform) }}

                                @if ($account->username)
                                    <span class="font-normal text-slate-400">
                                        {{ '@' . $account->username }}
                                    </span>
                                @endif
                            </p>

                            <p class="mt-1 text-xs text-slate-500">
                                Terakhir diverifikasi:
                                {{ $account->last_verified_at
                                    ? \Illuminate\Support\Carbon::parse($account->last_verified_at)->diffForHumans()
                                    : 'Belum pernah' }}
                            </p>
                        </div>

                        @php
                            $accountActive =
                                $account->status === 'connected' &&
                                (!$account->token_expires_at ||
                                    \Illuminate\Support\Carbon::parse($account->token_expires_at)->isFuture());
                        @endphp

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
                            Kata kunci yang digunakan dalam pencarian.
                        </p>
                    </div>

                    <a href="{{ route('keywords.index') }}"
                        class="shrink-0 text-xs font-semibold text-blue-700 hover:underline">
                        Kelola keyword →
                    </a>
                </div>

                @forelse ($keywords->take(6) as $keyword)
                    <div class="flex items-center justify-between gap-3 border-t border-slate-100 py-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-slate-900">
                                {{ $keyword->phrase }}
                            </p>

                            <p class="mt-0.5 text-xs text-slate-500">
                                {{ ucfirst(str_replace('_', ' ', $keyword->category)) }}
                                ·
                                {{ ucfirst($keyword->platform) }}
                            </p>
                        </div>

                        <span
                            class="rounded-full px-2.5 py-1 text-xs font-semibold
                            {{ $keyword->enabled ? 'bg-blue-50 text-blue-700' : 'bg-slate-100 text-slate-500' }}">
                            {{ $keyword->enabled ? 'Aktif' : 'Nonaktif' }}
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

        {{-- DISCOVERY HISTORY --}}
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

                <form method="GET" action="{{ route('discovery.index') }}" class="flex flex-wrap items-center gap-2">
                    <label for="discovery-status" class="sr-only">
                        Status
                    </label>

                    <select id="discovery-status" name="status"
                        class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                        <option value="">Semua status</option>

                        @foreach ([
            'queued' => 'Antrean',
            'running' => 'Berjalan',
            'completed' => 'Selesai',
            'failed' => 'Gagal',
            'partial' => 'Sebagian',
        ] as $key => $label)
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
                        <option value="">Semua keyword</option>

                        @foreach ($keywords as $keyword)
                            <option value="{{ $keyword->id }}" @selected((string) ($filters['keyword'] ?? '') === (string) $keyword->id)>
                                {{ $keyword->phrase }}
                            </option>
                        @endforeach
                    </select>

                    <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">
                        Terapkan
                    </button>

                    <a href="{{ route('discovery.index') }}"
                        class="px-2 py-2 text-sm text-slate-500 hover:text-slate-900">
                        Reset
                    </a>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] text-sm">

                    <thead>
                        <tr class="border-y border-slate-100 text-left text-xs text-slate-500">
                            <th class="py-3 pr-4 font-semibold">Keyword</th>
                            <th class="py-3 pr-4 font-semibold">Platform</th>
                            <th class="py-3 pr-4 font-semibold">Status</th>
                            <th class="py-3 pr-4 font-semibold">Diambil</th>
                            <th class="py-3 pr-4 font-semibold">Ditambahkan</th>
                            <th class="py-3 font-semibold">Waktu</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($runs as $run)
                            <tr class="border-b border-slate-100">

                                <td class="py-4 pr-4">
                                    <p class="font-semibold text-slate-900">
                                        {{ $run->keyword_phrase }}
                                    </p>

                                    @if ($run->error_summary)
                                        <p class="mt-1 max-w-xs break-words text-xs text-red-700">
                                            {{ $run->error_summary }}
                                        </p>
                                    @endif
                                </td>

                                <td class="py-4 pr-4 text-slate-600">
                                    {{ ucfirst($run->platform) }}
                                </td>

                                <td class="py-4 pr-4">
                                    <span
                                        class="rounded-full px-2.5 py-1 text-xs font-semibold
                                        {{ $run->status === 'completed'
                                            ? 'bg-emerald-50 text-emerald-700'
                                            : ($run->status === 'failed'
                                                ? 'bg-red-50 text-red-700'
                                                : 'bg-slate-100 text-slate-600') }}">
                                        {{ ucfirst($run->status) }}
                                    </span>
                                </td>

                                <td class="py-4 pr-4 tabular-nums">
                                    {{ $run->fetched_count }}
                                </td>

                                <td class="py-4 pr-4 tabular-nums">
                                    {{ $run->added_count }}
                                </td>

                                <td class="whitespace-nowrap py-4 text-slate-500">
                                    {{ \Illuminate\Support\Carbon::parse($run->created_at)->format('d M Y, H:i') }}
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
                                        Pilih keyword lalu jalankan pencarian.
                                    </p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-5">
                {{ $runs->links() }}
            </div>
        </section>
    </div>

@endsection
