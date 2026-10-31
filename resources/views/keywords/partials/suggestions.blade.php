@php
    $suggestionGroups = [
        'direct_demand' => [
            'label' => 'Kebutuhan jasa',
            'description' => 'Cari permintaan bantuan yang lebih jelas.',
            'phrases' => ['butuh website', 'cari jasa website', 'nyari jasa website', 'rekomendasi jasa website', 'cari developer', 'butuh aplikasi', 'jasa bikin website', 'bikin website toko'],
        ],
        'business_pain' => [
            'label' => 'Masalah bisnis',
            'description' => 'Temukan kendala yang bisa dibantu dengan software.',
            'phrases' => ['stok barang berantakan', 'pencatatan masih manual', 'laporan penjualan', 'aplikasi kasir', 'sistem stok barang', 'rekap pesanan', 'absensi karyawan', 'sistem booking'],
        ],
        'developer_community' => [
            'label' => 'Komunitas developer',
            'description' => 'Mulai diskusi, networking, dan kolaborasi.',
            'phrases' => ['ngoding', 'belajar laravel', 'developer indonesia', 'programmer indonesia', 'freelance developer', 'proyek sampingan', 'bangun produk', 'bikin aplikasi', 'cari partner ngoding', 'pengalaman freelance', 'deploy aplikasi', 'cerita ngoding'],
        ],
    ];
@endphp
<div class="rg-keyword-suggestions {{ ($compact ?? false) ? 'rg-keyword-suggestions-compact' : '' }}">
    @foreach($suggestionGroups as $category => $group)
        <div class="rg-keyword-suggestion-group">
            <h3>{{ $group['label'] }}</h3>
            @unless($compact ?? false)<p>{{ $group['description'] }}</p>@endunless
            <div class="rg-keyword-chips">
                @foreach($group['phrases'] as $phrase)
                    <button type="button" data-keyword-suggestion="{{ $phrase }}" data-keyword-category="{{ $category }}" aria-label="Gunakan keyword {{ $phrase }}">{{ $phrase }}<span aria-hidden="true">＋</span></button>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
