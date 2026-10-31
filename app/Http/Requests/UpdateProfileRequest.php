<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'name' => ['nullable', 'string', 'min:2', 'max:100'],
            'email' => ['nullable', 'email', 'max:150', "unique:users,email,{$userId}"],
            'avatar_url' => ['nullable', 'url', 'max:500'],
            'pickup_area' => ['nullable', 'string', 'max:150'],
            'email_notifications_enabled' => ['nullable', 'boolean'],
            'sms_notifications_enabled' => ['nullable', 'boolean'],
            'push_notifications_enabled' => ['nullable', 'boolean'],
        ];
    }
}
