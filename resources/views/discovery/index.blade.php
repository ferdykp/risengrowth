@extends('layouts.workspace')
@section('title', 'Eksplorasi')
@section('page_heading', 'Eksplorasi')
@section('content')
<section class="px-6 lg:px-10 pt-9 pb-6 max-w-[1500px] mx-auto">
<div class="flex flex-col lg:flex-row lg:justify-between lg:items-end gap-5">
 <div><div class="text-[11px] uppercase tracking-[.18em] text-slate-500 font-bold mb-2">RISENGROWTH / DISCOVERY</div><h1 class="text-[30px] md:text-[38px] font-bold tracking-[-.045em] text-slate-950">Eksplorasi Prospek</h1><p class="text-slate-500 mt-2 text-sm">Pantau sumber pencarian, keyword aktif, dan riwayat proses discovery.</p></div>
 <div class="flex flex-wrap gap-2"><span class="inline-flex items-center rounded-full px-3 py-2 text-xs font-semibold border border-amber-200 bg-amber-50 text-amber-800">Integrasi Threads belum aktif</span><button type="button" disabled title="Perlu konfigurasi OAuth Threads dan proses discovery terlebih dahulu" class="rounded-xl px-4 py-2 text-sm font-semibold bg-slate-200 text-slate-500 cursor-not-allowed">Jalankan Pencarian</button></div>
</div>
</section>
<section class="max-w-[1500px] mx-auto px-6 lg:px-10 pb-12 space-y-6">
<div class="grid grid-cols-2 xl:grid-cols-4 gap-4">
 @foreach([['label'=>'Total proses','value'=>$totalRuns,'note'=>'Semua discovery run'],['label'=>'Proses selesai','value'=>$completedRuns,'note'=>'Status completed'],['label'=>'Postingan ditemukan','value'=>$totalDiscovered,'note'=>'Postingan unik tersimpan'],['label'=>'Keyword aktif','value'=>$activeKeywords,'note'=>'Keyword yang diaktifkan']] as $stat)
 <article class="rounded-2xl border border-slate-200 bg-white p-5 min-h-36"><div class="text-xs font-semibold text-slate-500">{{ $stat['label'] }}</div><div class="mt-5 text-3xl font-bold tracking-tight tabular-nums text-slate-950">{{ number_format($stat['value'],0,',','.') }}</div><p class="mt-3 text-xs text-slate-500">{{ $stat['note'] }}</p></article>
 @endforeach
</div>
<div class="grid grid-cols-1 xl:grid-cols-12 gap-5">
<section class="xl:col-span-5 rounded-2xl border border-slate-200 bg-white p-6">
 <div class="flex items-center justify-between mb-5"><div><h2 class="text-lg font-bold text-slate-950">Akun terhubung</h2><p class="text-xs text-slate-500 mt-1">Status dari database, bukan simulasi koneksi.</p></div><span class="material-symbols-outlined text-slate-400">link</span></div>
 @forelse($accounts as $account)
 <div class="flex items-center justify-between gap-3 border-t border-slate-100 py-4"><div class="min-w-0"><p class="text-sm font-semibold text-slate-900">{{ ucfirst($account->platform) }} <span class="text-slate-400 font-normal">{{ $account->username ? '@'.$account->username : '' }}</span></p><p class="mt-1 text-xs text-slate-500">Terakhir diverifikasi: {{ $account->last_verified_at ? \Illuminate\Support\Carbon::parse($account->last_verified_at)->diffForHumans() : 'Belum pernah' }}</p></div><span class="px-2.5 py-1 rounded-full text-xs font-semibold {{ $account->status === 'connected' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $account->status === 'connected' ? 'Terhubung (tercatat)' : ucfirst(str_replace('_',' ',$account->status)) }}</span></div>
 @empty
 <div class="py-10 text-center"><span class="material-symbols-outlined text-4xl text-slate-300">cable</span><h3 class="font-semibold text-slate-900 mt-2">Belum ada akun</h3><p class="text-sm text-slate-500 mt-2">Koneksi Threads OAuth akan tersedia pada tahap integrasi.</p></div>
 @endforelse
