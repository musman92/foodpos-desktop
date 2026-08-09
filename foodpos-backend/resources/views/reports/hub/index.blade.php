@extends('layouts.app')

@section('title', 'Operational Reporting')

@section('content')
<div class="max-w-[90rem] mx-auto"
     x-data="reportHub({
         panelUrl: @js(route('reports.panel')),
         initialReport: @js($initialReport),
         catalog: @js($catalogJson),
         groupLabels: @js($groupLabels),
         filterDefaults: @js($filterDefaults),
         filterOptions: @js($filterOptions),
         consumptionOptionsUrl: @js(route('reports.consumption.filter-options')),
         branches: @js($availableBranches->map(fn ($b) => ['id' => (string) $b->id, 'name' => $b->name])->values()),
         showBranchFilter: @js(show_branch_ui() && $availableBranches->isNotEmpty()),
     })"
     x-init="init()"
     @report-hub-apply.window="applyFilters()">

    <style>
        @media print {
            @page {
                size: portrait;
                margin: 10mm;
            }

            body {
                background: #fff !important;
            }

            /* App shell uses h-screen + overflow scroll; that clips print to one viewport. */
            body > .flex.h-screen,
            body > .flex.h-screen > .flex-1,
            main {
                display: block !important;
                height: auto !important;
                max-height: none !important;
                overflow: visible !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .h-screen,
            .overflow-hidden,
            .overflow-y-auto,
            .overflow-x-auto {
                height: auto !important;
                max-height: none !important;
                overflow: visible !important;
            }

            .report-hub-chrome,
            body > .flex.h-screen > .hidden.lg\:flex,
            body > .flex.h-screen > .flex-1.flex.flex-col > div:first-child,
            body > .flex.h-screen .fixed.inset-y-0,
            [x-show="sidebarOpen"] {
                display: none !important;
            }

            #report-print-area { display: block !important; }
            .period-closing-expense-dialog { display: none !important; }
            .period-closing-info-btn { display: none !important; }
            .period-closing-print,
            .period-closing-print * {
                overflow: visible !important;
            }
            .period-closing-print .period-closing-grid {
                display: block !important;
            }
            .period-closing-print .period-closing-col {
                width: 100% !important;
                max-width: 100% !important;
                display: block !important;
                border-right: none !important;
                page-break-inside: auto;
                break-inside: auto;
                margin-bottom: 0.75rem;
                padding-bottom: 0.75rem;
                border-bottom: 1px solid #e5e7eb;
            }
            .period-closing-day-card {
                page-break-inside: avoid;
                break-inside: avoid;
            }
        }

        .report-hub-filters {
            padding: 0.5rem;
        }

        .report-hub-filters .filter-control {
            height: 2rem;
            padding-left: 0.5rem;
            padding-right: 0.5rem;
            border-radius: 0.375rem;
            font-size: 0.8125rem;
            line-height: 1.25rem;
            box-shadow: none;
        }

        .report-hub-filters .filter-control[multiple] {
            height: auto;
            min-height: 2rem;
        }
    </style>

    <div class="report-hub-chrome mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Operational Reporting</h1>
            <p class="mt-1 text-sm text-gray-500" x-text="activeTitle"></p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" @click="print()" :disabled="loading" class="inline-flex items-center h-10 px-4 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 shadow-sm disabled:opacity-50">
                <i class="fas fa-print mr-2 text-gray-600"></i>Print
            </button>
            <a :href="exports.excel || '#'" :class="exports.excel ? '' : 'pointer-events-none opacity-50'" class="inline-flex items-center h-10 px-4 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 shadow-sm">
                <i class="fas fa-file-excel mr-2 text-green-600"></i>Export Excel
            </a>
            <a :href="exports.pdf || '#'" :class="exports.pdf ? '' : 'pointer-events-none opacity-50'" class="inline-flex items-center h-10 px-4 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 shadow-sm">
                <i class="fas fa-file-pdf mr-2 text-red-600"></i>Download PDF
            </a>
        </div>
    </div>

    <div class="report-hub-chrome bg-white rounded-lg shadow border border-gray-200 mb-4 p-2">
        <div class="flex flex-wrap gap-1.5">
            @foreach($flatReports as $report)
                <button type="button"
                        @click="selectReport(@js($report['key']))"
                        :class="activeKey === @js($report['key']) ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-50'"
                        class="inline-flex items-center gap-1.5 h-8 px-2.5 rounded-md border text-xs font-medium transition-colors">
                    <i class="fas fa-{{ $report['icon'] }} text-[10px] opacity-80"></i>
                    {{ $report['label'] }}
                </button>
            @endforeach
        </div>
    </div>

    <div class="report-hub-chrome report-hub-filters bg-white rounded-lg shadow border border-gray-200 mb-4">
        <div class="flex flex-wrap items-end gap-1.5">
            <div class="flex flex-wrap items-end gap-1.5 flex-1 min-w-0">
            <template x-if="hasFilter('branch') && showBranchFilter">
                <div class="w-36">
                    <label class="block text-[11px] font-medium text-gray-500 mb-0.5">Branch</label>
                    <select x-model="filters.branch_id" class="block w-full filter-control">
                        <template x-if="branches.length > 1"><option value="">All branches</option></template>
                        <template x-for="branch in branches" :key="branch.id">
                            <option :value="branch.id" x-text="branch.name"></option>
                        </template>
                    </select>
                </div>
            </template>
            <template x-if="hasFilter('dates')">
                <div class="w-[9.5rem]">
                    <label class="block text-[11px] font-medium text-gray-500 mb-0.5">From</label>
                    <input type="date" x-model="filters.from" class="block w-full filter-control">
                </div>
            </template>
            <template x-if="hasFilter('dates')">
                <div class="w-[9.5rem]">
                    <label class="block text-[11px] font-medium text-gray-500 mb-0.5">To</label>
                    <input type="date" x-model="filters.to" class="block w-full filter-control">
                </div>
            </template>
            <template x-if="hasFilter('week')">
                <div class="w-[9.5rem]">
                    <label class="block text-[11px] font-medium text-gray-500 mb-0.5">Week of</label>
                    <input type="date" x-model="filters.week_of" class="block w-full filter-control">
                </div>
            </template>
            <template x-if="hasFilter('week')">
                <div class="w-20">
                    <label class="block text-[11px] font-medium text-gray-500 mb-0.5">Weeks</label>
                    <input type="number" min="1" max="12" x-model="filters.week_count" class="block w-full filter-control">
                </div>
            </template>
            <template x-if="hasFilter('month')">
                <div class="w-36">
                    <label class="block text-[11px] font-medium text-gray-500 mb-0.5">Month</label>
                    <input type="month" x-model="filters.month" class="block w-full filter-control">
                </div>
            </template>
            <template x-if="hasFilter('limit')">
                <div class="w-20">
                    <label class="block text-[11px] font-medium text-gray-500 mb-0.5">Limit</label>
                    <input type="number" min="1" max="100" x-model="filters.limit" class="block w-full filter-control">
                </div>
            </template>
            <template x-if="hasFilter('category')">
                <div class="w-44"
                     x-data="((parent) => searchableSelect({
                         getOptions: () => parent.filterOptions.categories || [],
                         value: parent.filters.category_id || '',
                         maxResults: 200,
                         placeholder: 'All categories',
                         emptyMessage: 'No categories found',
                         onChange: (value) => parent.setCategoryFilter(value),
                         onInit: (component) => {
                             component.$watch(() => parent.filters.category_id, (value) => {
                                 component.selectedValue = value ? String(value) : '';
                                 component.syncLabelFromValue();
                             });
                         },
                     }))($data)"
                     x-init="init()">
                    <label class="block text-[11px] font-medium text-gray-500 mb-0.5">Category</label>
                    <x-searchable-select
                        compact
                        useButtonOptions
                        id="hub_category"
                    />
                </div>
            </template>
            <template x-if="hasFilter('menu_item')">
                <div class="w-48"
                     x-data="((parent) => searchableSelect({
                         getOptions: () => parent.menuItemsForCategory(),
                         value: parent.filters.menu_item_id || '',
                         maxResults: 200,
                         placeholder: 'All menu items',
                         emptyMessage: 'No menu items found',
                         onChange: (value) => { parent.filters.menu_item_id = value ? String(value) : ''; },
                         onInit: (component) => {
                             component.$watch(() => parent.filters.menu_item_id, (value) => {
                                 component.selectedValue = value ? String(value) : '';
                                 component.syncLabelFromValue();
                             });
                             component.$watch(() => parent.filters.category_id, () => component.syncLabelFromValue());
                         },
                     }))($data)"
                     x-init="init()">
                    <label class="block text-[11px] font-medium text-gray-500 mb-0.5">Menu item</label>
                    <x-searchable-select
                        compact
                        useButtonOptions
                        id="hub_menu_item"
                    />
                </div>
            </template>
            <template x-if="hasFilter('search')">
                <div class="min-w-[12rem] flex-1 max-w-sm">
                    <label class="block text-[11px] font-medium text-gray-500 mb-0.5">Search</label>
                    <input type="search" x-model="filters.search" class="block w-full filter-control" placeholder="Ingredient names…">
                </div>
            </template>
            <template x-if="hasFilter('ingredient')">
                <div class="w-52"
                     x-data="((parent) => searchableSelect({
                         getOptions: () => parent.filterOptions.ingredients || [],
                         value: parent.filters.ingredient_id || '',
                         maxResults: 150,
                         placeholder: 'Select ingredient…',
                         emptyMessage: 'No ingredients found',
                         onChange: (value) => { parent.filters.ingredient_id = value ? String(value) : ''; },
                         onInit: (component) => {
                             component.$watch(() => parent.filters.ingredient_id, (value) => {
                                 component.selectedValue = value ? String(value) : '';
                                 component.syncLabelFromValue();
                             });
                         },
                     }))($data)"
                     x-init="init()">
                    <label class="block text-[11px] font-medium text-gray-500 mb-0.5">Ingredient</label>
                    <x-searchable-select
                        compact
                        useButtonOptions
                        id="hub_ingredient"
                    />
                </div>
            </template>
            <template x-if="hasFilter('money_sources')">
                <div class="w-52"
                     x-data="((parent) => hubMoneySourceMultiSelect({
                         getOptions: () => parent.filterOptions.moneySources || [],
                         getSelected: () => parent.filters.money_source_ids || [],
                         onChange: (ids) => { parent.filters.money_source_ids = ids; },
                     }))($data)"
                     x-init="init()"
                     @keydown.escape.window="open = false"
                     @click.outside="open = false">
                    <label class="block text-[11px] font-medium text-gray-500 mb-0.5">Money sources</label>
                    <div class="relative">
                        <button type="button"
                                @click="open = !open"
                                class="filter-control w-full flex items-center justify-between gap-2 text-left bg-white">
                            <span class="truncate text-sm" x-text="summaryLabel"></span>
                            <i class="fas fa-chevron-down text-gray-400 text-xs shrink-0 transition-transform" :class="open ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open"
                             x-cloak
                             x-transition
                             class="absolute z-30 mt-1 w-full min-w-[16rem] rounded-lg border border-gray-200 bg-white shadow-lg overflow-hidden">
                            <div class="p-2 border-b border-gray-100">
                                <input type="search"
                                       x-model="query"
                                       placeholder="Search sources…"
                                       class="block w-full h-8 px-2.5 rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <div class="flex items-center justify-between gap-2 px-2.5 py-1.5 border-b border-gray-100 bg-gray-50">
                                <button type="button" @click="selectAll()" class="text-xs font-medium text-indigo-600 hover:text-indigo-800">Select all</button>
                                <button type="button" @click="clearAll()" class="text-xs font-medium text-gray-500 hover:text-gray-700">Clear</button>
                            </div>
                            <div class="max-h-48 overflow-y-auto py-1">
                                <template x-for="source in filtered" :key="source.id">
                                    <label class="flex items-center gap-2 px-2.5 py-1.5 text-sm text-gray-700 hover:bg-gray-50 cursor-pointer">
                                        <input type="checkbox"
                                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                               :value="source.id"
                                               :checked="selected.includes(source.id)"
                                               @change="toggle(source.id)">
                                        <span x-text="source.name"></span>
                                    </label>
                                </template>
                                <p x-show="filtered.length === 0" class="px-2.5 py-2 text-sm text-gray-500">No sources match.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
            <template x-if="hasFilter('sort')">
                <div class="w-40">
                    <label class="block text-[11px] font-medium text-gray-500 mb-0.5">Sort</label>
                    <select x-model="filters.sort" class="block w-full filter-control">
                        <option value="margin_desc">Margin % ↓</option>
                        <option value="margin_asc">Margin % ↑</option>
                        <option value="name">Name</option>
                        <option value="price_desc">Price ↓</option>
                        <option value="cost_desc">Cost ↓</option>
                    </select>
                </div>
            </template>
            <template x-if="hasFilter('customer')">
                <div class="w-44"
                     x-data="((parent) => searchableSelect({
                         getOptions: () => parent.filterOptions.customers || [],
                         value: parent.filters.customer_id || '',
                         maxResults: 100,
                         placeholder: 'All customers',
                         emptyMessage: 'No customers found',
                         onChange: (value) => { parent.filters.customer_id = value ? String(value) : ''; },
                         onInit: (component) => {
                             component.$watch(() => parent.filters.customer_id, (value) => {
                                 component.selectedValue = value ? String(value) : '';
                                 component.syncLabelFromValue();
                             });
                         },
                     }))($data)"
                     x-init="init()">
                    <label class="block text-[11px] font-medium text-gray-500 mb-0.5">Customer</label>
                    <x-searchable-select
                        compact
                        useButtonOptions
                        id="hub_customer"
                    />
                </div>
            </template>
            <template x-if="hasFilter('waiter')">
                <div class="w-40"
                     x-data="((parent) => searchableSelect({
                         getOptions: () => parent.filterOptions.staff || [],
                         value: parent.filters.waiter_id || '',
                         maxResults: 100,
                         placeholder: 'All waiters',
                         emptyMessage: 'No waiters found',
                         onChange: (value) => { parent.filters.waiter_id = value ? String(value) : ''; },
                         onInit: (component) => {
                             component.$watch(() => parent.filters.waiter_id, (value) => {
                                 component.selectedValue = value ? String(value) : '';
                                 component.syncLabelFromValue();
                             });
                         },
                     }))($data)"
                     x-init="init()">
                    <label class="block text-[11px] font-medium text-gray-500 mb-0.5">Waiter</label>
                    <x-searchable-select
                        compact
                        useButtonOptions
                        id="hub_waiter"
                    />
                </div>
            </template>
            <template x-if="hasFilter('rider')">
                <div class="w-40"
                     x-data="((parent) => searchableSelect({
                         getOptions: () => parent.filterOptions.staff || [],
                         value: parent.filters.delivery_rider_id || '',
                         maxResults: 100,
                         placeholder: 'All riders',
                         emptyMessage: 'No riders found',
                         onChange: (value) => { parent.filters.delivery_rider_id = value ? String(value) : ''; },
                         onInit: (component) => {
                             component.$watch(() => parent.filters.delivery_rider_id, (value) => {
                                 component.selectedValue = value ? String(value) : '';
                                 component.syncLabelFromValue();
                             });
                         },
                     }))($data)"
                     x-init="init()">
                    <label class="block text-[11px] font-medium text-gray-500 mb-0.5">Rider</label>
                    <x-searchable-select
                        compact
                        useButtonOptions
                        id="hub_rider"
                    />
                </div>
            </template>
            <template x-if="hasFilter('order_type')">
                <div class="w-32">
                    <label class="block text-[11px] font-medium text-gray-500 mb-0.5">Type</label>
                    <select x-model="filters.type" class="block w-full filter-control">
                        <option value="">All types</option>
                        <option value="dine_in">Dine in</option>
                        <option value="takeaway">Take away</option>
                        <option value="delivery">Delivery</option>
                    </select>
                </div>
            </template>
            <template x-if="hasFilter('bill_number')">
                <div class="w-32">
                    <label class="block text-[11px] font-medium text-gray-500 mb-0.5">Order #</label>
                    <input type="text" x-model="filters.order_number" class="block w-full filter-control" placeholder="Bill #">
                </div>
            </template>
            <template x-if="hasFilter('statement_type')">
                <div class="w-36">
                    <label class="block text-[11px] font-medium text-gray-500 mb-0.5">Type</label>
                    <select x-model="filters.type" @change="onStatementTypeChange()" class="block w-full filter-control">
                        <option value="customer">Customer</option>
                        <option value="supplier">Supplier</option>
                        <option value="employee">Employee</option>
                    </select>
                </div>
            </template>
            <template x-if="hasFilter('party')">
                <div class="min-w-[14rem] flex-1" x-data="hubPartySearch({
                         getType: () => $data.filters.type || 'customer',
                         getPartyId: () => $data.filters.party_id || '',
                         getPartyLabel: () => $data.filters.party_label || $data.filters.party_query || '',
                         searchUrl: @js(route('account-statements.search')),
                         onChange: (id, label, query) => {
                             $data.filters.party_id = id ? String(id) : '';
                             $data.filters.party_label = label || '';
                             $data.filters.party_query = query || '';
                         },
                     })"
                     x-init="init()">
                    <label class="block text-[11px] font-medium text-gray-500 mb-0.5" x-text="typeLabel"></label>
                    <div class="relative">
                        <input type="text"
                               x-model="searchQuery"
                               @input.debounce.300ms="searchParties()"
                               @focus="dropdownOpen = true"
                               @blur="setTimeout(() => dropdownOpen = false, 200)"
                               :placeholder="'Search ' + typeLabel.toLowerCase() + '…'"
                               autocomplete="off"
                               class="block w-full filter-control">
                        <div x-show="dropdownOpen && searchResults.length > 0"
                             x-cloak
                             class="absolute z-50 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-auto">
                            <template x-for="row in searchResults" :key="row.id">
                                <button type="button"
                                        @mousedown.prevent="selectParty(row)"
                                        class="w-full text-left px-3 py-2 text-sm text-gray-900 hover:bg-gray-50 border-b border-gray-100 last:border-0">
                                    <span x-text="row.label"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>
            </template>
            </div>
            <div class="shrink-0 ml-auto">
                <label class="block text-[11px] font-medium text-transparent mb-0.5 leading-none select-none" aria-hidden="true">Apply</label>
                <button type="button" @click="applyFilters()" :disabled="loading" class="inline-flex items-center justify-center h-8 px-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                    <i class="fas fa-sync-alt mr-1.5" :class="loading ? 'fa-spin' : ''"></i>Apply
                </button>
            </div>
        </div>
    </div>

    <div id="report-panel" @click="onPanelClick($event)">
        <div id="report-print-area">
            <div x-show="loading" class="bg-white rounded-lg shadow border border-gray-200 p-12 text-center text-gray-500">
                <i class="fas fa-spinner fa-spin text-2xl mb-3"></i>
                <p>Loading report…</p>
            </div>
            <div x-show="!loading" x-html="panelHtml"></div>
        </div>
    </div>
