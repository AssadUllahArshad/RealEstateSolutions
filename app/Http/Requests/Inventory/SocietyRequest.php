<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class SocietyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('configuration.manage') ?? false;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'office_name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:160'],
            'address' => ['nullable', 'string', 'max:500'],
            'id' => ['prohibited'],
            'society_id' => ['prohibited'],
        ];
    }
}
