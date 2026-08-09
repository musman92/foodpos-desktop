@extends('layouts.app')

@section('title', 'Import Customers')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Import Customers</h1>
            <p class="mt-1 text-sm text-gray-500">Upload a CSV or Excel file to create or update customers in bulk.</p>
        </div>
        <a href="{{ route('customers.index') }}"
           class="inline-flex items-center px-4 py-2 h-12 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
            <i class="fas fa-arrow-left mr-2"></i>
            Back to Customers
        </a>
    </div>
    @if(!empty($importResult))
        @php
            $result = $importResult;
        @endphp
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

    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Download sample file</h2>
            <p class="mt-1 text-sm text-gray-500">Use this template to prepare your customers before uploading.</p>
        </div>
        <div class="px-6 py-5 flex flex-wrap gap-3">
            <a href="{{ route('customers.import.sample', ['format' => 'xlsx']) }}"
               class="inline-flex items-center px-4 py-2 h-11 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                <i class="fas fa-file-excel mr-2 text-green-600"></i>
                Download Excel sample
            </a>
            <a href="{{ route('customers.import.sample', ['format' => 'csv']) }}"
               class="inline-flex items-center px-4 py-2 h-11 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                <i class="fas fa-file-csv mr-2 text-indigo-600"></i>
                Download CSV sample
            </a>
        </div>
    </div>

    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Export current data</h2>
            <p class="mt-1 text-sm text-gray-500">Download your customers in the same format used for import.</p>
        </div>
        <div class="px-6 py-5">
            @include('partials.catalog-export-actions', ['routeName' => 'customers.export'])
        </div>
    </div>

    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Upload file</h2>
            <p class="mt-1 text-sm text-gray-500">Accepted formats: CSV, XLSX, XLS (max 5 MB, up to 1,000 rows).</p>
        </div>
        <form action="{{ route('customers.import.store') }}" method="POST" enctype="multipart/form-data" class="px-6 py-6 space-y-5">
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
                <a href="{{ route('customers.index') }}"
                   class="px-4 py-2 h-12 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    Cancel
                </a>
                <button type="submit"
                        class="inline-flex items-center px-4 py-2 h-12 bg-indigo-600 border border-transparent rounded-lg text-sm font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    <i class="fas fa-upload mr-2"></i>
                    Import customers
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
                        <td class="px-4 py-3 font-mono text-gray-900">code</td>
                        <td class="px-4 py-3 text-gray-600">No</td>
                        <td class="px-4 py-3 text-gray-600">Leave blank to auto-assign CU01, CU02, etc. If the code already exists, that customer is updated.</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 font-mono text-gray-900">name</td>
                        <td class="px-4 py-3 text-gray-600">Yes</td>
                        <td class="px-4 py-3 text-gray-600">Customer full name.</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 font-mono text-gray-900">email</td>
                        <td class="px-4 py-3 text-gray-600">No</td>
                        <td class="px-4 py-3 text-gray-600">Email address.</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 font-mono text-gray-900">phone</td>
                        <td class="px-4 py-3 text-gray-600">No</td>
                        <td class="px-4 py-3 text-gray-600">Optional. When provided, must be unique within your company (duplicates in the file or with existing customers are rejected).</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 font-mono text-gray-900">date_of_birth</td>
                        <td class="px-4 py-3 text-gray-600">No</td>
                        <td class="px-4 py-3 text-gray-600">Use YYYY-MM-DD format.</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 font-mono text-gray-900">gender</td>
                        <td class="px-4 py-3 text-gray-600">No</td>
                        <td class="px-4 py-3 text-gray-600">male, female, or other.</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 font-mono text-gray-900">balance</td>
                        <td class="px-4 py-3 text-gray-600">No</td>
                        <td class="px-4 py-3 text-gray-600">Opening balance. Positive = customer owes you; negative = you owe the customer (advance/credit). Defaults to 0 on create; leave blank on update to keep the current balance.</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 font-mono text-gray-900">notes</td>
                        <td class="px-4 py-3 text-gray-600">No</td>
                        <td class="px-4 py-3 text-gray-600">Optional notes.</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 font-mono text-gray-900">is_active</td>
                        <td class="px-4 py-3 text-gray-600">No</td>
                        <td class="px-4 py-3 text-gray-600">Use yes/no, true/false, or 1/0. Defaults to yes.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
