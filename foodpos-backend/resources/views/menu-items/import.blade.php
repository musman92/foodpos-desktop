@extends('layouts.app')

@section('title', 'Import Menu Items')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Import Menu Items</h1>
            <p class="mt-1 text-sm text-gray-500">Upload a four-sheet Excel workbook to create or update menu items, variant prices, addon links, and recipes.</p>
        </div>
        <a href="{{ route('menu-items.index') }}"
           class="inline-flex items-center px-4 py-2 h-12 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
            <i class="fas fa-arrow-left mr-2"></i>
            Back to Menu Items
        </a>
    </div>
    @if(!empty($importResult))
        @php $result = $importResult; @endphp
        <div class="bg-white shadow rounded-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200"><h2 class="text-lg font-semibold text-gray-900">Import Summary</h2></div>
            <div class="px-6 py-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="rounded-lg bg-green-50 border border-green-100 p-4"><p class="text-xs font-medium uppercase text-green-700">Created</p><p class="mt-1 text-2xl font-bold text-green-900">{{ $result['created'] ?? 0 }}</p></div>
                <div class="rounded-lg bg-blue-50 border border-blue-100 p-4"><p class="text-xs font-medium uppercase text-blue-700">Updated</p><p class="mt-1 text-2xl font-bold text-blue-900">{{ $result['updated'] ?? 0 }}</p></div>
                <div class="rounded-lg bg-amber-50 border border-amber-100 p-4"><p class="text-xs font-medium uppercase text-amber-700">Skipped</p><p class="mt-1 text-2xl font-bold text-amber-900">{{ $result['skipped'] ?? 0 }}</p></div>
            </div>
            @if(!empty($result['errors']))
                <div class="px-6 pb-6">
                    <div class="overflow-x-auto border border-gray-200 rounded-lg">
                        <table class="listing-table min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50"><tr><th class="px-4 py-2 text-left">Location</th><th class="px-4 py-2 text-left">Message</th></tr></thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach($result['errors'] as $error)
                                    <tr>
                                        <td class="px-4 py-2 font-mono text-xs">{{ $error['row'] ?? '—' }}</td>
                                        <td class="px-4 py-2 text-red-700">{{ $error['message'] ?? '' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    @endif

    <div class="rounded-lg border border-indigo-100 bg-indigo-50/60 px-4 py-3 text-sm text-indigo-900 space-y-2">
        <p class="font-medium">Import order (prerequisites)</p>
        <p class="text-indigo-800">Import <strong>categories</strong>, <strong>variants</strong>, <strong>product addons</strong>, and <strong>recipes</strong> (Menu → Recipes) first. Menu item import links to those masters by code.</p>
        <p class="text-indigo-800">Use the same <code class="font-mono text-xs bg-white/70 px-1 rounded">menu_item_code</code> on all four sheets (stored as the menu item SKU, e.g. MI01).</p>
    </div>

    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200"><h2 class="text-lg font-semibold text-gray-900">Workbook sheets</h2></div>
        <div class="px-6 py-5 space-y-4 text-sm text-gray-700">
            <div>
                <p class="font-semibold text-gray-900">1. menu_items</p>
                <p class="mt-1">One row per item — name, category, price, type (<code class="font-mono text-xs">single</code> or <code class="font-mono text-xs">recipe</code>), flags.</p>
            </div>
            <div>
                <p class="font-semibold text-gray-900">2. variant_prices</p>
                <p class="mt-1">One row per menu item + variant + option selling price. Leave <code class="font-mono text-xs">menu_item_code</code> and <code class="font-mono text-xs">variant_code</code> blank on follow-up rows to reuse the values above. Leave this sheet empty to keep existing variant links unchanged.</p>
            </div>
            <div>
                <p class="font-semibold text-gray-900">3. addons</p>
                <p class="mt-1">One row per menu item + addon code (PA01). Blank <code class="font-mono text-xs">menu_item_code</code> cells reuse the value from the row above. Links only — prices come from addon master.</p>
            </div>
            <div>
                <p class="font-semibold text-gray-900">4. recipes</p>
                <p class="mt-1">One row per catalog recipe link using <code class="font-mono text-xs">recipe_code</code> (from Menu → Recipes). Without variants: leave <code class="font-mono text-xs">variant_code</code> and <code class="font-mono text-xs">option_name</code> blank. With variants: one row per option (both codes required; no default/fallback). Blank <code class="font-mono text-xs">menu_item_code</code> / variant / option cells reuse the row above.</p>
            </div>
        </div>
    </div>

    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200"><h2 class="text-lg font-semibold text-gray-900">Download sample</h2></div>
        <div class="px-6 py-5">
            <a href="{{ route('menu-items.import.sample') }}" class="inline-flex items-center px-4 py-2 h-11 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                <i class="fas fa-file-excel mr-2 text-green-600"></i>
                Download Excel sample (4 sheets)
            </a>
        </div>
    </div>

    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Export current data</h2>
            <p class="mt-1 text-sm text-gray-500">Download your menu items in the same four-sheet Excel layout used for import.</p>
        </div>
        <div class="px-6 py-5">
            @include('partials.menu-item-export-action')
        </div>
    </div>

    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200"><h2 class="text-lg font-semibold text-gray-900">Upload file</h2></div>
        <form action="{{ route('menu-items.import.store') }}" method="POST" enctype="multipart/form-data" class="px-6 py-6 space-y-5">
            @csrf
            <input type="file" name="file" required accept=".xlsx,.xls,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel" class="block w-full text-sm text-gray-700 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-indigo-50 file:text-indigo-700">
            @error('file')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            <p class="text-xs text-gray-500">Excel only (.xlsx, .xls), max 5 MB, up to 1,000 rows per sheet.</p>
            <button type="submit" class="inline-flex items-center px-4 py-2 h-12 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700">
                <i class="fas fa-upload mr-2"></i>
                Import menu items
            </button>
        </form>
    </div>
</div>
@endsection
