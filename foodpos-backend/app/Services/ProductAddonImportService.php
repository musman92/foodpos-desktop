<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\MenuItem;
use App\Models\ProductAddon;
use App\Models\ProductAddonRecipe;
use App\Support\IngredientQuantity;
use App\Support\ProductAddonImportSampleExport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ProductAddonImportService
{
    private const MAX_ROWS = 1000;

    /** @var array<string, list<string>> */
    private const HEADER_ALIASES = [
        'addon_code' => ['addon_code', 'addon code', 'code', 'pa code'],
        'addon_name' => ['addon_name', 'addon name', 'name'],
        'price' => ['price', 'sale_price', 'sale price'],
        'inventory_type' => ['inventory_type', 'inventory type', 'type'],
        'track_inventory' => ['track_inventory', 'track inventory', 'track stock'],
        'menu_item_code' => ['menu_item_code', 'menu item code', 'menu_item_sku', 'sku'],
        'ingredient_code' => ['ingredient_code', 'ingredient code', 'ingredient'],
        'ingredient_quantity' => ['ingredient_quantity', 'ingredient quantity', 'quantity', 'qty'],
        'unit' => ['unit', 'unit_id', 'uom'],
        'waste_percentage' => ['waste_percentage', 'waste percentage', 'waste', 'waste %'],
    ];

    /**
     * @return array{created: int, updated: int, skipped: int, errors: list<array{row: int, message: string}>}
     */
    public function import(UploadedFile $file, int $companyId): array
    {
        $parsed = $this->parseFile($file);

        $result = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => $parsed['errors'],
        ];

        if ($parsed['rows'] === []) {
            if ($result['errors'] === []) {
                $result['errors'][] = ['row' => 1, 'message' => 'The file has no data rows to import.'];
            }

            return $result;
        }

        $grouped = $this->groupRows($parsed['rows']);
        $result['errors'] = array_merge($result['errors'], $grouped['errors']);

        if ($grouped['groups'] === []) {
            return $result;
        }

        DB::transaction(function () use ($grouped, $companyId, &$result) {
            foreach ($grouped['groups'] as $group) {
                $outcome = $this->importGroup($group, $companyId);

                if ($outcome['status'] === 'created') {
                    $result['created']++;
                } elseif ($outcome['status'] === 'updated') {
                    $result['updated']++;
                } else {
                    $result['skipped']++;
                    $result['errors'][] = [
                        'row' => $group['first_row'],
                        'message' => $outcome['message'] ?? 'Could not import addon group.',
                    ];
                }
            }
        });

        return $result;
    }

    /**
     * @return array{rows: list<array<string, mixed>>, errors: list<array{row: int, message: string}>}
     */
    public function parseFile(UploadedFile $file): array
    {
        try {
            $reader = IOFactory::createReaderForFile($file->getRealPath());
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($file->getRealPath());
        } catch (\Throwable) {
            return [
                'rows' => [],
                'errors' => [['row' => 1, 'message' => 'Could not read the uploaded file. Please use a valid CSV or Excel file.']],
            ];
        }

        $sheetRows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        if ($sheetRows === []) {
            return [
                'rows' => [],
                'errors' => [['row' => 1, 'message' => 'The file is empty.']],
            ];
        }

        $headerRow = array_shift($sheetRows);
        $columnMap = $this->mapHeaders($headerRow);

        if (! isset($columnMap['addon_name'])) {
            return [
                'rows' => [],
                'errors' => [['row' => 1, 'message' => 'Missing required "addon_name" column. Download the sample file for the expected format.']],
            ];
        }

        $rows = [];
        $errors = [];

        foreach ($sheetRows as $index => $rawRow) {
            $rowNumber = $index + 2;
            $row = $this->normalizeRow($rawRow, $columnMap, $rowNumber);

            if ($this->isBlankRow($row)) {
                continue;
            }

            if ($rowNumber - 1 > self::MAX_ROWS) {
                $errors[] = ['row' => $rowNumber, 'message' => 'Maximum '.self::MAX_ROWS.' rows allowed.'];
                break;
            }

            $rows[] = $row;
        }

        return ['rows' => $rows, 'errors' => $errors];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{groups: list<array<string, mixed>>, errors: list<array{row: int, message: string}>}
     */
    private function groupRows(array $rows): array
    {
        $groups = [];
        $errors = [];

        foreach ($rows as $row) {
            $key = trim((string) ($row['addon_code'] ?? ''));
            if ($key === '') {
                $key = 'name:'.mb_strtolower(trim((string) ($row['addon_name'] ?? '')));
            }

            if ($key === 'name:') {
                $errors[] = ['row' => (int) $row['_row'], 'message' => 'addon_name is required.'];
                continue;
            }

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'first_row' => (int) $row['_row'],
                    'rows' => [],
                ];
            }

            $groups[$key]['rows'][] = $row;
        }

        return [
            'groups' => array_values($groups),
            'errors' => $errors,
        ];
    }

    /**
     * @param  array<string, mixed>  $group
     * @return array{status: string, message?: string}
     */
    private function importGroup(array $group, int $companyId): array
    {
        $rows = $group['rows'] ?? [];
        if ($rows === []) {
            return ['status' => 'skipped', 'message' => 'Empty addon group.'];
        }

        $master = $rows[0];
        $name = trim((string) ($master['addon_name'] ?? ''));
        if ($name === '') {
            return ['status' => 'skipped', 'message' => 'addon_name is required.'];
        }

        $type = $this->parseInventoryType((string) ($master['inventory_type'] ?? 'none'));
        $trackInventory = $this->parseBool($master['track_inventory'] ?? false);
        $price = $this->parseDecimal($master['price'] ?? 0);
        if ($price === null || $price < 0) {
            return ['status' => 'skipped', 'message' => 'Invalid price for addon "'.$name.'".'];
        }

        $code = trim((string) ($master['addon_code'] ?? ''));
        $addon = null;
        if ($code !== '') {
            $addon = ProductAddon::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('code', $code)
                ->first();
        }
        if (! $addon) {
            $addon = ProductAddon::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('name', $name)
                ->first();
        }

        $isNew = ! $addon;
        if (! $addon) {
            $addon = new ProductAddon([
                'company_id' => $companyId,
            ]);
        }

        $menuItemId = null;
        if ($type === ProductAddon::TYPE_SINGLE) {
            $menuItemCode = trim((string) ($master['menu_item_code'] ?? ''));
            if ($menuItemCode === '') {
                return ['status' => 'skipped', 'message' => "menu_item_code is required for single inventory addon \"{$name}\"."];
            }
            $menuItem = MenuItem::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where(function ($query) use ($menuItemCode) {
                    $query->where('sku', $menuItemCode)->orWhere('id', $menuItemCode);
                })
                ->first();
            if (! $menuItem) {
                return ['status' => 'skipped', 'message' => "Menu item \"{$menuItemCode}\" not found for addon \"{$name}\"."];
            }
            $menuItemId = $menuItem->id;
        }

        $addon->fill([
            'code' => $code !== '' ? $code : ($addon->code ?: ProductAddon::generateNextCode($companyId)),
            'name' => $name,
            'price' => $price,
            'type' => $type,
            'track_inventory' => $trackInventory && $type !== ProductAddon::TYPE_NONE,
            'menu_item_id' => $menuItemId,
            'tax_id' => null,
        ]);
        $addon->save();

        if ($type === ProductAddon::TYPE_RECIPE) {
            $addon->recipes()->delete();
            $added = 0;
            foreach ($rows as $recipeRow) {
                if (! $this->hasRecipeData($recipeRow)) {
                    continue;
                }
                $ingredientCode = trim((string) ($recipeRow['ingredient_code'] ?? ''));
                $ingredient = Ingredient::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->where(function ($query) use ($ingredientCode) {
                        $query->where('sku', $ingredientCode)->orWhere('name', $ingredientCode);
                    })
                    ->first();
                if (! $ingredient) {
                    return ['status' => 'skipped', 'message' => "Ingredient \"{$ingredientCode}\" not found for addon \"{$name}\"."];
                }

                $qty = $this->parseDecimal($recipeRow['ingredient_quantity'] ?? null);
                if ($qty === null || $qty <= 0) {
                    return ['status' => 'skipped', 'message' => "Invalid ingredient quantity for addon \"{$name}\"."];
                }

                $unitInput = trim((string) ($recipeRow['unit'] ?? ''));
                $unitId = IngredientQuantity::resolveRecipeUnitId($ingredient, $unitInput !== '' ? $unitInput : null);
                if ($unitId === null) {
                    return ['status' => 'skipped', 'message' => IngredientQuantity::conversionErrorMessage($ingredient, $unitInput).' (addon "'.$name.'").'];
                }

                ProductAddonRecipe::create([
                    'product_addon_id' => $addon->id,
                    'ingredient_id' => $ingredient->id,
                    'quantity' => $qty,
                    'unit_id' => $unitId,
                    'waste_percentage' => $this->parseDecimal($recipeRow['waste_percentage'] ?? 0) ?? 0,
                ]);
                $added++;
            }

            if ($trackInventory && $added === 0) {
                return ['status' => 'skipped', 'message' => "Recipe addon \"{$name}\" needs at least one ingredient row."];
            }
        } else {
            $addon->recipes()->delete();
        }

        $addon->unsetRelation('recipes');
        $addon->load(['recipes.ingredient', 'menuItem']);
        $addon->cost = $addon->calculateCost();
        $addon->save();

        return ['status' => $isNew ? 'created' : 'updated'];
    }

    /**
     * @param  array<int, mixed>|null  $headerRow
     * @return array<string, int>
     */
    private function mapHeaders(?array $headerRow): array
    {
        $map = [];
        if (! is_array($headerRow)) {
            return $map;
        }

        foreach ($headerRow as $index => $cell) {
            $normalized = $this->normalizeHeader((string) $cell);
            if ($normalized === '') {
                continue;
            }
            foreach (self::HEADER_ALIASES as $field => $aliases) {
                if (in_array($normalized, $aliases, true)) {
                    $map[$field] = (int) $index;
                }
            }
        }

        return $map;
    }

    /**
     * @param  array<int, mixed>  $rawRow
     * @param  array<string, int>  $columnMap
     * @return array<string, mixed>
     */
    private function normalizeRow(array $rawRow, array $columnMap, int $rowNumber): array
    {
        $row = ['_row' => $rowNumber];
        foreach (array_keys(self::HEADER_ALIASES) as $field) {
            $row[$field] = isset($columnMap[$field]) ? trim((string) ($rawRow[$columnMap[$field]] ?? '')) : '';
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function isBlankRow(array $row): bool
    {
        return trim((string) ($row['addon_name'] ?? '')) === ''
            && trim((string) ($row['addon_code'] ?? '')) === '';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function hasRecipeData(array $row): bool
    {
        return trim((string) ($row['ingredient_code'] ?? '')) !== '';
    }

    private function normalizeHeader(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? '';

        return trim($value, '_');
    }

    private function parseInventoryType(string $value): string
    {
        $value = strtolower(trim($value));
        if (in_array($value, ['recipe', 'recipes'], true)) {
            return ProductAddon::TYPE_RECIPE;
        }
        if (in_array($value, ['single', 'item', 'menu_item'], true)) {
            return ProductAddon::TYPE_SINGLE;
        }

        return ProductAddon::TYPE_NONE;
    }

    private function parseBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $value = strtolower(trim((string) $value));

        return in_array($value, ['1', 'true', 'yes', 'y', 'on'], true);
    }

    private function parseDecimal(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return round((float) $value, 4);
    }
}
