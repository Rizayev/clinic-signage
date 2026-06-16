<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'device_code' => ['required', 'string', 'max:255', 'unique:devices,device_code'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'zone_id' => ['nullable', 'integer', 'exists:zones,id'],
            'device_type' => ['nullable', 'string', 'in:android_tv,android_box,browser_player,windows_player,raspberry_player'],
            'screen_orientation' => ['nullable', 'string', 'in:landscape,portrait'],
        ];
    }
}
