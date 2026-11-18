<?php

namespace App\Domain\Import;

class ImportTemplates
{
    public const VERSION = 2;

    /** @return array<string,array{label:string,headers:list<string>,example:list<string>}> */
    public static function all(): array
    {
        return [
            'customers' => [
                'label' => 'Customers',
                'headers' => ['name', 'phone', 'email', 'cnic', 'address', 'assigned_to_email'],
                'example' => ['DEMO Customer', '03001234567', 'demo@example.com', '3520212345671', 'DEMO Address', 'agent@example.com'],
            ],
            'plot_owners' => [
                'label' => 'Plot owners',
                'headers' => ['name', 'admin_email', 'default_percentage'],
                'example' => ['DEMO Owner', 'admin@example.com', '20.0000'],
            ],
            'inventory' => [
                'label' => 'Plot inventory',
                'headers' => ['block_code', 'size_label', 'number', 'category', 'extra_area_sq_ft', 'list_price', 'owner_name', 'owner_percentage'],
                'example' => ['A', '5 Marla', 'A-1', 'commercial', '125.50', '5000000.00', 'DEMO Owner', '20.0000'],
            ],
            'bookings' => [
                'label' => 'Confirmed bookings',
                'headers' => ['customer_phone', 'block_code', 'plot_number', 'confirmed_on', 'net', 'down_payment', 'frequency', 'count', 'first_due_date'],
                'example' => ['03001234567', 'A', 'A-1', '2026-01-15', '5000000.00', '500000.00', 'monthly', '36', '2026-02-15'],
            ],
        ];
    }

    /** @return array{label:string,headers:list<string>,example:list<string>}|null */
    public static function get(string $template): ?array
    {
        return self::all()[$template] ?? null;
    }
}
