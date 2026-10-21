            @forelse($postReviews as $analysis)
                <article class="border-t border-slate-100 pt-4 space-y-2">
                    <p class="text-xs text-slate-500">{{ $analysis->output['source'] ?? $analysis->post->platform }} · {{ $analysis->output['status'] ?? $analysis->status }} · {{ $analysis->score === null ? 'Manual' : $analysis->score.'/100' }}</p>
                    <p class="text-sm whitespace-pre-wrap break-words">{{ $analysis->post->body }}</p>
                    <p class="text-xs text-slate-600">{{ $analysis->output['rationale'] ?? '' }}</p>
                    @if(!empty($analysis->output['evidence']))<p class="text-xs text-slate-500 break-words">Bukti: {{ json_encode($analysis->output['evidence'], JSON_UNESCAPED_UNICODE) }}</p>@endif
                    @if($analysis->post->permalink && parse_url($analysis->post->permalink, PHP_URL_SCHEME) === 'https')<a href="{{ $analysis->post->permalink }}" target="_blank" rel="noopener noreferrer" class="text-sm text-blue-700 underline">Postingan asli ↗</a>@endif
                    @if(!empty($analysis->output['local_ai']))<p class="text-xs text-slate-600">Saran AI (perlu review): {{ $analysis->output['local_ai']['reason'] }} {{ $analysis->output['local_ai']['follow_up'] }}</p>@endif
                    @if(config('services.ollama.enabled'))<form method="POST" action="{{ route('discovery.posts.local', $analysis->post, false) }}">@csrf<button class="text-sm text-blue-700 underline">Minta saran AI lokal</button></form>@endif
                    <form method="POST" action="{{ route('discovery.posts.review', $analysis->post, false) }}" class="flex flex-col sm:flex-row gap-2">
                        @csrf @method('PATCH')
                        <select name="qualification" aria-label="Hasil review" class="rounded-lg border border-slate-200 p-2 text-sm"><option value="qualified">Qualified</option><option value="uncertain">Perlu review</option><option value="rejected">Ditolak</option></select>
                        <input name="reason" required maxlength="1000" placeholder="Alasan review" aria-label="Alasan review" class="min-w-0 flex-1 rounded-lg border border-slate-200 p-2 text-sm">
                        <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm text-white">Simpan review</button>
                    </form>
                </article>
            @empty<p class="text-sm text-slate-500">Belum ada hasil kualifikasi.</p>@endforelse
            {{ $postReviews->links() }}
