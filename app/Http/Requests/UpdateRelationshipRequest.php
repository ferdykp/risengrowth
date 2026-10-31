<?php

namespace App\Http\Requests;

use App\Models\Relationship;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRelationshipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'category' => ['required', Rule::in(Relationship::CATEGORIES)],
            'stage' => ['required', Rule::in(Relationship::STAGES)],
            'follow_up_on' => ['nullable', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'interaction' => ['nullable', 'string', 'max:2000'],
            'confirm_interaction' => ['required_if:stage,engaged,connected,opportunity,won,lost', Rule::when(in_array($this->input('stage'), ['engaged', 'connected', 'opportunity', 'won', 'lost'], true), ['accepted'])],
        ];
    }
}
