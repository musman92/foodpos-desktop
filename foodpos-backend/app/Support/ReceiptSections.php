<?php

namespace App\Support;

class ReceiptSections
{
    /**
     * @return array<string, bool>
     */
    public static function defaults(): array
    {
        return [
            'logo' => true,
            'branch_name' => true,
            'address' => true,
            'phone' => true,
            'invoice_title' => true,
            'order_number' => true,
            'date_cashier' => true,
            'order_type' => true,
            'table' => true,
            'customer_block' => true,
            'items_header' => true,
            'item_variants' => true,
            'item_addons' => true,
            'item_notes' => true,
            'deal_items' => true,
            'subtotal' => true,
            'discount' => true,
            'service_charge' => true,
            'delivery_fee' => true,
            'tax' => true,
            'payment_info' => true,
            'order_notes' => true,
            'thank_you' => true,
        ];
    }

    /**
     * @return array<string, array{label: string, description?: string, keys: list<string>}>
     */
    public static function groups(): array
    {
        return [
            'header' => [
                'label' => 'Header',
                'keys' => ['logo', 'branch_name', 'address', 'phone'],
            ],
            'invoice_info' => [
                'label' => 'Invoice info',
                'keys' => ['invoice_title', 'order_number', 'date_cashier'],
            ],
            'order_details' => [
                'label' => 'Order details',
                'keys' => ['order_type', 'table'],
            ],
            'customer' => [
                'label' => 'Customer',
                'keys' => ['customer_block'],
            ],
            'items' => [
                'label' => 'Line items',
                'keys' => ['items_header', 'item_variants', 'item_addons', 'item_notes', 'deal_items'],
            ],
            'totals' => [
                'label' => 'Totals',
                'description' => 'Grand total is always shown. Subtotal is hidden automatically when it matches the total.',
                'keys' => ['subtotal', 'discount', 'service_charge', 'delivery_fee', 'tax'],
            ],
            'payment' => [
                'label' => 'Payment',
                'keys' => ['payment_info'],
            ],
            'footer' => [
                'label' => 'Footer',
                'keys' => ['order_notes', 'thank_you'],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            'logo' => 'Logo',
            'branch_name' => 'Branch name',
            'address' => 'Address',
            'phone' => 'Phone number',
            'invoice_title' => '"INVOICE" title',
            'order_number' => 'Order number',
            'date_cashier' => 'Date & cashier (one line)',
            'order_type' => 'Order type',
            'table' => 'Table',
            'customer_block' => 'Customer details',
            'items_header' => 'Column headers (Item / Qty / Price)',
            'item_variants' => 'Variants (e.g. size)',
            'item_addons' => 'Addons',
            'item_notes' => 'Special instructions',
            'deal_items' => 'Deal contents (menu items inside a deal)',
            'subtotal' => 'Subtotal',
            'discount' => 'Discount',
            'service_charge' => 'Service charge',
            'delivery_fee' => 'Delivery fee',
            'tax' => 'Tax',
            'payment_info' => 'Payment method & status',
            'order_notes' => 'Order notes',
            'thank_you' => 'Thank you message',
        ];
    }

    /**
     * @param  array<string, mixed>|null  $input
     * @return array<string, bool>
     */
    public static function normalize(?array $input): array
    {
        $defaults = self::defaults();
        $normalized = [];

        foreach ($defaults as $key => $default) {
            if ($input === null || ! array_key_exists($key, $input)) {
                $normalized[$key] = $default;

                continue;
            }

            $normalized[$key] = filter_var($input[$key], FILTER_VALIDATE_BOOLEAN);
        }

        return $normalized;
    }

    /**
     * @param  array<string, bool>  $sections
     */
    public static function enabled(array $sections, string $key): bool
    {
        return (bool) ($sections[$key] ?? self::defaults()[$key] ?? false);
    }

    /**
     * Whether subtotal row should render (skips redundant subtotal when equal to total).
     *
     * @param  array<string, bool>  $sections
     * @param  array<string, mixed>|object  $order
     */
    public static function shouldShowSubtotal(array $sections, array|object $order): bool
    {
        if (! self::enabled($sections, 'subtotal')) {
            return false;
        }

        $subtotal = (float) (is_array($order) ? ($order['subtotal'] ?? 0) : ($order->subtotal ?? 0));
        $total = (float) (is_array($order) ? ($order['total_amount'] ?? 0) : ($order->total_amount ?? 0));
        $discount = (float) (is_array($order) ? ($order['discount_amount'] ?? 0) : ($order->discount_amount ?? 0));
        $service = (float) (is_array($order) ? ($order['service_charge'] ?? 0) : ($order->service_charge ?? 0));
        $delivery = (float) (is_array($order) ? ($order['delivery_fee'] ?? 0) : ($order->delivery_fee ?? 0));
        $tax = (float) (is_array($order) ? ($order['tax_amount'] ?? 0) : ($order->tax_amount ?? 0));

        if (abs($subtotal - $total) < 0.01
            && $discount <= 0.01
            && $service <= 0.01
            && $delivery <= 0.01
            && $tax <= 0.01) {
            return false;
        }

        return true;
    }

    /**
     * Sample order payload for receipt preview in company settings.
     *
     * @return array<string, mixed>
     */
    public static function sampleOrder(): array
    {
        return [
            'order_number' => 'TAS001-20260712-0009',
            'created_at' => '2026-07-12T11:42:00+00:00',
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_method' => 'cash',
            'type' => 'takeaway',
            'subtotal' => 1100,
            'discount_amount' => 0,
            'service_charge' => 0,
            'delivery_fee' => 0,
            'tax_amount' => 0,
            'total_amount' => 1100,
            'notes' => null,
            'customer_name' => null,
            'customer_phone' => null,
            'customer_email' => null,
            'customer_address' => null,
            'company' => [
                'name' => 'FAST FOOD RESTAURANT',
                'logo_url' => null,
            ],
            'branch' => [
                'name' => 'Taste Inn 2nd Branch',
                'address' => 'Main Bazar kotli loharan East',
                'phone' => '0329-1602989',
            ],
            'cashier' => ['name' => 'Hamid'],
            'table' => null,
            'items' => [
                [
                    'item_name' => 'Chicken Fajita Pizza',
                    'quantity' => 1,
                    'total_price' => 600,
                    'variants' => ['variant_name' => 'Size', 'option_name' => 'Small 6"'],
                    'addons' => [],
                    'special_instructions' => null,
                ],
                [
                    'item_name' => 'Lunch Combo',
                    'quantity' => 1,
                    'total_price' => 500,
                    'deal_id' => 1,
                    'deal' => [
                        'title' => 'Lunch Combo',
                        'menu_items' => [
                            ['id' => 1, 'name' => 'Burger', 'pivot' => ['quantity' => 1, 'option_name' => null]],
                            ['id' => 2, 'name' => 'Fries', 'pivot' => ['quantity' => 1, 'option_name' => null]],
                            ['id' => 3, 'name' => 'Cola', 'pivot' => ['quantity' => 1, 'option_name' => null]],
                        ],
                    ],
                    'variants' => [],
                    'addons' => [],
                    'special_instructions' => null,
                ],
            ],
            'payments' => [],
        ];
    }
}
