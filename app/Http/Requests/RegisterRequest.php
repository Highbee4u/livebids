<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'min:10', 'max:20'],
            'device_id' => ['nullable', 'string', 'max:100'],
            'platform' => ['nullable', 'string', 'in:android,ios,web'],
            'fcm_token' => ['nullable', 'string', 'max:255'],
        ];
    }
}
