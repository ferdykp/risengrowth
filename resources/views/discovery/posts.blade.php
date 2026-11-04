@extends('layouts.workspace')

@section('title', 'Postingan ditemukan')
@section('page_heading', 'Postingan ditemukan')

@section('content')
<div class="space-y-6">
    <header class="rg-page-intro">
        <div>
            <p class="rg-page-eyebrow">HASIL PENCARIAN</p>
            <h1>Postingan ditemukan</h1>
            <p>Semua postingan hasil pencarian dan input manual ada di sini. Pilih kategori untuk menemukan peluang yang ingin ditinjau.</p>
        </div>
        <a href="{{ route('discovery.index') }}" class="rg-action bg-white"><span class="material-symbols-outlined mr-2 text-[18px]" aria-hidden="true">travel_explore</span>Jalankan pencarian</a>
    </header>

    <section class="grid gap-3 sm:grid-cols-2" aria-label="Ringkasan hasil">
        <article class="rg-panel rounded-2xl border border-slate-200 bg-white"><p class="text-sm text-slate-500">Semua postingan</p><p class="mt-2 text-3xl font-bold tabular-nums">{{ number_format($totalDiscovered) }}</p><p class="mt-1 text-xs text-slate-500">Postingan unik yang tersimpan</p></article>
        <article class="rg-panel rounded-2xl border border-slate-200 bg-white"><p class="text-sm text-slate-500">Sudah dianalisis</p><p class="mt-2 text-3xl font-bold tabular-nums">{{ number_format($totalAnalyzed) }}</p><p class="mt-1 text-xs text-slate-500">Hasil analisis terbaru per postingan</p></article>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <form method="GET" action="{{ route('discovery.posts.index') }}" class="grid gap-4 p-5 sm:grid-cols-2 sm:items-end">
            <label class="block text-sm font-semibold text-slate-700">Kelompok peluang
                <select name="category" class="mt-1.5 block w-full rounded-xl border border-slate-200 bg-white p-3 text-sm font-normal">
                    <option value="">Semua kelompok</option>
                    @foreach(['business' => 'Peluang bisnis', 'community' => 'Komunitas', 'not_relevant' => 'Tidak relevan'] as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['category'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block text-sm font-semibold text-slate-700">Hasil penilaian
                <select name="qualification" class="mt-1.5 block w-full rounded-xl border border-slate-200 bg-white p-3 text-sm font-normal">
                    <option value="">Semua hasil</option>
                    @foreach(['qualified' => 'Layak ditinjau', 'uncertain' => 'Perlu ditinjau', 'rejected' => 'Tidak lolos'] as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['qualification'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <div class="flex flex-wrap items-center gap-3 sm:col-span-2">
                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-slate-950 px-5 py-2.5 text-sm font-semibold text-white">Terapkan filter</button>
                @if(count(array_filter($filters)))<a href="{{ route('discovery.posts.index') }}" class="text-sm font-semibold text-slate-600 underline underline-offset-4">Hapus filter</a>@endif
                <p class="text-xs text-slate-500 sm:ml-auto">{{ number_format($posts->total()) }} hasil · tampil 15 per halaman</p>
            </div>
        </form>
        <div class="border-t border-slate-100 p-5 sm:p-6">
            @if($posts->isEmpty())
                <div class="py-12 text-center">
                    <span class="material-symbols-outlined text-4xl text-slate-300" aria-hidden="true">search_off</span>
                    <h2 class="mt-3 font-semibold text-slate-900">Belum ada postingan untuk pilihan ini</h2>
                    <p class="mt-1 text-sm text-slate-500">Coba ubah filter atau mulai pencarian baru.</p>
                </div>
            @else
                <div class="rg-review-list">@include('discovery.partials.posts-list')</div>
            @endif
        </div>
    </section>
</div>
@endsection
