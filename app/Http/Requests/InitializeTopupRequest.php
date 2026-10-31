<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InitializeTopupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'amount_kobo' => ['required', 'integer', 'min:10000', 'max:500000000'], // ₦100 min to ₦5,000,000 max
            'callback_url' => ['nullable', 'url'],
        ];
    }
}
