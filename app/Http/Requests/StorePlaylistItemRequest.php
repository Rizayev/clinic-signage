<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlaylistItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'media_id' => ['required', 'integer', 'exists:media,id'],
            'duration_seconds' => ['nullable', 'integer', 'min:1'],
            'transition_effect' => ['nullable', Rule::in(['none', 'fade', 'slide_left', 'slide_right', 'zoom', 'crossfade'])],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'days_of_week' => ['nullable', 'array'],
            'days_of_week.*' => ['integer', 'between:1,7'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
