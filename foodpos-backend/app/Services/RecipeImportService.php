<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Recipe;
use App\Support\IngredientImportReferences;
use App\Support\IngredientQuantity;
use App\Support\RecipeImportSampleExport;
use App\Support\TenantIngredientAccess;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class RecipeImportService
{
    private const MAX_ROWS = 2000;

    /** @var array<string, list<string>> */
    private const HEADER_ALIASES = [
        'recipe_code' => ['recipe_code', 'recipe code', 'r code', 'code'],
        'recipe_name' => ['recipe_name', 'recipe name', 'name'],
        'description' => ['description', 'desc', 'recipe_description', 'recipe description'],
        'is_active' => ['is_active', 'active', 'status', 'enabled'],
        'ingredient_code' => ['ingredient_code', 'ingredient code', 'ingredient sku', 'sku', 'i code'],
        'ingredient_name' => ['ingredient_name', 'ingredient name', 'ingredient'],
        'quantity' => ['quantity', 'qty', 'amount'],
        'unit' => ['unit', 'unit_id', 'unit id', 'uom'],
        'waste_percentage' => ['waste_percentage', 'waste percentage', 'waste %', 'waste'],
        'notes' => ['notes', 'note', 'remark', 'remarks'],
    ];

    /** @var list<string> */
    private const CARRY_FORWARD_FIELDS = [
        'recipe_code',
        'recipe_name',
        'description',
        'is_active',
    ];

    /**
     * @return array{created: int, updated: int, skipped: int, errors: list<array{row: int|string, message: string}>}
     */
    public function import(UploadedFile $file, int $companyId): array
    {
        if ($companyId <= 0) {
            return [
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => [['row' => 1, 'message' => 'Recipe import requires a company context.']],
            ];
        }

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
                        'message' => $outcome['message'] ?? 'Could not import recipe group.',
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
        $columnMap = $this->mapHeaders($headerRow ?? []);

        if (! isset($columnMap['recipe_name']) && ! isset($columnMap['recipe_code'])) {
            return [
                'rows' => [],
                'errors' => [['row' => 1, 'message' => 'Missing required "recipe_name" or "recipe_code" column. Download the sample file for the expected format.']],
            ];
        }

        $rows = [];
        $errors = [];
        $carried = [];

        foreach ($sheetRows as $index => $rawRow) {
            $rowNumber = $index + 2;
            $row = $this->normalizeRow($rawRow, $columnMap, $rowNumber);
            $row = $this->applyCarryForward($row, $carried);

            if ($this->isBlankRow($row)) {
                continue;
            }

            if ($row['recipe_code'] === '' && $row['recipe_name'] === '') {
                $errors[] = ['row' => $rowNumber, 'message' => 'Provide recipe_code or recipe_name (or both).'];
                continue;
            }

            if ($row['ingredient_code'] === '' && $row['ingredient_name'] === '') {
                $errors[] = ['row' => $rowNumber, 'message' => 'ingredient_code or ingredient_name is required.'];
                continue;
            }

            if ($row['quantity'] === false || (float) $row['quantity'] <= 0) {
                $errors[] = ['row' => $rowNumber, 'message' => 'quantity must be a number greater than zero.'];
                continue;
            }

            if ($row['waste_percentage'] === false) {
                $errors[] = ['row' => $rowNumber, 'message' => 'waste_percentage must be a number.'];
                continue;
            }

            if (count($rows) >= self::MAX_ROWS) {
                $errors[] = ['row' => $rowNumber, 'message' => 'Import limit reached ('.self::MAX_ROWS.' rows per file).'];
                break;
            }

            $rows[] = $row;
        }

        return [
            'rows' => $rows,
            'errors' => $errors,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{groups: list<array<string, mixed>>, errors: list<array{row: int, message: string}>}
     */
    private function groupRows(array $rows): array
    {
        /** @var array<string, array<string, mixed>> $groups */
        $groups = [];
        $errors = [];

        foreach ($rows as $row) {
            $key = $this->groupKey($row);

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'key' => $key,
                    'first_row' => $row['_row'],
                    'recipe_code' => $row['recipe_code'],
                    'recipe_name' => $row['recipe_name'],
                    'description' => $row['description'],
                    'is_active' => $row['is_active'],
                    'ingredient_keys' => [],
                    'items' => [],
                ];
            }

            $group = &$groups[$key];

            if ($row['recipe_code'] !== '' && $group['recipe_code'] === '') {
                $group['recipe_code'] = $row['recipe_code'];
            }

            if ($row['recipe_name'] !== '') {
                if ($group['recipe_name'] !== '' && strcasecmp($group['recipe_name'], $row['recipe_name']) !== 0) {
                    $errors[] = [
                        'row' => $row['_row'],
                        'message' => "Recipe name \"{$row['recipe_name']}\" conflicts with \"{$group['recipe_name']}\" for the same recipe (see row {$group['first_row']}).",
                    ];
                    continue;
                }
                $group['recipe_name'] = $row['recipe_name'];
            }

            if ($row['description'] !== '') {
                $group['description'] = $row['description'];
            }

            $group['is_active'] = $row['is_active'];

            $ingKey = $row['ingredient_code'] !== ''
                ? 'code:'.Str::lower($row['ingredient_code'])
                : 'name:'.Str::lower($row['ingredient_name']);

            if (isset($group['ingredient_keys'][$ingKey])) {
                $errors[] = [
                    'row' => $row['_row'],
                    'message' => 'Duplicate ingredient for this recipe (also on row '.$group['ingredient_keys'][$ingKey].').',
                ];
                continue;
            }

            $group['ingredient_keys'][$ingKey] = $row['_row'];
            $group['items'][] = [
                'row' => $row['_row'],
                'ingredient_code' => $row['ingredient_code'],
                'ingredient_name' => $row['ingredient_name'],
                'quantity' => $row['quantity'],
                'unit' => $row['unit'],
                'waste_percentage' => $row['waste_percentage'],
                'notes' => $row['notes'],
            ];
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
        if ($group['recipe_name'] === '') {
            return ['status' => 'error', 'message' => 'Recipe name is required for each recipe group (first row of the group).'];
        }

        if ($group['items'] === []) {
            return ['status' => 'error', 'message' => 'Each recipe must have at least one ingredient row.'];
        }

        $lines = [];
        foreach ($group['items'] as $item) {
            $found = $this->findIngredient($companyId, $item['ingredient_code'], $item['ingredient_name']);
            if ($found['error'] !== null) {
                return ['status' => 'error', 'message' => $found['error'].' (row '.$item['row'].').'];
            }

            /** @var Ingredient $ingredient */
            $ingredient = $found['ingredient'];
            if (! TenantIngredientAccess::isUsableByCompany($ingredient, $companyId)) {
                return ['status' => 'error', 'message' => 'Ingredient does not belong to this company (row '.$item['row'].').'];
            }

            $unitRaw = IngredientImportReferences::normalizeUnitReference($item['unit']);
            if ($unitRaw === '') {
                $unitId = IngredientQuantity::canonicalRecipeUnitId($ingredient);
            } elseif (! IngredientQuantity::isValidRecipeUnit($ingredient, $unitRaw)) {
                return ['status' => 'error', 'message' => IngredientQuantity::conversionErrorMessage($ingredient, $unitRaw).' (row '.$item['row'].').'];
            } else {
                $unitId = IngredientQuantity::resolveRecipeUnitId($ingredient, $unitRaw);
            }

            $lines[] = [
                'ingredient_id' => $ingredient->id,
                'quantity' => $item['quantity'],
                'unit_id' => $unitId !== '' ? $unitId : null,
                'waste_percentage' => $item['waste_percentage'] ?? 0,
                'notes' => $item['notes'] !== '' ? $item['notes'] : null,
            ];
        }

        $existing = null;
        if ($group['recipe_code'] !== '') {
            $existing = Recipe::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->whereRaw('LOWER(code) = ?', [Str::lower($group['recipe_code'])])
                ->first();
        } else {
            $existing = Recipe::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->whereRaw('LOWER(name) = ?', [Str::lower($group['recipe_name'])])
                ->first();
        }

        $payload = [
            'company_id' => $companyId,
            'name' => $group['recipe_name'],
            'description' => $group['description'] !== '' ? $group['description'] : null,
            'is_active' => (bool) $group['is_active'],
        ];

        try {
            if ($existing) {
                if ($group['recipe_code'] !== '') {
                    $payload['code'] = $group['recipe_code'];
                }
                $existing->update($payload);
                $existing->syncItems($lines);
                $this->refreshLinkedMenuItemCosts($existing);

                return ['status' => 'updated'];
            }

            $payload['code'] = Recipe::resolveCode($companyId, $group['recipe_code']);
            $recipe = Recipe::withoutGlobalScopes()->create($payload);
            $recipe->syncItems($lines);

            return ['status' => 'created'];
        } catch (\Throwable $exception) {
            return ['status' => 'error', 'message' => 'Could not save recipe: '.$exception->getMessage()];
        }
    }

    private function refreshLinkedMenuItemCosts(Recipe $recipe): void
    {
        $menuItemIds = $recipe->menuItemsAsDefault()->pluck('id')
            ->merge($recipe->variantOptionLinks()->pluck('menu_item_id'))
            ->unique()
            ->filter();

        if ($menuItemIds->isEmpty()) {
            return;
        }

        \App\Models\MenuItem::withoutGlobalScopes()
            ->where('company_id', $recipe->company_id)
            ->whereIn('id', $menuItemIds)
            ->get()
            ->each(function (\App\Models\MenuItem $menuItem) {
                if ($menuItem->type !== 'recipe') {
                    return;
                }
                $menuItem->cost = $menuItem->calculateCost();
                $menuItem->save();
            });
    }

    /**
     * @return array{ingredient: ?Ingredient, error: ?string}
     */
    private function findIngredient(int $companyId, string $code, string $name): array
    {
        $code = trim($code);
        $name = trim($name);

        $baseQuery = Ingredient::withoutGlobalScopes()->where('company_id', $companyId);

        if ($code !== '') {
            $matches = (clone $baseQuery)
                ->where(function ($query) use ($code) {
                    $query->whereRaw('UPPER(sku) = ?', [Str::upper($code)])
                        ->orWhereRaw('LOWER(name) = ?', [Str::lower($code)]);
                })
                ->get();

            if ($matches->count() === 1) {
                return ['ingredient' => $matches->first(), 'error' => null];
            }

            if ($matches->count() > 1) {
                return [
                    'ingredient' => null,
                    'error' => "Multiple ingredients match \"{$code}\" — use ingredient_code (SKU)",
                ];
            }

            if ($name === '') {
                return [
                    'ingredient' => null,
                    'error' => "Ingredient \"{$code}\" not found",
                ];
            }
        }

        $matches = (clone $baseQuery)
            ->whereRaw('LOWER(name) = ?', [Str::lower($name)])
            ->get();

        if ($matches->count() === 1) {
            return ['ingredient' => $matches->first(), 'error' => null];
        }

        if ($matches->count() > 1) {
            return [
                'ingredient' => null,
                'error' => "Multiple ingredients named \"{$name}\" — use ingredient_code (SKU)",
            ];
        }

        $label = $name !== '' ? $name : $code;

        return [
            'ingredient' => null,
            'error' => "Ingredient \"{$label}\" not found",
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function groupKey(array $row): string
    {
        if ($row['recipe_code'] !== '') {
            return 'code:'.Str::lower($row['recipe_code']);
        }

        return 'name:'.Str::lower($row['recipe_name']);
    }

    /**
     * @param  list<mixed>  $headerRow
     * @return array<string, int>
     */
    private function mapHeaders(array $headerRow): array
    {
        $map = [];

        foreach ($headerRow as $index => $header) {
            $normalized = $this->normalizeHeader((string) $header);
            if ($normalized === '') {
                continue;
            }

            foreach (self::HEADER_ALIASES as $field => $aliases) {
                if (in_array($normalized, $aliases, true)) {
                    $map[$field] = $index;
                }
            }
        }

        return $map;
    }

    /**
     * @param  list<mixed>  $rawRow
     * @param  array<string, int>  $columnMap
     * @return array<string, mixed>
     */
    private function normalizeRow(array $rawRow, array $columnMap, int $rowNumber): array
    {
        return [
            '_row' => $rowNumber,
            'recipe_code' => $this->cellValue($rawRow, $columnMap, 'recipe_code'),
            'recipe_name' => $this->cellValue($rawRow, $columnMap, 'recipe_name'),
            'description' => $this->cellValue($rawRow, $columnMap, 'description'),
            'is_active' => $this->parseBoolean($this->cellValue($rawRow, $columnMap, 'is_active'), true),
            'ingredient_code' => $this->cellValue($rawRow, $columnMap, 'ingredient_code'),
            'ingredient_name' => $this->cellValue($rawRow, $columnMap, 'ingredient_name'),
            'quantity' => $this->parseDecimal($this->cellValue($rawRow, $columnMap, 'quantity')),
            'unit' => $this->cellValue($rawRow, $columnMap, 'unit'),
            'waste_percentage' => $this->parseDecimal($this->cellValue($rawRow, $columnMap, 'waste_percentage'), 0.0),
            'notes' => $this->cellValue($rawRow, $columnMap, 'notes'),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, string>  $carried
     * @return array<string, mixed>
     */
    private function applyCarryForward(array $row, array &$carried): array
    {
        foreach (self::CARRY_FORWARD_FIELDS as $field) {
            if ($field === 'is_active') {
                // Boolean already parsed; carry only when cell was blank before parse —
                // blank becomes true by default, so track raw in separate pass if needed.
                continue;
            }

            if ($row[$field] === '' && isset($carried[$field]) && $carried[$field] !== '') {
                $row[$field] = $carried[$field];
            }

            if ($row[$field] !== '') {
                $carried[$field] = $row[$field];
            }
        }

        return $row;
    }

    /**
     * @param  list<mixed>  $rawRow
     * @param  array<string, int>  $columnMap
     */
    private function cellValue(array $rawRow, array $columnMap, string $field): string
    {
        if (! isset($columnMap[$field])) {
            return '';
        }

        $value = $rawRow[$columnMap[$field]] ?? '';

        if ($field === 'unit') {
            return IngredientImportReferences::normalizeUnitReference($value);
        }

        return trim((string) $value);
    }

    private function normalizeHeader(string $header): string
    {
        $header = trim($header);
        $header = ltrim($header, "\xEF\xBB\xBF");
        $header = Str::lower($header);
        $header = preg_replace('/[\s_\-]+/', ' ', $header) ?? $header;

        return trim($header);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function isBlankRow(array $row): bool
    {
        return $row['recipe_code'] === ''
            && $row['recipe_name'] === ''
            && $row['ingredient_code'] === ''
            && $row['ingredient_name'] === ''
            && ($row['quantity'] === 0.0 || $row['quantity'] === false || $row['quantity'] === '');
    }

    private function parseBoolean(string $value, bool $default): bool
    {
        if ($value === '') {
            return $default;
        }

        $normalized = Str::lower($value);

        if (in_array($normalized, ['1', 'true', 'yes', 'y', 'active', 'enabled'], true)) {
            return true;
        }

        if (in_array($normalized, ['0', 'false', 'no', 'n', 'inactive', 'disabled'], true)) {
            return false;
        }

        return $default;
    }

    /**
     * @return float|false
     */
    private function parseDecimal(string $value, float|false $blank = false): float|false
    {
        if ($value === '') {
            return $blank;
        }

        if (! is_numeric($value)) {
            return false;
        }

        return round((float) $value, 4);
    }

    /** @return list<string> */
    public static function expectedHeaders(): array
    {
        return RecipeImportSampleExport::HEADERS;
    }
}
