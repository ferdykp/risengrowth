<section id="quick-comments" class="space-y-6">
    <div class="grid gap-5 xl:grid-cols-2 items-start">
        <section class="rg-panel rounded-2xl border border-slate-200 bg-white space-y-5">
            <div>
                <h2 class="text-lg font-bold">Tempel postingan, siapkan komentar atau DM</h2>
                <p class="mt-2 text-sm text-slate-500">Nemu postingan di Threads? Salin teksnya ke sini. Kita buat balasan yang spesifik, santai, dan nyambung dengan isi postingan.</p>
            </div>
            <form id="quick-comment-form" method="POST" action="{{ route('comments.suggestions.store', [], false) }}" class="space-y-4">
                @csrf
                <div>
                    <label for="quick-post-text" class="rg-field-label">Isi postingan</label>
                    <textarea id="quick-post-text" name="post_text" required minlength="10" maxlength="6000" rows="7" placeholder="Contoh: Ada yang bisa bantu bikin website katalog buat toko kecil? Penginnya pelanggan bisa langsung chat WhatsApp." class="w-full rounded-xl border border-slate-200 p-3 text-sm">{{ old('post_text', $sourcePost?->body) }}</textarea>
                    <p class="mt-2 text-xs text-slate-500">Tempel teks lengkap agar komentarnya relevan. Link saja belum cukup.</p>
                </div>
                <div>
                    <label for="quick-post-url" class="rg-field-label">Link postingan (opsional)</label>
                    <input id="quick-post-url" name="post_url" type="url" maxlength="2048" value="{{ old('post_url', $sourcePost?->permalink) }}" placeholder="https://www.threads.com/@nama/post/..." class="w-full rounded-xl border border-slate-200 p-3 text-sm">
                    <p class="mt-2 text-xs text-slate-500">Hanya disimpan sebagai referensi. Isi halaman tidak diambil dari link.</p>
                </div>
                <div>
                    <label for="quick-purpose" class="rg-field-label">Mau membuat apa?</label>
                    <select id="quick-purpose" name="purpose" class="w-full rounded-xl border border-slate-200 p-3 text-sm">
                        <option value="comment" @selected(old('purpose', 'comment') === 'comment')>Komentar publik</option>
                        <option value="dm" @selected(old('purpose') === 'dm')>Draft DM pribadi</option>
                    </select>
                    <p class="mt-2 text-xs text-slate-500">DM dibuat sebagai pembuka percakapan, bukan menyalin komentar publik. Salin hasilnya, buka profil, lalu kirim sendiri di Threads jika penerima mengizinkan pesan.</p>
                </div>
                <div>
                    <label for="quick-tone" class="rg-field-label">Gaya bahasa</label>
                    <select id="quick-tone" name="tone" class="w-full rounded-xl border border-slate-200 p-3 text-sm">
                        <option value="casual" @selected(old('tone', 'casual') === 'casual')>Santai — seperti ngobrol</option>
                        <option value="friendly" @selected(old('tone') === 'friendly')>Friendly — ramah dan ringan</option>
                        <option value="insightful" @selected(old('tone') === 'insightful')>Insight — satu sudut pandang yang membantu</option>
                        <option value="direct" @selected(old('tone') === 'direct')>To the point — singkat dan jelas</option>
                        <option value="playful" @selected(old('tone') === 'playful')>Ringan — sedikit jenaka bila cocok</option>
                    </select>
                </div>
                <label class="flex items-start gap-3 rounded-xl bg-slate-50 p-3 text-sm text-slate-600">
                    <input type="hidden" name="allow_brand" value="0">
                    <input type="checkbox" name="allow_brand" value="1" class="mt-1" @checked((bool) old('allow_brand', true))>
                    <span>Boleh menyebut {{ config('services.comment_brand.mention') }} atau {{ config('services.comment_brand.website') }} bila relevan.<span class="mt-1 block text-xs text-slate-500">Tidak dipaksakan. Untuk diskusi umum dan promosi orang lain, balasan tetap tanpa promosi.</span></span>
                </label>
                <button type="submit" class="w-full rounded-xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white">Buat draft balasan</button>
                <p class="text-xs text-slate-500">{{ $aiConfigured ? 'Teks postingan akan diproses oleh provider AI yang dikonfigurasi.' : 'AI belum dikonfigurasi. Saran template gratis tetap tersedia.' }} Komentar dan DM bisa diedit dan disalin; tidak dikirim otomatis.</p>
            </form>
        </section>
        <section class="space-y-4" aria-label="Hasil komentar cepat">
            <div><h2 class="text-lg font-bold">Draft balasan kamu</h2><p class="mt-1 text-sm text-slate-500">Riwayat ini hanya terlihat oleh akunmu. Baca dulu, sesuaikan, lalu salin ke Threads.</p></div>
            @forelse($suggestions as $suggestion)
                <article class="rg-panel rounded-2xl border border-slate-200 bg-white space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{{ $suggestion->provider === 'template' ? 'Template gratis' : 'Saran AI' }} · {{ $suggestion->purpose === 'dm' ? 'DM pribadi' : 'Komentar' }} · {{ ucfirst($suggestion->tone) }}</span>
                        <span class="text-xs text-slate-400">{{ \Illuminate\Support\Carbon::parse($suggestion->created_at, 'UTC')->copy()->timezone('Asia/Jakarta')->format('d M, H:i \W\I\B') }}</span>
                    </div>
                    <details class="text-sm"><summary class="cursor-pointer font-semibold text-slate-600">Lihat postingan asal</summary><p class="mt-3 whitespace-pre-wrap break-words text-slate-600">{{ $suggestion->post_text }}</p></details>
                    <div class="flex flex-wrap gap-x-4 gap-y-2">
                    @if($suggestion->post_url)<a href="{{ $suggestion->post_url }}" target="_blank" rel="noopener noreferrer" class="inline-block text-sm font-semibold text-blue-700 underline">Buka postingan di Threads ↗</a>@endif
                    @if($suggestion->post_url && preg_match('~^https://(?:www\.)?threads\.(?:com|net)/@([a-zA-Z0-9._]+)/post/~', $suggestion->post_url, $profileMatch))
                        <a href="{{ 'https://www.threads.com/@'.$profileMatch[1] }}" target="_blank" rel="noopener noreferrer" class="inline-block text-sm font-semibold text-blue-700 underline">Buka profil penulis untuk DM ↗</a>
                    @endif
                    </div>
                    <form method="POST" action="{{ route('comments.suggestions.update', $suggestion->id, false) }}" class="space-y-3">
                        @csrf @method('PATCH')
                        <label for="suggestion-{{ $suggestion->id }}" class="rg-field-label">{{ $suggestion->purpose === 'dm' ? 'DM (boleh diedit)' : 'Komentar (boleh diedit)' }}</label>
                        <textarea id="suggestion-{{ $suggestion->id }}" name="body" rows="4" required minlength="10" maxlength="2000" class="w-full rounded-xl border border-slate-200 p-3 text-sm">{{ $suggestion->body }}</textarea>
                        <div class="flex flex-wrap gap-3">
                            <button type="button" data-copy-target="suggestion-{{ $suggestion->id }}" class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white">{{ $suggestion->purpose === 'dm' ? 'Salin DM' : 'Salin komentar' }}</button>
                            <button type="submit" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700">Simpan edit</button>
                        </div>
                    </form>
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center"><span class="material-symbols-outlined text-4xl text-slate-300" aria-hidden="true">chat_bubble</span><p class="mt-3 font-semibold">Belum ada ide komentar</p><p class="mt-2 text-sm text-slate-500">Tempel postingan di sebelah kiri. Hasilnya muncul di sini dan siap kamu edit.</p></div>
            @endforelse
            {{ $suggestions->links() }}
        </section>
    </div>
</section>
@push('scripts')
<script>
document.getElementById('quick-comment-form')?.addEventListener('submit', event => {
    const button = event.currentTarget.querySelector('button[type="submit"]');
    button.disabled = true; button.textContent = 'Menyusun balasan…'; button.setAttribute('aria-busy', 'true');
});
document.querySelectorAll('[data-copy-target]').forEach(button => button.addEventListener('click', async () => {
    const input = document.getElementById(button.dataset.copyTarget);
    try { await navigator.clipboard.writeText(input.value); button.textContent = 'Tersalin ✓'; }
    catch (_) { input.focus(); input.select(); button.textContent = 'Pilih teks lalu salin manual'; }
}));
</script>
@endpush
