@extends('layouts.app')

@section('title', $title)

@section('content')
@php
    $categoryOptions = $categories->map(fn ($category) => [
        'id' => (int) $category->id,
        'name' => $category->displayLabel(),
    ])->values();

    $menuItemOptions = $menuItems->map(fn ($item) => [
        'id' => (int) $item->id,
        'name' => $item->name.($item->category ? ' · '.$item->category->name : ''),
        'category_id' => $item->category_id ? (int) $item->category_id : null,
    ])->values();
@endphp
<div class="max-w-7xl mx-auto space-y-6"
     x-data="salesByItemPage(@js([
         'categories' => $categoryOptions,
         'items' => $menuItemOptions,
         'categoryIds' => $categoryIds,
         'menuItemIds' => $menuItemIds,
         'generateUrl' => $generateUrl,
     ]))">
    <div>
        <a href="{{ route('reports.index') }}" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium mb-2 inline-block">
            <i class="fas fa-arrow-left mr-1"></i> Back to Reports
        </a>
        <h1 class="text-2xl font-bold text-gray-900">{{ $title }}</h1>
        <p class="mt-1 text-sm text-gray-500">{{ $description }}</p>
    </div>

    <div x-show="errorMessage" x-cloak class="p-4 rounded-lg border border-red-200 bg-red-50 text-sm text-red-700" x-text="errorMessage"></div>

    <form method="GET"
          action="{{ route($routeName) }}"
          @submit.prevent="generate()"
          class="bg-white rounded-xl shadow border border-gray-200 p-4 sm:p-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4 items-end">
            @if($availableBranches->isNotEmpty())
                <div class="min-w-0">
                    <label for="branch_id" class="block text-sm font-medium text-gray-700 mb-1">Branch</label>
                    <select name="branch_id" id="branch_id" x-model="branchId" class="block w-full filter-control pr-8">
                        @if(show_branch_ui() && $availableBranches->count() > 1)
                            <option value="">All branches</option>
                        @endif
                        @foreach($availableBranches as $branch)
                            <option value="{{ $branch->id }}" @selected((string) $branchId === (string) $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="min-w-0 relative"
                 x-on:keydown.escape.window="categoryOpen = false"
                 x-on:click.outside="categoryOpen = false">
                <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                <button type="button"
                        @click="categoryOpen = !categoryOpen; itemOpen = false"
                        class="filter-control w-full flex items-center justify-between gap-2 text-left bg-white">
                    <span class="truncate text-sm" x-text="categorySummary"></span>
                    <i class="fas fa-chevron-down text-gray-400 text-xs shrink-0 transition-transform" :class="categoryOpen ? 'rotate-180' : ''"></i>
                </button>
                <div x-show="categoryOpen"
                     x-cloak
                     x-transition
                     class="absolute z-30 mt-1 w-full min-w-[16rem] rounded-lg border border-gray-200 bg-white shadow-lg overflow-hidden">
                    <div class="p-2 border-b border-gray-100">
                        <input type="search"
                               x-model="categoryQuery"
                               placeholder="Search categories…"
                               class="block w-full h-9 px-3 rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div class="flex items-center justify-between gap-2 px-3 py-2 border-b border-gray-100 bg-gray-50">
                        <button type="button" @click="selectAllCategories()" class="text-xs font-medium text-indigo-600 hover:text-indigo-800">Select all</button>
                        <button type="button" @click="clearCategories()" class="text-xs font-medium text-gray-500 hover:text-gray-700">Clear</button>
                    </div>
                    <div class="max-h-56 overflow-y-auto py-1">
                        <template x-for="category in filteredCategories" :key="category.id">
                            <label class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 cursor-pointer">
                                <input type="checkbox"
                                       class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                       :value="category.id"
                                       :checked="selectedCategories.includes(category.id)"
                                       @change="toggleCategory(category.id)">
                                <span x-text="category.name"></span>
                            </label>
                        </template>
                        <p x-show="filteredCategories.length === 0" class="px-3 py-3 text-sm text-gray-500">No categories match.</p>
                    </div>
                </div>
            </div>

            <div class="min-w-0 relative"
                 x-on:keydown.escape.window="itemOpen = false"
                 x-on:click.outside="itemOpen = false">
                <label class="block text-sm font-medium text-gray-700 mb-1">Menu item</label>
                <button type="button"
                        @click="itemOpen = !itemOpen; categoryOpen = false"
                        class="filter-control w-full flex items-center justify-between gap-2 text-left bg-white">
                    <span class="truncate text-sm" x-text="itemSummary"></span>
                    <i class="fas fa-chevron-down text-gray-400 text-xs shrink-0 transition-transform" :class="itemOpen ? 'rotate-180' : ''"></i>
                </button>
                <div x-show="itemOpen"
                     x-cloak
                     x-transition
                     class="absolute z-30 mt-1 w-full min-w-[16rem] rounded-lg border border-gray-200 bg-white shadow-lg overflow-hidden">
                    <div class="p-2 border-b border-gray-100">
                        <input type="search"
                               x-model="itemQuery"
                               placeholder="Search items…"
                               class="block w-full h-9 px-3 rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div class="flex items-center justify-between gap-2 px-3 py-2 border-b border-gray-100 bg-gray-50">
                        <button type="button" @click="selectAllItems()" class="text-xs font-medium text-indigo-600 hover:text-indigo-800">Select all</button>
                        <button type="button" @click="clearItems()" class="text-xs font-medium text-gray-500 hover:text-gray-700">Clear</button>
                    </div>
                    <div class="max-h-56 overflow-y-auto py-1">
                        <template x-for="item in filteredItems" :key="item.id">
                            <label class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 cursor-pointer">
                                <input type="checkbox"
                                       class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                       :value="item.id"
                                       :checked="selectedItems.includes(item.id)"
                                       @change="toggleItem(item.id)">
                                <span x-text="item.name"></span>
                            </label>
                        </template>
                        <p x-show="filteredItems.length === 0" class="px-3 py-3 text-sm text-gray-500">No items match.</p>
                    </div>
                </div>
            </div>

            <div class="min-w-0">
                <label for="from" class="block text-sm font-medium text-gray-700 mb-1">Start date</label>
                <input type="date" name="from" id="from" x-model="from" class="block w-full filter-control" required>
            </div>
            <div class="min-w-0">
                <label for="to" class="block text-sm font-medium text-gray-700 mb-1">End date</label>
                <input type="date" name="to" id="to" x-model="to" class="block w-full filter-control" required>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap gap-2">
            <button type="submit"
                    class="inline-flex items-center justify-center h-11 px-4 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-60"
                    :disabled="loading">
                <i class="fas mr-2" :class="loading ? 'fa-spinner fa-spin' : 'fa-search'"></i>
                <span x-text="loading ? 'Generating…' : 'Generate report'"></span>
            </button>
            <a href="{{ route($routeName) }}" class="inline-flex items-center justify-center h-11 px-4 border border-gray-300 bg-white text-sm font-medium rounded-lg text-gray-700 hover:bg-gray-50">Reset</a>
        </div>
    </form>

    <div x-show="loading && !resultsHtml" x-cloak class="rounded-xl border border-dashed border-gray-300 bg-white py-12 text-center text-gray-500">
        <i class="fas fa-spinner fa-spin text-2xl text-indigo-400 mb-3"></i>
        <p class="font-medium text-gray-700">Generating report…</p>
    </div>

    <div x-show="resultsHtml" x-cloak @click="onResultsClick($event)" x-html="resultsHtml"></div>

    <div x-show="!resultsHtml && !loading" class="rounded-xl border border-dashed border-gray-300 bg-white py-12 text-center text-gray-500">
        <i class="fas fa-filter text-2xl text-gray-300 mb-3"></i>
        <p class="font-medium text-gray-700">Select categories and/or menu items, then a date range</p>
        <p class="mt-1 text-sm">Choosing a category first limits the menu item list to items in that category.</p>
    </div>
</div>

<script>
function salesByItemPage(config) {
    const categoryOptions = config?.categories || [];
    const menuItemOptions = config?.items || [];
    const selectedCategoryIds = config?.categoryIds || [];
    const selectedMenuItemIds = config?.menuItemIds || [];

    const categories = (Array.isArray(categoryOptions) ? categoryOptions : []).map((category) => ({
        id: Number(category.id),
        name: String(category.name || ''),
    }));
    const items = (Array.isArray(menuItemOptions) ? menuItemOptions : []).map((item) => ({
        id: Number(item.id),
        name: String(item.name || ''),
        category_id: item.category_id == null ? null : Number(item.category_id),
    }));

    return {
        categoryOpen: false,
        itemOpen: false,
        categoryQuery: '',
        itemQuery: '',
        categories,
        items,
        selectedCategories: (Array.isArray(selectedCategoryIds) ? selectedCategoryIds : [])
            .map((id) => Number(id))
            .filter((id) => id > 0),
        selectedItems: (Array.isArray(selectedMenuItemIds) ? selectedMenuItemIds : [])
            .map((id) => Number(id))
            .filter((id) => id > 0),
        branchId: @js((string) ($branchId ?? '')),
        from: @js($from),
        to: @js($to),
        generateUrl: String(config?.generateUrl || ''),
        loading: false,
        resultsHtml: '',
        errorMessage: '',

        init() {
            const params = new URLSearchParams(window.location.search);
            if (params.get('generate') === '1') {
                this.fetchResults(window.location.pathname + window.location.search, false);
            }
        },

        get filteredCategories() {
            const q = this.categoryQuery.trim().toLowerCase();
            if (!q) {
                return this.categories;
            }

            return this.categories.filter((category) => category.name.toLowerCase().includes(q));
        },

        get availableItems() {
            if (this.selectedCategories.length === 0) {
                return this.items;
            }

            return this.items.filter((item) => item.category_id != null && this.selectedCategories.includes(item.category_id));
        },

        get filteredItems() {
            const q = this.itemQuery.trim().toLowerCase();
            const pool = this.availableItems;
            if (!q) {
                return pool;
            }

            return pool.filter((item) => item.name.toLowerCase().includes(q));
        },

        get categorySummary() {
            if (this.selectedCategories.length === 0) {
                return 'All categories';
            }
            if (this.selectedCategories.length === 1) {
                const match = this.categories.find((category) => category.id === this.selectedCategories[0]);

                return match ? match.name : '1 selected';
            }

            return this.selectedCategories.length + ' categories selected';
        },

        get itemSummary() {
            if (this.selectedItems.length === 0) {
                return this.selectedCategories.length > 0 ? 'All items in categories' : 'Select items…';
            }
            if (this.selectedItems.length === 1) {
                const match = this.items.find((item) => item.id === this.selectedItems[0]);

                return match ? match.name : '1 selected';
            }

            return this.selectedItems.length + ' items selected';
        },

        toggleCategory(id) {
            const value = Number(id);
            if (this.selectedCategories.includes(value)) {
                this.selectedCategories = this.selectedCategories.filter((item) => item !== value);
            } else {
                this.selectedCategories = [...this.selectedCategories, value];
            }
            this.pruneItemsToAvailable();
        },

        selectAllCategories() {
            this.selectedCategories = this.categories.map((category) => category.id);
            this.pruneItemsToAvailable();
        },

        clearCategories() {
            this.selectedCategories = [];
            this.pruneItemsToAvailable();
        },

        toggleItem(id) {
            const value = Number(id);
            if (this.selectedItems.includes(value)) {
                this.selectedItems = this.selectedItems.filter((item) => item !== value);
            } else {
                this.selectedItems = [...this.selectedItems, value];
            }
        },

        selectAllItems() {
            this.selectedItems = this.availableItems.map((item) => item.id);
        },

        clearItems() {
            this.selectedItems = [];
        },

        pruneItemsToAvailable() {
            const allowed = new Set(this.availableItems.map((item) => item.id));
            this.selectedItems = this.selectedItems.filter((id) => allowed.has(id));
        },

        buildParams() {
            const params = new URLSearchParams();
            params.set('generate', '1');
            if (this.branchId) {
                params.set('branch_id', this.branchId);
            }
            params.set('from', this.from || '');
            params.set('to', this.to || '');
            this.selectedCategories.forEach((id) => params.append('category_ids[]', String(id)));
            this.selectedItems.forEach((id) => params.append('menu_item_ids[]', String(id)));

            return params;
        },

        async generate() {
            if (!this.from || !this.to) {
                this.errorMessage = 'Please select a start and end date.';
                return;
            }
            if (this.selectedCategories.length === 0 && this.selectedItems.length === 0) {
                this.errorMessage = 'Select at least one category or menu item.';
                return;
            }

            const url = this.generateUrl + '?' + this.buildParams().toString();
            await this.fetchResults(url, true);
        },

        async fetchResults(url, pushHistory) {
            this.loading = true;
            this.errorMessage = '';

            try {
                const response = await fetch(url, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'text/html',
                    },
                    credentials: 'same-origin',
                });
                const contentType = response.headers.get('content-type') || '';

                if (contentType.includes('application/json')) {
                    const data = await response.json();
                    const firstError = data.errors
                        ? Object.values(data.errors).flat()[0]
                        : null;
                    this.errorMessage = firstError || data.message || 'Could not generate the report. Please try again.';
                    this.resultsHtml = '';
                    return;
                }

                const html = await response.text();

                if (!response.ok && response.status !== 422) {
                    this.errorMessage = 'Could not generate the report. Please try again.';
                    this.resultsHtml = '';
                    return;
                }

                this.resultsHtml = html;
                if (pushHistory && window.history?.replaceState) {
                    window.history.replaceState({}, '', url);
                }
            } catch (e) {
                this.errorMessage = 'Could not generate the report. Please try again.';
                this.resultsHtml = '';
            } finally {
                this.loading = false;
            }
        },

        onResultsClick(event) {
            const link = event.target.closest('a');
            if (!link || !link.closest('.sales-by-item-pagination')) {
                return;
            }

            event.preventDefault();
            if (link.href) {
                this.fetchResults(link.href, true);
            }
        },
    };
}
</script>
@endsection
