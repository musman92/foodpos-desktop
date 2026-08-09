@extends('layouts.app')

@section('title', 'Import Recipes')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Import Recipes</h1>
            <p class="mt-1 text-sm text-gray-500">Upload a CSV or Excel file to create or update recipes and their ingredients in bulk.</p>
        </div>
        <a href="{{ route('recipes.index') }}"
           class="inline-flex items-center px-4 py-2 h-12 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
            <i class="fas fa-arrow-left mr-2"></i>
            Back to Recipes
        </a>
    </div>
    @if(!empty($importResult))
        @php $result = $importResult; @endphp
        <div class="bg-white shadow rounded-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Import Summary</h2>
            </div>
            <div class="px-6 py-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="rounded-lg bg-green-50 border border-green-100 p-4">
                    <p class="text-xs font-medium uppercase text-green-700">Created</p>
                    <p class="mt-1 text-2xl font-bold text-green-900">{{ $result['created'] ?? 0 }}</p>
                </div>
                <div class="rounded-lg bg-blue-50 border border-blue-100 p-4">
                    <p class="text-xs font-medium uppercase text-blue-700">Updated</p>
                    <p class="mt-1 text-2xl font-bold text-blue-900">{{ $result['updated'] ?? 0 }}</p>
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

    <div class="rounded-lg border border-indigo-100 bg-indigo-50/60 px-4 py-3 text-sm text-indigo-900">
        <p class="font-medium">Long-format rows</p>
        <p class="mt-1 text-indigo-800">
            Use <strong>one row per ingredient</strong>. Repeat the same
            <code class="font-mono text-xs bg-white/70 px-1 rounded">recipe_code</code>
            for each line of that recipe. Blank recipe columns inherit from the row above.
            Ingredients must already exist (matched by SKU or exact name).
        </p>
        <p class="mt-2 text-indigo-800">
            <strong>Unit column:</strong> use the ingredient's consumption or purchase unit
            (<code class="font-mono text-xs bg-white/70 px-1 rounded">Gram</code>,
            <code class="font-mono text-xs bg-white/70 px-1 rounded">Kilogram</code>,
            <code class="font-mono text-xs bg-white/70 px-1 rounded">Piece</code>),
            common abbreviations (<code class="font-mono text-xs bg-white/70 px-1 rounded">g</code>,
            <code class="font-mono text-xs bg-white/70 px-1 rounded">kg</code>,
            <code class="font-mono text-xs bg-white/70 px-1 rounded">pcs</code>),
            or leave blank to default to the consumption unit.
            Unit codes (e.g. C20) are company-specific — use names or abbreviations in spreadsheets.
        </p>
    </div>

    <div class="bg-white shadow rounded-lg p-6 space-y-6">
        <div>
            <h2 class="text-lg font-semibold text-gray-900 mb-2">1. Download sample</h2>
            <p class="text-sm text-gray-600 mb-3">Expected columns:</p>
            <div class="flex flex-wrap gap-2 mb-4">
                @foreach($expectedHeaders as $header)
                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-mono bg-gray-100 text-gray-800">{{ $header }}</span>
                @endforeach
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('recipes.import.sample', ['format' => 'xlsx']) }}"
                   class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    <i class="fas fa-file-excel mr-2 text-green-600"></i> Sample Excel
                </a>
                <a href="{{ route('recipes.import.sample', ['format' => 'csv']) }}"
                   class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    <i class="fas fa-file-csv mr-2 text-indigo-600"></i> Sample CSV
                </a>
            </div>
        </div>

        <div class="border-t border-gray-200 pt-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-2">2. Upload file</h2>
            <form action="{{ route('recipes.import.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <div>
                    <input type="file" name="file" accept=".csv,.xlsx,.xls,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                           required
                           class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                    @error('file')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit"
                        class="inline-flex items-center px-4 py-2 h-12 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                    <i class="fas fa-upload mr-2"></i>
                    Import Recipes
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
