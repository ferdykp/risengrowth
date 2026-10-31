@extends('layouts.workspace')
@section('title', 'Detail Prospek')
@section('page_heading', 'Detail Prospek')
@section('content')
<div class="flex flex-wrap gap-4 justify-between items-start">
                <div>
                    <p class="text-xs uppercase font-bold tracking-[.17em] text-slate-500">LEAD WORKSPACE / DETAILS</p>
                    <h1 class="mt-2 text-3xl md:text-[36px] font-bold tracking-[-.04em]">Detail Prospek</h1>
                    <p class="mt-2 text-sm text-slate-500">Tinjau postingan, potensi kebutuhan, dan riwayat interaksi dalam satu tempat.</p>
                </div>
                <div class="flex flex-wrap gap-2 items-center">
                    <span class="px-3 py-2 rounded-full border text-xs font-semibold border-slate-200 bg-white capitalize">{{ $lead->post->platform }}</span>
                    <span class="px-3 py-2 rounded-full text-xs font-semibold {{ $lead->priority === 'high' ? 'bg-red-50 text-red-700' : ($lead->priority === 'medium' ? 'bg-amber-50 text-amber-800' : 'bg-slate-100 text-slate-600') }}">{{ ['high'=>'Prioritas tinggi','medium'=>'Prioritas sedang','low'=>'Prioritas rendah','unscored'=>'Belum dinilai'][$lead->priority] ?? $lead->priority }}</span>
                </div>
            </div>
            <div class="grid grid-cols-1 xl:grid-cols-12 gap-5 items-start">
                <div class="xl:col-span-8 space-y-5 min-w-0">
                    <section class="rg-panel bg-white rounded-2xl border border-slate-200 p-5 md:p-7">
                        <div class="flex flex-wrap gap-3 justify-between items-start"><div class="flex gap-3 items-center"><span class="h-11 w-11 bg-slate-100 rounded-xl flex items-center justify-center"><span class="material-symbols-outlined">alternate_email</span></span><div><div class="font-bold text-sm">{{ $lead->post->username ? '@'.$lead->post->username : 'Akun tidak diketahui' }}</div><div class="text-xs text-slate-500 mt-1">{{ ucfirst($lead->post->platform) }} · {{ $lead->post->posted_at?->copy()->timezone('Asia/Jakarta')->format('d M Y, H:i \W\I\B') ?? 'Waktu posting tidak tersedia' }}</div></div></div>
                        @if($lead->post->permalink && parse_url($lead->post->permalink, PHP_URL_SCHEME) === 'https')
                            <a href="{{ $lead->post->permalink }}" target="_blank" rel="noopener noreferrer" class="text-sm text-blue-700 font-semibold hover:underline flex gap-1 items-center">Postingan asli <span class="material-symbols-outlined text-base">open_in_new</span></a>
                        @endif</div>
                        <h2 class="text-xs text-slate-500 uppercase tracking-wider font-semibold mt-8">Postingan ditemukan</h2>
                        <p class="text-[15px] md:text-base leading-7 whitespace-pre-wrap break-words mt-3 text-slate-800">{{ $lead->post->body ?: 'Postingan ini tidak memiliki teks.' }}</p>
                        <div class="border-t border-slate-100 mt-7 pt-4 text-xs text-slate-500">Ditemukan: {{ $lead->post->first_discovered_at?->copy()->timezone('Asia/Jakarta')->format('d M Y, H:i \W\I\B') ?? $lead->post->created_at?->copy()->timezone('Asia/Jakarta')->format('d M Y, H:i \W\I\B') }}</div>
                    </section>
                    <section class="rg-panel bg-white rounded-2xl border border-slate-200 p-5 md:p-7 space-y-5">
                        <div><h2 class="text-lg font-bold">Kualifikasi prospek</h2><p class="text-xs text-slate-500 mt-1">Hasil analisis yang telah tersimpan untuk postingan ini.</p></div>
                        @if($lead->score === null && $lead->post->analyses->isEmpty())
                            <div class="py-9 text-center border border-dashed border-slate-200 rounded-xl"><span class="material-symbols-outlined text-4xl text-slate-300">psychology</span><h3 class="font-semibold mt-2">Belum dianalisis</h3><p class="text-sm text-slate-500 mt-2 max-w-md mx-auto">Analisis AI baru akan tersedia setelah integrasi provider dan proses scoring diaktifkan.</p></div>
                        @else
                            <div class="grid sm:grid-cols-2 gap-3"><div class="rounded-xl bg-slate-50 border border-slate-100 p-4"><div class="text-xs text-slate-500">Skor prospek</div><div class="text-3xl font-bold mt-2">{{ $lead->score === null ? '—' : $lead->score.'/100' }}</div></div><div class="rounded-xl bg-slate-50 border border-slate-100 p-4"><div class="text-xs text-slate-500">Layanan yang relevan</div><div class="text-sm font-semibold mt-3">{{ $lead->service_category ? str_replace('_', ' ', ucfirst($lead->service_category)) : 'Belum diklasifikasi' }}</div></div></div>
                            <div><h3 class="text-xs uppercase tracking-wide text-slate-500 font-bold">Alasan penilaian</h3><p class="text-sm leading-6 whitespace-pre-wrap mt-2 text-slate-700">{{ $lead->rationale ?: 'Penjelasan analisis belum tersedia.' }}</p></div>
                            <div class="text-xs text-slate-500">Kategori intent: {{ str_replace('_', ' ', $lead->intent) }}</div>
                        @endif
                        @if($lead->post->analyses->isNotEmpty())
                            <details class="border-t border-slate-100 pt-4"><summary class="cursor-pointer text-sm font-semibold">Riwayat analisis ({{ $lead->post->analyses->count() }} terbaru)</summary><div class="mt-3 space-y-2">@foreach($lead->post->analyses as $analysis)<div class="text-xs text-slate-600 bg-slate-50 p-3 rounded-lg">{{ $analysis->provider }} / {{ $analysis->model }} · {{ $analysis->status }} · {{ $analysis->created_at?->copy()->timezone('Asia/Jakarta')->format('d M Y H:i \W\I\B') }}</div>@endforeach</div></details>
                        @endif
                    </section>
                    <section class="rg-panel bg-white rounded-2xl border border-slate-200 p-5 md:p-7">
                        <h2 class="text-lg font-bold">Catatan internal</h2><p class="text-sm text-slate-500 mt-3 whitespace-pre-wrap">{{ $lead->operator_notes ?: 'Belum ada catatan internal untuk prospek ini.' }}</p>
                        <p class="text-xs text-slate-400 mt-3">Catatan dapat diedit melalui formulir status prospek.</p>
                    </section>
                </div>
                <div class="xl:col-span-4 space-y-5 min-w-0">
