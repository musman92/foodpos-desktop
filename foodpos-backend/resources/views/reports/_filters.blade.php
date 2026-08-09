{{-- Branch + date range filters for report pages --}}
@php
    $showBranch = show_branch_ui() && isset($availableBranches) && $availableBranches->isNotEmpty();
    $showDates = isset($from) && isset($to);
    $showItemSearch = $showItemSearch ?? false;
    $showMenuFilters = $showMenuFilters ?? false;
    $formUrl = $formUrl ?? url()->current();
    $categoryId = $categoryId ?? null;
    $menuItemId = $menuItemId ?? null;
@endphp
<form method="get"
      action="{{ $formUrl }}"
      class="bg-white rounded-lg shadow border border-gray-200 p-4 mb-6"
      @if($showMenuFilters)
          x-data="consumptionMenuFilters({
              optionsUrl: @js(route('reports.consumption.filter-options')),
              categoryId: @js($categoryId ? (string) $categoryId : ''),
              menuItemId: @js($menuItemId ? (string) $menuItemId : ''),
          })"
          x-init="loadOptions()"
      @endif>
    <div class="flex flex-wrap items-end gap-4">
        @if($showBranch)
            <div>
                <label for="branch_id" class="block text-sm font-medium text-gray-700 mb-1">Branch</label>
                <select name="branch_id" id="branch_id" class="block w-full filter-control md:w-48">
                    @if(show_branch_ui() && $availableBranches->count() > 1)
                        <option value="">All branches</option>
                    @endif
                    @foreach($availableBranches as $b)
                        <option value="{{ $b->id }}" {{ (request('branch_id', optional($selectedBranch)->id ?? null) == $b->id) ? 'selected' : '' }}>{{ $b->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        @if($showDates)
            <div>
                <label for="from" class="block text-sm font-medium text-gray-700 mb-1">From</label>
                <input type="date" name="from" id="from" value="{{ $from }}" class="block w-full filter-control md:w-40">
            </div>
            <div>
                <label for="to" class="block text-sm font-medium text-gray-700 mb-1">To</label>
                <input type="date" name="to" id="to" value="{{ $to }}" class="block w-full filter-control md:w-40">
            </div>
        @endif
        @if($showMenuFilters)
            <div class="min-w-[12rem] w-48"
                 x-data="((parent) => searchableSelect({
                     getOptions: () => parent.categoryOptions || [],
                     value: parent.categoryId || '',
                     maxResults: 200,
                     placeholder: 'All categories',
                     emptyMessage: 'No categories found',
                     onChange: (value) => parent.onCategoryChange(value),
                     onInit: (component) => {
                         component.$watch(() => parent.categoryOptions, () => component.syncLabelFromValue());
                         component.$watch(() => parent.categoryId, (value) => {
                             component.selectedValue = value ? String(value) : '';
                             component.syncLabelFromValue();
                         });
                     },
                 }))($data)"
                 x-init="init()">
                <x-searchable-select
                    label="Category"
                    compact
                    useButtonOptions
                    id="consumption_category"
                >
                    <x-slot:hiddenInput>
                        <input type="hidden" name="category_id" x-model="selectedValue">
                    </x-slot:hiddenInput>
                </x-searchable-select>
            </div>
            <div class="min-w-[14rem] w-56"
                 x-data="((parent) => searchableSelect({
                     getOptions: () => parent.filteredMenuItems || [],
                     value: parent.menuItemId || '',
                     maxResults: 200,
                     placeholder: 'All menu items',
                     emptyMessage: 'No menu items found',
                     onChange: (value) => { parent.menuItemId = value ? String(value) : ''; },
                     onInit: (component) => {
                         component.$watch(() => parent.filteredMenuItems, () => component.syncLabelFromValue());
                         component.$watch(() => parent.menuItemId, (value) => {
                             component.selectedValue = value ? String(value) : '';
                             component.syncLabelFromValue();
                         });
                     },
                 }))($data)"
                 x-init="init()">
                <x-searchable-select
                    label="Menu item"
                    compact
                    useButtonOptions
                    id="consumption_menu_item"
                >
                    <x-slot:hiddenInput>
                        <input type="hidden" name="menu_item_id" x-model="selectedValue">
                    </x-slot:hiddenInput>
                </x-searchable-select>
            </div>
        @endif
        @if($showItemSearch)
            <div class="flex-1 min-w-[16rem] max-w-2xl">
                <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                <input type="search"
                       name="search"
                       id="search"
                       value="{{ $search ?? '' }}"
                       placeholder="Name or several names separated by commas…"
                       class="block w-full filter-control">
            </div>
        @endif
        <div>
            <button type="submit" class="inline-flex items-center justify-center h-11 px-4 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                <i class="fas fa-sync-alt mr-2"></i>Apply
            </button>
        </div>
    </div>
    @if($showMenuFilters)
        <p class="mt-2 text-xs text-gray-500" x-show="loadingOptions" x-cloak>Loading categories and menu items…</p>
        <p class="mt-2 text-xs text-red-600" x-show="optionsError" x-text="optionsError" x-cloak></p>
    @endif
</form>

@if($showMenuFilters)
@once
<script>
window.consumptionMenuFilters = function (config) {
    return {
        optionsUrl: config.optionsUrl,
        categoryId: config.categoryId ? String(config.categoryId) : '',
        menuItemId: config.menuItemId ? String(config.menuItemId) : '',
        categoryOptions: [],
        menuItemOptions: [],
        loadingOptions: false,
        optionsError: '',

        get filteredMenuItems() {
            const categoryId = this.categoryId ? String(this.categoryId) : '';
            const list = Array.isArray(this.menuItemOptions) ? this.menuItemOptions : [];

            if (!categoryId) {
                return list;
            }

            return list.filter((item) => String(item.category_id || '') === categoryId);
        },

        async loadOptions() {
            this.loadingOptions = true;
            this.optionsError = '';

            try {
                const response = await fetch(this.optionsUrl, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    throw new Error('Failed to load filter options');
                }

                const data = await response.json();
                this.categoryOptions = Array.isArray(data.categories) ? data.categories : [];
                this.menuItemOptions = Array.isArray(data.menu_items) ? data.menu_items : [];

                if (this.menuItemId && this.categoryId) {
                    const selected = this.menuItemOptions.find((item) => String(item.id) === String(this.menuItemId));
                    if (selected && String(selected.category_id || '') !== String(this.categoryId)) {
                        this.menuItemId = '';
                    }
                }
            } catch (error) {
                this.optionsError = error?.message || 'Could not load categories and menu items.';
            } finally {
                this.loadingOptions = false;
            }
        },

        onCategoryChange(value) {
            this.categoryId = value ? String(value) : '';

            if (!this.menuItemId) {
                return;
            }

            const selected = this.menuItemOptions.find((item) => String(item.id) === String(this.menuItemId));
            if (!selected) {
                return;
            }

            if (this.categoryId && String(selected.category_id || '') !== this.categoryId) {
                this.menuItemId = '';
            }
        },
    };
};
</script>
@endonce
@endif
