<?php

namespace App\Http\Requests\Inventory;

use App\Models\Plot;
use App\Models\PlotOwner;
use App\Rules\Percentage;
use App\Rules\PositiveDecimal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inventory.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('number'))) {
            $this->merge(['number' => mb_strtoupper(preg_replace('/\s+/u', ' ', trim($this->input('number'))))]);
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $plot = $this->route('plot');

        return [
            'block_id' => ['required', 'integer', 'exists:blocks,id'],
            'plot_size_id' => ['required', 'integer', 'exists:plot_sizes,id'],
            'number' => ['required', 'string', 'max:64', Rule::unique('plots')->where('block_id', $this->input('block_id'))->ignore($plot instanceof Plot ? $plot->id : null)],
            'list_price' => ['nullable', new PositiveDecimal],
            'plot_owner_id' => [Rule::requiredIf(fn (): bool => PlotOwner::query()->exists()), 'nullable', 'integer', 'exists:plot_owners,id'],
            'owner_percentage' => ['nullable', new Percentage],
            'society_id' => ['prohibited'],
            'created_by' => ['prohibited'],
            'status' => ['prohibited'],
            'availability' => ['prohibited'],
        ];
    }
}
