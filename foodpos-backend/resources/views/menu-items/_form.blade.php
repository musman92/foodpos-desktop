@php
    use App\Models\IngredientUnit;
    use App\Models\MenuItem;
    use App\Models\PlatformMedia;

    $defaultPiece = IngredientUnit::findOrCreateDefaultPiece((int) auth()->user()->company_id);
    $unitOptions = collect($ingredientUnits ?? [])->map(fn ($unit) => [
        'id' => (int) $unit->id,
        'name' => $unit->name,
        'code' => $unit->code,
        'label' => $unit->displayLabel(),
    ])->values();

    $isEdit = isset($menuItem) && $menuItem->exists;
    $formAction = $isEdit ? route('menu-items.update', $menuItem) : route('menu-items.store');
    $formMethod = $isEdit ? 'PUT' : 'POST';

    if ($isEdit) {
        $menuItemData = $menuItem->toArray();
        if (session()->has('errors')) {
            $menuItemData['name'] = old('name', $menuItemData['name'] ?? '');
            $menuItemData['category_id'] = old('category_id') !== null ? (string) old('category_id') : ($menuItemData['category_id'] ?? '');
            $menuItemData['cuisine_id'] = old('cuisine_id') !== null && old('cuisine_id') !== ''
                ? (string) old('cuisine_id')
                : ($menuItemData['cuisine_id'] ?? '');
            $menuItemData['type'] = old('type', $menuItemData['type'] ?? 'single');
            $menuItemData['description'] = old('description', $menuItemData['description'] ?? '');
            $menuItemData['price'] = old('price', $menuItemData['price'] ?? 0);
            $menuItemData['cost'] = old('cost', $menuItemData['cost'] ?? 0);
            $menuItemData['min_stock_level'] = old('min_stock_level', $menuItemData['min_stock_level'] ?? 0);
            $menuItemData['sku'] = old('sku', $menuItemData['sku'] ?? '');
            $menuItemData['preparation_time'] = old('preparation_time', $menuItemData['preparation_time'] ?? '');
            $menuItemData['is_available'] = (bool) old('is_available');
            $menuItemData['track_inventory'] = (bool) old('track_inventory');
            $menuItemData['purchase_unit_id'] = old('purchase_unit_id', $menuItemData['purchase_unit_id'] ?? $defaultPiece->id);
            $menuItemData['consumption_unit_id'] = old('consumption_unit_id', $menuItemData['consumption_unit_id'] ?? $defaultPiece->id);
            $menuItemData['conversion_rate'] = old('conversion_rate', $menuItemData['conversion_rate'] ?? 1);
            $menuItemData['purchase_price'] = old('purchase_price', $menuItemData['purchase_price'] ?? 0);
        }
    } else {
        $hasValidationErrors = session()->has('errors');
        $menuItemData = [
            'name' => old('name', ''),
            'category_id' => old('category_id') !== null ? (string) old('category_id') : '',
            'cuisine_id' => old('cuisine_id') !== null && old('cuisine_id') !== '' ? (string) old('cuisine_id') : '',
            'type' => old('type', 'single'),
            'description' => old('description', ''),
            'price' => old('price', '') !== '' && old('price', null) !== null ? old('price') : 0,
            'cost' => old('cost', '') !== '' && old('cost', null) !== null ? old('cost') : 0,
            'min_stock_level' => old('min_stock_level', '') !== '' && old('min_stock_level', null) !== null ? old('min_stock_level') : 0,
            'sku' => old('sku', ''),
            'preparation_time' => old('preparation_time', ''),
            'is_available' => $hasValidationErrors ? (bool) old('is_available') : (bool) old('is_available', true),
            'track_inventory' => $hasValidationErrors ? (bool) old('track_inventory') : (bool) old('track_inventory', true),
            'purchase_unit_id' => old('purchase_unit_id', $defaultPiece->id),
            'consumption_unit_id' => old('consumption_unit_id', $defaultPiece->id),
            'conversion_rate' => old('conversion_rate', 1),
            'purchase_price' => old('purchase_price', 0),
            'platform_media_path' => old('platform_media_path', ''),
            'image_preview_url' => '',
        ];
    }

    $menuItemData['platform_media_path'] = old('platform_media_path', '');
    $menuItemData['image_preview_url'] = '';

    if (old('platform_media_path')) {
        $menuItemData['platform_media_path'] = old('platform_media_path');
    } elseif ($isEdit && isset($menuItem) && $menuItem->image && MenuItem::imagePathIsPlatformMedia($menuItem->image)) {
        $menuItemData['platform_media_path'] = $menuItem->image;
    }

    if ($menuItemData['platform_media_path']) {
        $libraryMedia = PlatformMedia::where('file_path', $menuItemData['platform_media_path'])->first();
        $menuItemData['image_preview_url'] = $libraryMedia?->url ?? '';
    } elseif ($isEdit && isset($menuItem) && $menuItem->image) {
        $menuItemData['image_preview_url'] = $menuItem->resolvedImageUrl();
    }

    $menuItemData['default_recipe_id'] = (string) old(
        'default_recipe_id',
        $isEdit ? ($menuItem->default_recipe_id ?? '') : ''
    );
    if ($menuItemData['default_recipe_id'] === '0') {
        $menuItemData['default_recipe_id'] = '';
    }

    $oldVariantRecipes = is_array(old('variant_recipes')) ? old('variant_recipes') : null;
    if ($isEdit && session()->has('errors') && $oldVariantRecipes !== null) {
        $variantRecipesData = collect($oldVariantRecipes)->map(fn ($r) => [
            'variant_id' => isset($r['variant_id']) ? (string) $r['variant_id'] : '',
            'option_name' => (string) ($r['option_name'] ?? ''),
            'recipe_id' => isset($r['recipe_id']) ? (string) $r['recipe_id'] : '',
        ])->values()->all();
    } elseif ($isEdit && $menuItem->relationLoaded('variantRecipes')) {
        $variantRecipesData = $menuItem->variantRecipes->map(fn ($r) => [
            'variant_id' => (string) $r->variant_id,
            'option_name' => (string) $r->option_name,
            'recipe_id' => (string) $r->recipe_id,
        ])->values()->all();
    } elseif (! $isEdit && $oldVariantRecipes !== null) {
        $variantRecipesData = collect($oldVariantRecipes)->map(fn ($r) => [
            'variant_id' => isset($r['variant_id']) ? (string) $r['variant_id'] : '',
            'option_name' => (string) ($r['option_name'] ?? ''),
            'recipe_id' => isset($r['recipe_id']) ? (string) $r['recipe_id'] : '',
        ])->values()->all();
    } else {
        $variantRecipesData = [];
    }

    $catalogRecipesJson = collect($catalogRecipes ?? [])->map(fn ($r) => [
        'id' => (string) $r->id,
        'name' => $r->name,
        'code' => $r->code,
        'label' => $r->code ? "{$r->code} — {$r->name}" : $r->name,
    ])->values()->all();

    $oldProductAddons = is_array(old('product_addons')) ? old('product_addons') : null;
    if ($isEdit && session()->has('errors') && $oldProductAddons !== null) {
        $productAddonsData = array_map('intval', $oldProductAddons);
    } elseif ($isEdit && $menuItem->productAddons) {
        $productAddonsData = $menuItem->productAddons->pluck('id')->toArray();
    } elseif (! $isEdit && $oldProductAddons !== null) {
        $productAddonsData = array_map('intval', $oldProductAddons);
    } else {
        $productAddonsData = [];
    }
    // Fetch this menu item's variants from menu_item_variant table (relationship) for pre-filling the form.
    // Build full options with prices server-side so dropdown and price inputs pre-fill correctly.
    $variantsData = [];
    $oldInputAll = session()->getOldInput();
    $hasOldVariantsKey = is_array($oldInputAll) && array_key_exists('variants', $oldInputAll);
    $oldVariants = $hasOldVariantsKey && is_array($oldInputAll['variants']) ? $oldInputAll['variants'] : null;

    if ($isEdit && session()->has('errors') && $hasOldVariantsKey) {
        foreach ($oldVariants ?? [] as $row) {
            if (empty($row['variant_id'])) {
                continue;
            }
            $optionsWithPrices = [];
            if (! empty($row['option_prices']) && is_array($row['option_prices'])) {
                foreach ($row['option_prices'] as $op) {
                    if (! is_array($op)) {
                        continue;
                    }
                    $optionsWithPrices[] = [
                        'name' => (string) ($op['name'] ?? ''),
                        'code' => (string) ($op['code'] ?? ''),
                        'price' => isset($op['price']) ? (float) $op['price'] : 0,
                    ];
                }
            }
            $variantsData[] = [
                'variant_id' => (int) $row['variant_id'],
                'option_prices' => [],
                'options' => $optionsWithPrices,
                'is_default' => ! empty($row['is_default']),
            ];
        }
    } elseif ($isEdit) {
        // Replace any prior eager load: tenant scope or soft-deletes can hide pivot rows while the pivot still exists.
        $menuItem->unsetRelation('variants');
        $menuItem->load(['variants' => function ($query) use ($menuItem) {
            $query->withoutGlobalScope('tenant')
                ->withTrashed()
                ->where('variants.company_id', $menuItem->company_id);
        }]);
        $variantsRelation = $menuItem->variants;
        if ($variantsRelation && $variantsRelation->isNotEmpty()) {
            $variantsData = $variantsRelation->map(function ($variant) {
                $raw = $variant->pivot->option_prices ?? null;
                if (is_array($raw)) {
                    $optionPrices = $raw;
                } elseif ($raw instanceof \stdClass) {
                    $optionPrices = json_decode(json_encode($raw), true) ?: [];
                } elseif (is_string($raw) && $raw !== '') {
                    $decoded = json_decode($raw, true);
                    $optionPrices = is_array($decoded) ? $decoded : [];
                } else {
                    $optionPrices = [];
                }
                $optionsFromVariant = $variant->options ?? [];
                $optionsWithPrices = [];
                if (is_array($optionsFromVariant)) {
                    foreach ($optionsFromVariant as $opt) {
                        $optName = is_array($opt) ? ($opt['name'] ?? '') : (is_object($opt) ? ($opt->name ?? '') : '');
                        if ($optName === '') continue;
                        $optCode = is_array($opt) ? ($opt['code'] ?? null) : (is_object($opt) ? ($opt->code ?? null) : null);
                        $price = isset($optionPrices[$optName]) ? (float)$optionPrices[$optName] : 0;
                        $optionsWithPrices[] = ['name' => $optName, 'code' => $optCode ?? '', 'price' => $price];
                    }
                }
                // If variant has no options array but we have option_prices keys, build from that
                if (empty($optionsWithPrices) && !empty($optionPrices)) {
                    foreach ($optionPrices as $optName => $price) {
                        if ($optName !== '' && $optName !== null) {
                            $optionsWithPrices[] = ['name' => (string)$optName, 'code' => '', 'price' => (float)$price];
                        }
                    }
                }
                return [
                    'variant_id' => (int) $variant->id,
                    'option_prices' => $optionPrices,
                    'options' => $optionsWithPrices,
                    'is_default' => (bool) ($variant->pivot->is_default ?? false),
                ];
            })->toArray();
        }
    } elseif (! $isEdit && $hasOldVariantsKey) {
        foreach ($oldVariants ?? [] as $row) {
            if (empty($row['variant_id'])) {
                continue;
            }
            $optionsWithPrices = [];
            if (! empty($row['option_prices']) && is_array($row['option_prices'])) {
                foreach ($row['option_prices'] as $op) {
                    if (! is_array($op)) {
                        continue;
                    }
                    $optionsWithPrices[] = [
                        'name' => (string) ($op['name'] ?? ''),
                        'code' => (string) ($op['code'] ?? ''),
                        'price' => isset($op['price']) ? (float) $op['price'] : 0,
                    ];
                }
            }
            $variantsData[] = [
                'variant_id' => (int) $row['variant_id'],
                'option_prices' => [],
                'options' => $optionsWithPrices,
                'is_default' => ! empty($row['is_default']),
            ];
        }
    }
    $title = $isEdit ? 'Edit Menu Item' : 'Create New Menu Item';
    $subtitle = $isEdit ? 'Update menu item information' : 'Add a new menu item to your menu';
    $buttonText = $isEdit ? 'Update Menu Item' : 'Create Menu Item';
    $companyCurrency = get_company_config()['currency'] ?? 'USD';
    $categoryOptions = ($categories ?? collect())->map(fn ($category) => [
        'id' => (int) $category->id,
        'name' => $category->name,
        'code' => $category->code,
        'label' => $category->displayLabel(),
    ])->values();

    $initialTab = 'basic';
    if ($errors->any()) {
        $errorKeys = collect($errors->keys());
        if ($errorKeys->contains(fn ($key) => str_starts_with((string) $key, 'default_recipe') || str_starts_with((string) $key, 'variant_recipes'))) {
            $initialTab = 'recipes';
        } elseif ($errorKeys->contains(fn ($key) => str_starts_with((string) $key, 'variants') || str_starts_with((string) $key, 'product_addons'))) {
            $initialTab = 'variants';
        }
    }
