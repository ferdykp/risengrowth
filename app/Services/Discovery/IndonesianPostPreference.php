<?php

namespace App\Services\Discovery;

use App\Models\DiscoveredPost;
use Illuminate\Database\Eloquent\Builder;

class IndonesianPostPreference
{
    public const MARKERS = ['butuh', 'membutuhkan', 'mencari', 'pembuatan', 'rekomendasi', 'gimana', 'banget', 'jualan', 'bantu', 'pengin', 'ingin', 'punya', 'saya', 'kamu', 'untuk', 'dengan', 'jasa', 'toko', 'usaha', 'kasir', 'gudang'];

    public function prioritize(Builder $query, string $postForeignKey): void
    {
        $clauses = array_fill(0, count(self::MARKERS), 'LOWER(body) LIKE ?');
        $patterns = array_map(fn (string $word): string => '%'.$word.'%', self::MARKERS);
        $rank = DiscoveredPost::query()->selectRaw('CASE WHEN ('.implode(' OR ', $clauses).') THEN 1 ELSE 0 END', $patterns)
            ->whereColumn('discovered_posts.id', $postForeignKey)->limit(1);
        $query->orderByDesc($rank);
    }
}
