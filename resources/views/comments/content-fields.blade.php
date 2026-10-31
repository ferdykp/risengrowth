@php($useOld = old('_content_id') === (string) ($content?->id ?? 'new'))
<input type="hidden" name="_content_id" value="{{ $content?->id ?? 'new' }}">
<label class="block text-sm">Topik<input required name="topic" maxlength="191" value="{{ $useOld ? old('topic') : $content?->topic }}" class="block w-full border rounded-lg p-2 mt-1"></label>
<div class="grid gap-3 sm:grid-cols-2">
<label class="block text-sm">Pilar<select name="pillar" class="block w-full border rounded-lg p-2 mt-1">@foreach(\App\Models\ContentDraft::PILLARS as $pillar)<option value="{{ $pillar }}" @selected(($useOld ? old('pillar') : $content?->pillar) === $pillar)>{{ str_replace('_', ' ', ucfirst($pillar)) }}</option>@endforeach</select></label>
<label class="block text-sm">Platform<select name="platform" class="block w-full border rounded-lg p-2 mt-1">@foreach(['threads', 'instagram', 'linkedin', 'other'] as $platform)<option value="{{ $platform }}" @selected(($useOld ? old('platform') : $content?->platform) === $platform)>{{ ucfirst($platform) }}</option>@endforeach</select></label>
</div>
<label class="block text-sm">Isi konten<textarea required name="body" maxlength="20000" rows="6" class="block w-full border rounded-lg p-3 mt-1">{{ $useOld ? old('body') : $content?->body }}</textarea></label>
<div class="grid gap-3 sm:grid-cols-2">
<label class="block text-sm">Status<select name="status" class="block w-full border rounded-lg p-2 mt-1">@foreach(\App\Models\ContentDraft::STATUSES as $status)<option value="{{ $status }}" @selected(($useOld ? old('status') : $content?->status) === $status)>{{ ucfirst($status) }}</option>@endforeach</select></label>
<label class="block text-sm">Tanggal publikasi (WIB)<input type="date" name="published_on" max="{{ now('Asia/Jakarta')->toDateString() }}" value="{{ $useOld ? old('published_on') : $content?->published_on?->format('Y-m-d') }}" class="block w-full border rounded-lg p-2 mt-1"></label>
</div>
<label class="flex gap-2 items-start text-sm"><input type="checkbox" name="confirm_published" value="1"><span>Saya sudah menerbitkan konten ini sendiri. Wajib untuk status Published.</span></label>
<details><summary class="text-sm cursor-pointer">Performa & catatan manual</summary><div class="grid gap-3 mt-3 sm:grid-cols-2"><label class="text-sm">Impressions<input type="number" min="0" name="impressions" value="{{ $useOld ? old('impressions') : $content?->impressions }}" class="block w-full border rounded-lg p-2"></label><label class="text-sm">Engagements<input type="number" min="0" name="engagements" value="{{ $useOld ? old('engagements') : $content?->engagements }}" class="block w-full border rounded-lg p-2"></label></div><label class="block text-sm mt-3">Catatan<textarea name="notes" maxlength="5000" class="block w-full border rounded-lg p-2 mt-1">{{ $useOld ? old('notes') : $content?->notes }}</textarea></label></details>
<button class="bg-slate-950 text-white rounded-xl px-4 py-2">Simpan konten</button>
