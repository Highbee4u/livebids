<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTermsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'summary_of_changes' => ['nullable', 'string', 'max:1000'],
            'is_required' => ['nullable', 'boolean'],
            'effective_date' => ['nullable', 'date'],
        ];
    }
}
