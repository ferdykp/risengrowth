@extends('layouts.workspace')
@section('title', 'Aktivitas')
@section('page_heading', 'Aktivitas')
@section('content')
<div class="space-y-6">
            <div><p class="text-[11px] font-bold tracking-[.18em] text-slate-500 uppercase">RisenGrowth / Activity</p>
                <h1 class="text-3xl lg:text-4xl font-bold tracking-tight mt-2">Aktivitas & Engagement</h1>
                <p class="mt-2 text-sm text-slate-500">Pantau keputusan review, percobaan pengiriman, dan aktivitas akun tanpa menganggap persetujuan sebagai publikasi.</p>
            </div>
            <div class="grid grid-cols-2 xl:grid-cols-4 gap-3">
                @foreach([['label'=>'Keputusan review','value'=>$approvalCount],['label'=>'Percobaan engagement','value'=>$attemptCount],['label'=>'Terbit terkonfirmasi','value'=>$publishedCount],['label'=>'Gagal / belum pasti','value'=>$failedCount]] as $metric)
                    <article class="rg-panel rounded-2xl bg-white border border-slate-200 p-5"><p class="text-xs text-slate-500 font-semibold">{{ $metric['label'] }}</p><p class="text-3xl font-bold mt-4 tabular-nums">{{ number_format($metric['value'], 0, ',', '.') }}</p></article>
                @endforeach
            </div>
            <div class="flex flex-col sm:flex-row gap-3 justify-between">
                <nav aria-label="Kategori aktivitas" class="flex flex-wrap gap-2">
                    @foreach(['all'=>'Semua','approvals'=>'Review','engagements'=>'Pengiriman','events'=>'Log aktivitas'] as $key=>$label)
                        <a href="{{ route('activity.index', ['tab'=>$key]) }}" class="text-xs font-semibold rounded-lg px-3.5 py-2.5 border {{ $tab===$key?'bg-black text-white border-black':'bg-white text-slate-700 border-slate-200 hover:border-slate-400' }}">{{ $label }}</a>
                    @endforeach
                </nav>
                @if($tab === 'approvals' || $tab === 'engagements')
                    <form method="GET" class="flex items-center gap-2">
                        <input type="hidden" name="tab" value="{{ $tab }}">
                        <label for="status" class="text-xs font-semibold text-slate-600">Status</label>
                        <select id="status" name="status" onchange="this.form.submit()" class="rounded-lg bg-white border border-slate-200 px-3 py-2 text-sm">
                            <option value="all">Semua</option>
                            @foreach(($tab === 'approvals' ? ['approved'=>'Disetujui','rejected'=>'Ditolak'] : ['pending'=>'Menunggu','publishing'=>'Sedang dikirim','published'=>'Terbit','failed'=>'Gagal','unknown'=>'Belum pasti']) as $key=>$label)
                                <option value="{{ $key }}" @selected($status===$key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </form>
                @endif
            </div>
            @if($approvals !== null)
                <section class="rounded-2xl bg-white border border-slate-200 overflow-hidden" aria-labelledby="approvals-heading">
                    <div class="p-5 border-b border-slate-100"><h2 id="approvals-heading" class="text-base font-bold">Riwayat keputusan</h2><p class="text-xs text-slate-500 mt-1">Disetujui tidak berarti sudah dikirim.</p></div>
                    <div class="divide-y divide-slate-100">
                        @forelse($approvals as $approval)
                            <article class="p-5 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                                <div class="min-w-0"><p class="text-sm font-semibold">{{ '@'.($approval->draft?->lead?->post?->username ?: 'pengguna') }}</p>
                                    <p class="text-sm text-slate-600 mt-1 break-words">{{ \Illuminate\Support\Str::limit($approval->approved_body_snapshot ?: $approval->draft?->body, 190) }}</p>
                                    @if($approval->reason)<p class="text-xs text-slate-500 mt-2">Alasan: {{ $approval->reason }}</p>@endif
                                    @if($approval->draft?->lead)<a href="{{ route('leads.show', $approval->draft->lead) }}" class="inline-block mt-2 text-xs font-semibold text-blue-700 hover:underline">Detail prospek →</a>@endif
                                </div>
                                <div class="sm:text-right shrink-0"><span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold {{ $approval->decision === 'approved' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">{{ $approval->decision === 'approved' ? 'Disetujui' : 'Ditolak' }}</span><p class="text-xs text-slate-500 mt-2">{{ $approval->decided_at?->format('d M Y, H:i') }}</p></div>
                            </article>
                        @empty <p class="p-10 text-center text-sm text-slate-500">Belum ada keputusan review.</p> @endforelse
                    </div>
                    @if($approvals->hasPages())<div class="p-5 border-t border-slate-100">{{ $approvals->links() }}</div>@endif
                </section>
            @endif
            @if($attempts !== null)
                <section class="rounded-2xl bg-white border border-slate-200 overflow-hidden" aria-labelledby="attempts-heading">
                    <div class="p-5 border-b border-slate-100"><h2 id="attempts-heading" class="text-base font-bold">Riwayat pengiriman</h2><p class="text-xs text-slate-500 mt-1">Data percobaan pengiriman yang tercatat. Tidak ada aksi publikasi pada halaman ini.</p></div>
                    <div class="divide-y divide-slate-100">
                        @forelse($attempts as $attempt)
                            @php
                                $confirmed = $attempt->status === 'published' && $attempt->confirmed_at !== null;
                                $label = $confirmed ? 'Terbit terkonfirmasi' : match($attempt->status) {
                                    'published' => 'Menunggu konfirmasi', 'pending' => 'Menunggu', 'publishing' => 'Sedang dikirim',
                                    'failed' => 'Gagal', 'unknown' => 'Status belum pasti', default => 'Belum terverifikasi'
                                };
                            @endphp
                            <article class="p-5 flex flex-col sm:flex-row sm:justify-between gap-3">
                                <div class="min-w-0"><p class="text-sm font-semibold">{{ '@'.($attempt->draft?->lead?->post?->username ?: 'pengguna') }} <span class="font-normal text-slate-500">· {{ $attempt->method === 'manual' ? 'Manual' : 'API' }}</span></p>
                                    <p class="text-sm text-slate-600 mt-1 break-words">{{ \Illuminate\Support\Str::limit($attempt->approved_body_snapshot, 190) }}</p>
                                    @if($attempt->failure_summary)<p class="mt-2 text-xs text-rose-700">{{ $attempt->failure_summary }}</p>@endif
                                    @if($attempt->draft?->lead)<a class="inline-block mt-2 text-xs font-semibold text-blue-700 hover:underline" href="{{ route('leads.show', $attempt->draft->lead) }}">Detail prospek →</a>@endif
                                </div>
                                <div class="shrink-0 sm:text-right"><span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold {{ $confirmed ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-700' }}">{{ $label }}</span><p class="text-xs text-slate-500 mt-2">{{ $attempt->attempted_at?->format('d M Y, H:i') ?: $attempt->created_at?->format('d M Y, H:i') }}</p></div>
                            </article>
                        @empty <p class="p-10 text-center text-sm text-slate-500">Belum ada percobaan engagement tercatat.</p> @endforelse
                    </div>
                    @if($attempts->hasPages())<div class="p-5 border-t border-slate-100">{{ $attempts->links() }}</div>@endif
                </section>
            @endif
            @if($events !== null)
                <section class="rounded-2xl bg-white border border-slate-200 overflow-hidden" aria-labelledby="events-heading">
                    <div class="p-5 border-b border-slate-100"><h2 id="events-heading" class="text-base font-bold">Log aktivitas</h2><p class="text-xs text-slate-500 mt-1">Peristiwa internal yang tersimpan untuk akun ini.</p></div>
                    <div class="divide-y divide-slate-100">
                        @forelse($events as $event)
                            <article class="p-5 flex flex-wrap items-center justify-between gap-2"><div><p class="text-sm font-semibold">{{ str_replace('_', ' ', $event->event) }}</p>@if($event->lead_id)<a href="{{ route('leads.show', $event->lead_id) }}" class="text-xs text-blue-700 hover:underline">Detail prospek →</a>@endif</div><time class="text-xs text-slate-500" datetime="{{ $event->created_at?->toIso8601String() }}">{{ $event->created_at?->format('d M Y, H:i') }}</time></article>
                        @empty <p class="p-10 text-center text-sm text-slate-500">Belum ada aktivitas tercatat.</p> @endforelse
                    </div>
                    @if($events->hasPages())<div class="p-5 border-t border-slate-100">{{ $events->links() }}</div>@endif
                </section>
            @endif
        </div>
@endsection
