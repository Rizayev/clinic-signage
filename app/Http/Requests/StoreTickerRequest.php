<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTickerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'text' => ['required', 'string'],
            'target_type' => ['nullable', 'string', 'in:device,zone,branch,all'],
            'target_id' => ['nullable', 'integer'],
            'position' => ['nullable', 'string', 'in:top,bottom'],
            'speed' => ['nullable', 'integer'],
            'font_size' => ['nullable', 'integer'],
            'text_color' => ['nullable', 'string', 'max:255'],
            'background_color' => ['nullable', 'string', 'max:255'],
            'opacity' => ['nullable', 'numeric', 'between:0,1'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'repeat_count' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'interval_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
