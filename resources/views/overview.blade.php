@extends('layouts.workspace')
@section('title', 'Ikhtisar')
@section('page_heading', 'Ikhtisar')
@section('content')
<section class="px-6 lg:px-10 pt-9 pb-5 max-w-[1500px] mx-auto">

<div class="flex flex-col md:flex-row md:items-end md:justify-between gap-5">

<div><div class="text-[11px] uppercase tracking-[.18em] text-slate-500 font-bold mb-2">RISENGROWTH / OVERVIEW</div>

<h1 class="text-[30px] md:text-[38px] font-bold tracking-[-.045em] text-slate-950">Pusat Komando Prospek</h1>

<p class="text-slate-500 mt-2 text-sm">Pantau prospek, review komentar, dan aktivitas pencarian dalam satu tempat.</p></div>

<div class="flex flex-wrap gap-2"><span class="inline-flex items-center rounded-full px-3 py-2 text-xs font-semibold bg-amber-50 text-amber-800 border border-amber-200">● Integrasi Threads belum dikonfigurasi</span><a class="px-4 py-2 rounded-xl text-sm bg-slate-950 text-white font-semibold" href="{{ route('discovery.index') }}">Eksplorasi prospek</a></div>

</div>

</section>

<section class="max-w-[1500px] mx-auto px-6 lg:px-10 pb-12 space-y-6">

<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">

    @foreach($metrics as $metric)

      <article class="rounded-2xl bg-white p-5 border border-slate-200 shadow-[0_2px_14px_rgba(15,23,42,.025)] min-h-40">

<div class="text-xs text-slate-500 font-semibold">{{ $metric['label'] }}</div>

<div class="mt-6 text-[36px] leading-none font-bold tracking-[-.055em] text-slate-950 tabular-nums">{{ number_format($metric['value'],0,',','.') }}</div>

<div class="mt-4 text-xs text-slate-500">{{ $metric['detail'] }}</div>

</article>

    @endforeach

  </div>

<div class="grid grid-cols-1 xl:grid-cols-12 gap-5">

<section class="xl:col-span-8 bg-white border border-slate-200 rounded-2xl p-6">

<div class="flex items-center justify-between gap-3"><div><h2 class="text-lg font-bold text-slate-950">Prospek prioritas</h2><p class="text-xs text-slate-500 mt-1">Postingan relevan berdasarkan hasil analisis.</p></div><span class="text-[11px] font-semibold text-slate-500">{{ $priorityLeads->count() }} ditampilkan</span></div>

      @forelse($priorityLeads as $lead)

        <div class="border-b border-slate-100 py-5 last:border-0"><div class="flex justify-between gap-4"><div><a class="text-sm font-semibold text-slate-900 hover:text-blue-700 hover:underline" href="{{ route('leads.show', $lead) }}">{{ '@'.($lead->post?->username ?: 'pengguna') }} ↗</a><p class="mt-1 text-sm text-slate-600 line-clamp-2">{{ $lead->post?->body ?: 'Postingan tanpa teks' }}</p></div><span class="shrink-0 bg-blue-50 text-blue-700 text-xs font-bold px-3 py-1.5 rounded-lg h-fit">{{ $lead->score ?? '—' }}/100</span></div></div>

      @empty

       <div class="py-14 flex flex-col items-center text-center"><span class="material-symbols-outlined text-[38px] text-slate-300">radar</span><h3 class="text-base font-semibold mt-3 text-slate-900">Belum ada prospek</h3><p class="text-sm text-slate-500 mt-2 max-w-sm">Setelah koneksi Threads dan pencarian dijalankan, prospek berprioritas tinggi akan tampil di sini.</p></div>

      @endforelse

    </section>

<section class="xl:col-span-4 bg-white border border-slate-200 rounded-2xl p-6"><div class="flex items-center justify-between"><h2 class="text-lg font-bold text-slate-950">Antrean review</h2><span class="text-xs rounded-full bg-slate-100 px-2 py-1 font-semibold text-slate-700">{{ $pendingDrafts->count() }}</span></div><p class="text-xs text-slate-500 mt-1">Draft yang menunggu keputusanmu.</p>

      @forelse($pendingDrafts as $draft)

       <div class="py-4 border-b border-slate-100 last:border-0"><div class="text-xs font-semibold text-slate-500">{{ '@'.($draft->lead?->post?->username ?: 'pengguna') }}</div><p class="text-sm text-slate-700 mt-1 line-clamp-3">{{ $draft->body }}</p><span class="inline-block mt-2 text-xs text-amber-700 font-semibold">Menunggu review</span></div>

      @empty

       <div class="flex flex-col items-center text-center py-12"><span class="material-symbols-outlined text-[34px] text-slate-300">task_alt</span><p class="font-semibold text-sm text-slate-900 mt-3">Belum ada draft untuk direview</p><p class="text-xs text-slate-500 mt-2">Draft AI akan muncul setelah fitur analisis dan komentar tersambung.</p></div>

      @endforelse

    </section>

</div>

<section class="bg-white rounded-2xl border border-slate-200 p-6"><div class="flex items-center justify-between"><div><h2 class="text-lg font-bold text-slate-950">Aktivitas terbaru</h2><p class="text-xs text-slate-500 mt-1">Riwayat aktivitas sistem dan interaksi.</p></div></div>

    @forelse($recentActivities as $activity)

     <div class="flex justify-between gap-4 py-4 border-b border-slate-100 last:border-0"><div class="text-sm text-slate-700">{{ str_replace('_', ' ', $activity->event) }}</div><time class="text-xs text-slate-500 shrink-0" datetime="{{ $activity->created_at?->toIso8601String() }}">{{ $activity->created_at?->diffForHumans() }}</time></div>

    @empty

     <div class="py-9 text-center text-sm text-slate-500">Belum ada aktivitas tercatat.</div>

    @endforelse

  </section>

</section>
@endsection
