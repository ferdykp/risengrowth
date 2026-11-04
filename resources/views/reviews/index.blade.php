@extends('layouts.workspace')
@section('title', 'Tinjau draf balasan')
@section('page_heading', 'Tinjau draf balasan')
@section('content')
<div class="space-y-6">
            <header class="rg-page-intro"><div><p class="rg-page-eyebrow">TINJAU SEBELUM MENGIRIM</p>
                <h1>Tinjau draf balasan</h1>
                <p>Periksa dan edit draf terlebih dahulu. Menyetujui draf tidak mengirim komentar secara otomatis.</p>
            </div><a href="{{ route('comments.index') }}" class="rg-action bg-white">Buat balasan</a></header>


            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                @foreach(['draft'=>'Draf', 'pending_review'=>'Menunggu tinjauan', 'approved'=>'Disetujui', 'rejected'=>'Ditolak'] as $key=>$label)
                    <a href="{{ route('reviews.index', ['status'=>$key]) }}" class="rounded-2xl bg-white border p-4 hover:border-slate-400 {{ $filter===$key ? 'border-slate-900' : 'border-slate-200' }}">
                        <p class="text-xs font-medium text-slate-500">{{ $label }}</p><p class="text-3xl font-bold mt-3 tabular-nums">{{ $counts[$key] ?? 0 }}</p>
                    </a>
                @endforeach
            </div>
            <section class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
                <div class="p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center gap-3 justify-between">
                    <h2 class="font-bold">Draf balasan</h2>
                    <form method="GET"><label for="status" class="sr-only">Filter status</label><select id="status" name="status" onchange="this.form.submit()" class="rounded-lg border border-slate-200 px-3 py-2 text-sm bg-white">
                        <option value="all" @selected($filter==='all')>Semua status</option>
                        @foreach(['draft'=>'Draf','pending_review'=>'Menunggu tinjauan','approved'=>'Disetujui','rejected'=>'Ditolak'] as $key=>$label)<option value="{{ $key }}" @selected($filter===$key)>{{ $label }}</option>@endforeach
                    </select></form>
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse($drafts as $draft)
                        <article class="p-5 lg:p-6 space-y-4">
                            <div class="flex flex-wrap gap-2 justify-between items-center">
                                <div><p class="text-sm font-semibold">{{ '@'.($draft->lead?->post?->username ?: 'pengguna') }} <span class="text-xs text-slate-400 font-normal">· {{ ['friendly'=>'Ramah','casual'=>'Santai','professional'=>'Profesional'][$draft->variant_key] ?? ucfirst($draft->variant_key) }} · revisi {{ $draft->revision }}</span></p>
                                    <p class="text-xs text-slate-500 mt-1">{{ $draft->lead?->post?->platform ?? 'Platform tidak tersedia' }} · {{ $draft->created_at?->diffForHumans() }}</p></div>
                                <span class="text-xs font-semibold px-2.5 py-1.5 rounded-full {{ $draft->status==='approved'?'bg-green-50 text-green-700':($draft->status==='rejected'?'bg-red-50 text-red-700':($draft->status==='pending_review'?'bg-amber-50 text-amber-700':'bg-slate-100 text-slate-700')) }}">{{ ['draft'=>'Draf','pending_review'=>'Menunggu tinjauan','approved'=>'Disetujui','rejected'=>'Ditolak'][$draft->status] ?? $draft->status }}</span>
                            </div>
                            <details class="rounded-xl bg-slate-50 border border-slate-100 p-4">
                                <summary class="cursor-pointer text-sm font-semibold text-slate-600">Lihat postingan asli</summary>
                                <p class="mt-3 text-sm leading-relaxed text-slate-600 whitespace-pre-line">{{ $draft->lead?->post?->body ?: 'Teks postingan tidak tersedia' }}</p>
                                @if($draft->lead?->post?->permalink)<a class="inline-block text-xs text-blue-700 mt-2 hover:underline" target="_blank" rel="noopener noreferrer" href="{{ $draft->lead->post->permalink }}">Buka postingan di Threads ↗</a>@endif
                            </details>
                            @if(in_array($draft->status, ['draft','pending_review'], true))
                                <form action="{{ route('reviews.update', $draft) }}" method="POST" class="space-y-3">@csrf @method('PATCH')
                                    <label for="body-{{ $draft->id }}" class="text-xs font-semibold text-slate-500">Balasan (boleh diedit sebelum keputusan)</label>
                                    <textarea id="body-{{ $draft->id }}" name="body" minlength="10" maxlength="2000" rows="3" required class="w-full rounded-xl border border-slate-200 p-3 text-sm leading-relaxed focus:outline-none focus:border-slate-900">{{ $draft->body }}</textarea>
                                    <div class="flex flex-wrap gap-2"><button type="submit" class="border border-slate-200 px-4 py-2 rounded-lg text-sm font-semibold hover:bg-slate-50">Simpan perubahan</button></div>
                                </form>
                                @if($draft->status==='draft')<form method="POST" action="{{ route('reviews.submit', $draft) }}">@csrf<button class="rounded-lg bg-slate-950 text-white px-4 py-2 text-sm font-semibold hover:bg-slate-800">Kirim untuk ditinjau</button></form>
                                @else
                                    <div class="flex flex-col sm:flex-row gap-3">
                                        <form method="POST" action="{{ route('reviews.decide', $draft) }}">@csrf<input type="hidden" name="decision" value="approved"><button class="rounded-lg bg-slate-950 text-white px-4 py-2 text-sm font-semibold hover:bg-slate-800">Setujui draf</button></form>
                                        <form method="POST" action="{{ route('reviews.decide', $draft) }}" class="flex gap-2 flex-wrap">@csrf<input type="hidden" name="decision" value="rejected"><input name="reason" maxlength="1000" placeholder="Alasan penolakan (opsional)" aria-label="Alasan penolakan" class="border border-slate-200 rounded-lg px-3 py-2 text-sm min-w-48"><button class="border border-red-200 text-red-700 px-4 py-2 rounded-lg text-sm font-semibold hover:bg-red-50">Tolak</button></form>
                                    </div>
                                @endif
                            @else
                                <div class="rounded-xl bg-slate-50 p-4 text-sm whitespace-pre-line">{{ $draft->body }}</div>
                                <p class="text-xs text-slate-500">Keputusan ini berlaku untuk versi draf ini. Buat draf baru jika ingin mengubahnya.</p>
                            @endif
                        </article>
                    @empty
                        <div class="text-center p-14"><h3 class="font-semibold">Belum ada komentar</h3><p class="mt-2 text-sm text-slate-500">Pilih prospek lalu buat draf balasan terlebih dahulu.</p><a href="{{ route('comments.index') }}" class="inline-block mt-4 text-sm font-semibold text-blue-700">Buat balasan →</a></div>
                    @endforelse
                </div>
                @if($drafts->hasPages())<div class="p-5 border-t border-slate-100">{{ $drafts->links() }}</div>@endif
            </section>
            <section class="rg-panel bg-white rounded-2xl border border-slate-200 p-5"><h2 class="font-bold">Riwayat keputusan terbaru</h2>
                @forelse($recentApprovals as $approval)
                    <div class="flex flex-wrap items-center justify-between border-b last:border-0 border-slate-100 py-3 gap-2 text-sm"><span>{{ '@'.($approval->draft?->lead?->post?->username ?: 'pengguna') }} · {{ $approval->decision === 'approved' ? 'Disetujui' : 'Ditolak' }}</span><span class="text-xs text-slate-500">{{ $approval->decided_at?->copy()->timezone('Asia/Jakarta')->format('d M Y, H:i \W\I\B') }}</span></div>
                @empty<p class="mt-3 text-sm text-slate-500">Belum ada keputusan tinjauan.</p>@endforelse
            </section>
        </div>
@endsection
