<?php

namespace App\Http\Requests;

use App\Models\Ingredient;
use App\Models\MenuItem;
use App\Models\MoneySource;
use App\Support\TenantIngredientAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePurchaseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'exists:branches,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'purchase_date' => ['required', 'date'],
            'payment_selection' => ['required', 'string'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'subtotal' => ['required', 'numeric', 'min:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_type' => ['required', 'in:ingredient,menu_item'],
            'items.*.item_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_id' => ['nullable', 'string'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.expiry_date' => ['nullable', 'date'],
            'items.*.notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $user = $this->user();
            $items = $this->input('items', []);

            if (! is_array($items)) {
                return;
            }

            foreach ($items as $index => $row) {
                $type = $row['item_type'] ?? null;
                $id = isset($row['item_id']) ? (int) $row['item_id'] : 0;

                if (! $type || ! $id) {
                    continue;
                }

                if ($type === 'ingredient') {
                    $ingredient = Ingredient::find($id);
                    if (! $ingredient || ($user->company_id && ! TenantIngredientAccess::isUsableByCompany($ingredient, (int) $user->company_id))) {
                        $v->errors()->add("items.{$index}.item_id", 'Invalid ingredient selected.');
                    } elseif (! $ingredient->is_active) {
                        $v->errors()->add("items.{$index}.item_id", 'This ingredient is not active.');
                    }

                    continue;
                }

                if ($type === 'menu_item') {
                    $menuItem = MenuItem::find($id);
                    if (! $menuItem || ($user->company_id && (int) $menuItem->company_id !== (int) $user->company_id)) {
                        $v->errors()->add("items.{$index}.item_id", 'Invalid menu item selected.');
                    } elseif ($menuItem->type === 'recipe') {
                        $v->errors()->add("items.{$index}.item_id", 'Recipe menu items cannot be purchased. Buy ingredients instead.');
                    } elseif ($menuItem->type !== 'single' || ! $menuItem->track_inventory) {
                        $v->errors()->add("items.{$index}.item_id", 'Only stocked single menu items can be purchased.');
                    } elseif (! $menuItem->is_available) {
                        $v->errors()->add("items.{$index}.item_id", 'This menu item is not available.');
                    }
                }
            }

            $total = (float) $this->input('total_amount', 0);
            $paid = (float) $this->input('paid_amount', 0);
            if ($paid > $total) {
                $v->errors()->add('paid_amount', 'Paid amount cannot exceed the purchase total.');
            }

            $selection = (string) $this->input('payment_selection', 'credit');
            if ($selection !== 'credit' && $selection !== '') {
                $moneySource = MoneySource::forPayments()
                    ->where('company_id', $this->user()?->company_id)
                    ->where('active', true)
                    ->find((int) $selection);
                if (! $moneySource) {
                    $v->errors()->add('payment_selection', 'Select a valid payment source.');
                }
            }

            $isCredit = $selection === 'credit' || $selection === '';
            $hasBalanceDue = $isCredit || $paid < $total;

            if ($hasBalanceDue && ! $this->input('supplier_id')) {
                $v->errors()->add('supplier_id', 'Supplier is required when buying on credit or leaving a balance due.');
            }
        });
    }
}
