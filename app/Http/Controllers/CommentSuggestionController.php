<?php

namespace App\Http\Controllers;

use App\Services\Discovery\GenerateCommentSuggestion;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CommentSuggestionController extends Controller
{
    public function store(Request $request, GenerateCommentSuggestion $generator): RedirectResponse
    {
        $request->merge(['post_text' => is_string($request->input('post_text')) ? trim($request->input('post_text')) : $request->input('post_text')]);
        $data = $request->validate([
            'post_text' => ['bail', 'required', 'string', 'min:10', 'max:6000', function (string $attribute, mixed $value, Closure $fail): void {
                if (filter_var($value, FILTER_VALIDATE_URL)) {
                    $fail('Tempel isi postingannya, bukan hanya link. Link boleh diisi sebagai referensi opsional.');
                }
            }],
            'post_url' => ['bail', 'nullable', 'string', 'max:2048', 'url:https', function (string $attribute, mixed $value, Closure $fail): void {
                $parts = parse_url($value);
                if (! is_array($parts) || ! in_array(strtolower($parts['host'] ?? ''), ['threads.net', 'www.threads.net', 'threads.com', 'www.threads.com'], true)
                    || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])
                    || ! preg_match('~^/(?:@[a-zA-Z0-9._]+/post/|t/)[a-zA-Z0-9_-]+/?$~', $parts['path'] ?? '')) {
                    $fail('Gunakan link postingan HTTPS dari threads.net atau threads.com.');
                }
            }],
            'tone' => ['required', Rule::in(['casual', 'friendly'])],
        ]);
        $result = $generator->generate($data['post_text'], $data['tone']);
        DB::table('comment_suggestions')->insert([
            'user_id' => $request->user()->id, 'post_text' => $data['post_text'], 'post_url' => $data['post_url'] ?? null,
            'tone' => $data['tone'], 'provider' => $result['provider'], 'body' => $result['body'], 'created_at' => now(), 'updated_at' => now(),
        ]);

        return redirect()->to(route('comments.index', [], false).'#quick-comments')->withInput($data)->with('success', $result['provider'] === 'template'
            ? 'Saran template siap. AI belum tersedia; edit sesuai postingan lalu salin ke Threads.'
            : 'Saran komentar siap. Baca dan edit dulu, lalu salin ke Threads.');
    }

    public function update(Request $request, int $suggestion): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'min:10', 'max:2000']]);
        $record = DB::table('comment_suggestions')->where('id', $suggestion)->where('user_id', $request->user()->id)->first();
        abort_unless($record, 404);
        DB::table('comment_suggestions')->where('id', $record->id)->where('user_id', $request->user()->id)
            ->update(['body' => trim($data['body']), 'updated_at' => now()]);

        return redirect()->to(route('comments.index', [], false).'#quick-comments')->with('success', 'Perubahan komentar tersimpan.');
    }
}
