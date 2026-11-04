@foreach($posts as $post)
    @php($analysis = $post->analyses->first())
    @php($analysisOutput = $analysis?->output ?? [])
    @php($localAi = data_get($analysisOutput, 'local_ai'))
    <article class="rg-review-card">
        <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500">
            <span>{{ data_get($analysisOutput, 'source', ucfirst($post->platform)) }}</span>
            @if($analysis)
                <span aria-hidden="true">·</span>
                <span>{{ ['qualified' => 'Layak ditinjau', 'uncertain' => 'Perlu ditinjau', 'rejected' => 'Tidak lolos'][data_get($analysisOutput, 'status', $analysis->status)] ?? ucfirst($analysis->status) }}</span>
                <span aria-hidden="true">·</span>
                <span>{{ $analysis->score === null ? 'Tanpa skor' : $analysis->score.'/100' }}</span>
            @else
                <span aria-hidden="true">·</span><span>Belum dianalisis</span>
            @endif
        </div>
        <div class="flex flex-wrap items-center gap-2 text-xs">
            <span class="rounded-full bg-slate-100 px-2.5 py-1 font-semibold text-slate-700">{{ ['business' => 'Peluang bisnis', 'community' => 'Komunitas', 'not_relevant' => 'Tidak relevan'][data_get($analysisOutput, 'category', '')] ?? 'Belum dikelompokkan' }}</span>
            @if($analysis)<span class="text-slate-500">Peninjauan: {{ ['pending' => 'Menunggu', 'reviewed' => 'Sudah ditinjau'][data_get($analysisOutput, 'review_status', 'pending')] ?? data_get($analysisOutput, 'review_status', 'Menunggu') }}</span>@endif
        </div>
        <p class="text-sm whitespace-pre-wrap break-words text-slate-800">{{ $post->body ?: 'Postingan ini tidak memiliki teks.' }}</p>
        @if(data_get($analysisOutput, 'rationale'))<p class="text-xs text-slate-600">Alasan analisis: {{ data_get($analysisOutput, 'rationale') }}</p>@endif
        @if(data_get($analysisOutput, 'evidence'))<p class="rg-review-evidence text-xs text-slate-500 break-words">Bukti: {{ json_encode(data_get($analysisOutput, 'evidence'), JSON_UNESCAPED_UNICODE) }}</p>@endif
        @if($post->permalink && parse_url($post->permalink, PHP_URL_SCHEME) === 'https')<a href="{{ $post->permalink }}" target="_blank" rel="noopener noreferrer" class="text-sm text-blue-700 underline">Buka postingan asli ↗</a>@endif
        <a href="{{ route('comments.index', ['post' => $post->id]) }}#quick-comments" class="text-sm font-semibold text-blue-700 underline">Buat balasan / pesan</a>
        @if($localAi)<p class="text-xs text-slate-600">Saran AI (perlu ditinjau): {{ data_get($localAi, 'reason') }} {{ data_get($localAi, 'follow_up') }}</p>@endif
        @if(config('services.ollama.enabled'))<form method="POST" action="{{ route('discovery.posts.local', $post) }}">@csrf<button class="text-sm text-blue-700 underline">Minta saran AI lokal</button></form>@endif
        <form method="POST" action="{{ route('discovery.posts.review', $post) }}" class="rg-review-form">
            @csrf @method('PATCH')
            <select name="qualification" aria-label="Hasil penilaian" class="rounded-lg border border-slate-200 p-2 text-sm"><option value="qualified">Layak ditinjau</option><option value="uncertain">Perlu ditinjau</option><option value="rejected">Tidak lolos</option></select>
            <select name="category" aria-label="Kelompok peluang" class="rounded-lg border p-2 text-sm"><option value="business">Peluang bisnis</option><option value="community">Komunitas</option><option value="not_relevant">Tidak relevan</option></select>
            <input name="reason" required maxlength="1000" placeholder="Alasan penilaian" aria-label="Alasan penilaian" class="min-w-0 flex-1 rounded-lg border border-slate-200 p-2 text-sm">
            <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm text-white">Simpan penilaian</button>
        </form>
    </article>
@endforeach
{{ $posts->links() }}
