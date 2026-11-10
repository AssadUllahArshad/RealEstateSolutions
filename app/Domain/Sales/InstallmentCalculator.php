<?php

namespace App\Domain\Sales;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use RoundingMode;

class InstallmentCalculator
{
    /** @return numeric-string */
    public function round(string $amount, string $mode): string
    {
        if (! is_numeric($amount)) {
            throw ValidationException::withMessages(['plan' => 'A calculation amount is invalid.']);
        }

        return bcround($amount, 2, match ($mode) {
            'half_up' => RoundingMode::HalfAwayFromZero,
            'half_even' => RoundingMode::HalfEven,
            'down' => RoundingMode::TowardsZero,
            default => throw ValidationException::withMessages(['plan' => 'Main admin must configure the rounding rule.']),
        });
    }

    /** @param array<string,mixed> $input
     * @param  array<string,mixed>  $booking
     * @param  array<string,mixed>  $policy
     * @return array<string,mixed>
     */
    public function calculate(string $gross, array $input, array $booking, array $policy): array
    {
        if (! is_numeric($gross)) {
            throw ValidationException::withMessages(['plan' => 'The proposed gross amount is invalid.']);
        }
        $net = bcsub($gross, $input['discount'], 2);
        if (bccomp($net, '0', 2) <= 0) {
            throw ValidationException::withMessages(['discount' => 'Discount must be less than the proposed gross price.']);
        }
        $minimum = $booking['down_payment_type'] === 'fixed' ? $booking['down_payment_value'] : $this->round(bcdiv(bcmul($net, $booking['down_payment_value'], 6), '100', 8), $policy['rounding']);
        if (bccomp($input['down_payment'], $minimum, 2) < 0 || bccomp($input['down_payment'], $net, 2) > 0) {
            throw ValidationException::withMessages(['down_payment' => 'Planned down payment must meet the configured minimum and cannot exceed the net price.']);
        }
        $balance = bcsub($net, $input['down_payment'], 2);
        $schedule = [];
        if ($input['frequency'] === 'full') {
            if (bccomp($balance, '0', 2) !== 0) {
                throw ValidationException::withMessages(['down_payment' => 'Full payment requires a planned payment equal to the net price.']);
            }
        } elseif (! in_array($input['frequency'], $policy['frequencies'], true)) {
            throw ValidationException::withMessages(['frequency' => 'This plan frequency is not enabled by main admin.']);
        } elseif ($input['frequency'] === 'custom') {
            $sum = '0.00';
            $previous = null;
            foreach ($input['custom'] as $row) {
                if ($previous !== null && $row['due_date'] <= $previous) {
                    throw ValidationException::withMessages(['custom' => 'Custom due dates must be distinct and in ascending order.']);
                }
                $previous = $row['due_date'];
                $sum = bcadd($sum, $row['amount'], 2);
                $schedule[] = ['sequence' => count($schedule) + 1, 'due_date' => $row['due_date'], 'amount' => bcadd($row['amount'], '0', 2)];
            }
            if (bccomp($sum, $balance, 2) !== 0) {
                throw ValidationException::withMessages(['custom' => 'Custom installments must total the remaining balance exactly.']);
            }
        } else {
            $interval = match ($input['frequency']) {
                'monthly' => 1, 'quarterly' => 3, 'half_yearly' => 6, 'yearly' => 12,
                default => throw ValidationException::withMessages(['frequency' => 'Choose a supported plan frequency.']),
            };
            $count = (int) $input['count'];
            if ($count < 1 || $count > 360) {
                throw ValidationException::withMessages(['count' => 'Enter between 1 and 360 installments.']);
            }
            $anchor = CarbonImmutable::createFromFormat('!Y-m-d', $input['first_due_date'], 'Asia/Karachi');
            $common = $this->round(bcdiv($balance, (string) $count, 10), $policy['rounding']);
            $remainder = bcsub($balance, bcmul($common, (string) $count, 2), 2);
            for ($index = 0; $index < $count; $index++) {
                $month = $anchor->startOfMonth()->addMonths($index * $interval);
                $day = $policy['month_end'] === 'clamp' ? min($anchor->day, $month->daysInMonth) : $anchor->day;
                $date = $month->addDays($day - 1);
                if ($date->year > 9999) {
                    throw ValidationException::withMessages(['first_due_date' => 'Schedule extends beyond the supported calendar.']);
                }
                $adjust = $policy['residual'] === 'first' ? $index === 0 : $index === $count - 1;
                $schedule[] = ['sequence' => $index + 1, 'due_date' => $date->format('Y-m-d'), 'amount' => $adjust ? bcadd($common, $remainder, 2) : $common];
            }
        }
        foreach ($schedule as $row) {
            if (bccomp($row['amount'], '0', 2) <= 0) {
                throw ValidationException::withMessages(['plan' => 'Every installment must be positive. Reduce the installment count or use a custom schedule.']);
            }
        }

        return ['gross' => $gross, 'discount' => $input['discount'], 'net' => $net, 'minimum_down_payment' => bcadd($minimum, '0', 2), 'down_payment' => $input['down_payment'], 'balance' => $balance, 'schedule' => $schedule];
    }
}
