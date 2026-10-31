<?php

namespace App\Http\Requests;

use App\Models\ContentDraft;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveContentDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        abort_if($this->route('contentDraft') && $this->route('contentDraft')->user_id !== $this->user()?->id, 404);

        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'topic' => ['required', 'string', 'max:191'],
            'pillar' => ['required', Rule::in(ContentDraft::PILLARS)],
            'platform' => ['required', Rule::in(['threads', 'instagram', 'linkedin', 'other'])],
            'body' => ['required', 'string', 'max:20000'],
            'status' => ['required', Rule::in(ContentDraft::STATUSES)],
            'published_on' => ['nullable', 'required_if:status,published', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Jakarta')->toDateString()],
            'confirm_published' => ['accepted_if:status,published'],
            'impressions' => ['nullable', 'integer', 'min:0', 'max:2147483647'],
            'engagements' => ['nullable', 'integer', 'min:0', 'max:2147483647'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