</div>

@once
<script>
function hubPartySearch({ getType, getPartyId, getPartyLabel, searchUrl, onChange }) {
    return {
        searchQuery: '',
        searchResults: [],
        dropdownOpen: false,
        searchUrl,

        get type() {
            return getType() || 'customer';
        },

        get typeLabel() {
            if (this.type === 'supplier') return 'Supplier';
            if (this.type === 'employee') return 'Employee';
            return 'Customer';
        },

        init() {
            this.searchQuery = getPartyLabel() || '';
            this.$watch(() => getType(), () => {
                // Parent clears party on type change; refresh the visible search box.
                this.searchQuery = getPartyLabel() || '';
                this.searchResults = [];
            });
            this.$watch(() => getPartyLabel(), (value) => {
                if (value && value !== this.searchQuery) {
                    this.searchQuery = value;
                }
            });
        },

        async searchParties() {
            const q = (this.searchQuery || '').trim();
            if (q.length < 2) {
                this.searchResults = [];
                onChange('', '', q);
                return;
            }
            try {
                const params = new URLSearchParams({ type: this.type, q });
                const res = await fetch(this.searchUrl + '?' + params.toString(), {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                this.searchResults = await res.json();
                this.dropdownOpen = true;
            } catch (e) {
                this.searchResults = [];
            }
        },

        selectParty(row) {
            this.searchQuery = row.label;
            this.searchResults = [];
            this.dropdownOpen = false;
            onChange(row.id, row.name, row.label);
        },
    };
}

function hubMoneySourceMultiSelect({ getOptions, getSelected, onChange }) {
    return {
        open: false,
        query: '',
        selected: [],

        init() {
            this.syncFromParent();
            this.$watch(() => getSelected(), () => this.syncFromParent());
        },

        syncFromParent() {
            this.selected = (getSelected() || [])
                .map((id) => Number(id))
                .filter((id) => id > 0);
        },

        get options() {
            return (getOptions() || []).map((source) => ({
                id: Number(source.id),
                name: String(source.name || ''),
            }));
        },

        get filtered() {
            const q = this.query.trim().toLowerCase();
            if (!q) {
                return this.options;
            }

            return this.options.filter((source) => source.name.toLowerCase().includes(q));
        },

        get summaryLabel() {
            if (this.selected.length === 0) {
                return 'All sources';
            }

            if (this.selected.length === 1) {
                const match = this.options.find((source) => source.id === this.selected[0]);

                return match ? match.name : '1 selected';
            }

            return this.selected.length + ' sources selected';
        },

        commit() {
            onChange([...this.selected]);
        },

        toggle(id) {
            const value = Number(id);
            if (this.selected.includes(value)) {
                this.selected = this.selected.filter((item) => item !== value);
            } else {
                this.selected = [...this.selected, value];
            }
            this.commit();
        },

        selectAll() {
            this.selected = this.options.map((source) => source.id);
            this.commit();
        },

        clearAll() {
            this.selected = [];
            this.commit();
        },
    };
}

function reportHub(config) {
    return {
        panelUrl: config.panelUrl,
        catalog: config.catalog || [],
        groupLabels: config.groupLabels || {},
        filterDefaults: config.filterDefaults || {},
        filterOptions: config.filterOptions || {},
        branches: config.branches || [],
        showBranchFilter: config.showBranchFilter,
        activeKey: config.initialReport || (config.catalog[0]?.key ?? null),
        activeTitle: '',
        filters: {},
        panelHtml: '',
        loading: false,
        exports: { pdf: null, excel: null },

        init() {
            if (this.activeKey) {
                this.resetFiltersForReport(this.activeKey);
                this.hydrateFiltersFromUrl();
                this.fetchPanel();
            }
        },

        hydrateFiltersFromUrl() {
            const params = new URLSearchParams(window.location.search);
            params.forEach((value, key) => {
                if (key === 'report') return;
                if (key.endsWith('[]')) {
                    const base = key.slice(0, -2);
                    if (!Array.isArray(this.filters[base])) {
                        this.filters[base] = [];
                    }
                    this.filters[base].push(value);
                    return;
                }
                this.filters[key] = value;
            });
        },

        catalogEntry(key) {
            return this.catalog.find((r) => r.key === key) || null;
        },

        hasFilter(name) {
            const entry = this.catalogEntry(this.activeKey);
            return entry ? (entry.filters || []).includes(name) : false;
        },

        setCategoryFilter(value) {
            this.filters.category_id = value ? String(value) : '';
            this.filters.menu_item_id = '';
        },

        menuItemsForCategory() {
            const items = this.filterOptions.menuItems || [];
            const categoryId = this.filters.category_id;
            if (!categoryId) {
                return items;
            }

            return items.filter((item) => String(item.category_id) === String(categoryId));
        },

        selectReport(key) {
            if (this.activeKey === key) return;
            this.activeKey = key;
            this.resetFiltersForReport(key);
            this.fetchPanel();
        },

        resetFiltersForReport(key) {
            const entry = this.catalogEntry(key);
            this.filters = { ...this.filterDefaults };
            if (entry?.filters?.includes('category')) this.filters.category_id = '';
            if (entry?.filters?.includes('menu_item')) this.filters.menu_item_id = '';
            if (entry?.filters?.includes('ingredient')) this.filters.ingredient_id = '';
            if (entry?.filters?.includes('customer')) this.filters.customer_id = '';
            if (entry?.filters?.includes('waiter')) this.filters.waiter_id = '';
            if (entry?.filters?.includes('rider')) this.filters.delivery_rider_id = '';
            if (entry?.filters?.includes('order_type')) this.filters.type = '';
            if (entry?.filters?.includes('bill_number')) this.filters.order_number = '';
            if (entry?.filters?.includes('search')) this.filters.search = '';
            if (entry?.filters?.includes('money_sources')) this.filters.money_source_ids = [];
            if (entry?.filters?.includes('statement_type')) this.filters.type = 'customer';
            if (entry?.filters?.includes('party')) {
                this.filters.party_id = '';
                this.filters.party_label = '';
                this.filters.party_query = '';
            }
            delete this.filters.page;
            delete this.filters.per_page;
        },

        onStatementTypeChange() {
            this.filters.party_id = '';
            this.filters.party_label = '';
            this.filters.party_query = '';
        },

        applyFilters() {
            delete this.filters.page;
            this.fetchPanel();
        },

        buildQuery() {
            const params = new URLSearchParams();
            params.set('report', this.activeKey);
            Object.entries(this.filters).forEach(([key, value]) => {
                if (key === 'party_label' || key === 'party_query') return;
                if (value === null || value === undefined || value === '') return;
                if (Array.isArray(value)) {
                    value.forEach((v) => params.append(key + '[]', v));
                } else {
                    params.set(key, value);
                }
            });
            return params;
        },

        onPanelClick(event) {
            const link = event.target.closest('a[href]');
            if (!link) return;

            const inPagination = link.closest(
                '.report-hub-pagination, .sales-by-item-pagination, nav[role="navigation"], .pagination'
            );
            if (!inPagination) return;

            event.preventDefault();

            let url;
            try {
                url = new URL(link.getAttribute('href'), window.location.origin);
            } catch (e) {
                return;
            }

            const page = url.searchParams.get('page');
            if (page && page !== '1') {
                this.filters.page = page;
            } else {
                delete this.filters.page;
            }

            const perPage = url.searchParams.get('per_page');
            if (perPage) {
                this.filters.per_page = perPage;
            }

            this.fetchPanel();
        },

        async fetchPanel() {
            if (!this.activeKey) return;
            this.loading = true;
            const params = this.buildQuery();
            try {
                const response = await fetch(`${this.panelUrl}?${params.toString()}`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                if (!response.ok) throw new Error('Failed to load report');
                const data = await response.json();
                this.panelHtml = data.html || '';
                this.activeTitle = data.title || '';
                this.exports = data.exports || { pdf: null, excel: null };
                const url = `/reports?${params.toString()}`;
                history.pushState({}, '', url);
            } catch (e) {
                this.panelHtml = '<div class="p-8 text-center text-red-600">Could not load report.</div>';
            } finally {
                this.loading = false;
            }
        },

        print() {
            window.print();
        },
    };
}
</script>
@endonce
@endsection
