<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Ingredient;
use App\Support\IngredientQuantity;
use App\Models\MenuItem;
use App\Models\ProductAddon;
use App\Models\Recipe;
use App\Models\Variant;
use App\Support\MenuItemImportSampleExport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MenuItemImportService
{
    private const MAX_ROWS = 1000;

    /** @var array<string, list<string>> */
    private const CARRY_FORWARD_FIELDS = [
        'variant_prices' => ['menu_item_code', 'variant_code'],
        'addons' => ['menu_item_code'],
        'recipes' => ['menu_item_code', 'variant_code', 'option_name'],
    ];

    /** @var array<string, list<string>> */
    private const SHEET_ALIASES = [
        'menu_items' => ['menu_items', 'menu items', 'items', 'menu'],
        'variant_prices' => ['variant_prices', 'variant prices', 'variants', 'variant'],
        'addons' => ['addons', 'addon_links', 'menu_item_addons', 'addon'],
        'recipes' => ['recipes', 'recipe', 'menu_item_recipes'],
    ];

    /** @var array<string, array<string, list<string>>> */
    private const HEADER_ALIASES = [
        'menu_items' => [
            'menu_item_code' => ['menu_item_code', 'menu item code', 'code', 'sku', 'item_code'],
            'name' => ['name', 'menu_item_name', 'menu item name', 'item_name'],
            'category_code' => ['category_code', 'category code', 'category'],
            'price' => ['price', 'sale_price', 'sale price', 'base_price'],
            'type' => ['type', 'item_type', 'inventory_type'],
            'track_inventory' => ['track_inventory', 'track inventory', 'track stock'],
            'is_available' => ['is_available', 'available', 'status', 'enabled'],
            'description' => ['description', 'desc'],
            'preparation_time' => ['preparation_time', 'prep time', 'preparation time', 'prep_time'],
            'sort_order' => ['sort_order', 'sort order', 'sort'],
        ],
        'variant_prices' => [
            'menu_item_code' => ['menu_item_code', 'menu item code', 'code', 'sku', 'item_code'],
            'variant_code' => ['variant_code', 'variant code', 'v code'],
            'option_name' => ['option_name', 'option name', 'option'],
            'option_price' => ['option_price', 'option price', 'price'],
            'is_default' => ['is_default', 'default', 'is default', 'default_variant'],
        ],
        'addons' => [
            'menu_item_code' => ['menu_item_code', 'menu item code', 'code', 'sku', 'item_code'],
            'addon_code' => ['addon_code', 'addon code', 'pa code', 'code'],
        ],
        'recipes' => [
            'menu_item_code' => ['menu_item_code', 'menu item code', 'code', 'sku', 'item_code'],
            'variant_code' => ['variant_code', 'variant code', 'v code'],
            'option_name' => ['option_name', 'option name', 'option'],
            'recipe_code' => ['recipe_code', 'recipe code', 'r code', 'bom_code'],
            // Legacy BOM columns (optional) — prefer linking via recipe_code
            'ingredient_code' => ['ingredient_code', 'ingredient code', 'ingredient sku'],
            'ingredient_name' => ['ingredient_name', 'ingredient name', 'ingredient'],
            'quantity' => ['quantity', 'qty', 'amount'],
            'unit' => ['unit', 'unit_id', 'uom'],
            'waste_percentage' => ['waste_percentage', 'waste percentage', 'waste', 'waste %'],
            'notes' => ['notes', 'note', 'comment'],
        ],
    ];

    /**
     * @return array{created: int, updated: int, skipped: int, errors: list<array{row: string, message: string}>}
     */
    public function import(UploadedFile $file, int $companyId): array
    {
        $parsed = $this->parseWorkbook($file);

        $result = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => $parsed['errors'],
        ];

        if ($parsed['menu_items'] === []) {
            if ($result['errors'] === []) {
                $result['errors'][] = [
                    'row' => 'menu_items:1',
                    'message' => 'The menu_items sheet has no data rows to import.',
                ];
            }

            return $result;
        }

        $parsed['menu_items'] = $this->assignMissingMenuItemCodes($parsed['menu_items'], $companyId);

        $result['errors'] = array_merge(
            $result['errors'],
            $this->validateCrossSheetReferences($parsed)
        );

        if ($this->hasBlockingErrors($result['errors'])) {
            return $result;
        }

        $variantGroups = $this->groupChildRowsByMenuItemCode($parsed['variant_prices']);
        $addonGroups = $this->groupChildRowsByMenuItemCode($parsed['addons']);
        $recipeGroups = $this->groupChildRowsByMenuItemCode($parsed['recipes']);

        $variantSheetCodes = array_keys($variantGroups);
        $addonSheetCodes = array_keys($addonGroups);
        $recipeSheetCodes = array_keys($recipeGroups);

        DB::transaction(function () use (
            $parsed,
            $companyId,
            $variantGroups,
            $addonGroups,
            $recipeGroups,
            $variantSheetCodes,
            $addonSheetCodes,
            $recipeSheetCodes,
            &$result
        ) {
            foreach ($parsed['menu_items'] as $row) {
                $code = $row['menu_item_code'];
                $outcome = $this->importMenuItem(
                    $row,
                    $companyId,
                    $variantGroups[$code] ?? [],
                    $addonGroups[$code] ?? [],
                    $recipeGroups[$code] ?? [],
                    in_array($code, $variantSheetCodes, true),
                    in_array($code, $addonSheetCodes, true),
                    in_array($code, $recipeSheetCodes, true),
                );

                if ($outcome['status'] === 'created') {
                    $result['created']++;
                } elseif ($outcome['status'] === 'updated') {
                    $result['updated']++;
                } else {
                    $result['skipped']++;
                    $result['errors'][] = [
                        'row' => 'menu_items:'.$row['_row'],
                        'message' => $outcome['message'] ?? 'Could not import menu item.',
                    ];
                }
            }
        });

        return $result;
    }

    /**
     * @return array{
     *     menu_items: list<array<string, mixed>>,
     *     variant_prices: list<array<string, mixed>>,
     *     addons: list<array<string, mixed>>,
     *     recipes: list<array<string, mixed>>,
     *     errors: list<array{row: string, message: string}>
     * }
     */
    public function parseWorkbook(UploadedFile $file): array
    {
        try {
            $reader = IOFactory::createReaderForFile($file->getRealPath());
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($file->getRealPath());
        } catch (\Throwable) {
            return $this->emptyParseResult([
                ['row' => 'file', 'message' => 'Could not read the uploaded file. Please use a valid Excel (.xlsx) file.'],
            ]);
        }

        $sheetMap = $this->resolveSheets($spreadsheet);
        $errors = $sheetMap['errors'];

        if (! isset($sheetMap['sheets']['menu_items'])) {
            $errors[] = [
                'row' => 'menu_items:1',
                'message' => 'Missing required sheet "menu_items". Download the sample file for the expected workbook layout.',
            ];

            return $this->emptyParseResult($errors);
        }

        $menuItems = $this->parseSheet(
            $sheetMap['sheets']['menu_items'],
            'menu_items',
            fn (array $row): bool => $row['menu_item_code'] === '' && $row['name'] === '',
            fn (array $row, int $rowNumber): ?string => $this->validateMenuItemRow($row, $rowNumber),
        );

        $variantPrices = isset($sheetMap['sheets']['variant_prices'])
            ? $this->parseSheet(
                $sheetMap['sheets']['variant_prices'],
                'variant_prices',
                fn (array $row): bool => $row['menu_item_code'] === '' && $row['variant_code'] === '' && $row['option_name'] === '',
                fn (array $row, int $rowNumber): ?string => $this->validateVariantRow($row, $rowNumber),
            )
            : ['rows' => [], 'errors' => []];

        $addons = isset($sheetMap['sheets']['addons'])
            ? $this->parseSheet(
                $sheetMap['sheets']['addons'],
                'addons',
                fn (array $row): bool => $row['menu_item_code'] === '' && $row['addon_code'] === '',
                fn (array $row, int $rowNumber): ?string => $this->validateAddonRow($row, $rowNumber),
            )
            : ['rows' => [], 'errors' => []];

        $recipes = isset($sheetMap['sheets']['recipes'])
            ? $this->parseSheet(
                $sheetMap['sheets']['recipes'],
                'recipes',
                fn (array $row): bool => $row['menu_item_code'] === ''
                    && ($row['recipe_code'] ?? '') === ''
                    && ($row['ingredient_code'] ?? '') === ''
                    && ($row['ingredient_name'] ?? '') === '',
                fn (array $row, int $rowNumber): ?string => $this->validateRecipeRow($row, $rowNumber),
            )
            : ['rows' => [], 'errors' => []];

        return [
            'menu_items' => $menuItems['rows'],
            'variant_prices' => $variantPrices['rows'],
            'addons' => $addons['rows'],
            'recipes' => $recipes['rows'],
            'errors' => array_merge(
                $errors,
                $menuItems['errors'],
                $variantPrices['errors'],
                $addons['errors'],
                $recipes['errors'],
            ),
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function expectedHeaders(): array
    {
        return [
            'menu_items' => array_keys(self::HEADER_ALIASES['menu_items']),
            'variant_prices' => array_keys(self::HEADER_ALIASES['variant_prices']),
            'addons' => array_keys(self::HEADER_ALIASES['addons']),
            'recipes' => MenuItemImportSampleExport::RECIPE_HEADERS,
        ];
    }

    /**
     * @param  list<array{row: string, message: string}>  $errors
     * @return array{menu_items: list<array<string, mixed>>, variant_prices: list<array<string, mixed>>, addons: list<array<string, mixed>>, recipes: list<array<string, mixed>>, errors: list<array{row: string, message: string}>}
     */
    private function emptyParseResult(array $errors): array
    {
        return [
            'menu_items' => [],
            'variant_prices' => [],
            'addons' => [],
            'recipes' => [],
            'errors' => $errors,
        ];
    }

    /**
     * @return array{sheets: array<string, Worksheet>, errors: list<array{row: string, message: string}>}
     */
    private function resolveSheets(Spreadsheet $spreadsheet): array
    {
        /** @var array<string, Worksheet> $sheets */
        $sheets = [];
        $errors = [];

        foreach ($spreadsheet->getWorksheetIterator() as $index => $worksheet) {
            $normalized = $this->normalizeHeader($worksheet->getTitle());

            foreach (self::SHEET_ALIASES as $key => $aliases) {
                if (in_array($normalized, $aliases, true)) {
                    if (isset($sheets[$key])) {
                        $errors[] = [
                            'row' => $worksheet->getTitle().':1',
                            'message' => "Duplicate sheet for \"{$key}\". Keep one sheet per section.",
                        ];
                        continue 2;
                    }
                    $sheets[$key] = $worksheet;
                    continue 2;
                }
            }

            if ($index === 0 && ! isset($sheets['menu_items'])) {
                $sheets['menu_items'] = $worksheet;
            } elseif ($index === 1 && ! isset($sheets['variant_prices'])) {
                $sheets['variant_prices'] = $worksheet;
            } elseif ($index === 2 && ! isset($sheets['addons'])) {
                $sheets['addons'] = $worksheet;
            } elseif ($index === 3 && ! isset($sheets['recipes'])) {
                $sheets['recipes'] = $worksheet;
            }
        }

        return ['sheets' => $sheets, 'errors' => $errors];
    }

    /**
     * @param  callable(array<string, mixed>): bool  $isBlank
     * @param  callable(array<string, mixed>, int): ?string  $validate
     * @return array{rows: list<array<string, mixed>>, errors: list<array{row: string, message: string}>}
     */
    private function parseSheet(
        Worksheet $worksheet,
        string $sheetKey,
        callable $isBlank,
        callable $validate,
    ): array {
        $sheetRows = $worksheet->toArray(null, true, true, false);
        if ($sheetRows === []) {
            return ['rows' => [], 'errors' => []];
        }

        $headerRow = array_shift($sheetRows);
        $columnMap = $this->mapHeaders($headerRow ?? [], $sheetKey);

        if ($sheetKey === 'menu_items' && ! isset($columnMap['menu_item_code'])) {
            return [
                'rows' => [],
                'errors' => [[
                    'row' => $sheetKey.':1',
                    'message' => 'Missing required "menu_item_code" column on the menu_items sheet.',
                ]],
            ];
        }

        $rows = [];
        $errors = [];
        $carried = [];

        foreach ($sheetRows as $index => $rawRow) {
            $rowNumber = $index + 2;
            $row = $this->normalizeRow($rawRow, $columnMap, $sheetKey, $rowNumber);
            $row = $this->applyCarryForward($row, $sheetKey, $carried);

            if ($isBlank($row)) {
                continue;
            }

            $validationMessage = $validate($row, $rowNumber);
            if ($validationMessage !== null) {
                $errors[] = ['row' => $sheetKey.':'.$rowNumber, 'message' => $validationMessage];
                continue;
            }

            if (count($rows) >= self::MAX_ROWS) {
                $errors[] = [
                    'row' => $sheetKey.':'.$rowNumber,
                    'message' => 'Import limit reached ('.self::MAX_ROWS.' rows per sheet).',
                ];
                break;
            }

            $rows[] = $row;
        }

        return ['rows' => $rows, 'errors' => $errors];
    }

    /**
     * Blank cells on child sheets inherit the last non-empty value above (Excel fill-down).
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, string>  $carried
     * @return array<string, mixed>
     */
    private function applyCarryForward(array $row, string $sheetKey, array &$carried): array
    {
        foreach (self::CARRY_FORWARD_FIELDS[$sheetKey] ?? [] as $field) {
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
     * @param  array<string, mixed>  $row
     */
    private function validateMenuItemRow(array $row, int $rowNumber): ?string
    {
        if ($row['name'] === '') {
            return 'name is required.';
        }

        if ($row['category_code'] === '') {
            return 'category_code is required.';
        }

        if ($row['price'] === false) {
            return 'price must be a number.';
        }

        if ($row['type'] === false) {
            return 'type must be single or recipe.';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function validateVariantRow(array $row, int $rowNumber): ?string
    {
        if ($row['menu_item_code'] === '') {
            return 'menu_item_code is required.';
        }

        if ($row['variant_code'] === '') {
            return 'variant_code is required.';
        }

        if ($row['option_name'] === '') {
            return 'option_name is required.';
        }

        if ($row['option_price'] === false) {
            return 'option_price must be a number.';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function validateAddonRow(array $row, int $rowNumber): ?string
    {
        if ($row['menu_item_code'] === '') {
            return 'menu_item_code is required.';
        }

        if ($row['addon_code'] === '') {
            return 'addon_code is required.';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function validateRecipeRow(array $row, int $rowNumber): ?string
    {
        if ($row['menu_item_code'] === '') {
            return 'menu_item_code is required.';
        }

        $hasRecipeCode = ($row['recipe_code'] ?? '') !== '';
        $hasIngredient = ($row['ingredient_code'] ?? '') !== '' || ($row['ingredient_name'] ?? '') !== '';

        if (! $hasRecipeCode && ! $hasIngredient) {
            return 'recipe_code is required (or use legacy ingredient_code / ingredient_name columns).';
        }

        if ($hasRecipeCode && $hasIngredient) {
            return 'Use either recipe_code (preferred) or ingredient columns — not both on the same row.';
        }

        $hasVariant = $row['variant_code'] !== '';
        $hasOption = $row['option_name'] !== '';
        if ($hasVariant xor $hasOption) {
            return 'Provide both variant_code and option_name for a per-option recipe, or leave both blank for an item without variants.';
        }

        if ($hasIngredient) {
            if ($row['quantity'] === false || $row['quantity'] <= 0) {
                return 'quantity must be a number greater than zero.';
            }
        }

        return null;
    }

    /**
     * @param  array{
     *     menu_items: list<array<string, mixed>>,
     *     variant_prices: list<array<string, mixed>>,
     *     addons: list<array<string, mixed>>,
     *     recipes: list<array<string, mixed>>,
     * }  $parsed
     * @return list<array{row: string, message: string}>
     */
    private function validateCrossSheetReferences(array $parsed): array
    {
        $errors = [];
        $menuItemCodes = [];

        foreach ($parsed['menu_items'] as $row) {
            $code = Str::upper($row['menu_item_code']);
            if (isset($menuItemCodes[$code])) {
                $errors[] = [
                    'row' => 'menu_items:'.$row['_row'],
                    'message' => "Duplicate menu_item_code \"{$row['menu_item_code']}\" (also on row {$menuItemCodes[$code]}).",
                ];
                continue;
            }
            $menuItemCodes[$code] = $row['_row'];
        }

        foreach (['variant_prices', 'addons', 'recipes'] as $sheetKey) {
            foreach ($parsed[$sheetKey] as $row) {
                $code = Str::upper($row['menu_item_code']);
                if (! isset($menuItemCodes[$code])) {
                    $errors[] = [
                        'row' => $sheetKey.':'.$row['_row'],
                        'message' => "menu_item_code \"{$row['menu_item_code']}\" is not listed on the menu_items sheet.",
                    ];
                }
            }
        }

        return $errors;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, list<array<string, mixed>>>
     */
    private function groupChildRowsByMenuItemCode(array $rows): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $key = Str::upper($row['menu_item_code']);
            $groups[$key][] = $row;
        }

        return $groups;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function assignMissingMenuItemCodes(array $rows, int $companyId): array
    {
        foreach ($rows as &$row) {
            if (trim((string) ($row['menu_item_code'] ?? '')) === '') {
                $row['menu_item_code'] = MenuItem::resolveSku($companyId, null);
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * @param  list<array{row: string, message: string}>  $errors
     */
    private function hasBlockingErrors(array $errors): bool
    {
        return $errors !== [];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<array<string, mixed>>  $variantRows
     * @param  list<array<string, mixed>>  $addonRows
     * @param  list<array<string, mixed>>  $recipeRows
     * @return array{status: string, message?: string}
     */
    private function importMenuItem(
        array $row,
        int $companyId,
        array $variantRows,
        array $addonRows,
        array $recipeRows,
        bool $syncVariants,
        bool $syncAddons,
        bool $syncRecipes,
    ): array {
        $code = MenuItem::resolveSku($companyId, $row['menu_item_code']);
        $category = $this->resolveCategory($companyId, $row['category_code']);
        if (! $category) {
            return ['status' => 'skipped', 'message' => "Category \"{$row['category_code']}\" not found."];
        }

        $menuItem = MenuItem::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereRaw('UPPER(sku) = ?', [Str::upper($code)])
            ->first();

        $isNew = ! $menuItem;
        if (! $menuItem) {
            $menuItem = new MenuItem(['company_id' => $companyId]);
        }

        $type = $row['type'];
        $trackInventory = $this->parseBoolean($row['track_inventory_raw'], false);

        if ($type === 'recipe' && $isNew && ! $syncRecipes) {
            return [
                'status' => 'skipped',
                'message' => 'New recipe-type menu items need at least one row on the recipes sheet.',
            ];
        }

        $slug = $isNew ? $this->uniqueSlug($row['name'], $companyId) : $menuItem->slug;

        $menuItem->fill([
            'category_id' => $category->id,
            'type' => $type,
            'name' => $row['name'],
            'slug' => $slug,
            'description' => $row['description'] !== '' ? $row['description'] : null,
            'price' => $row['price'],
            'sku' => $code,
            'preparation_time' => $row['preparation_time'],
            'is_available' => $this->parseBoolean($row['is_available_raw'], true),
            'track_inventory' => $trackInventory,
            'sort_order' => $row['sort_order'] ?? 0,
        ]);
        $menuItem->save();

        if ($syncVariants) {
            $variantOutcome = $this->syncVariantPrices($menuItem, $variantRows, $companyId);
            if ($variantOutcome['status'] === 'skipped') {
                return $variantOutcome;
            }
        }

        if ($syncAddons) {
            $addonOutcome = $this->syncAddonLinks($menuItem, $addonRows, $companyId);
            if ($addonOutcome['status'] === 'skipped') {
                return $addonOutcome;
            }
        }

        if ($syncRecipes) {
            $recipeOutcome = $this->syncRecipes($menuItem, $recipeRows, $companyId);
            if ($recipeOutcome['status'] === 'skipped') {
                return $recipeOutcome;
            }

            if ($type === 'recipe' && $recipeRows === []) {
                return [
                    'status' => 'skipped',
                    'message' => 'Recipe-type menu items need at least one recipe row when the recipes sheet includes this menu item.',
                ];
            }
        }

        return ['status' => $isNew ? 'created' : 'updated'];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{status: string, message?: string}
     */
    private function syncVariantPrices(MenuItem $menuItem, array $rows, int $companyId): array
    {
        /** @var array<string, array{variant: Variant, option_prices: array<string, float>, is_default: bool, first_row: int}> $groups */
        $groups = [];

        foreach ($rows as $row) {
            $variantCode = $row['variant_code'];
            $groupKey = Str::upper($variantCode);

            $variant = Variant::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->whereRaw('UPPER(code) = ?', [Str::upper($variantCode)])
                ->first();

            if (! $variant) {
                return [
                    'status' => 'skipped',
                    'message' => "Variant \"{$variantCode}\" not found (variant_prices row {$row['_row']}).",
                ];
            }

            $canonicalOptionName = $this->resolveVariantOptionName($variant, $row['option_name']);
            if ($canonicalOptionName === null) {
                return [
                    'status' => 'skipped',
                    'message' => "Option \"{$row['option_name']}\" not found on variant \"{$variantCode}\" (variant_prices row {$row['_row']}).",
                ];
            }

            if (! isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'variant' => $variant,
                    'option_prices' => [],
                    'is_default' => false,
                    'first_row' => $row['_row'],
                ];
            }

            if (isset($groups[$groupKey]['option_prices'][$canonicalOptionName])) {
                return [
                    'status' => 'skipped',
                    'message' => "Duplicate option \"{$canonicalOptionName}\" for variant \"{$variantCode}\" (variant_prices row {$row['_row']}).",
                ];
            }

            $groups[$groupKey]['option_prices'][$canonicalOptionName] = (float) $row['option_price'];

            if ($this->parseBoolean($row['is_default_raw'], false)) {
                $groups[$groupKey]['is_default'] = true;
            }
        }

        if ($groups === []) {
            $menuItem->variants()->sync([]);

            return ['status' => 'ok'];
        }

        $defaultAssigned = false;
        $variantsData = [];

        foreach ($groups as $group) {
            $isDefault = $group['is_default'];
            if ($isDefault) {
                if ($defaultAssigned) {
                    return [
                        'status' => 'skipped',
                        'message' => 'Only one variant can be marked is_default per menu item.',
                    ];
                }
                $defaultAssigned = true;
            }

            $variantsData[$group['variant']->id] = [
                'price' => 0,
                'option_prices' => json_encode($group['option_prices']),
                'is_default' => $isDefault,
            ];
        }

        if (! $defaultAssigned && $variantsData !== []) {
            $firstKey = array_key_first($variantsData);
            $variantsData[$firstKey]['is_default'] = true;
        }

        $menuItem->variants()->sync($variantsData);

        return ['status' => 'ok'];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{status: string, message?: string}
     */
    private function syncAddonLinks(MenuItem $menuItem, array $rows, int $companyId): array
    {
        $addonIds = [];
        $seen = [];

        foreach ($rows as $row) {
            $addonCode = $row['addon_code'];
            $dedupeKey = Str::upper($addonCode);

            if (isset($seen[$dedupeKey])) {
                return [
                    'status' => 'skipped',
                    'message' => "Duplicate addon \"{$addonCode}\" for this menu item (addons row {$row['_row']}).",
                ];
            }
            $seen[$dedupeKey] = true;

            $addon = ProductAddon::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->whereRaw('UPPER(code) = ?', [Str::upper($addonCode)])
                ->first();

            if (! $addon) {
                return [
                    'status' => 'skipped',
                    'message' => "Addon \"{$addonCode}\" not found (addons row {$row['_row']}).",
                ];
            }

            $addonIds[] = $addon->id;
        }

        $menuItem->productAddons()->sync($addonIds);

        return ['status' => 'ok'];
    }

    /**
     * Attach catalog recipes by recipe_code (preferred), or build from legacy ingredient lines.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{status: string, message?: string}
     */
    private function syncRecipes(MenuItem $menuItem, array $rows, int $companyId): array
    {
        $menuItem->syncCatalogRecipes(null, []);

        if ($rows === []) {
            $this->refreshCalculatedCost($menuItem);

            return ['status' => 'ok'];
        }

        $linkRows = [];
        $lineRows = [];
        foreach ($rows as $row) {
            if (($row['recipe_code'] ?? '') !== '') {
                $linkRows[] = $row;
            } else {
                $lineRows[] = $row;
            }
        }

        if ($linkRows !== [] && $lineRows !== []) {
            return [
                'status' => 'skipped',
                'message' => 'Cannot mix recipe_code links and legacy ingredient lines for the same menu item. Use recipe_code only (import recipes under Menu → Recipes first).',
            ];
        }

        if ($linkRows !== []) {
            return $this->syncRecipesByCatalogCode($menuItem, $linkRows, $companyId);
        }

        return $this->syncRecipesFromIngredientLines($menuItem, $lineRows, $companyId);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{status: string, message?: string}
     */
    private function syncRecipesByCatalogCode(MenuItem $menuItem, array $rows, int $companyId): array
    {
        $menuItem->load('variants');
        $hasVariants = $menuItem->variants->isNotEmpty();

        $attachedVariantCodes = $menuItem->variants
            ->keyBy(fn (Variant $variant) => Str::upper((string) $variant->code));

        $defaultRecipeId = null;
        /** @var list<array{variant_id:int, option_name:string, recipe_id:int}> $variantRecipeRows */
        $variantRecipeRows = [];
        /** @var array<string, true> $seenOptions */
        $seenOptions = [];

        foreach ($rows as $row) {
            $recipe = Recipe::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->whereRaw('UPPER(code) = ?', [Str::upper($row['recipe_code'])])
                ->first();

            if (! $recipe) {
                return [
                    'status' => 'skipped',
                    'message' => "Recipe \"{$row['recipe_code']}\" not found (recipes row {$row['_row']}). Import recipes under Menu → Recipes first.",
                ];
            }

            $hasScope = $row['variant_code'] !== '' && $row['option_name'] !== '';

            if (! $hasScope) {
                if ($hasVariants) {
                    return [
                        'status' => 'skipped',
                        'message' => "Menu item has variants — every recipes row needs variant_code and option_name (row {$row['_row']}). Do not use a blank/default recipe link.",
                    ];
                }

                if ($defaultRecipeId !== null && $defaultRecipeId !== (int) $recipe->id) {
                    return [
                        'status' => 'skipped',
                        'message' => "Only one default recipe_code is allowed when the item has no variants (row {$row['_row']}).",
                    ];
                }

                $defaultRecipeId = (int) $recipe->id;

                continue;
            }

            if (! $hasVariants) {
                return [
                    'status' => 'skipped',
                    'message' => "Variant/option on recipes row {$row['_row']} but this menu item has no variants. Add rows on variant_prices first, or leave variant_code and option_name blank.",
                ];
            }

            $variant = Variant::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->whereRaw('UPPER(code) = ?', [Str::upper($row['variant_code'])])
                ->first();

            if (! $variant) {
                return [
                    'status' => 'skipped',
                    'message' => "Variant \"{$row['variant_code']}\" not found (recipes row {$row['_row']}).",
                ];
            }

            if (! $attachedVariantCodes->has(Str::upper($row['variant_code']))) {
                return [
                    'status' => 'skipped',
                    'message' => "Variant \"{$row['variant_code']}\" is not linked to this menu item (recipes row {$row['_row']}). Add it on variant_prices first.",
                ];
            }

            $optionName = $this->resolveVariantOptionName($variant, $row['option_name']);
            if ($optionName === null) {
                return [
                    'status' => 'skipped',
                    'message' => "Option \"{$row['option_name']}\" not found on variant \"{$row['variant_code']}\" (recipes row {$row['_row']}).",
                ];
            }

            $optionKey = ((int) $variant->id).':'.$optionName;
            if (isset($seenOptions[$optionKey])) {
                return [
                    'status' => 'skipped',
                    'message' => "Duplicate recipe link for {$row['variant_code']} / {$optionName} (recipes row {$row['_row']}).",
                ];
            }
            $seenOptions[$optionKey] = true;

            $variantRecipeRows[] = [
                'variant_id' => (int) $variant->id,
                'option_name' => $optionName,
                'recipe_id' => (int) $recipe->id,
            ];
        }

        if ($hasVariants) {
            foreach ($menuItem->variants as $variant) {
                $optionPrices = json_decode($variant->pivot->option_prices ?? '[]', true);
                if (! is_array($optionPrices)) {
                    $optionPrices = [];
                }
                foreach (array_keys($optionPrices) as $optionName) {
                    $optionName = (string) $optionName;
                    if ($optionName === '') {
                        continue;
                    }
                    $optionKey = ((int) $variant->id).':'.$optionName;
                    if (! isset($seenOptions[$optionKey])) {
                        return [
                            'status' => 'skipped',
                            'message' => "Missing recipe_code for variant \"{$variant->code}\" option \"{$optionName}\". Every variant option needs a recipe link.",
                        ];
                    }
                }
            }

            $menuItem->syncCatalogRecipes(null, $variantRecipeRows);
        } else {
            if ($defaultRecipeId === null) {
                return [
                    'status' => 'skipped',
                    'message' => 'Select a recipe_code for this menu item (no variants).',
                ];
            }
            $menuItem->syncCatalogRecipes($defaultRecipeId, []);
        }

        $this->refreshCalculatedCost($menuItem);

        return ['status' => 'ok'];
    }

    /**
     * Legacy: build catalog recipes from inline ingredient BOM rows.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{status: string, message?: string}
     */
    private function syncRecipesFromIngredientLines(MenuItem $menuItem, array $rows, int $companyId): array
    {
        $menuItem->load('variants');
        $hasVariants = $menuItem->variants->isNotEmpty();

        $attachedVariantCodes = $menuItem->variants
            ->keyBy(fn (Variant $variant) => Str::upper((string) $variant->code));

        /** @var array<string, list<array<string, mixed>>> $groups */
        $groups = [];
        /** @var array<string, array{variant_id:?int, option_name:string, label:string}> $meta */
        $meta = [];

        foreach ($rows as $row) {
            $ingredientResult = $this->findIngredient($companyId, $row['ingredient_code'], $row['ingredient_name']);
            if ($ingredientResult['error'] !== null) {
                return [
                    'status' => 'skipped',
                    'message' => $ingredientResult['error'].' (recipes row '.$row['_row'].').',
                ];
            }

            $ingredient = $ingredientResult['ingredient'];
            $variantId = null;
            $optionName = '';
            $groupKey = 'default';
            $label = $menuItem->name.' — Default';

            if ($row['variant_code'] !== '' && $row['option_name'] !== '') {
                $variant = Variant::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->whereRaw('UPPER(code) = ?', [Str::upper($row['variant_code'])])
                    ->first();

                if (! $variant) {
                    return [
                        'status' => 'skipped',
                        'message' => "Variant \"{$row['variant_code']}\" not found (recipes row {$row['_row']}).",
                    ];
                }

                if (! $attachedVariantCodes->has(Str::upper($row['variant_code']))) {
                    return [
                        'status' => 'skipped',
                        'message' => "Variant \"{$row['variant_code']}\" is not linked to this menu item (recipes row {$row['_row']}). Add it on variant_prices first.",
                    ];
                }

                $canonicalOptionName = $this->resolveVariantOptionName($variant, $row['option_name']);
                if ($canonicalOptionName === null) {
                    return [
                        'status' => 'skipped',
                        'message' => "Option \"{$row['option_name']}\" not found on variant \"{$row['variant_code']}\" (recipes row {$row['_row']}).",
                    ];
                }

                $variantId = (int) $variant->id;
                $optionName = $canonicalOptionName;
                $groupKey = $variantId.':'.$optionName;
                $label = $menuItem->name.' — '.$variant->name.': '.$optionName;
            } elseif ($hasVariants) {
                return [
                    'status' => 'skipped',
                    'message' => "Menu item has variants — legacy ingredient rows need variant_code and option_name (row {$row['_row']}), or switch to recipe_code links.",
                ];
            }

            $groups[$groupKey][] = [
                'ingredient_id' => $ingredient->id,
                'quantity' => $row['quantity'],
                'unit_id' => $this->resolveRecipeUnitId($ingredient, $row['unit']),
                'waste_percentage' => $row['waste_percentage'] ?? 0,
                'notes' => $row['notes'] !== '' ? $row['notes'] : null,
            ];
            $meta[$groupKey] = [
                'variant_id' => $variantId,
                'option_name' => $optionName,
                'label' => $label,
            ];
        }

        foreach ($groups as $groupKey => $lines) {
            $info = $meta[$groupKey];
            if ($groupKey === 'default') {
                \App\Support\MenuItemCatalogRecipeBuilder::setDefaultFromLines($menuItem, $info['label'], $lines);
            } else {
                \App\Support\MenuItemCatalogRecipeBuilder::setOptionFromLines(
                    $menuItem,
                    (int) $info['variant_id'],
                    $info['option_name'],
                    $info['label'],
                    $lines
                );
            }
        }

        // Match product UI: no default_recipe_id when variants exist.
        if ($hasVariants && $menuItem->default_recipe_id) {
            $menuItem->default_recipe_id = null;
            $menuItem->save();
        }

        $this->refreshCalculatedCost($menuItem);

        return ['status' => 'ok'];
    }

    /**
     * @return array{ingredient: ?Ingredient, error: ?string}
     */
    private function findIngredient(int $companyId, string $code, string $name): array
    {
        $code = trim($code);
        $name = trim($name);

        if ($code === '' && $name === '') {
            return [
                'ingredient' => null,
                'error' => 'ingredient_code or ingredient_name is required',
            ];
        }

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
                    'error' => "Multiple ingredients match \"{$code}\" — use ingredient_code (SKU) instead",
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
                'error' => "Multiple ingredients named \"{$name}\" — use ingredient_code (SKU) instead",
            ];
        }

        $label = $name !== '' ? $name : $code;

        return [
            'ingredient' => null,
            'error' => "Ingredient \"{$label}\" not found",
        ];
    }

    private function resolveCategory(int $companyId, string $code): ?Category
    {
        return Category::withoutGlobalScopes()
            ->where(function ($query) use ($companyId) {
                $query->where('company_id', $companyId)->orWhereNull('company_id');
            })
            ->where('is_active', true)
            ->whereRaw('UPPER(code) = ?', [Str::upper(trim($code))])
            ->first();
    }

    private function resolveVariantOptionName(Variant $variant, string $optionName): ?string
    {
        foreach ($variant->options ?? [] as $option) {
            if (! is_array($option)) {
                continue;
            }
            if (strcasecmp((string) ($option['name'] ?? ''), $optionName) === 0) {
                return (string) $option['name'];
            }
        }

        return null;
    }

    private function resolveRecipeUnitId(Ingredient $ingredient, string $unit): ?string
    {
        $unit = trim($unit);
        if ($unit === '') {
            return IngredientQuantity::canonicalRecipeUnitId($ingredient);
        }

        if (! IngredientQuantity::isValidRecipeUnit($ingredient, $unit)) {
            return null;
        }

        if (IngredientQuantity::matchRecipeUnit($ingredient, $unit) === IngredientQuantity::UNIT_CONSUMPTION) {
            return IngredientQuantity::canonicalRecipeUnitId($ingredient);
        }

        return $unit;
    }

    private function refreshCalculatedCost(MenuItem $menuItem): void
    {
        $menuItem->unsetRelation('defaultRecipe');
        $menuItem->unsetRelation('variantRecipes');
        $menuItem->load(['defaultRecipe.items.ingredient', 'variantRecipes.recipe.items.ingredient']);
        $menuItem->cost = $menuItem->calculateCost();
        $menuItem->save();
    }

    private function uniqueSlug(string $name, int $companyId): string
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $counter = 1;

        while (MenuItem::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('slug', $slug)
            ->exists()) {
            $slug = $originalSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * @param  array<int, mixed>|null  $headerRow
     * @return array<string, int>
     */
    private function mapHeaders(?array $headerRow, string $sheetKey): array
    {
        $map = [];
        if (! is_array($headerRow)) {
            return $map;
        }

        $aliases = self::HEADER_ALIASES[$sheetKey] ?? [];

        foreach ($headerRow as $index => $cell) {
            $normalized = $this->normalizeHeader((string) $cell);
            if ($normalized === '') {
                continue;
            }
            foreach ($aliases as $field => $fieldAliases) {
                if (in_array($normalized, $fieldAliases, true)) {
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
    private function normalizeRow(array $rawRow, array $columnMap, string $sheetKey, int $rowNumber): array
    {
        $row = ['_row' => $rowNumber, '_sheet' => $sheetKey];

        foreach (array_keys(self::HEADER_ALIASES[$sheetKey]) as $field) {
            $value = isset($columnMap[$field])
                ? trim((string) ($rawRow[$columnMap[$field]] ?? ''))
                : '';
            $row[$field] = $value;
        }

        if ($sheetKey === 'menu_items') {
            $row['price'] = $this->parseDecimal($row['price']);
            $row['type'] = $this->parseMenuItemType($row['type']);
            $row['track_inventory_raw'] = $row['track_inventory'];
            $row['is_available_raw'] = $row['is_available'];
            $row['preparation_time'] = $row['preparation_time'] !== ''
                ? (int) $row['preparation_time']
                : null;
            $row['sort_order'] = $row['sort_order'] !== ''
                ? (int) $row['sort_order']
                : 0;
        }

        if ($sheetKey === 'variant_prices') {
            $row['option_price'] = $this->parseDecimal($row['option_price']);
            $row['is_default_raw'] = $row['is_default'];
        }

        if ($sheetKey === 'recipes') {
            $row['quantity'] = $this->parseDecimal($row['quantity'] ?? '');
            $row['waste_percentage'] = $this->parseDecimal($row['waste_percentage'] ?? '') ?? 0;
        }

        return $row;
    }

    private function normalizeHeader(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? '';

        return trim($value, '_');
    }

    /**
     * @return float|false
     */
    private function parseDecimal(mixed $value): float|false
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (! is_numeric($value)) {
            return false;
        }

        return round((float) $value, 4);
    }

    /**
     * @return string|false
     */
    private function parseMenuItemType(string $value): string|false
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return 'single';
        }

        if (in_array($value, ['recipe', 'recipes', 'bom'], true)) {
            return 'recipe';
        }

        if (in_array($value, ['single', 'item', 'product', 'simple'], true)) {
            return 'single';
        }

        return false;
    }

    private function parseBoolean(string $value, bool $default): bool
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return $default;
        }

        if (in_array($value, ['1', 'true', 'yes', 'y', 'on', 'active', 'enabled'], true)) {
            return true;
        }

        if (in_array($value, ['0', 'false', 'no', 'n', 'off', 'inactive', 'disabled'], true)) {
            return false;
        }

        return $default;
    }
}
