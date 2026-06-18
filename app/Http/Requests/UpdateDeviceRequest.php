<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'zone_id' => ['nullable', 'integer', 'exists:zones,id'],
            'screen_orientation' => ['nullable', 'string', 'in:landscape,portrait'],
            'audio_enabled' => ['nullable', 'boolean'],
            'device_type' => ['nullable', 'string', 'in:android_tv,android_box,browser_player,windows_player,raspberry_player'],
            'status' => ['nullable', 'string', 'in:online,offline,error,updating,disabled'],
            'resolution' => ['nullable', 'string', 'max:255'],
        ];
    }
}
