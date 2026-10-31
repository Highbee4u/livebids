<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PlaceBidRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'amount_kobo' => ['required', 'integer', 'min:10000'],
            'client_request_id' => ['nullable', 'string', 'max:100'],
        ];
    }
}
