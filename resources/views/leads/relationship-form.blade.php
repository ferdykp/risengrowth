<section class="rg-panel bg-white rounded-2xl border border-slate-200 space-y-4" id="relationship">
<h2 class="font-bold">Relationship & follow-up</h2>
<p class="text-sm text-slate-500">Catatan relasi bersifat pribadi. Networking tidak harus menjadi peluang penjualan.</p>
<form method="POST" action="{{ route('relationships.update', $lead) }}" class="space-y-4">
@csrf @method('PUT')
<label class="block text-sm">Kategori relasi<select name="category" class="block w-full border rounded-lg p-2 mt-1">@foreach(\App\Models\Relationship::CATEGORIES as $category)<option value="{{ $category }}" @selected(old('category', $relationship?->category ?? 'community') === $category)>{{ str_replace('_', ' ', ucfirst($category)) }}</option>@endforeach</select></label>
<label class="block text-sm">Tahap<select name="stage" class="block w-full border rounded-lg p-2 mt-1">@foreach(\App\Models\Relationship::STAGES as $stage)<option value="{{ $stage }}" @selected(old('stage', $relationship?->stage ?? 'discovered') === $stage)>{{ ucfirst($stage) }}</option>@endforeach</select></label>
<label class="block text-sm">Follow-up (WIB)<input type="date" name="follow_up_on" value="{{ old('follow_up_on', $relationship?->follow_up_on?->format('Y-m-d')) }}" class="block w-full border rounded-lg p-2 mt-1"></label>
<label class="block text-sm">Catatan relasi<textarea name="notes" maxlength="5000" class="block w-full border rounded-lg p-2 mt-1">{{ old('notes', $relationship?->notes) }}</textarea></label>
<label class="block text-sm">Interaksi baru (opsional)<textarea name="interaction" maxlength="2000" class="block w-full border rounded-lg p-2 mt-1" placeholder="Misalnya: sudah membalas pertanyaan tentang alur inventory.">{{ old('interaction') }}</textarea></label>
<label class="flex gap-2 items-start text-sm"><input type="checkbox" name="confirm_interaction" value="1"><span>Saya mengonfirmasi interaksi/keputusan ini benar-benar dilakukan. Wajib untuk Engaged hingga Won/Lost.</span></label>
<button class="bg-slate-950 text-white rounded-xl px-4 py-2">Simpan relasi</button>
</form>
</section>
