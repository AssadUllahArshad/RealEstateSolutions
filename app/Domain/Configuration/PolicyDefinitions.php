<?php

namespace App\Domain\Configuration;

class PolicyDefinitions
{
    /** @return array<string,array{title:string,description:string,fields:array<string,array<string,mixed>>}> */
    public static function all(): array
    {
        return [
            'receipt_posting' => ['title' => 'Receipt posting controls', 'description' => 'Configure official receipt numbering, open business dates and recognition controls. All fields require explicit choices. Future-dated posting is not supported. Receipt movements do not include opening balances or expenses.', 'fields' => [
                'prefix' => self::field('Official receipt prefix', 'prefix', 'Uppercase letters, digits and hyphens, up to 15 characters. Each prefix has a separate permanent sequence.'),
                'starting_number' => self::field('First number for a new prefix', 'counter', '1–999999999. Cannot change after this prefix has issued a receipt.'),
                'padding' => self::field('Receipt number minimum digits', 'width', '1–12. Example: prefix ABC and 6 digits produces ABC-000001.'),
                'open_from' => self::field('First open posting date', 'date', 'Posting and reversal dates earlier than this Karachi business date are closed.'),
                'max_backdate_days' => self::field('Maximum backdating (calendar days)', 'integer', 'Enter 0 explicitly to allow only today. Future business dates are not supported.'),
                'unallocated' => self::select('Receipts before installment allocation', ['allow' => 'Allow as unallocated customer credit', 'block' => 'Block until installment allocation is available']),
                'routing' => self::select('Payment destination rules', ['matching' => 'Cash → cash; transfers/cheques → bank; mobile wallet → wallet; other → other', 'any_active' => 'Staff verifies any active destination account']),
                'duplicates' => self::select('Duplicate non-cash references', ['account_method' => 'Reject the same exact reference/method within an account', 'global_method' => 'Reject the same exact reference/method across all accounts']),
            ]],
            'booking' => ['title' => 'Booking requirements', 'description' => 'Agents may finalize once both the required down payment and documents are satisfied. The checklist must include a signed booking form. Set the threshold and held-plot access rule here.', 'fields' => [
                'down_payment_type' => self::select('Minimum down payment basis', ['fixed' => 'Fixed PKR amount', 'percentage' => 'Percentage of agreed contract price']),
                'down_payment_value' => self::field('Minimum down payment value', 'decimal', 'PKR for a fixed amount, or 0–100 for a percentage. A positive value is required.'),
                'required_documents' => self::multi('Required booking documents', ['signed_booking_form' => 'Signed booking form', 'cnic_copy' => 'CNIC copy', 'customer_photo' => 'Customer photograph', 'payment_evidence' => 'Down payment evidence']),
                'held_plot_access' => self::select('Who may book a plot already on hold', ['holder_or_admin' => 'The holder or an authorized admin', 'any_agent' => 'Any authorized agent with access to the customer']),
            ]],
            'installments' => ['title' => 'Installment plans', 'description' => 'Configure the plan types staff may offer and explicit date/rounding rules. Custom plans use individually entered dates and amounts.', 'fields' => [
                'frequencies' => self::multi('Available plan frequencies', ['monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'half_yearly' => 'Half-yearly', 'yearly' => 'Yearly', 'custom' => 'Custom dates and amounts']),
                'first_due_date' => self::select('First due date', ['entered_per_booking' => 'Staff enters the first due date for each booking']),
                'month_end' => self::select('When a day does not exist in a due month', ['clamp' => 'Use the last day of that month', 'roll_forward' => 'Carry excess days into the next month']),
                'rounding' => self::select('PKR rounding to two decimal places', ['half_up' => 'Half up', 'half_even' => 'Half even', 'down' => 'Round down']),
                'residual' => self::select('Place any rounding remainder in', ['last' => 'Last installment', 'first' => 'First installment']),
            ]],
            'collections' => ['title' => 'Receiving payments', 'description' => 'Payment methods will appear as radio choices on receipt entry. Recognition and allocation rules must be selected before receipt posting is introduced.', 'fields' => [
                'methods' => self::multi('Accepted payment methods', ['cash' => 'Cash', 'bank_transfer' => 'Bank transfer', 'cheque' => 'Cheque', 'mobile_wallet' => 'Mobile wallet', 'other' => 'Other (with reference)']),
                'allocation' => self::select('Installment allocation', ['oldest_first' => 'Oldest unpaid installment first', 'manual' => 'Authorized staff selects installments', 'hybrid' => 'Suggest oldest first; allow authorized selection']),
                'cheque_recognition' => self::select('A cheque becomes collectible when', ['cleared' => 'Bank clearance is recorded', 'deposited' => 'Deposit is recorded']),
                'overpayment' => self::select('Money above the current installment balance', ['unallocated' => 'Retain as unallocated customer credit', 'reject' => 'Reject until the allocation is corrected']),
            ]],
            'charges' => ['title' => 'Late payments and charges', 'description' => 'Choose manual entry to let authorized admins post each approved charge with its own amount and reason. Automatic fees are not supported. Saving this setting does not levy a charge.', 'fields' => [
                'late_fee_mode' => self::select('Late payment fees', ['disabled' => 'No late payment fees', 'manual' => 'Authorized admin enters each approved charge']),
                'late_fee_value' => self::field('Late fee value', 'decimal', 'PKR for a fixed fee, or a percentage up to 100. Leave empty when fees are disabled.'),
                'grace_days' => self::field('Grace period (calendar days)', 'integer', 'Enter 0 explicitly if there is no grace period.'),
                'basis' => self::select('Percentage fee basis', ['overdue_installment' => 'Overdue installment balance', 'outstanding_principal' => 'Outstanding contract principal']),
                'frequency' => self::select('Fee application', ['once' => 'Once per overdue installment', 'daily' => 'Each overdue day after grace', 'monthly' => 'Each overdue month after grace']),
                'cap_mode' => self::select('Maximum late fee per installment', ['none' => 'No cap (explicit choice)', 'fixed' => 'Fixed PKR cap']),
                'cap_value' => self::field('Maximum late fee (PKR)', 'money', 'Required if a fixed cap is selected.'),
            ]],
            'owners' => ['title' => 'Landowner settlements', 'description' => 'Set the default owner percentage and calculation basis. Future agreements will capture their own snapshot and may have a specific percentage.', 'fields' => [
                'default_percentage' => self::field('Default owner share (%)', 'percentage', 'Enter the default share for new owner agreements. Enter 0 explicitly for no share.'),
                'basis' => self::select('Owner percentage applies to', ['net_contract' => 'Agreed contract price after approved discount', 'cleared_collections' => 'Cleared customer collections']),
                'trigger' => self::select('Owner entitlement is recognized on', ['booking_confirmed' => 'Final booking confirmation', 'collection_cleared' => 'Each cleared collection']),
                'cancellation' => self::select('Cancellation/refund handling', ['admin_review' => 'Admin reviews the entitlement adjustment', 'proportional_reversal' => 'Reverse proportionally to refunded eligible collections']),
            ]],
            'commissions' => ['title' => 'Agent commissions', 'description' => 'Configure the default calculation and earning trigger. No commission is accrued or paid by this settings screen.', 'fields' => [
                'type' => self::select('Commission calculation', ['percentage' => 'Percentage', 'fixed' => 'Fixed PKR amount per booking']),
                'value' => self::field('Commission value', 'decimal', 'PKR for fixed, or percentage up to 100. Zero percent explicitly disables accrual.'),
                'basis' => self::select('Percentage applies to', ['net_contract' => 'Agreed contract price after approved discount', 'cleared_collections' => 'Cleared customer collections']),
                'trigger' => self::select('Commission is earned on', ['booking_confirmed' => 'Final booking confirmation', 'down_payment_cleared' => 'Required down payment has cleared', 'collection_cleared' => 'Each cleared collection']),
                'cancellation' => self::select('Cancellation/refund handling', ['admin_review' => 'Admin reviews unpaid/paid commission adjustments', 'reverse_and_recover' => 'Reverse unpaid commission and record recovery of paid commission']),
            ]],
        ];
    }

    /** @return array<string,mixed> */
    private static function field(string $label, string $type, string $help = ''): array
    {
        return compact('label', 'type', 'help');
    }

    /** @param array<string,string> $options
     * @return array<string,mixed>
     */
    private static function select(string $label, array $options): array
    {
        return ['label' => $label, 'type' => 'select', 'options' => $options, 'help' => ''];
    }

    /** @param array<string,string> $options
     * @return array<string,mixed>
     */
    private static function multi(string $label, array $options): array
    {
        return ['label' => $label, 'type' => 'multi', 'options' => $options, 'help' => ''];
    }
}
