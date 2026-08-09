<?php

namespace App\Http\Requests;

use App\Models\Ingredient;
use App\Models\MenuItem;
use App\Models\StockMovement;
use App\Support\TenantIngredientAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateInventoryAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        /** @var StockMovement $movement */
        $movement = $this->route('stockMovement');

        // Edit only allows revising the signed quantity change + notes.
        $this->merge([
            'branch_id' => $movement->branch_id,
            'adjustable' => $movement->ingredient_id ? 'ingredient' : 'menu_item',
            'ingredient_id' => $movement->ingredient_id,
            'menu_item_id' => $movement->menu_item_id,
            'unit' => 'consumption',
            'mode' => 'change',
        ]);
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
            /** @var StockMovement $movement */
            $movement = $this->route('stockMovement');
            if ($movement->type !== 'adjustment') {
                $v->errors()->add('quantity', 'Only adjustment records can be edited.');

                return;
            }

            $mode = $this->input('mode');
            $qty = $this->input('quantity');
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

            if ($movement->ingredient_id) {
                $ingredient = Ingredient::find($movement->ingredient_id);
                if ($ingredient && $user->company_id && ! TenantIngredientAccess::isUsableByCompany($ingredient, (int) $user->company_id)) {
                    $v->errors()->add('ingredient_id', 'Invalid ingredient for your company.');
                }
                if ($this->input('unit') === 'purchase' && $ingredient && ! $ingredient->hasDualUnits()) {
                    $v->errors()->add('unit', 'This ingredient does not have a separate purchase unit.');
                }
            }

            if ($movement->menu_item_id) {
                $menuItem = MenuItem::find($movement->menu_item_id);
                if ($menuItem && $user->company_id && (int) $menuItem->company_id !== (int) $user->company_id) {
                    $v->errors()->add('menu_item_id', 'Invalid menu item for your company.');
                }
                if ($this->input('unit') === 'purchase') {
                    $v->errors()->add('unit', 'Menu items use pieces only.');
                }
            }
        });
    }
}
