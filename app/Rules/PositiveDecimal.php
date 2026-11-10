<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PositiveDecimal implements ValidationRule
{
    public function __construct(private int $integerDigits = 16) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! is_numeric($value) || ! preg_match('/^\d{1,'.$this->integerDigits.'}(?:\.\d{1,2})?$/D', $value) || bccomp($value, '0', 2) <= 0) {
            $fail('Enter a positive decimal amount with at most two decimal places.');
        }
    }
}
