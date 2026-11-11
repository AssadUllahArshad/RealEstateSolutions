<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SignedDecimal implements ValidationRule
{
    public function __construct(private bool $allowZero = true) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^-?\d{1,16}(?:\.\d{1,2})?$/D', $value)) {
            $fail('Enter a signed decimal amount with at most two decimal places.');

            return;
        }

        if (! $this->allowZero && preg_match('/^-?0+(?:\.0{1,2})?$/D', $value)) {
            $fail('Enter a non-zero amount.');
        }
    }
}
