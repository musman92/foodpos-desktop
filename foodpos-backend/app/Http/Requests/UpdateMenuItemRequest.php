<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMenuItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = auth()->user()->company_id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category_id' => [
                'required',
                Rule::exists('categories', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'cuisine_id' => ['nullable', 'exists:cuisines,id'],
            'type' => ['required', 'in:single,recipe'],
            'price' => ['required', 'numeric', 'min:0'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'min_stock_level' => ['nullable', 'numeric', 'min:0'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
            'platform_media_path' => [
                'nullable',
                'string',
                'max:500',
                Rule::exists('platform_media', 'file_path')->where(fn ($q) => $q->where('is_active', true)->whereNull('deleted_at')),
            ],
            'clear_image' => ['nullable', 'boolean'],
            'sku' => ['nullable', 'string', 'max:255'],
            'preparation_time' => ['nullable', 'integer', 'min:1', 'max:600'],
            'is_available' => ['nullable', 'boolean'],
            'track_inventory' => ['nullable', 'boolean'],
            'purchase_unit_id' => [
                Rule::requiredIf(fn () => $this->input('type') === 'single'),
                'nullable',
                Rule::exists('ingredient_units', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'consumption_unit_id' => [
                Rule::requiredIf(fn () => $this->input('type') === 'single'),
                'nullable',
                Rule::exists('ingredient_units', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'conversion_rate' => ['nullable', 'numeric', 'gt:0'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'product_addons' => ['nullable', 'array'],
            'product_addons.*' => ['exists:product_addons,id'],
            'default_recipe_id' => [
                'nullable',
                Rule::exists('recipes', 'id')->where(fn ($q) => $q->where('company_id', $companyId)->whereNull('deleted_at')),
            ],
            'variant_recipes' => ['nullable', 'array'],
            'variant_recipes.*.variant_id' => ['nullable', 'exists:variants,id'],
            'variant_recipes.*.option_name' => ['nullable', 'string', 'max:255'],
            'variant_recipes.*.recipe_id' => [
                'nullable',
                Rule::exists('recipes', 'id')->where(fn ($q) => $q->where('company_id', $companyId)->whereNull('deleted_at')),
            ],
            'variants' => ['nullable', 'array'],
            'variants.*.variant_id' => ['nullable', 'exists:variants,id'],
            'variants.*.option_prices' => ['nullable', 'array'],
            'variants.*.option_prices.*.name' => ['nullable', 'string', 'max:255'],
            'variants.*.option_prices.*.price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.is_default' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->input('type') !== 'recipe') {
                return;
            }

            $variants = collect($this->input('variants', []))
                ->filter(fn ($v) => ! empty($v['variant_id']));
            $hasVariants = $variants->isNotEmpty();

            if (! $hasVariants) {
                if (! $this->filled('default_recipe_id')) {
                    $validator->errors()->add('default_recipe_id', 'Select a recipe for this menu item.');
                }

                return;
            }

            $links = collect($this->input('variant_recipes', []));
            foreach ($variants as $variantData) {
                $variantId = (int) ($variantData['variant_id'] ?? 0);
                $optionPrices = $variantData['option_prices'] ?? [];
                if (! is_array($optionPrices)) {
                    continue;
                }
                foreach ($optionPrices as $op) {
                    $optionName = trim((string) ($op['name'] ?? ''));
                    if ($optionName === '') {
                        continue;
                    }
                    $hasLink = $links->contains(function ($row) use ($variantId, $optionName) {
                        return (int) ($row['variant_id'] ?? 0) === $variantId
                            && trim((string) ($row['option_name'] ?? '')) === $optionName
                            && ! empty($row['recipe_id']);
                    });
                    if (! $hasLink) {
                        $validator->errors()->add(
                            'variant_recipes',
                            "Select a recipe for {$optionName}."
                        );

                        return;
                    }
                }
            }
        });
    }
}