</section>
<section class="xl:col-span-7 rounded-2xl border border-slate-200 bg-white p-6">
 <div class="flex items-center justify-between mb-5"><div><h2 class="text-lg font-bold text-slate-950">Keyword pencarian</h2><p class="text-xs text-slate-500 mt-1">Keyword yang telah tercatat di sistem.</p></div><a href="{{ route('keywords.index') }}" class="text-xs font-semibold text-blue-700 hover:underline">Kelola keyword →</a></div>
 @forelse($keywords->take(6) as $keyword)
 <div class="py-3 border-t border-slate-100 flex items-center justify-between gap-3"><div class="min-w-0"><p class="text-sm font-semibold text-slate-900 truncate">{{ $keyword->phrase }}</p><p class="text-xs text-slate-500 mt-0.5">{{ ucfirst(str_replace('_',' ',$keyword->category)) }} · {{ ucfirst($keyword->platform) }}</p></div><span class="text-xs font-semibold rounded-full px-2.5 py-1 {{ $keyword->enabled ? 'bg-blue-50 text-blue-700' : 'bg-slate-100 text-slate-500' }}">{{ $keyword->enabled ? 'Aktif' : 'Nonaktif' }}</span></div>
 @empty
 <div class="py-10 text-center"><span class="material-symbols-outlined text-4xl text-slate-300">manage_search</span><h3 class="font-semibold text-slate-900 mt-2">Belum ada keyword</h3><p class="text-sm text-slate-500 mt-2">Pengelolaan keyword akan tersedia di Page 05.</p></div>
 @endforelse
</section>
</div>
<section class="rounded-2xl border border-slate-200 bg-white p-6">
 <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-5"><div><h2 class="text-lg font-bold text-slate-950">Riwayat pencarian</h2><p class="text-xs text-slate-500 mt-1">Pencarian yang tercatat oleh sistem, termasuk status dan hasilnya.</p></div>
 <form method="GET" action="{{ route('discovery.index') }}" class="flex flex-wrap items-center gap-2"><label class="sr-only" for="discovery-status">Status</label><select id="discovery-status" name="status" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"><option value="">Semua status</option>@foreach(['queued'=>'Antrean','running'=>'Berjalan','completed'=>'Selesai','failed'=>'Gagal','partial'=>'Sebagian'] as $key=>$label)<option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>@endforeach</select><label class="sr-only" for="discovery-keyword">Keyword</label><select id="discovery-keyword" name="keyword" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"><option value="">Semua keyword</option>@foreach($keywords as $keyword)<option value="{{ $keyword->id }}" @selected((string)($filters['keyword'] ?? '') === (string)$keyword->id)>{{ $keyword->phrase }}</option>@endforeach</select><button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Terapkan</button><a href="{{ route('discovery.index') }}" class="text-sm text-slate-500 px-2 py-2 hover:text-slate-900">Reset</a></form>
 </div>
 <div class="overflow-x-auto"><table class="w-full text-sm min-w-[720px]"><thead><tr class="border-y border-slate-100 text-left text-xs text-slate-500"><th class="py-3 pr-4 font-semibold">Keyword</th><th class="py-3 pr-4 font-semibold">Platform</th><th class="py-3 pr-4 font-semibold">Status</th><th class="py-3 pr-4 font-semibold">Diambil</th><th class="py-3 pr-4 font-semibold">Ditambahkan</th><th class="py-3 font-semibold">Waktu</th></tr></thead><tbody>
 @forelse($runs as $run)
 <tr class="border-b border-slate-100"><td class="py-4 pr-4"><p class="font-semibold text-slate-900">{{ $run->keyword_phrase }}</p>@if($run->error_summary)<p class="text-xs text-red-700 mt-1 max-w-xs break-words">{{ $run->error_summary }}</p>@endif</td><td class="py-4 pr-4 text-slate-600">{{ ucfirst($run->platform) }}</td><td class="py-4 pr-4"><span class="text-xs font-semibold rounded-full px-2.5 py-1 {{ $run->status === 'completed' ? 'bg-emerald-50 text-emerald-700' : ($run->status === 'failed' ? 'bg-red-50 text-red-700' : 'bg-slate-100 text-slate-600') }}">{{ ucfirst($run->status) }}</span></td><td class="py-4 pr-4 tabular-nums">{{ $run->fetched_count }}</td><td class="py-4 pr-4 tabular-nums">{{ $run->added_count }}</td><td class="py-4 text-slate-500 whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($run->created_at)->format('d M Y, H:i') }}</td></tr>
 @empty
 <tr><td colspan="6" class="py-16 text-center"><span class="material-symbols-outlined text-4xl text-slate-300">history</span><p class="font-semibold mt-2 text-slate-900">Belum ada riwayat pencarian</p><p class="text-sm text-slate-500 mt-1">Riwayat akan muncul setelah discovery engine diaktifkan.</p></td></tr>
 @endforelse
 </tbody></table></div><div class="mt-5">{{ $runs->links() }}</div>
</section>
</section>
@endsection
