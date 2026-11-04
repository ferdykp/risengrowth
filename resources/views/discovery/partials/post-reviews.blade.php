            @forelse($postReviews as $analysis)
                <article class="rg-review-card">
                    <p class="text-xs text-slate-500">{{ $analysis->output['source'] ?? ucfirst($analysis->post->platform) }} · {{ ['qualified' => 'Layak ditinjau', 'uncertain' => 'Perlu ditinjau', 'rejected' => 'Tidak lolos'][$analysis->output['status'] ?? $analysis->status] ?? ucfirst($analysis->status) }} · {{ $analysis->score === null ? 'Tanpa skor' : $analysis->score.'/100' }}</p>
                    <p class="text-xs text-slate-500">{{ ['business' => 'Peluang bisnis', 'community' => 'Komunitas', 'not_relevant' => 'Tidak relevan'][$analysis->output['category'] ?? ''] ?? 'Belum dikelompokkan' }} · Tingkat keyakinan: {{ $analysis->output['confidence'] ?? 'tidak tersedia' }} · Status review: {{ ['pending' => 'Menunggu', 'reviewed' => 'Sudah ditinjau'][$analysis->output['review_status'] ?? 'pending'] ?? ($analysis->output['review_status'] ?? 'Menunggu') }}</p>
                    <p class="text-sm whitespace-pre-wrap break-words">{{ $analysis->post->body }}</p>
                    <p class="text-xs text-slate-600">{{ $analysis->output['rationale'] ?? '' }}</p>
                    @if(!empty($analysis->output['evidence']))<p class="rg-review-evidence text-xs text-slate-500 break-words">Bukti: {{ json_encode($analysis->output['evidence'], JSON_UNESCAPED_UNICODE) }}</p>@endif
                    @if($analysis->post->permalink && parse_url($analysis->post->permalink, PHP_URL_SCHEME) === 'https')<a href="{{ $analysis->post->permalink }}" target="_blank" rel="noopener noreferrer" class="text-sm text-blue-700 underline">Postingan asli ↗</a>@endif
                    <a href="{{ route('comments.index', ['post' => $analysis->post->id], false) }}#quick-comments" class="text-sm font-semibold text-blue-700 underline">Buat komentar / draft DM</a>
                    @if(!empty($analysis->output['local_ai']))<p class="text-xs text-slate-600">Saran AI (perlu review): {{ $analysis->output['local_ai']['reason'] }} {{ $analysis->output['local_ai']['follow_up'] }}</p>@endif
                    @if(config('services.ollama.enabled'))<form method="POST" action="{{ route('discovery.posts.local', $analysis->post, false) }}">@csrf<button class="text-sm text-blue-700 underline">Minta saran AI lokal</button></form>@endif
                    <form method="POST" action="{{ route('discovery.posts.review', $analysis->post, false) }}" class="rg-review-form">
                        @csrf @method('PATCH')
                        <select name="qualification" aria-label="Hasil penilaian" class="rounded-lg border border-slate-200 p-2 text-sm"><option value="qualified">Layak ditinjau</option><option value="uncertain">Perlu ditinjau</option><option value="rejected">Tidak lolos</option></select>
                        <select name="category" aria-label="Kelompok peluang" class="rounded-lg border p-2 text-sm"><option value="business">Peluang bisnis</option><option value="community">Komunitas</option><option value="not_relevant">Tidak relevan</option></select>
                        <input name="reason" required maxlength="1000" placeholder="Alasan penilaian" aria-label="Alasan penilaian" class="min-w-0 flex-1 rounded-lg border border-slate-200 p-2 text-sm">
                        <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm text-white">Simpan penilaian</button>
                    </form>
                </article>
            @empty<p class="text-sm text-slate-500">Belum ada hasil kualifikasi.</p>@endforelse
            {{ $postReviews->links() }}
