@extends('layouts.workspace')
@section('title', 'Ringkasan')
@section('page_heading', 'Ringkasan')
@section('content')
<header class="rg-page-intro">
<div><p class="rg-page-eyebrow">RINGKASAN RUANG KERJA</p><h1>Peluang dan aktivitasmu</h1><p>Pilih percakapan yang ingin ditinjau, siapkan balasan, dan lanjutkan relasi.</p></div>
<a class="px-4 py-2 rounded-xl text-sm bg-slate-950 text-white font-semibold" href="{{ route('discovery.index') }}">Mulai pencarian postingan</a>
</header>

<section class="rg-panel bg-white border border-slate-200 rounded-2xl">
<h2 class="text-lg font-bold">Langkah berikutnya</h2>
<div class="flex flex-wrap gap-3 mt-4">
<a class="rg-action" href="{{ route('leads.index', ['status' => 'new', 'sort' => 'score_high']) }}">Tinjau {{ $newLeadsCount }} peluang baru →</a>
<a class="rg-action" href="{{ route('relationships.index') }}">Lanjutkan {{ $networkingCount }} relasi →</a>
<a class="rg-action" href="{{ route('reviews.index', ['status' => 'pending_review']) }}">Tinjau draf balasan →</a>
<a class="rg-action" href="{{ route('studio.index') }}">Siapkan ide konten →</a>
</div>
</section>
<section class="rg-panel bg-white border border-slate-200 rounded-2xl">
<div class="flex justify-between gap-3"><h2 class="font-bold">Tindak lanjut yang jatuh tempo</h2><a class="text-sm underline" href="{{ route('relationships.index', ['due' => 1]) }}">Lihat semua</a></div>
@forelse($followUps as $relationship)
<a class="block border-b border-slate-100 py-4" href="{{ route('leads.show', $relationship->lead_id) }}"><strong>{{ $relationship->lead?->post?->username ?: 'Kontak' }}</strong> · {{ $relationship->follow_up_on->format('d M Y') }}<p class="text-sm text-slate-500">{{ \Illuminate\Support\Str::limit($relationship->notes, 140) }}</p></a>
@empty<p class="py-5 text-sm text-slate-500">Tidak ada tindak lanjut yang jatuh tempo. Kamu bisa menjadwalkannya dari catatan relasi.</p>@endforelse
</section>
<section class="space-y-6">

<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">

    @foreach($metrics as $metric)

      <article class="rg-panel rounded-2xl bg-white p-5 border border-slate-200 shadow-[0_2px_14px_rgba(15,23,42,.025)] min-h-40">

<div class="text-xs text-slate-500 font-semibold">{{ $metric['label'] }}</div>

<div class="mt-6 text-[36px] leading-none font-bold tracking-[-.055em] text-slate-950 tabular-nums">{{ number_format($metric['value'],0,',','.') }}</div>

<div class="mt-4 text-xs text-slate-500">{{ $metric['detail'] }}</div>

</article>

    @endforeach

  </div>

<div class="grid grid-cols-1 xl:grid-cols-12 gap-5">

<section class="rg-panel xl:col-span-8 bg-white border border-slate-200 rounded-2xl p-6">

<div class="flex items-center justify-between gap-3"><div><h2 class="text-lg font-bold text-slate-950">Peluang yang perlu diperhatikan</h2><p class="text-xs text-slate-500 mt-1">Postingan relevan berdasarkan hasil analisis.</p></div><span class="text-[11px] font-semibold text-slate-500">{{ $priorityLeads->count() }} ditampilkan</span></div>

      @forelse($priorityLeads as $lead)

        <div class="border-b border-slate-100 py-5 last:border-0"><div class="flex justify-between gap-4"><div><a class="text-sm font-semibold text-slate-900 hover:text-blue-700 hover:underline" href="{{ route('leads.show', $lead) }}">{{ '@'.($lead->post?->username ?: 'pengguna') }} ↗</a><p class="mt-1 text-sm text-slate-600 line-clamp-2">{{ $lead->post?->body ?: 'Postingan tanpa teks' }}</p></div><span class="shrink-0 bg-blue-50 text-blue-700 text-xs font-bold px-3 py-1.5 rounded-lg h-fit">{{ $lead->score ?? '—' }}/100</span></div>
