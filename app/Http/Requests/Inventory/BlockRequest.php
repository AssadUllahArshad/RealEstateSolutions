<?php

namespace App\Http\Requests\Inventory;

use App\Models\Block;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('configuration.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => mb_strtoupper(preg_replace('/\s+/u', ' ', trim($this->input('code'))))]);
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $block = $this->route('block');

        return [
            'code' => ['required', 'string', 'max:32', Rule::unique('blocks')->ignore($block instanceof Block ? $block->id : null)],
            'name' => ['required', 'string', 'max:120'],
            'society_id' => ['prohibited'],
            'created_by' => ['prohibited'],
        ];
    }
}
