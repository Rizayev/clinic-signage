<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmergencyMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'text' => ['required', 'string'],
            'target_type' => ['nullable', 'string', 'in:device,zone,branch,all'],
            'target_id' => ['nullable', 'integer'],
            'duration_seconds' => ['nullable', 'integer'],
            'scheduled_start' => ['nullable', 'date'],
            'scheduled_end' => ['nullable', 'date', 'after:scheduled_start'],
            'display_style' => ['nullable', 'string', 'in:fullscreen,banner'],
            'position' => ['nullable', 'string', 'in:top,bottom'],
            'font_size' => ['nullable', 'integer', 'min:12', 'max:200'],
            'blink' => ['nullable', 'boolean'],
            'background_color' => ['nullable', 'string', 'max:255'],
            'text_color' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
