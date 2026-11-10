<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

class UpdateUserAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        $subject = $this->route('user');

        return $subject instanceof User && ($this->user()?->can('updateAccess', $subject) ?? false);
    }

    /** @return array<string, array<int, ValidationRule|In|string>> */
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::in(['super_admin', 'admin', 'accountant', 'sales_agent'])],
            'permissions' => ['prohibited'],
            'actor_id' => ['prohibited'],
            'user_id' => ['prohibited'],
        ];
    }
}
