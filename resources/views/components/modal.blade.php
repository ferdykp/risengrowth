@props(['id', 'title', 'description' => null])
<dialog id="{{ $id }}" {{ $attributes->class(['rg-dialog']) }} aria-labelledby="{{ $id }}-title" @if($description) aria-describedby="{{ $id }}-description" @endif>
    <header class="rg-dialog-header">
        <div class="min-w-0">
            <h2 id="{{ $id }}-title">{{ $title }}</h2>
            @if($description)<p id="{{ $id }}-description">{{ $description }}</p>@endif
        </div>
        <button type="button" class="rg-dialog-close" data-dialog-close aria-label="Tutup {{ strtolower($title) }}">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" stroke-linecap="round"/></svg>
        </button>
    </header>
    {{ $slot }}
</dialog>
