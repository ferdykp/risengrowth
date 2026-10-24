@php($clockNow = now()->timezone('Asia/Jakarta'))
<div data-wib-clock data-server-time="{{ $clockNow->toIso8601String() }}" class="{{ ($compact ?? false) ? 'flex items-center gap-2 text-[11px] text-slate-500' : 'text-right' }}" aria-label="Waktu Indonesia Barat">
    <time data-wib-date datetime="{{ $clockNow->toIso8601String() }}" class="{{ ($compact ?? false) ? '' : 'block text-[11px] text-slate-500' }}">{{ $clockNow->locale('id')->translatedFormat('d M Y') }}</time>
    <time data-wib-time datetime="{{ $clockNow->toIso8601String() }}" class="{{ ($compact ?? false) ? 'font-semibold' : 'block text-sm font-semibold text-slate-700' }} tabular-nums whitespace-nowrap">{{ $clockNow->format('H:i:s') }} WIB</time>
</div>