@endphp

<div class="max-w-5xl mx-auto pb-28"
     x-data="menuItemForm({{ json_encode($menuItemData) }}, {{ json_encode($variantRecipesData) }}, {{ json_encode($productAddonsData) }}, {{ json_encode($variantsData) }}, {{ $isEdit ? 'true' : 'false' }}, {{ json_encode(isset($variants) ? $variants->toArray() : []) }}, {{ json_encode($companyCurrency) }}, {{ json_encode($categoryOptions) }}, {{ json_encode($catalogRecipesJson) }}, {{ json_encode($initialTab) }}, {{ json_encode($unitOptions) }})"
     x-init="initForm()">
    <div class="bg-white shadow rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <h1 class="text-xl font-semibold text-gray-900">{{ $title }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ $subtitle }}</p>
        </div>

        <div class="px-6 border-b border-gray-200">
            <nav class="-mb-px flex gap-1 overflow-x-auto" role="tablist" aria-label="Menu item sections">
                <button type="button"
                        role="tab"
                        :aria-selected="activeTab === 'basic'"
                        @click="activeTab = 'basic'"
                        :class="activeTab === 'basic'
                            ? 'border-indigo-600 text-indigo-700'
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap border-b-2 px-4 py-3 text-sm font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 rounded-t-md">
                    <i class="fas fa-info-circle mr-2"></i>Basic
                </button>
                <button type="button"
                        role="tab"
                        :aria-selected="activeTab === 'variants'"
                        @click="activeTab = 'variants'"
                        :class="activeTab === 'variants'
                            ? 'border-indigo-600 text-indigo-700'
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap border-b-2 px-4 py-3 text-sm font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 rounded-t-md">
                    <i class="fas fa-layer-group mr-2"></i>Variants &amp; addons
                </button>
                <button type="button"
                        role="tab"
                        :aria-selected="activeTab === 'recipes'"
                        @click="activeTab = 'recipes'"
                        :class="activeTab === 'recipes'
                            ? 'border-indigo-600 text-indigo-700'
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap border-b-2 px-4 py-3 text-sm font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 rounded-t-md">
                    <i class="fas fa-utensils mr-2"></i>Recipes
                </button>
            </nav>
        </div>

        <form id="menu-item-form" action="{{ $formAction }}" method="POST" class="p-6 relative space-y-6" enctype="multipart/form-data" @submit.prevent="submitForm">
            @csrf
            @if($isEdit)
                @method('PUT')
            @endif

            {{-- Hide inactive tabs without display:none so HTML5 validation still sees required fields --}}
            <style>
                .menu-item-tab-panel.is-inactive {
                    position: absolute;
                    left: -10000px;
                    top: 0;
                    width: 1px;
                    height: 1px;
                    overflow: hidden;
                    clip: rect(0, 0, 0, 0);
                    white-space: nowrap;
                    pointer-events: none;
                }
            </style>

            <!-- Basic Information -->
            <div data-form-tab="basic"
                 class="menu-item-tab-panel space-y-6"
                 :class="activeTab === 'basic' ? '' : 'is-inactive'"
                 :aria-hidden="activeTab !== 'basic'">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Name -->
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                            Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" 
                               name="name" 
                               id="name" 
                               x-model="formData.name"
                               required
                               class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('name') border-red-500 @enderror"
                               placeholder="Burger, Pizza, Coffee...">
                        @error('name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Category -->
                    <div x-data="categorySelect($data)" x-init="init()">
                        <x-searchable-select
                            label="Category"
                            required
                            id="category_id"
                        >
                            <x-slot:hiddenInput>
                                <input type="hidden" name="category_id" x-model="selectedValue" required>
                            </x-slot:hiddenInput>
                        </x-searchable-select>
                        @error('category_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Type -->
                    <div>
                        <label for="type" class="block text-sm font-medium text-gray-700 mb-2">
                            Type <span class="text-red-500">*</span>
                        </label>
                        <select name="type" 
                                id="type" 
                                x-model="formData.type"
                                @change="handleTypeChange()"
                                required
                                class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('type') border-red-500 @enderror">
                            <option value="single">Single</option>
                            <option value="recipe">Recipe</option>
                        </select>
                        <p class="mt-1 text-xs text-gray-500">Select 'Recipe' to define ingredients for this item</p>
                        @error('type')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Cuisine -->
                    <div>
                        <label for="cuisine_id" class="block text-sm font-medium text-gray-700 mb-2">
                            Cuisine
                        </label>
                        <select name="cuisine_id" 
                                id="cuisine_id" 
                                x-model="formData.cuisine_id"
                                class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('cuisine_id') border-red-500 @enderror">
                            <option value="">No cuisine</option>
                            @foreach($cuisines as $cuisine)
                                <option value="{{ $cuisine->id }}" @selected((string) ($isEdit ? $menuItem->cuisine_id : old('cuisine_id')) === (string) $cuisine->id)>
                                    {{ $cuisine->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('cuisine_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Description -->
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                        Short Description
                    </label>
                    <textarea name="description" 
                              id="description" 
                              x-model="formData.description"
                              rows="3"
                              class="block w-full px-4 py-3 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('description') border-red-500 @enderror"
                              placeholder="Brief description of the menu item..."></textarea>
                    @error('description')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Price -->
                    <div>
                        <label for="price" class="block text-sm font-medium text-gray-700 mb-2">
                            Price <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input type="number" 
                                   name="price" 
                                   id="price" 
                                   step="0.01"
                                   min="0"
                                   x-model="formData.price"
                                   required
                                   class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('price') border-red-500 @enderror"
                                   placeholder="0.00">
                        </div>
                        @error('price')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- SKU -->
                    <div>
                        <label for="sku" class="block text-sm font-medium text-gray-700 mb-2">
                            SKU
                        </label>
                        <input type="text" 
                               name="sku" 
                               id="sku" 
                               x-model="formData.sku"
                               class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('sku') border-red-500 @enderror"
                               placeholder="Leave blank to auto-generate (e.g. MI01)">
                        @error('sku')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div x-show="formData.type === 'single'" x-cloak class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="cost" class="block text-sm font-medium text-gray-700 mb-2">
                            Cost price
                        </label>
                        <input type="number"
                               name="cost"
                               id="cost"
                               step="0.01"
                               min="0"
                               x-model="formData.cost"
                               class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('cost') border-red-500 @enderror"
                               placeholder="0.00">
                        <p class="mt-1 text-xs text-gray-500">Starting cost before purchases. Updates to weighted average when you buy stock.</p>
                        @error('cost')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div x-show="formData.track_inventory">
                        <label for="min_stock_level" class="block text-sm font-medium text-gray-700 mb-2">
                            Low stock alert
                        </label>
                        <input type="number"
                               name="min_stock_level"
                               id="min_stock_level"
                               step="0.01"
                               min="0"
                               x-model="formData.min_stock_level"
                               class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('min_stock_level') border-red-500 @enderror"
                               placeholder="e.g. 10">
                        <p class="mt-1 text-xs text-gray-500">Warn when on-hand qty is at or below this level (in sell units).</p>
                        @error('min_stock_level')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div x-show="formData.type === 'single'" x-cloak class="rounded-lg border border-indigo-100 bg-indigo-50/60 px-4 py-3 text-sm text-indigo-900">
                    <p class="font-medium">Purchase &amp; sell units</p>
                    <p class="mt-1 text-indigo-800">How you buy stock vs how you sell and count it. Example: buy cola by <strong>case</strong>, sell by <strong>bottle</strong>, conversion <strong>24</strong>.</p>
                </div>

                <div x-show="formData.type === 'single'" x-cloak class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div x-data="ingredientUnitSelect($data, 'purchase_unit_id')" x-init="init()">
                        <x-searchable-select label="Purchase unit" required id="purchase_unit_id">
                            <x-slot:hiddenInput>
                                <input type="hidden" name="purchase_unit_id" x-model="selectedValue" :required="formData.type === 'single'">
                            </x-slot:hiddenInput>
                        </x-searchable-select>
                        @error('purchase_unit_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div x-data="ingredientUnitSelect($data, 'consumption_unit_id')" x-init="init()">
                        <x-searchable-select label="Sell unit" required id="consumption_unit_id">
                            <x-slot:hiddenInput>
                                <input type="hidden" name="consumption_unit_id" x-model="selectedValue" :required="formData.type === 'single'">
                            </x-slot:hiddenInput>
                        </x-searchable-select>
                        @error('consumption_unit_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="conversion_rate" class="block text-sm font-medium text-gray-700 mb-2">Conversion <span class="text-red-500">*</span></label>
                        <input type="number"
                               name="conversion_rate"
                               id="conversion_rate"
                               x-model="formData.conversion_rate"
                               @input="recalculateSellCost()"
                               step="0.0001"
                               min="0.0001"
                               class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('conversion_rate') border-red-500 @enderror"
                               placeholder="1">
                        <p class="mt-1 text-xs text-gray-500">Sell units in 1 purchase unit</p>
                        @error('conversion_rate')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div x-show="formData.type === 'single'" x-cloak class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="purchase_price" class="block text-sm font-medium text-gray-700 mb-2">Purchase price</label>
                        <input type="number"
                               name="purchase_price"
                               id="purchase_price"
                               x-model="formData.purchase_price"
                               @input="recalculateSellCost()"
                               step="0.01"
                               min="0"
                               class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('purchase_price') border-red-500 @enderror"
                               placeholder="0.00">
                        <p class="mt-1 text-xs text-gray-500">Default price for 1 purchase unit on supplier orders</p>
                        @error('purchase_price')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Cost per sell unit</label>
                        <div class="h-12 px-4 flex items-center rounded-lg border border-gray-200 bg-gray-50 text-sm font-semibold text-gray-900">
                            <span x-text="displaySellUnitCost()"></span>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">Auto: purchase price ÷ conversion (updates cost field)</p>
                    </div>
                </div>

                @if(app(\App\Services\CompanyAddonService::class)->kitchenTrackingEnabled(auth()->user()?->company))
                <div>
                    <label for="preparation_time" class="block text-sm font-medium text-gray-700 mb-2">
                        Preparation time (minutes)
                    </label>
                    <input type="number"
                           name="preparation_time"
                           id="preparation_time"
                           min="1"
                           max="600"
                           value="{{ old('preparation_time', $menuItemData['preparation_time'] ?? '') }}"
                           class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('preparation_time') border-red-500 @enderror"
                           placeholder="e.g. 15">
                    <p class="mt-1 text-xs text-gray-500">Used for expected ready time on POS orders. Leave blank to use the default (10 min).</p>
                    @error('preparation_time')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                @endif

                <!-- Image -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Image</label>
                    <input type="hidden" name="platform_media_path" x-model="platformMediaPath">
                    <input type="hidden" name="clear_image" :value="clearImage ? 1 : 0">

                    <div class="flex flex-wrap items-start gap-4">
                        <template x-if="imagePreviewUrl">
                            <div class="flex-shrink-0 relative">
                                <img :src="imagePreviewUrl" alt="Preview" class="h-24 w-24 rounded-lg object-cover border border-gray-200">
                                <button type="button" @click="clearImageSelection()"
                                        class="absolute -top-2 -right-2 h-6 w-6 rounded-full bg-red-600 text-white text-xs hover:bg-red-700"
                                        title="Remove image">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </template>
                        <div class="flex-1 min-w-[200px] space-y-3">
                            <div class="flex flex-wrap gap-2">
                                <button type="button" @click="openMediaLibrary()"
                                        class="inline-flex items-center px-3 py-2 border border-indigo-300 rounded-lg text-sm font-medium text-indigo-700 bg-indigo-50 hover:bg-indigo-100">
                                    <i class="fas fa-images mr-2"></i>
                                    Choose from library
                                </button>
                            </div>
                            <div>
                                <input type="file"
                                       name="image"
                                       id="image"
                                       accept="image/*"
                                       @change="onFileSelected($event)"
                                       class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                                <p class="mt-1 text-xs text-gray-500">Upload your own (JPG, PNG, GIF, WebP up to 2MB). Images are resized to 800×800 WebP for faster POS loading.</p>
                            </div>
                        </div>
                    </div>
                    @error('image')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    @error('platform_media_path')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror

                    <!-- Platform media picker modal (fixed height — only the grid scrolls) -->
                    <div x-show="libraryOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" @keydown.escape.window="libraryOpen = false">
                        <div class="absolute inset-0 bg-gray-900/50" @click="libraryOpen = false"></div>
                        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-4xl h-[min(85vh,720px)] flex flex-col min-h-0 overflow-hidden">
                            <div class="flex-shrink-0 px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                                <h3 class="text-lg font-semibold text-gray-900">Platform image library</h3>
                                <button type="button" @click="libraryOpen = false" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
                            </div>
                            <div class="flex-shrink-0 px-6 py-3 border-b border-gray-100 space-y-3">
                                <div class="flex gap-2 flex-nowrap items-center overflow-x-auto pb-1 -mx-1 px-1">
                                    <button type="button"
                                            @click="setLibraryCategory(null)"
                                            class="flex-shrink-0 px-3 py-1.5 rounded-full text-xs sm:text-sm font-medium transition-colors"
                                            :class="libraryCategory === null ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'">
                                        All
                                    </button>
                                    <template x-for="cat in libraryCategories" :key="cat.name">
                                        <button type="button"
                                                @click="setLibraryCategory(cat.name)"
                                                class="flex-shrink-0 px-3 py-1.5 rounded-full text-xs sm:text-sm font-medium transition-colors whitespace-nowrap"
                                                :class="libraryCategory === cat.name ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                                                x-text="cat.name + ' (' + cat.count + ')'"></button>
                                    </template>
                                </div>
                                <input type="search" x-model="librarySearch" @input.debounce.300ms="loadMediaLibrary(1)"
                                       placeholder="Search by title..."
                                       class="block w-full h-10 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <div class="flex-1 min-h-0 overflow-y-auto overscroll-contain p-6">
                                <div x-show="libraryLoading" class="flex items-center justify-center h-full min-h-[12rem] text-sm text-gray-500">
                                    <span><i class="fas fa-spinner fa-spin mr-2"></i>Loading...</span>
                                </div>
                                <div x-show="!libraryLoading && libraryItems.length === 0" class="flex items-center justify-center h-full min-h-[12rem] text-sm text-gray-500">
                                    No images in this category yet.
                                </div>
                                <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 gap-3" x-show="!libraryLoading && libraryItems.length > 0">
                                    <template x-for="item in libraryItems" :key="item.id">
                                        <button type="button" @click="selectLibraryItem(item)"
                                                class="border rounded-lg overflow-hidden hover:ring-2 hover:ring-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 text-left"
                                                :class="platformMediaPath === item.file_path ? 'ring-2 ring-indigo-600' : 'border-gray-200'">
                                            <div class="aspect-square bg-gray-100">
                                                <img :src="item.url" :alt="item.title" class="w-full h-full object-cover" loading="lazy">
                                            </div>
                                            <p class="p-1.5 text-xs font-medium text-gray-800 truncate" x-text="item.title"></p>
                                        </button>
                                    </template>
                                </div>
                            </div>
                            <div class="flex-shrink-0 h-14 px-6 border-t border-gray-200 flex items-center justify-between">
                                <button type="button" @click="loadMediaLibrary(libraryPage - 1)"
                                        :disabled="libraryPage <= 1 || libraryLastPage <= 1"
                                        class="px-3 py-1.5 text-sm border rounded-lg disabled:opacity-40 disabled:cursor-not-allowed">Previous</button>
                                <span class="text-sm text-gray-500" x-text="libraryLastPage > 1 ? ('Page ' + libraryPage + ' of ' + libraryLastPage) : '\u00a0'"></span>
                                <button type="button" @click="loadMediaLibrary(libraryPage + 1)"
                                        :disabled="libraryPage >= libraryLastPage || libraryLastPage <= 1"
                                        class="px-3 py-1.5 text-sm border rounded-lg disabled:opacity-40 disabled:cursor-not-allowed">Next</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Checkboxes -->
                <div class="space-y-3">
                    <label class="flex items-center">
                        <input type="checkbox" 
                               name="is_available" 
                               value="1"
                               x-model="formData.is_available"
                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <span class="ml-2 text-sm text-gray-600">Available (Item will be shown in menu)</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" 
                               name="track_inventory" 
                               value="1"
                               x-model="formData.track_inventory"
                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <span class="ml-2 text-sm text-gray-600" x-text="formData.type === 'recipe'
                            ? 'Track Inventory (Deduct recipe ingredients when sold; turn off to sell without stock checks)'
                            : 'Track Inventory (Deduct finished-goods stock when sold — for purchased items like drinks)'"></span>
                    </label>
                </div>
            </div>

            <!-- Variants & addons -->
            <div data-form-tab="variants"
                 class="menu-item-tab-panel space-y-8"
                 :class="activeTab === 'variants' ? '' : 'is-inactive'"
                 :aria-hidden="activeTab !== 'variants'">
                <div class="space-y-6">
                    <div class="flex items-center justify-between border-b border-gray-200 pb-2">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">Variants</h2>
                            <p class="mt-1 text-sm text-gray-500">Optional sizes or options with prices for this menu item.</p>
                        </div>
                        <a href="{{ route('variants.create') }}" target="_blank" class="text-sm text-indigo-600 hover:text-indigo-900 font-medium">
                            <i class="fas fa-plus-circle mr-1"></i>Create New Variant
                        </a>
                    </div>
                
                <div class="space-y-4">
                    <template x-for="(variant, index) in selectedVariants" :key="'sv-' + index + '-' + String(variant.variant_id || '')">
                        <div class="p-4 border border-gray-200 rounded-lg bg-gray-50 space-y-4">
                            <div x-data="menuItemVariantSelect($data, variant, index)" x-init="init()">
                                <x-searchable-select
                                    label="Variant"
                                    required
                                    x-bind:id="'variant_id_' + index"
                                >
                                    <x-slot:hiddenInput>
                                        <input type="hidden"
                                               :name="'variants[' + index + '][variant_id]'"
                                               x-model="selectedValue"
                                               required>
                                    </x-slot:hiddenInput>
                                </x-searchable-select>
                            </div>

                            <div x-show="variant.variant_id && variant.options && variant.options.length > 0" class="space-y-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Set Prices for Options <span class="text-red-500">*</span>
                                    </label>
                                    <p class="text-xs text-gray-500">Prefilled from variant default prices. Override here for this menu item only.</p>
                                </div>
                                <template x-for="(option, optIndex) in variant.options" :key="optIndex">
                                    <div class="flex items-center gap-4 p-3 bg-white rounded-lg border border-gray-200">
                                        <div class="flex-1">
                                            <span class="text-sm font-medium text-gray-900" x-text="option.name"></span>
                                            <template x-if="option.code">
                                                <span class="ml-2 text-xs text-gray-500" x-text="'(' + option.code + ')'"></span>
                                            </template>
                                        </div>
                                        <div class="w-40">
                                            <input type="hidden" 
                                                   :name="'variants[' + index + '][option_prices][' + optIndex + '][name]'"
                                                   :value="option.name">
                                            <input type="number" 
                                                   :name="'variants[' + index + '][option_prices][' + optIndex + '][price]'"
                                                   x-model.number="option.price"
                                                   step="0.01"
                                                   min="0"
                                                   required
                                                   class="block w-full h-10 px-3 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                   placeholder="0.00">
                                        </div>
                                    </div>
                                </template>
                            </div>

                            <div x-show="variant.variant_id && (!variant.options || variant.options.length === 0)"
                                 class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                                This variant has no options defined. Open
                                <a href="{{ route('variants.index') }}" target="_blank" class="font-medium underline">Variants</a>
                                and add options (e.g. Small, Large, Family).
                            </div>
                            
                            <div class="flex items-center justify-between pt-2 border-t border-gray-200">
                                <label class="flex items-center">
                                    <input type="hidden" :name="'variants[' + index + '][is_default]'" value="0">
                                    <input type="checkbox" 
                                           :name="'variants[' + index + '][is_default]'"
                                           value="1"
                                           x-model="variant.is_default"
                                           @change="setDefaultVariant(index)"
                                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <span class="ml-2 text-sm text-gray-600">Set as default variant</span>
                                </label>
                                <button type="button" 
                                        @click="removeVariant(index)"
                                        class="px-4 py-2 text-sm font-medium text-red-700 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100">
                                    <i class="fas fa-trash mr-1"></i>Remove
                                </button>
                            </div>
                        </div>
                    </template>
                    
                    <button type="button" 
                            @click="addVariant()"
                            class="w-full py-3 px-4 text-sm font-medium text-indigo-700 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100">
                        <i class="fas fa-plus mr-2"></i>Add Variant
                    </button>
                </div>
                </div>

                <!-- Product Addons -->
                <div class="space-y-6">
                    <div class="border-b border-gray-200 pb-2">
                        <h2 class="text-lg font-semibold text-gray-900">Product Addons</h2>
                        <p class="mt-1 text-sm text-gray-500">Extras customers can add when ordering this item.</p>
                    </div>
                
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach($productAddons as $addon)
                            <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer">
                                <input type="checkbox" 
                                       name="product_addons[]" 
                                       value="{{ $addon->id }}"
                                       :checked="formData.product_addons.includes({{ $addon->id }})"
                                       @change="toggleAddon({{ $addon->id }})"
                                       class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <div class="ml-3 flex-1">
                                    <span class="text-sm font-medium text-gray-900">{{ $addon->name }}</span>
                                    <span class="ml-2 text-sm text-gray-500">{{ format_currency($addon->price) }}</span>
                                </div>
                            </label>
                        @endforeach
                    </div>
                    @if(count($productAddons) === 0)
                        <p class="text-sm text-gray-500">No product addons available. <a href="{{ route('product-addons.create') }}" class="text-indigo-600 hover:text-indigo-900">Create one</a></p>
                    @endif
                </div>
            </div>

            <!-- Recipe links (catalog) -->
            <div data-form-tab="recipes"
                 class="menu-item-tab-panel space-y-6"
                 :class="activeTab === 'recipes' ? '' : 'is-inactive'"
                 :aria-hidden="activeTab !== 'recipes'">
                <div x-show="formData.type !== 'recipe'" class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                    Set <span class="font-medium">Type</span> to <span class="font-medium">Recipe</span> on the Basic tab to attach recipes and track ingredients.
                </div>

                <div x-show="formData.type === 'recipe'" class="space-y-6">
                <div class="border-b border-gray-200 pb-2 flex items-center justify-between gap-3 flex-wrap">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Recipes</h2>
                        <p class="mt-1 text-sm text-gray-500" x-show="!hasVariantOptions">
                            Attach one recipe for this menu item. Create recipes under Menu → Recipes first.
                        </p>
                        <p class="mt-1 text-sm text-gray-500" x-show="hasVariantOptions" x-cloak>
                            Choose a recipe for each variant option. Create recipes under Menu → Recipes first.
                        </p>
                    </div>
                    <a href="{{ route('recipes.create') }}" target="_blank" class="text-sm text-indigo-600 hover:text-indigo-900 font-medium">
                        <i class="fas fa-plus-circle mr-1"></i>Create recipe
                    </a>
                </div>

                <div x-show="!hasVariantOptions">
                    <div x-data="menuItemDefaultRecipeSelect($data)" x-init="init()">
                        <x-searchable-select
                            label="Recipe"
                            required
                            id="default_recipe_id"
                        >
                            <x-slot:hiddenInput>
                                <input type="hidden"
                                       name="default_recipe_id"
                                       x-model="selectedValue"
                                       :disabled="hasVariantOptions"
                                       :required="!hasVariantOptions">
                            </x-slot:hiddenInput>
                        </x-searchable-select>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">Used for this menu item when it has no variants.</p>
                    @error('default_recipe_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div x-show="hasVariantOptions" class="space-y-3">
                    <h3 class="text-sm font-semibold text-gray-800">Recipe per variant option</h3>
                    @error('variant_recipes')
                        <p class="text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <template x-for="(row, index) in variantRecipeRows" :key="'vr-' + row.variant_id + '-' + row.option_name">
                        <div class="flex flex-col sm:flex-row sm:items-center gap-3 p-3 border border-gray-200 rounded-lg bg-gray-50">
                            <input type="hidden" :name="'variant_recipes[' + index + '][variant_id]'" :value="row.variant_id">
                            <input type="hidden" :name="'variant_recipes[' + index + '][option_name]'" :value="row.option_name">
                            <div class="sm:w-1/3 text-sm font-medium text-gray-900" x-text="row.label"></div>
                            <div class="flex-1" x-data="menuItemRecipeSelect($data, row, 'recipe_id')" x-init="init()">
                                <x-searchable-select
                                    compact
                                    x-bind:id="'variant_recipe_' + index"
                                >
                                    <x-slot:hiddenInput>
                                        <input type="hidden"
                                               :name="'variant_recipes[' + index + '][recipe_id]'"
                                               x-model="selectedValue"
                                               required>
                                    </x-slot:hiddenInput>
                                </x-searchable-select>
                            </div>
                        </div>
                    </template>
                    <p x-show="variantRecipeRows.length === 0" class="text-sm text-amber-600">
                        Add a variant with options (e.g. Small, Large) on Variants &amp; addons to assign recipes.
                    </p>
                </div>
                </div>
            </div>

        </form>
    </div>

    {{-- Fixed footer: always visible Save (clears desktop sidebar) --}}
    <div class="fixed bottom-0 left-0 right-0 lg:left-64 z-40 border-t border-gray-200 bg-white/95 backdrop-blur supports-[backdrop-filter]:bg-white/80 shadow-[0_-4px_12px_rgba(0,0,0,0.06)]">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="flex items-center gap-2 min-h-[1.25rem]">
                <span x-show="isDirty" x-cloak class="inline-flex items-center text-sm text-amber-700">
                    <span class="w-2 h-2 rounded-full bg-amber-500 mr-2"></span>
                    Unsaved changes
                </span>
            </div>
            <div class="flex items-center justify-end gap-3">
                <button type="button"
                        @click="requestLeave('{{ route('menu-items.index') }}')"
                        class="px-4 py-2 h-12 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Cancel
                </button>
                <button type="button"
                        @click="saveForm()"
                        class="px-4 py-2 h-12 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <i class="fas fa-save mr-2"></i>
                    {{ $buttonText }}
                </button>
            </div>
        </div>
    </div>

    {{-- Leave confirmation when there are unsaved changes --}}
    <div x-show="leaveDialogOpen"
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         role="dialog"
         aria-modal="true"
         aria-labelledby="unsaved-title"
         @keydown.escape.window="stayOnPage()">
        <div class="absolute inset-0 bg-gray-900/50" @click="stayOnPage()"></div>
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md p-6 space-y-4">
            <h3 id="unsaved-title" class="text-lg font-semibold text-gray-900">You have unsaved changes</h3>
            <p class="text-sm text-gray-600">
                You changed something on this menu item. Do you want to save before leaving, or continue without saving?
            </p>
            <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-2">
                <button type="button"
                        @click="stayOnPage()"
                        class="px-4 py-2 h-11 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    Keep editing
                </button>
                <button type="button"
                        @click="leaveWithoutSaving()"
                        class="px-4 py-2 h-11 border border-amber-300 rounded-lg text-sm font-medium text-amber-900 bg-amber-50 hover:bg-amber-100">
                    Continue without saving
                </button>
                <button type="button"
                        @click="saveAndLeave()"
                        class="px-4 py-2 h-11 border border-transparent rounded-lg text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                    Save
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function menuItemForm(menuItemData = null, variantRecipesData = [], productAddonsData = [], variantsData = [], isEdit = false, availableVariants = [], currency = 'USD', categoryOptions = [], catalogRecipes = [], initialTab = 'basic', unitOptions = []) {
    return {
        activeTab: ['basic', 'variants', 'recipes'].includes(initialTab) ? initialTab : 'basic',
        formData: {
            name: menuItemData?.name || '',
            category_id: menuItemData?.category_id ? String(menuItemData.category_id) : '',
            cuisine_id: menuItemData?.cuisine_id || '',
            type: menuItemData?.type || 'single',
            description: menuItemData?.description || '',
            price: menuItemData?.price ?? 0,
            cost: menuItemData?.cost ?? 0,
            min_stock_level: menuItemData?.min_stock_level ?? 0,
            purchase_unit_id: menuItemData?.purchase_unit_id ? String(menuItemData.purchase_unit_id) : '',
            consumption_unit_id: menuItemData?.consumption_unit_id ? String(menuItemData.consumption_unit_id) : '',
            conversion_rate: menuItemData?.conversion_rate ?? 1,
            purchase_price: menuItemData?.purchase_price ?? 0,
            sku: menuItemData?.sku || '',
            is_available: menuItemData?.is_available ?? true,
            track_inventory: menuItemData?.track_inventory ?? true,
            product_addons: productAddonsData || [],
            default_recipe_id: menuItemData?.default_recipe_id ? String(menuItemData.default_recipe_id) : '',
        },
        unitOptions: Array.isArray(unitOptions) ? unitOptions : [],
        selectedVariants: variantsData.length > 0 ? variantsData.map(v => {
            const variantIdRaw = v.variant_id != null ? v.variant_id : '';
            const variantId = variantIdRaw === '' ? '' : String(variantIdRaw);
            if (Array.isArray(v.options) && v.options.length > 0) {
                return {
                    variant_id: variantId,
                    options: v.options.map(opt => ({
                        name: opt.name || '',
                        code: opt.code || '',
                        price: Number(opt.price) || 0,
                    })),
                    is_default: !!v.is_default,
                };
            }
            const variant = availableVariants.find(av => String(av.id) === variantId || av.id == variantIdRaw);
            const optionPrices = v.option_prices || {};
            const basePrice = Number(menuItemData?.price) || 0;
            return {
                variant_id: variantId,
                options: variant && variant.options ? variant.options.map(opt => ({
                    name: opt.name,
                    code: opt.code || '',
                    price: Object.prototype.hasOwnProperty.call(optionPrices, opt.name)
                        ? Number(optionPrices[opt.name]) || 0
                        : (opt.price != null && opt.price !== '' ? Number(opt.price) : basePrice),
                })) : [],
                is_default: !!v.is_default,
            };
        }) : [],
        availableVariants: availableVariants || [],
        catalogRecipes: Array.isArray(catalogRecipes) ? catalogRecipes : [],
        savedVariantRecipes: Array.isArray(variantRecipesData) ? variantRecipesData.map(r => ({
            variant_id: r.variant_id ? String(r.variant_id) : '',
            option_name: r.option_name || '',
            recipe_id: r.recipe_id ? String(r.recipe_id) : '',
        })) : [],
        variantRecipeRows: [],
        currency: currency || 'USD',
        categoryOptions: Array.isArray(categoryOptions) ? categoryOptions : [],
        platformMediaPath: menuItemData?.platform_media_path || '',
        imagePreviewUrl: menuItemData?.image_preview_url || '',
        clearImage: false,
        libraryOpen: false,
        libraryItems: [],
        libraryLoading: false,
        librarySearch: '',
        libraryCategory: null,
        libraryCategories: [],
        libraryPage: 1,
        libraryLastPage: 1,
        newImageSelected: false,
        isDirty: false,
        leaveDialogOpen: false,
        pendingLeaveUrl: null,
        allowNavigation: false,
        _snapshot: '',
        _boundBeforeUnload: null,
        _boundDocClick: null,
        indexUrl: @json(route('menu-items.index')),

        recalculateSellCost() {
            const price = parseFloat(this.formData.purchase_price) || 0;
            const rate = parseFloat(this.formData.conversion_rate) || 0;
            if (rate > 0) {
                this.formData.cost = Number((price / rate).toFixed(4));
            }
        },

        displaySellUnitCost() {
            const price = parseFloat(this.formData.purchase_price) || 0;
            const rate = parseFloat(this.formData.conversion_rate) || 0;
            if (rate <= 0) {
                return '0.0000';
            }

            return (price / rate).toFixed(4);
        },

        initForm() {
            this.selectedVariants.forEach((_, index) => {
                if (this.selectedVariants[index]?.variant_id) {
                    this.loadVariantOptions(index);
                }
            });
            this.rebuildVariantRecipeRows();
            this.$watch('selectedVariants', () => this.onVariantsChanged(), { deep: true });

            this.$nextTick(() => {
                this.markClean();
                this.$watch('formData', () => this.checkDirty(), { deep: true });
                this.$watch('selectedVariants', () => this.checkDirty(), { deep: true });
                this.$watch('variantRecipeRows', () => this.checkDirty(), { deep: true });
                this.$watch('platformMediaPath', () => this.checkDirty());
                this.$watch('clearImage', () => this.checkDirty());
                this.$watch('imagePreviewUrl', () => this.checkDirty());
                this.$watch('newImageSelected', () => this.checkDirty());
            });

            this._boundBeforeUnload = (event) => {
                if (!this.isDirty || this.allowNavigation) {
                    return;
                }
                event.preventDefault();
                event.returnValue = '';
            };
            window.addEventListener('beforeunload', this._boundBeforeUnload);

            this._boundDocClick = (event) => this.onDocumentClick(event);
            document.addEventListener('click', this._boundDocClick, true);
        },

        destroy() {
            this.teardownGuards();
        },

        teardownGuards() {
            if (this._boundBeforeUnload) {
                window.removeEventListener('beforeunload', this._boundBeforeUnload);
                this._boundBeforeUnload = null;
            }
            if (this._boundDocClick) {
                document.removeEventListener('click', this._boundDocClick, true);
                this._boundDocClick = null;
            }
        },

        getSnapshot() {
            const addons = Array.isArray(this.formData.product_addons)
                ? [...this.formData.product_addons].map(String).sort()
                : [];

            return JSON.stringify({
                formData: {
                    name: this.formData.name || '',
                    category_id: String(this.formData.category_id || ''),
                    cuisine_id: String(this.formData.cuisine_id || ''),
                    type: this.formData.type || 'single',
                    description: this.formData.description || '',
                    price: Number(this.formData.price) || 0,
                    cost: Number(this.formData.cost) || 0,
                    min_stock_level: Number(this.formData.min_stock_level) || 0,
                    purchase_unit_id: String(this.formData.purchase_unit_id || ''),
                    consumption_unit_id: String(this.formData.consumption_unit_id || ''),
                    conversion_rate: Number(this.formData.conversion_rate) || 1,
                    purchase_price: Number(this.formData.purchase_price) || 0,
                    sku: this.formData.sku || '',
                    is_available: !!this.formData.is_available,
                    track_inventory: !!this.formData.track_inventory,
                    product_addons: addons,
                    default_recipe_id: String(this.formData.default_recipe_id || ''),
                },
                selectedVariants: (this.selectedVariants || []).map((variant) => ({
                    variant_id: String(variant.variant_id || ''),
                    is_default: !!variant.is_default,
                    options: (variant.options || []).map((opt) => ({
                        name: opt.name || '',
                        code: opt.code || '',
                        price: Number(opt.price) || 0,
                    })),
                })),
                variantRecipeRows: (this.variantRecipeRows || []).map((row) => ({
                    variant_id: String(row.variant_id || ''),
                    option_name: row.option_name || '',
                    recipe_id: String(row.recipe_id || ''),
                })),
                platformMediaPath: this.platformMediaPath || '',
                clearImage: !!this.clearImage,
                imagePreviewUrl: this.imagePreviewUrl || '',
                newImageSelected: !!this.newImageSelected,
            });
        },

        markClean() {
            this._snapshot = this.getSnapshot();
            this.isDirty = false;
        },

        checkDirty() {
            if (this.allowNavigation) {
                this.isDirty = false;
                return;
            }
            this.isDirty = this.getSnapshot() !== this._snapshot;
        },

        saveForm() {
            const form = document.getElementById('menu-item-form');
            if (!form) {
                return;
            }

            if (!form.checkValidity()) {
                this.focusFirstInvalidField(form);
                return;
            }

            this.allowNavigation = true;
            this.isDirty = false;
            this.teardownGuards();
            form.submit();
        },

        focusFirstInvalidField(form) {
            const invalid = form.querySelector(':invalid');
            if (!invalid) {
                return;
            }

            const panel = invalid.closest('[data-form-tab]');
            if (panel?.dataset?.formTab) {
                this.activeTab = panel.dataset.formTab;
            }

            this.$nextTick(() => {
                try {
                    invalid.focus({ preventScroll: false });
                } catch (e) {
                    invalid.focus();
                }
                if (typeof invalid.reportValidity === 'function') {
                    invalid.reportValidity();
                }
            });
        },

        requestLeave(url) {
            if (!this.isDirty || this.allowNavigation) {
                this.allowNavigation = true;
                window.location.href = url;
                return;
            }
            this.pendingLeaveUrl = url;
            this.leaveDialogOpen = true;
        },

        stayOnPage() {
            this.leaveDialogOpen = false;
            this.pendingLeaveUrl = null;
        },

        leaveWithoutSaving() {
            this.allowNavigation = true;
            this.isDirty = false;
            this.teardownGuards();
            window.location.href = this.pendingLeaveUrl || this.indexUrl;
        },

        saveAndLeave() {
            this.leaveDialogOpen = false;
            this.pendingLeaveUrl = null;
            this.saveForm();
        },

        onDocumentClick(event) {
            if (!this.isDirty || this.allowNavigation || this.leaveDialogOpen) {
                return;
            }

            const anchor = event.target.closest?.('a[href]');
            if (!anchor) {
                return;
            }

            const href = anchor.getAttribute('href');
            if (!href || href.startsWith('#') || href.toLowerCase().startsWith('javascript:')) {
                return;
            }

            if (anchor.target === '_blank' || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                return;
            }

            let url;
            try {
                url = new URL(href, window.location.href);
            } catch (e) {
                return;
            }

            if (url.origin !== window.location.origin) {
                return;
            }

            if (url.href === window.location.href) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            this.requestLeave(url.href);
        },

        get hasVariantOptions() {
            return this.variantRecipeRows.length > 0;
        },

        onVariantsChanged() {
            this.rebuildVariantRecipeRows();
        },

        rebuildVariantRecipeRows() {
            const prev = {};
            for (const row of this.variantRecipeRows) {
                prev[`${row.variant_id}:${row.option_name}`] = row.recipe_id || '';
            }
            for (const row of this.savedVariantRecipes) {
                const key = `${row.variant_id}:${row.option_name}`;
                if (!prev[key] && row.recipe_id) {
                    prev[key] = row.recipe_id;
                }
            }

            const rows = [];
            for (const sv of this.selectedVariants) {
                if (!sv.variant_id || !Array.isArray(sv.options)) continue;
                const vdef = this.availableVariants.find(v => String(v.id) === String(sv.variant_id));
                const vname = vdef?.name || 'Variant';
                for (const opt of sv.options) {
                    if (!opt?.name) continue;
                    const key = `${sv.variant_id}:${opt.name}`;
                    rows.push({
                        variant_id: String(sv.variant_id),
                        option_name: opt.name,
                        label: `${vname}: ${opt.name}`,
                        recipe_id: prev[key] || '',
                    });
                }
            }
            this.variantRecipeRows = rows;
        },

        openMediaLibrary() {
            this.libraryOpen = true;
            this.libraryCategory = null;
            this.loadMediaLibrary(1);
        },

        setLibraryCategory(category) {
            this.libraryCategory = category;
            this.loadMediaLibrary(1);
        },

        async loadMediaLibrary(page = 1) {
            this.libraryLoading = true;
            this.libraryPage = page;
            try {
                const params = new URLSearchParams({ page: String(page) });
                if (this.librarySearch.trim()) {
                    params.set('q', this.librarySearch.trim());
                }
                if (this.libraryCategory) {
                    params.set('category', this.libraryCategory);
                }
                const response = await fetch(`{{ route('platform-media.browse') }}?${params.toString()}`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const json = await response.json();
                this.libraryItems = json.data || [];
                this.libraryCategories = json.categories || [];
                this.libraryLastPage = json.meta?.last_page || 1;
            } catch (e) {
                this.libraryItems = [];
            } finally {
                this.libraryLoading = false;
            }
        },

        selectLibraryItem(item) {
            this.platformMediaPath = item.file_path;
            this.imagePreviewUrl = item.url;
            this.clearImage = false;
            this.newImageSelected = false;
            const fileInput = document.getElementById('image');
            if (fileInput) {
                fileInput.value = '';
            }
            this.libraryOpen = false;
        },

        onFileSelected(event) {
            const file = event.target.files?.[0];
            if (!file) {
                return;
            }
            this.platformMediaPath = '';
            this.clearImage = false;
            this.newImageSelected = true;
            this.imagePreviewUrl = URL.createObjectURL(file);
        },

        clearImageSelection() {
            this.platformMediaPath = '';
            this.imagePreviewUrl = '';
            this.clearImage = true;
            this.newImageSelected = false;
            const fileInput = document.getElementById('image');
            if (fileInput) {
                fileInput.value = '';
            }
        },

        handleTypeChange() {},

        toggleAddon(addonId) {
            const index = this.formData.product_addons.indexOf(addonId);
            if (index > -1) {
                this.formData.product_addons.splice(index, 1);
            } else {
                this.formData.product_addons.push(addonId);
            }
        },

        addVariant() {
            this.selectedVariants.push({
                variant_id: '',
                options: [],
                is_default: false,
            });
            this.onVariantsChanged();
        },

        removeVariant(index) {
            this.selectedVariants.splice(index, 1);
            this.onVariantsChanged();
        },

        setDefaultVariant(index) {
            this.selectedVariants.forEach((variant, i) => {
                if (i !== index) {
                    variant.is_default = false;
                }
            });
        },

        loadVariantOptions(index, event) {
            const variant = this.selectedVariants[index];
            const variantId = event?.target?.value != null
                ? String(event.target.value)
                : String(variant.variant_id || '');
            variant.variant_id = variantId;
            const userChangedVariant = event?.target?.value != null;

            if (variantId) {
                const selectedVariant = this.availableVariants.find(v => String(v.id) === variantId);
                if (selectedVariant?.options?.length) {
                    const existingPrices = {};
                    if (!userChangedVariant) {
                        (variant.options || []).forEach(opt => {
                            if (opt?.name) {
                                existingPrices[opt.name] = Number(opt.price) || 0;
                            }
                        });
                    }
                    variant.options = selectedVariant.options.map(opt => ({
                        name: opt.name || '',
                        code: opt.code || '',
                        price: this.resolveOptionPrefillPrice(opt, existingPrices),
                    }));
                } else {
                    variant.options = [];
                }
            } else {
                variant.options = [];
            }
            this.onVariantsChanged();
        },

        resolveOptionPrefillPrice(opt, existingPrices = {}) {
            const name = opt?.name || '';
            if (name && Object.prototype.hasOwnProperty.call(existingPrices, name)) {
                return Number(existingPrices[name]) || 0;
            }
            if (opt?.price != null && opt?.price !== '') {
                return Number(opt.price) || 0;
            }
            return Number(this.formData.price) || 0;
        },

        submitForm(event) {
            this.allowNavigation = true;
            this.isDirty = false;
            this.teardownGuards();
            event.target.submit();
        }
    }
}
</script>

