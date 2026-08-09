@extends('layouts.app')

@section('title', $table->name)

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <div class="flex items-start justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $table->name }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ $table->branch->name ?? '' }} @if($table->floor) · {{ $table->floor->name }} @endif</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('tables.edit', $table) }}" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700">Edit</a>
            <a href="{{ route('tables.index', ['branch_id' => $table->branch_id]) }}" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">Back</a>
        </div>
    </div>

    <dl class="bg-white shadow rounded-lg divide-y divide-gray-100">
        <div class="px-6 py-4 grid grid-cols-3 gap-4">
            <dt class="text-sm font-medium text-gray-500">Slug</dt>
            <dd class="text-sm text-gray-900 col-span-2"><code class="bg-gray-100 px-2 py-0.5 rounded">{{ $table->slug }}</code></dd>
        </div>
        <div class="px-6 py-4 grid grid-cols-3 gap-4">
            <dt class="text-sm font-medium text-gray-500">Code</dt>
            <dd class="text-sm text-gray-900 col-span-2">{{ $table->code ?? '—' }}</dd>
        </div>
        <div class="px-6 py-4 grid grid-cols-3 gap-4">
            <dt class="text-sm font-medium text-gray-500">Capacity</dt>
            <dd class="text-sm text-gray-900 col-span-2">{{ $table->capacity }}</dd>
        </div>
        <div class="px-6 py-4 grid grid-cols-3 gap-4">
            <dt class="text-sm font-medium text-gray-500">Status</dt>
            <dd class="text-sm text-gray-900 col-span-2 capitalize">{{ str_replace('_', ' ', $table->status) }}</dd>
        </div>
        @if($table->section)
            <div class="px-6 py-4 grid grid-cols-3 gap-4">
                <dt class="text-sm font-medium text-gray-500">Section</dt>
                <dd class="text-sm text-gray-900 col-span-2">{{ $table->section }}</dd>
            </div>
        @endif
        @if($table->notes)
            <div class="px-6 py-4 grid grid-cols-3 gap-4">
                <dt class="text-sm font-medium text-gray-500">Notes</dt>
                <dd class="text-sm text-gray-900 col-span-2">{{ $table->notes }}</dd>
            </div>
        @endif
    </dl>
</div>
@endsection
