<?php

namespace App\Http\Controllers;

use App\Models\SearchKeyword;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class KeywordController extends Controller
{
    private const CATEGORIES = ['direct_demand', 'business_pain', 'developer_community'];

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable','string','max:100'],
            'category' => ['nullable',Rule::in(self::CATEGORIES)],
            'status' => ['nullable',Rule::in(['active','inactive','deleted'])],
        ]);
        $query = SearchKeyword::query()->where('user_id', Auth::id());
        if (($filters['status'] ?? null) === 'deleted') {
            $query->onlyTrashed();
        } elseif (($filters['status'] ?? null) === 'active') {
            $query->where('enabled', true);
        } elseif (($filters['status'] ?? null) === 'inactive') {
            $query->where('enabled', false);
        }
        if (filled($filters['q'] ?? null)) {
            $query->where('phrase','like','%'.$filters['q'].'%');
        }
        if (filled($filters['category'] ?? null)) {
            $query->where('category',$filters['category']);
        }
        return view('keywords.index', [
            'keywords' => $query->orderByDesc('created_at')->orderByDesc('id')->paginate(12)->withQueryString(),
            'filters' => $filters,
            'categories' => self::CATEGORIES,
            'activeCount' => SearchKeyword::where('user_id',Auth::id())->where('enabled',true)->count(),
            'totalCount' => SearchKeyword::where('user_id',Auth::id())->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['user_id'] = Auth::id();
        SearchKeyword::create($data);
        return redirect()->route('keywords.index')->with('success','Keyword berhasil ditambahkan.');
    }

    public function update(Request $request, SearchKeyword $keyword): RedirectResponse
    {
        $this->authorizeOwner($keyword);
        $data = $this->validated($request, $keyword->id);
        $keyword->update($data);
        return redirect()->route('keywords.index')->with('success','Keyword berhasil diperbarui.');
    }

    public function toggle(SearchKeyword $keyword): RedirectResponse
    {
        $this->authorizeOwner($keyword);
        $keyword->update(['enabled' => ! $keyword->enabled]);
        return back()->with('success', $keyword->enabled ? 'Keyword diaktifkan.' : 'Keyword dinonaktifkan.');
    }

    public function destroy(SearchKeyword $keyword): RedirectResponse
    {
        $this->authorizeOwner($keyword);
        $keyword->delete();
        return back()->with('success','Keyword dipindahkan ke arsip.');
    }

    public function restore(int $keyword): RedirectResponse
    {
        $record = SearchKeyword::onlyTrashed()->where('user_id',Auth::id())->findOrFail($keyword);
        $record->restore();
        return redirect()->route('keywords.index', ['status'=>'deleted'])->with('success','Keyword berhasil dipulihkan.');
    }

    private function authorizeOwner(SearchKeyword $keyword): void
    {
        abort_unless($keyword->user_id === Auth::id(), 404);
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'phrase' => ['required','string','min:2','max:191'],
            'platform' => ['required',Rule::in(['threads'])],
            'category' => ['required',Rule::in(self::CATEGORIES)],
            'search_type' => ['required',Rule::in(['RECENT','TOP'])],
            'interval_minutes' => ['nullable','integer','min:30','max:10080'],
            'notes' => ['nullable','string','max:2000'],
            'enabled' => ['required','boolean'],
        ]);
        $data['phrase'] = trim(preg_replace('/\s+/u',' ', $data['phrase']));
        $data['normalized_phrase'] = mb_strtolower($data['phrase']);
        if (mb_strlen($data['phrase']) < 2) {
            throw ValidationException::withMessages(['phrase'=>'Keyword minimal 2 karakter.']);
        }
        $exists = SearchKeyword::withTrashed()
            ->where('user_id', Auth::id())
            ->where('platform',$data['platform'])
            ->where('normalized_phrase',$data['normalized_phrase'])
            ->where('category',$data['category'])
            ->when($ignoreId,fn ($q) => $q->where('id','!=',$ignoreId))
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages(['phrase'=>'Keyword pada kategori ini sudah ada (termasuk di arsip). Pulihkan keyword lama jika diperlukan.']);
        }
        return $data;
    }
}