@include('leads.relationship-form')
                    <section class="rg-panel bg-white rounded-2xl border border-slate-200 p-5">
                        <h2 class="font-bold">Status Prospek</h2>
                        <form method="POST" action="{{ route('leads.status', $lead) }}" class="mt-4 space-y-3">
                            @csrf @method('PATCH')
                            <label class="block text-xs font-semibold">Status<select name="status" class="mt-1 w-full rounded-lg border border-slate-200 p-2 text-sm">@foreach(['new'=>'Baru','reviewing'=>'Ditinjau','qualified'=>'Potensial','contacted'=>'Dihubungi','saved'=>'Disimpan','completed'=>'Selesai','converted'=>'Konversi','dismissed'=>'Ditolak'] as $value => $label)<option value="{{ $value }}" @selected($lead->status === $value)>{{ $label }}</option>@endforeach</select></label>
                            <label class="block text-xs font-semibold">Catatan<textarea name="operator_notes" maxlength="5000" class="mt-1 w-full rounded-lg border border-slate-200 p-2 text-sm">{{ $lead->operator_notes }}</textarea></label>
                            <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm text-white">Simpan status</button>
                        </form><div class="mt-4 flex justify-between items-center text-sm"><span class="text-slate-500">Status</span><span class="font-semibold">{{ ['new'=>'Baru','reviewing'=>'Ditinjau','qualified'=>'Potensial','contacted'=>'Dihubungi','converted'=>'Konversi','dismissed'=>'Diabaikan'][$lead->status] ?? $lead->status }}</span></div><div class="mt-3 flex justify-between items-center text-sm"><span class="text-slate-500">Skor prospek</span><span class="font-semibold">{{ $lead->score === null ? 'Belum dinilai' : $lead->score.'/100' }}</span></div>
                        <div class="mt-5 pt-4 border-t border-slate-100"><p class="text-xs text-slate-500">Perubahan status tersimpan dalam riwayat aktivitas.</p></div>
                    </section>
                    <section class="rg-panel bg-white rounded-2xl border border-slate-200 p-5"><div class="flex items-center justify-between gap-2"><h2 class="font-bold">Draft Komentar</h2><span class="text-xs bg-slate-100 rounded-full px-2 py-1">{{ $drafts->count() }}</span></div>
                        @forelse($drafts as $draft)<div class="mt-4 border-t border-slate-100 pt-3"><div class="flex justify-between text-xs font-semibold text-slate-500"><span>{{ ucfirst($draft->variant_key) }}</span><span>{{ str_replace('_', ' ', $draft->status) }}</span></div><p class="text-sm text-slate-700 whitespace-pre-wrap break-words mt-2">{{ $draft->body }}</p></div>
                        @empty<div class="text-center py-8"><span class="material-symbols-outlined text-3xl text-slate-300">edit_note</span><p class="font-semibold text-sm mt-2">Belum ada draft komentar</p><p class="text-xs text-slate-500 mt-2">Draft muncul setelah fitur generator AI tersedia.</p></div>@endforelse
                    </section>
                    <section class="rg-panel bg-white rounded-2xl border border-slate-200 p-5"><h2 class="font-bold">Aktivitas</h2>@forelse($activities as $activity)<div class="border-b border-slate-100 last:border-0 py-3"><div class="text-sm text-slate-700">{{ str_replace('_', ' ', $activity->event) }}</div><p class="text-sm text-slate-600 whitespace-pre-wrap">{{ (is_array($activity->metadata) ? $activity->metadata : json_decode($activity->metadata ?? '{}', true))['interaction'] ?? '' }}</p><time class="text-xs text-slate-500">{{ $activity->created_at?->copy()->timezone('Asia/Jakarta')->format('d M Y H:i \W\I\B') }}</time></div>@empty<div class="text-center py-8"><span class="material-symbols-outlined text-3xl text-slate-300">history</span><p class="font-semibold text-sm mt-2">Belum ada aktivitas</p><p class="text-xs text-slate-500 mt-2">Riwayat engagement prospek akan muncul di sini.</p></div>@endforelse</section>
                </div>
            </div>
        <div class="rg-panel mt-6 p-5 bg-white border border-slate-200 rounded-2xl"><div class="font-semibold text-sm">Siapkan komentar untuk prospek ini</div><p class="text-sm text-slate-500 mt-1">Tulis draft dan review sebelum publikasi.</p>@auth<a class="inline-block mt-3 px-4 py-2 rounded-xl bg-black text-white text-sm font-semibold" href="{{ route('comments.index', ['lead' => $lead->id]) }}">Buka Generator Komentar</a>@else<a class="inline-block mt-3 px-4 py-2 rounded-xl bg-black text-white text-sm font-semibold" href="{{ route('login') }}">Masuk untuk membuat draft</a>@endauth</div>
@endsection