<p class="text-xs text-slate-500 mt-2">{{ ucfirst($lead->post?->analyses->first()?->output['category'] ?? 'Belum diklasifikasi') }} · {{ $lead->post?->analyses->first()?->output['source'] ?? $lead->post?->platform }} · {{ $lead->post?->first_discovered_at?->diffForHumans() }}</p>
<div class="flex flex-wrap gap-2 mt-3">
<a class="rg-action text-xs" href="{{ route('leads.show', $lead) }}">Tinjau peluang</a>
<a class="rg-action text-xs" href="{{ route('comments.index', ['lead' => $lead->id]) }}">Buat balasan</a>
<a class="rg-action text-xs" href="{{ route('leads.show', $lead) }}#relationship">Catatan relasi</a>
@foreach(['saved' => 'Simpan', 'dismissed' => 'Abaikan'] as $status => $label)
<form method="POST" action="{{ route('leads.status', $lead) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="{{ $status }}"><button class="rg-action text-xs">{{ $label }}</button></form>
@endforeach
@if($lead->post?->permalink && parse_url($lead->post->permalink, PHP_URL_SCHEME) === 'https')<a class="rg-action text-xs" href="{{ $lead->post->permalink }}" target="_blank" rel="noopener noreferrer">Postingan asli ↗</a>@endif
</div></div>

      @empty

       <div class="py-14 flex flex-col items-center text-center"><span class="material-symbols-outlined text-[38px] text-slate-300">radar</span><h3 class="text-base font-semibold mt-3 text-slate-900">Belum ada peluang</h3><p class="text-sm text-slate-500 mt-2 max-w-sm">Mulai pencarian atau tambahkan postingan yang ingin ditinjau.</p><a class="rg-action mt-4" href="{{ route('discovery.index') }}">Buka pencarian postingan</a></div>

      @endforelse

    </section>

<section class="rg-panel xl:col-span-4 bg-white border border-slate-200 rounded-2xl p-6"><div class="flex items-center justify-between"><h2 class="text-lg font-bold text-slate-950">Draf balasan menunggu ditinjau</h2><span class="text-xs rounded-full bg-slate-100 px-2 py-1 font-semibold text-slate-700">{{ $pendingDrafts->count() }}</span></div><p class="text-xs text-slate-500 mt-1">Baca dan putuskan sebelum membalas sendiri.</p>

      @forelse($pendingDrafts as $draft)

       <div class="py-4 border-b border-slate-100 last:border-0"><div class="text-xs font-semibold text-slate-500">{{ '@'.($draft->lead?->post?->username ?: 'pengguna') }}</div><p class="text-sm text-slate-700 mt-1 line-clamp-3">{{ $draft->body }}</p><span class="inline-block mt-2 text-xs text-amber-700 font-semibold">Menunggu tinjauan</span></div>

      @empty

       <div class="flex flex-col items-center text-center py-12"><span class="material-symbols-outlined text-[34px] text-slate-300">task_alt</span><p class="font-semibold text-sm text-slate-900 mt-3">Belum ada draf untuk ditinjau</p><p class="text-xs text-slate-500 mt-2">Buat draf balasan dari percakapan yang ingin kamu tanggapi.</p></div>

      @endforelse

    </section>

</div>

<section class="rg-panel bg-white rounded-2xl border border-slate-200 p-6"><div class="flex items-center justify-between"><div><h2 class="text-lg font-bold text-slate-950">Aktivitas terbaru</h2><p class="text-xs text-slate-500 mt-1">Riwayat aktivitas sistem dan interaksi.</p></div></div>

    @forelse($recentActivities as $activity)

     <div class="flex justify-between gap-4 py-4 border-b border-slate-100 last:border-0"><div class="text-sm text-slate-700">{{ ['relationship_updated' => 'Catatan relasi diperbarui', 'lead_status_updated' => 'Status peluang diperbarui'][$activity->event] ?? 'Aktivitas akun' }}</div><time class="text-xs text-slate-500 shrink-0" datetime="{{ $activity->created_at?->toIso8601String() }}">{{ $activity->created_at?->diffForHumans() }}</time></div>

    @empty

     <div class="py-9 text-center text-sm text-slate-500">Belum ada aktivitas tercatat.</div>

    @endforelse

  </section>

</section>
@endsection
