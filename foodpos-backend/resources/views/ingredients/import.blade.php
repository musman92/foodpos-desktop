@extends('layouts.app')

@section('title', 'Import Ingredients')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Import Ingredients</h1>
            <p class="mt-1 text-sm text-gray-500">Upload a CSV or Excel file to create or update ingredients in bulk.</p>
        </div>
        <a href="{{ route('ingredients.index') }}"
           class="inline-flex items-center px-4 py-2 h-12 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
            <i class="fas fa-arrow-left mr-2"></i>
            Back to Ingredients
        </a>
    </div>
    @if(!empty($importResult))
        @php $result = $importResult; @endphp
        <div class="bg-white shadow rounded-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Import Summary</h2>
            </div>
            <div class="px-6 py-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="rounded-lg bg-green-50 border border-green-100 p-4">
                    <p class="text-xs font-medium uppercase text-green-700">Created</p>
                    <p class="mt-1 text-2xl font-bold text-green-900">{{ $result['created'] ?? 0 }}</p>
                </div>
                <div class="rounded-lg bg-blue-50 border border-blue-100 p-4">
                    <p class="text-xs font-medium uppercase text-blue-700">Updated</p>
                    <p class="mt-1 text-2xl font-bold text-blue-900">{{ $result['updated'] ?? 0 }}</p>
                </div>
                <div class="rounded-lg bg-indigo-50 border border-indigo-100 p-4">
                    <p class="text-xs font-medium uppercase text-indigo-700">Restored</p>
                    <p class="mt-1 text-2xl font-bold text-indigo-900">{{ $result['restored'] ?? 0 }}</p>
                </div>
                <div class="rounded-lg bg-amber-50 border border-amber-100 p-4">
                    <p class="text-xs font-medium uppercase text-amber-700">Skipped</p>
                    <p class="mt-1 text-2xl font-bold text-amber-900">{{ $result['skipped'] ?? 0 }}</p>
                </div>
            </div>

            @if(!empty($result['errors']))
                <div class="px-6 pb-6">
                    <h3 class="text-sm font-semibold text-gray-900 mb-3">Row errors</h3>
                    <div class="overflow-x-auto border border-gray-200 rounded-lg">
                        <table class="listing-table min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase tracking-wider w-24">Row</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Message</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                @foreach($result['errors'] as $error)
                                    <tr>
                                        <td class="px-4 py-2 whitespace-nowrap text-gray-700">{{ $error['row'] ?? '—' }}</td>
                                        <td class="px-4 py-2 text-red-700">{{ $error['message'] ?? 'Unknown error' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    @endif

    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        Import ingredient categories and units first. Rows reference them by <strong>category_code</strong>, <strong>purchase_unit_code</strong>, and <strong>consumption_unit_code</strong>. Excel may strip leading letters from codes (e.g. <strong>C24</strong> becomes <strong>24</strong>) — the importer handles that. Previously deleted categories, units, and ingredients are restored when matched on re-import.
    </div>

    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Download sample file</h2>
            <p class="mt-1 text-sm text-gray-500">Use this template to prepare your ingredients before uploading.</p>
        </div>
        <div class="px-6 py-5 flex flex-wrap gap-3">
            <a href="{{ route('ingredients.import.sample', ['format' => 'xlsx']) }}"
               class="inline-flex items-center px-4 py-2 h-11 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                <i class="fas fa-file-excel mr-2 text-green-600"></i>
                Download Excel sample
            </a>
            <a href="{{ route('ingredients.import.sample', ['format' => 'csv']) }}"
               class="inline-flex items-center px-4 py-2 h-11 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                <i class="fas fa-file-csv mr-2 text-indigo-600"></i>
                Download CSV sample
            </a>
        </div>
    </div>

    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Export current data</h2>
            <p class="mt-1 text-sm text-gray-500">Download your ingredients in the same format used for import.</p>
        </div>
        <div class="px-6 py-5">
            @include('partials.catalog-export-actions', ['routeName' => 'ingredients.export'])
        </div>
    </div>

    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Upload file</h2>
            <p class="mt-1 text-sm text-gray-500">Accepted formats: CSV, XLSX, XLS (max 5 MB, up to 1,000 rows).</p>
        </div>
        <form action="{{ route('ingredients.import.store') }}" method="POST" enctype="multipart/form-data" class="px-6 py-6 space-y-5">
            @csrf
            <div>
                <label for="file" class="block text-sm font-medium text-gray-700 mb-2">
                    Choose file <span class="text-red-500">*</span>
                </label>
                <input type="file"
                       name="file"
                       id="file"
                       accept=".csv,.txt,.xlsx,.xls,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel"
                       required
                       class="block w-full text-sm text-gray-700 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                @error('file')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center justify-end gap-3 pt-2">
                <a href="{{ route('ingredients.index') }}"
                   class="px-4 py-2 h-12 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    Cancel
                </a>
                <button type="submit"
                        class="inline-flex items-center px-4 py-2 h-12 bg-indigo-600 border border-transparent rounded-lg text-sm font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    <i class="fas fa-upload mr-2"></i>
                    Import ingredients
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Column reference</h2>
        </div>
        <div class="px-6 py-5 overflow-x-auto">
            <table class="listing-table min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Column</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Required</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    <tr>
                        <td class="px-4 py-3 font-mono text-gray-900">sku</td>
                        <td class="px-4 py-3 text-gray-600">No</td>
                        <td class="px-4 py-3 text-gray-600">Ingredient code. Leave blank to auto-assign. If the sku exists (including previously deleted items), that ingredient is updated and restored.</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 font-mono text-gray-900">name</td>
                        <td class="px-4 py-3 text-gray-600">Yes</td>
                        <td class="px-4 py-3 text-gray-600">Ingredient display name.</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 font-mono text-gray-900">category_code</td>
                        <td class="px-4 py-3 text-gray-600">Yes*</td>
                        <td class="px-4 py-3 text-gray-600">Code of an existing ingredient category. You can use category_name instead. Codes like C02 may be read as 2 from Excel — both are accepted.</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 font-mono text-gray-900">purchase_unit_code</td>
                        <td class="px-4 py-3 text-gray-600">Yes</td>
                        <td class="px-4 py-3 text-gray-600">Code (or name) of the purchase unit.</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 font-mono text-gray-900">consumption_unit_code</td>
                        <td class="px-4 py-3 text-gray-600">Yes</td>
                        <td class="px-4 py-3 text-gray-600">Code (or name) of the consumption unit.</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 font-mono text-gray-900">conversion_rate</td>
                        <td class="px-4 py-3 text-gray-600">Yes</td>
                        <td class="px-4 py-3 text-gray-600">Consumption units in 1 purchase unit (must be &gt; 0).</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 font-mono text-gray-900">purchase_price</td>
                        <td class="px-4 py-3 text-gray-600">Yes</td>
                        <td class="px-4 py-3 text-gray-600">Price for one purchase unit.</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 font-mono text-gray-900">min_stock_level</td>
                        <td class="px-4 py-3 text-gray-600">No</td>
                        <td class="px-4 py-3 text-gray-600">Low stock alert threshold in consumption units. Defaults to 0.</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 font-mono text-gray-900">max_stock_level</td>
                        <td class="px-4 py-3 text-gray-600">No</td>
                        <td class="px-4 py-3 text-gray-600">Optional maximum stock level.</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 font-mono text-gray-900">track_stock</td>
                        <td class="px-4 py-3 text-gray-600">No</td>
                        <td class="px-4 py-3 text-gray-600">yes/no. Defaults to yes.</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 font-mono text-gray-900">is_active</td>
                        <td class="px-4 py-3 text-gray-600">No</td>
                        <td class="px-4 py-3 text-gray-600">yes/no. Defaults to yes.</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 font-mono text-gray-900">description</td>
                        <td class="px-4 py-3 text-gray-600">No</td>
                        <td class="px-4 py-3 text-gray-600">Optional notes.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
