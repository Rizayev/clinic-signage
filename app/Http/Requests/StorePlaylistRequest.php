<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePlaylistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'description' => ['nullable', 'string'],
        ];
    }
}
