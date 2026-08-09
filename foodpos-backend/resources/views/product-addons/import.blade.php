@extends('layouts.app')

@section('title', 'Import Product Addons')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Import Product Addons</h1>
            <p class="mt-1 text-sm text-gray-500">Upload a CSV or Excel file to create or update addons and recipe lines in bulk.</p>
        </div>
        <a href="{{ route('product-addons.index') }}"
           class="inline-flex items-center px-4 py-2 h-12 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
            <i class="fas fa-arrow-left mr-2"></i>
            Back to Addons
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
                            <thead class="bg-gray-50"><tr><th class="px-4 py-2 text-left">Row</th><th class="px-4 py-2 text-left">Message</th></tr></thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach($result['errors'] as $error)
                                    <tr><td class="px-4 py-2">{{ $error['row'] ?? '—' }}</td><td class="px-4 py-2 text-red-700">{{ $error['message'] ?? '' }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    @endif

    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200"><h2 class="text-lg font-semibold text-gray-900">Download sample</h2></div>
        <div class="px-6 py-5 flex flex-wrap gap-3">
            <a href="{{ route('product-addons.import.sample', ['format' => 'xlsx']) }}" class="inline-flex items-center px-4 py-2 h-11 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"><i class="fas fa-file-excel mr-2 text-green-600"></i>Excel sample</a>
            <a href="{{ route('product-addons.import.sample', ['format' => 'csv']) }}" class="inline-flex items-center px-4 py-2 h-11 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"><i class="fas fa-file-csv mr-2 text-indigo-600"></i>CSV sample</a>
        </div>
    </div>

    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Export current data</h2>
            <p class="mt-1 text-sm text-gray-500">Download your addons in the same format used for import.</p>
        </div>
        <div class="px-6 py-5">
            @include('partials.catalog-export-actions', ['routeName' => 'product-addons.export'])
        </div>
    </div>

    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200"><h2 class="text-lg font-semibold text-gray-900">Upload file</h2></div>
        <form action="{{ route('product-addons.import.store') }}" method="POST" enctype="multipart/form-data" class="px-6 py-6 space-y-5">
            @csrf
            <input type="file" name="file" required accept=".csv,.txt,.xlsx,.xls" class="block w-full text-sm text-gray-700 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-indigo-50 file:text-indigo-700">
            @error('file')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            <button type="submit" class="inline-flex items-center px-4 py-2 h-12 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700"><i class="fas fa-upload mr-2"></i>Import addons</button>
        </form>
    </div>
</div>
@endsection
