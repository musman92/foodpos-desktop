<?php

namespace App\Http\Requests;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreOrderRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'notes' => ['required', 'string', 'min:3', 'max:5000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.order_item_id' => ['required', 'integer', 'exists:order_items,id'],
            'lines.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'lines.*.restock_inventory' => ['sometimes', 'boolean'],
            'lines.*.line_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $order = $this->route('order');
            if (! $order instanceof Order) {
                return;
            }
            $lines = $this->input('lines', []);
            $hasPositive = false;
            foreach ($lines as $i => $row) {
                $itemId = $row['order_item_id'] ?? null;
                if (! $itemId) {
                    continue;
                }
                if (! $order->items()->whereKey($itemId)->exists()) {
                    $validator->errors()->add("lines.{$i}.order_item_id", 'This line does not belong to the order.');

                    continue;
                }
                $q = isset($row['quantity']) ? (float) $row['quantity'] : 0;
                if ($q > 0) {
                    $hasPositive = true;
                    $item = $order->items()->whereKey($itemId)->first();
                    $billable = (float) $item->quantity - (float) $item->quantity_refunded;
                    if ($q > $billable + 0.0001) {
                        $validator->errors()->add("lines.{$i}.quantity", 'Refund quantity cannot exceed remaining quantity for this line.');
                    }
                }
            }
            if (! $hasPositive) {
                $validator->errors()->add('lines', 'Enter a refund quantity greater than zero for at least one line.');
            }
        });
    }
}
