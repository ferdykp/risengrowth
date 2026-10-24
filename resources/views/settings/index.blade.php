@extends('layouts.workspace')
@section('title', 'Pengaturan')
@section('page_heading', 'Pengaturan')
@section('content')
    <div class="space-y-6">
        <div>
            <p class="text-[11px] font-bold tracking-[.18em] text-slate-500 uppercase">RISENGROWTH / SETTINGS</p>
            <h1 class="text-3xl lg:text-4xl font-bold tracking-tight mt-2">Pengaturan</h1>
            <p class="mt-2 text-sm text-slate-500">Kelola profil, integrasi, dan preferensi workspace.</p>
        </div>


        <div class="grid grid-cols-1 xl:grid-cols-12 gap-5 items-start">
            <div class="xl:col-span-7 space-y-5">
                <section class="rg-panel rounded-2xl bg-white border border-slate-200 p-6">
                    <h2 class="text-lg font-bold">Profil akun</h2>
                    <p class="text-xs text-slate-500 mt-1">Informasi yang digunakan untuk masuk ke workspace.</p>
                    <form action="{{ route('settings.profile') }}" method="POST" class="mt-5 space-y-4">@csrf
                        @method('PATCH')
                        <div><label class="block text-xs font-semibold mb-2" for="name">Nama</label><input
                                id="name" name="name" required maxlength="100"
                                value="{{ old('name', $user->name) }}"
                                class="block w-full rounded-xl border border-slate-200 px-3.5 py-3 text-sm focus:border-slate-900 focus:outline-none">
                        </div>
                        <div><label class="block text-xs font-semibold mb-2" for="email">Email</label><input
                                id="email" type="email" name="email" required maxlength="191"
                                value="{{ old('email', $user->email) }}"
                                class="block w-full rounded-xl border border-slate-200 px-3.5 py-3 text-sm focus:border-slate-900 focus:outline-none">
                        </div>
                        <button
                            class="rounded-lg bg-black px-4 py-2.5 text-white text-sm font-semibold hover:bg-slate-800">Simpan
                            profil</button>
                    </form>
                </section>
                <section class="rg-panel rounded-2xl bg-white border border-slate-200 p-6">
                    <h2 class="text-lg font-bold">Preferensi pencarian</h2>
                    <p class="text-xs text-slate-500 mt-1">Preferensi ini disimpan, tetapi pencarian otomatis belum
                        dijalankan sebelum integrasi Threads siap.</p>
                    <form action="{{ route('settings.preferences') }}" method="POST" class="mt-5 space-y-5">@csrf
                        @method('PATCH')
                        <div><label for="discovery_interval_minutes" class="block text-xs font-semibold mb-2">Interval
                                pencarian yang diinginkan</label><select id="discovery_interval_minutes"
                                name="discovery_interval_minutes"
                                class="w-full rounded-xl border border-slate-200 px-3.5 py-3 text-sm">
                                @foreach ([30 => '30 menit', 60 => '1 jam', 180 => '3 jam', 360 => '6 jam', 720 => '12 jam', 1440 => '24 jam'] as $interval => $label)
                                    <option value="{{ $interval }}" @selected((int) old('discovery_interval_minutes', $settings->discovery_interval_minutes) === $interval)>{{ $label }}
                                    </option>
                                @endforeach
                            </select></div>
                        <div><label class="flex gap-3 items-start"><input type="hidden" name="notify_on_new_leads"
                                    value="0"><input type="checkbox" name="notify_on_new_leads" value="1"
                                    @checked((bool) old('notify_on_new_leads', $settings->notify_on_new_leads)) class="mt-1 h-4 w-4"><span><strong
                                        class="text-sm">Notifikasi prospek baru</strong><span
                                        class="block text-xs text-slate-500 mt-1">Preferensi tersimpan; pengiriman
                                        notifikasi belum diaktifkan.</span></span></label></div>
                        <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900">Status
                            automation: <strong>Belum aktif</strong>. Menyimpan pengaturan tidak menjalankan job terjadwal.
                        </div>
                        <button
                            class="rounded-lg bg-black px-4 py-2.5 text-white text-sm font-semibold hover:bg-slate-800">Simpan
                            preferensi</button>
                    </form>
                </section>
            </div>
            <div class="xl:col-span-5 space-y-5">

                <section class="rg-panel rounded-2xl border border-slate-200 bg-white p-6">

                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-bold text-slate-950">
                                Akun media sosial
                            </h2>

                            <p class="mt-1 text-xs text-slate-500">
                                Hubungkan akun Threads untuk kebutuhan
                                discovery RisenGrowth.
                            </p>
                        </div>
                    </div>

                    @if ($isAdmin)

                        @php
                            $connectedThreads = $accounts->first(
                                fn($account) => $account->platform === 'threads' && $account->isConnected(),
                            );
                        @endphp

                        <div class="mt-5 rounded-xl border border-slate-200 p-4">

                            <div class="flex items-center justify-between gap-3">

                                <div>
                                    <p class="text-sm font-semibold text-slate-950">
                                        Threads
                                    </p>

                                    <p class="mt-1 text-xs text-slate-500">
                                        Integrasi resmi Meta
                                    </p>
                                </div>

                                @if ($connectedThreads)
                                    <span
                                        class="rounded-full bg-emerald-50 px-3 py-1
                                 text-xs font-semibold text-emerald-700">
                                        Connected
                                    </span>
                                @else
                                    <span
                                        class="rounded-full bg-amber-50 px-3 py-1
                                 text-xs font-semibold text-amber-700">
                                        Not connected
                                    </span>
                                @endif

                            </div>

                            @if ($connectedThreads)

                                <div class="mt-5 rounded-xl bg-slate-50 p-4">

                                    <p class="text-sm font-semibold">
                                        {{ '@' . $connectedThreads->username }}
                                    </p>

                                    <p class="mt-1 text-xs text-slate-500">
                                        Threads Account ID:
                                        {{ $connectedThreads->external_account_id }}
                                    </p>

                                    <p class="mt-2 text-xs text-slate-500">
                                        Token berlaku sampai:
                                        {{ $connectedThreads->token_expires_at?->copy()->timezone('Asia/Jakarta')->format('d M Y H:i \W\I\B') ?? 'Tidak diketahui' }}
                                    </p>

                                </div>

                                <form action="{{ route('threads.disconnect', $connectedThreads) }}" method="POST"
                                    class="mt-4">
                                    @csrf
                                    @method('DELETE')

                                    <button type="submit"
                                        class="rounded-xl border border-rose-200
                               px-4 py-2.5 text-sm font-semibold
                               text-rose-700 hover:bg-rose-50">
                                        Disconnect Threads
                                    </button>
                                </form>
                            @else
                                <p class="mt-4 text-sm text-slate-600">
                                    Belum ada akun Threads yang terhubung.
                                </p>

                                @if ($threadsConfigured)
                                    <form action="{{ route('threads.connect') }}" method="POST" class="mt-4">
                                        @csrf

                                        <button type="submit"
                                            class="inline-flex items-center justify-center
                                   rounded-xl bg-slate-950 px-5 py-3
                                   text-sm font-semibold text-white
                                   hover:bg-slate-800">
                                            Connect Threads
                                        </button>
                                    </form>
                                @else
                                    <div
                                        class="mt-4 rounded-xl border border-amber-200
                                bg-amber-50 p-3 text-xs text-amber-800">
                                        Konfigurasi Meta Threads belum lengkap.
                                        Periksa file .env server.
                                    </div>
                                @endif

                            @endif

                        </div>
                    @else
                        <div class="mt-5 rounded-xl bg-slate-50 p-4">
                            <p class="text-sm font-medium">
                                Integrasi dikelola oleh Administrator.
                            </p>
                            <p class="mt-1 text-xs text-slate-500">
                                Member dapat menggunakan fitur workspace,
                                tetapi tidak dapat mengubah koneksi sosial.
                            </p>
                        </div>

                    @endif

                    <div class="mt-4 border-t border-slate-100 pt-4">

                        <p class="text-sm font-semibold text-slate-900">
                            Instagram
                        </p>

                        <p class="mt-1 text-xs text-slate-500">
                            Integrasi Instagram akan dibuat secara
                            terpisah setelah koneksi Threads selesai.
                        </p>

                    </div>

                </section>
                <section class="rg-panel rounded-2xl bg-white border border-slate-200 p-6">
                    <h2 class="text-lg font-bold">AI Assistant</h2>
                    <p class="text-xs text-slate-500 mt-1">Konfigurasi server, tanpa menampilkan API key.</p>
                    <div class="mt-5 flex items-center justify-between gap-3"><span class="text-sm font-semibold">Groq
                            API</span><span
                            class="rounded-full px-3 py-1 text-xs font-semibold {{ $groqConfigured ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-800' }}">{{ $groqConfigured ? 'Key tersedia' : 'Belum dikonfigurasi' }}</span>
                    </div>
                    <div class="mt-4 border-t border-slate-100 pt-4">
                        <p class="text-xs text-slate-500">Model</p>
                        <p class="text-sm font-medium mt-1">{{ $groqModel }}</p>
                    </div>
                    <p class="text-xs text-slate-500 mt-4">Ketersediaan key tidak memastikan API dapat diakses. Atur
                        <code>GROQ_API_KEY</code> di environment server.
                    </p>
                </section>
                <section class="rounded-2xl border border-slate-200 p-5 bg-slate-50">
                    <h2 class="font-bold text-sm">Keamanan</h2>
                    <p class="text-xs text-slate-500 mt-2">Rahasia integrasi tidak ditampilkan. Perubahan profil dan
                        preferensi membutuhkan login. Publikasi komentar tetap memerlukan approval terpisah.</p>
                </section>
            </div>
        </div>
    </div>
    <section class="rg-panel bg-white border border-slate-200 rounded-2xl p-6 my-6">
        <h2 class="font-semibold text-lg">Keamanan akun</h2>
        <p class="text-sm text-slate-500 mt-1 mb-4">Ubah password. Kamu akan diminta login ulang.</p>
        <form method="POST" action="{{ route('account.password.update') }}" class="space-y-3 max-w-md">@csrf
            @method('PUT')<label class="block text-sm" for="current_password">Password saat ini</label><input
                id="current_password" type="password" name="current_password" autocomplete="current-password" required
                class="w-full border border-slate-300 rounded-lg p-2.5"><label class="block text-sm"
                for="new_password">Password baru</label><input id="new_password" type="password" name="password"
                autocomplete="new-password" required class="w-full border border-slate-300 rounded-lg p-2.5"><label
                class="block text-sm" for="new_password_confirmation">Konfirmasi password baru</label><input
                id="new_password_confirmation" type="password" name="password_confirmation" autocomplete="new-password"
                required class="w-full border border-slate-300 rounded-lg p-2.5">
            @foreach (['current_password', 'password'] as $field)
                @error($field)
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror
            @endforeach
            <button type="submit" class="rounded-lg bg-slate-950 text-white px-4 py-2.5">Ganti password</button>
        </form>
    </section>
@endsection
