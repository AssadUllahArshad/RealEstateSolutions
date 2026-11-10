<?php

namespace App\Http\Requests\Inventory;

use App\Models\PlotSize;
use App\Rules\PositiveDecimal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlotSizeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('configuration.manage') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $size = $this->route('size');

        return [
            'label' => ['required', 'string', 'max:80', Rule::unique('plot_sizes')->ignore($size instanceof PlotSize ? $size->id : null)],
            'area_marla' => ['required', new PositiveDecimal(6)],
            'society_id' => ['prohibited'],
            'created_by' => ['prohibited'],
        ];
    }
}
