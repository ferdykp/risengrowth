<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CapturePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'platform' => ['required', Rule::in(['threads', 'web', 'instagram'])],
            'url' => ['bail', 'required', 'string', 'url:https', 'max:2048', function (string $attribute, mixed $value, Closure $fail): void {
                $parts = parse_url($value);
                if (isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
                    $fail('URL tidak boleh memuat kredensial atau port khusus.');
                }
                $host = strtolower($parts['host'] ?? '');
                if ($this->input('platform') === 'threads' && (! in_array($host, ['threads.net', 'www.threads.net', 'threads.com', 'www.threads.com'], true)
                    || ! preg_match('~^/(?:@[a-zA-Z0-9._]+/post/|t/)[a-zA-Z0-9_-]+/?$~', $parts['path'] ?? ''))) {
                    $fail('Gunakan permalink postingan Threads yang valid.');
                }
                if ($this->input('platform') === 'instagram' && (! in_array($host, ['instagram.com', 'www.instagram.com'], true)
                    || ! preg_match('~^/(?:p|reel)/[a-zA-Z0-9_-]+/?$~', $parts['path'] ?? ''))) {
                    $fail('Gunakan permalink postingan Instagram yang valid.');
                }
            }],
            'username' => ['nullable', 'string', 'max:191'],
            'body' => ['required', 'string', 'min:10', 'max:20000'],
            'keyword' => ['required', 'string', 'max:191'],
        ];
    }
}
