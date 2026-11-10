<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Percentage implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! is_numeric($value) || ! preg_match('/^\d{1,3}(?:\.\d{1,4})?$/D', $value) || bccomp($value, '100', 4) > 0) {
            $fail('Enter a percentage from 0 to 100 with at most four decimal places.');
        }
    }
}
