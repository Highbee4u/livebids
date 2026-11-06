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
            'phone' => ['required', 'string', 'min:11', 'max:20'],
            'device_id' => ['required', 'string', 'max:100'],
            'platform' => ['nullable', 'string', 'in:android,ios,web'],
            'fcm_token' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required' => 'Phone number is required',
            'phone.min' => 'Phone number must be at least 11 digits',
            'phone.max' => 'Phone number must not be more than 20 digits',
            'device_id.required' => 'Device ID is required',
            'platform.in' => 'Platform must be android, ios, or web',
            'fcm_token.max' => 'FCM token must not be more than 255 characters',
        ];
    }
}
