<?php

namespace App\Http\Requests;

use App\Models\Ingredient;
use App\Models\MenuItem;
use App\Support\TenantIngredientAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreInventoryAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $adjustable = $this->input('adjustable');
        $ing = $this->input('ingredient_id');
        $mi = $this->input('menu_item_id');
        $ing = ($ing === '' || $ing === null) ? null : $ing;
        $mi = ($mi === '' || $mi === null) ? null : $mi;

        if ($adjustable === 'ingredient') {
            $this->merge(['ingredient_id' => $ing, 'menu_item_id' => null]);
        } elseif ($adjustable === 'menu_item') {
            $this->merge([
                'menu_item_id' => $mi,
                'ingredient_id' => null,
                'unit' => 'consumption',
            ]);
        }

        if (! $this->filled('mode')) {
            $this->merge(['mode' => 'change']);
        }

        if (! $this->filled('unit')) {
            $this->merge(['unit' => 'consumption']);
        }
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'adjustable' => ['required', 'string', 'in:ingredient,menu_item'],
            'ingredient_id' => ['nullable', 'integer', 'exists:ingredients,id'],
            'menu_item_id' => ['nullable', 'integer', 'exists:menu_items,id'],
            'mode' => ['required', 'string', 'in:change,exact'],
            'unit' => ['required', 'string', 'in:consumption,purchase'],
            'quantity' => ['required', 'numeric'],
            'notes' => ['required', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $adjustable = $this->input('adjustable');
            $mode = $this->input('mode');
            $qty = $this->input('quantity');

            if ($adjustable === 'ingredient') {
                if (! $this->filled('ingredient_id')) {
                    $v->errors()->add('ingredient_id', 'Select an ingredient.');
                }
                if ($this->filled('menu_item_id')) {
                    $v->errors()->add('menu_item_id', 'Do not select a menu item when adjusting an ingredient.');
                }
            } elseif ($adjustable === 'menu_item') {
                if (! $this->filled('menu_item_id')) {
                    $v->errors()->add('menu_item_id', 'Select a menu item.');
                }
                if ($this->filled('ingredient_id')) {
                    $v->errors()->add('ingredient_id', 'Do not select an ingredient when adjusting a menu item.');
                }
                if ($this->input('unit') === 'purchase') {
                    $v->errors()->add('unit', 'Menu items use pieces only.');
                }
            }

            if (is_numeric($qty)) {
                $qty = (float) $qty;
                if ($mode === 'exact' && $qty < -0.0001) {
                    $v->errors()->add('quantity', 'Exact quantity cannot be negative.');
                }
                if ($mode === 'change' && abs($qty) < 0.01) {
                    $v->errors()->add('quantity', 'Quantity change must be at least 0.01 (use a negative value to decrease).');
                }
            }

            $user = $this->user();
            if (! $user) {
                return;
            }

            if ($adjustable === 'ingredient' && $this->filled('ingredient_id')) {
                $ingredient = Ingredient::find($this->input('ingredient_id'));
                if (! $ingredient) {
                    return;
                }
                if ($user->company_id && ! TenantIngredientAccess::isUsableByCompany($ingredient, (int) $user->company_id)) {
                    $v->errors()->add('ingredient_id', 'Invalid ingredient for your company.');
                }
                if ($ingredient->track_stock === 'no') {
                    $v->errors()->add('ingredient_id', 'This ingredient does not track stock.');
                }
                if ($this->input('unit') === 'purchase' && ! $ingredient->hasDualUnits()) {
                    $v->errors()->add('unit', 'This ingredient does not have a separate purchase unit.');
                }
            }

            if ($adjustable === 'menu_item' && $this->filled('menu_item_id')) {
                $menuItem = MenuItem::find($this->input('menu_item_id'));
                if (! $menuItem) {
                    return;
                }
                if ($user->company_id && (int) $menuItem->company_id !== (int) $user->company_id) {
                    $v->errors()->add('menu_item_id', 'Invalid menu item for your company.');
                }
                if ($menuItem->type !== 'single') {
                    $v->errors()->add('menu_item_id', 'Only single-type menu items can be adjusted here. Use ingredient adjustments for recipes.');
                }
                if (! $menuItem->track_inventory) {
                    $v->errors()->add('menu_item_id', 'This menu item does not track inventory.');
                }
            }
        });
    }
}
