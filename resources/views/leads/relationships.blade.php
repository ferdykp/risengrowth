@extends('layouts.workspace')
@section('title', 'Relasi & tindak lanjut')
@section('page_heading', 'Relasi & tindak lanjut')
@section('content')
<header class="rg-page-intro"><div><p class="rg-page-eyebrow">HUBUNGAN DAN TINDAK LANJUT</p><h1>Relasi & tindak lanjut</h1><p>Simpan catatan pribadi dan jadwalkan kapan kamu ingin melanjutkan percakapan.</p></div><a class="rg-action bg-white" href="{{ route('leads.index') }}">Pilih prospek</a></header>
<form class="rg-panel bg-white border rounded-2xl grid gap-4 sm:grid-cols-4" method="GET">
<label class="text-sm">Cari kontak<input name="q" value="{{ $filters['q'] ?? '' }}" class="w-full block border rounded-lg p-2 mt-1" placeholder="Nama atau isi postingan"></label>
<label class="text-sm">Tahap hubungan<select name="stage" class="w-full block border rounded-lg p-2 mt-1"><option value="">Semua tahap</option>@foreach(\App\Models\Relationship::STAGES as $stage)<option value="{{ $stage }}" @selected(($filters['stage'] ?? '') === $stage)>{{ ['discovered'=>'Ditemukan','reviewed'=>'Ditinjau','engaged'=>'Sudah berinteraksi','connected'=>'Terhubung','opportunity'=>'Ada peluang','won'=>'Berhasil','lost'=>'Tidak lanjut','dismissed'=>'Diabaikan'][$stage] ?? ucfirst($stage) }}</option>@endforeach</select></label>
<label class="flex gap-2 items-center"><input type="checkbox" name="due" value="1" @checked($filters['due'] ?? false)> Tindak lanjut jatuh tempo</label>
<div class="flex items-end gap-3"><button class="bg-slate-950 text-white rounded-lg px-4 py-2">Terapkan</button><a href="{{ route('relationships.index') }}" class="underline text-sm">Reset</a></div>
</form>
<div class="grid gap-4 md:grid-cols-2">
@forelse($relationships as $relationship)
<article class="rg-panel bg-white border rounded-2xl space-y-3"><div class="flex justify-between gap-3"><h2 class="font-bold">{{ $relationship->lead?->post?->username ?: 'Kontak tanpa nama' }}</h2><span class="text-xs rounded-full bg-orange-50 px-3 py-1">{{ ['discovered'=>'Ditemukan','reviewed'=>'Ditinjau','engaged'=>'Sudah berinteraksi','connected'=>'Terhubung','opportunity'=>'Ada peluang','won'=>'Berhasil','lost'=>'Tidak lanjut','dismissed'=>'Diabaikan'][$relationship->stage] ?? ucfirst($relationship->stage) }}</span></div><p class="text-xs uppercase text-slate-500">{{ ['community'=>'Komunitas','potential_client'=>'Calon klien','developer'=>'Developer','freelancer'=>'Freelancer','founder'=>'Pendiri','agency'=>'Agensi'][$relationship->category] ?? $relationship->category }} · {{ ucfirst($relationship->lead?->post?->platform ?? 'sumber tidak diketahui') }}</p><p class="text-sm text-slate-600">{{ \Illuminate\Support\Str::limit($relationship->lead?->post?->body, 180) }}</p><p class="text-sm whitespace-pre-wrap">{{ $relationship->notes ?: 'Belum ada catatan.' }}</p><p class="text-xs text-slate-500">Tindak lanjut: {{ $relationship->follow_up_on?->format('d M Y') ?? 'Belum dijadwalkan' }}</p><a class="rg-action" href="{{ route('leads.show', $relationship->lead_id) }}#relationship">Buka catatan relasi</a></article>
@empty<div class="rg-panel bg-white border border-dashed rounded-2xl md:col-span-2 py-12 text-center"><h2 class="font-bold">Belum ada relasi yang cocok.</h2><p class="text-sm text-slate-500 mt-2">Buka percakapan dari inbox, lalu simpan kategori, catatan, dan jadwal follow-up.</p></div>@endforelse
</div>
{{ $relationships->links() }}
@endsection
