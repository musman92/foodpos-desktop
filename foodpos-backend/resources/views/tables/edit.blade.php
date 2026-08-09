@extends('layouts.app')

@section('title', 'Edit Table')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Edit Table</h1>
        <p class="mt-1 text-sm text-gray-500">{{ $table->name }}</p>
    </div>

    <form action="{{ route('tables.update', $table) }}" method="POST" class="bg-white shadow rounded-lg p-6 space-y-6">
        @csrf
        @method('PUT')
        @if(show_branch_ui())
        <div>
            <label for="branch_id" class="block text-sm font-medium text-gray-700 mb-2">Branch <span class="text-red-500">*</span></label>
            <select name="branch_id" id="branch_id" required
                    class="block w-full max-w-md h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('branch_id') border-red-500 @enderror">
                @foreach($branches as $b)
                    <option value="{{ $b->id }}" @selected((int) old('branch_id', $table->branch_id) === (int) $b->id)>
                        {{ $b->name }}@if(auth()->user()->isSuperAdmin() && $b->company) — {{ $b->company->name }}@endif
                    </option>
                @endforeach
            </select>
            @error('branch_id')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
        @else
            <input type="hidden" name="branch_id" value="{{ old('branch_id', $table->branch_id) }}">
        @endif
        <div>
            <label for="floor_id" class="block text-sm font-medium text-gray-700 mb-2">Floor</label>
            <select name="floor_id" id="floor_id"
                    class="block w-full max-w-md h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('floor_id') border-red-500 @enderror">
                <option value="">— None —</option>
                @foreach($floors as $f)
                    <option value="{{ $f->id }}" @selected((int) old('floor_id', $table->floor_id) === (int) $f->id)>{{ $f->name }}</option>
                @endforeach
            </select>
            @error('floor_id')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Name <span class="text-red-500">*</span></label>
            <input type="text" name="name" id="name" value="{{ old('name', $table->name) }}" required
                   class="block w-full max-w-md h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('name') border-red-500 @enderror">
            @error('name')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="slug" class="block text-sm font-medium text-gray-700 mb-2">Slug</label>
            <input type="text" name="slug" id="slug" value="{{ old('slug', $table->slug) }}"
                   class="block w-full max-w-md h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('slug') border-red-500 @enderror"
                   placeholder="URL-safe identifier (unique per company)">
            @error('slug')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
            <p class="mt-1 text-xs text-gray-500">Lowercase, hyphens. Unique within your company. Leave blank on create to auto-generate.</p>
        </div>
        <div>
            <label for="code" class="block text-sm font-medium text-gray-700 mb-2">Short code</label>
            <input type="text" name="code" id="code" value="{{ old('code', $table->code) }}"
                   class="block w-full max-w-md h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('code') border-red-500 @enderror">
            @error('code')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="capacity" class="block text-sm font-medium text-gray-700 mb-2">Capacity (seats)</label>
            <input type="number" name="capacity" id="capacity" value="{{ old('capacity', $table->capacity) }}" min="1" max="500"
                   class="block w-full max-w-md h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('capacity') border-red-500 @enderror">
            @error('capacity')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status <span class="text-red-500">*</span></label>
            <select name="status" id="status" required
                    class="block w-full max-w-md h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('status') border-red-500 @enderror">
                @foreach(['available' => 'Available', 'occupied' => 'Occupied', 'reserved' => 'Reserved', 'dirty' => 'Dirty', 'out_of_service' => 'Out of service'] as $val => $label)
                    <option value="{{ $val }}" @selected(old('status', $table->status) === $val)>{{ $label }}</option>
                @endforeach
            </select>
            @error('status')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="section" class="block text-sm font-medium text-gray-700 mb-2">Section / zone</label>
            <input type="text" name="section" id="section" value="{{ old('section', $table->section) }}"
                   class="block w-full max-w-md h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('section') border-red-500 @enderror">
            @error('section')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
            <textarea name="notes" id="notes" rows="2"
                      class="block w-full max-w-md px-4 py-3 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('notes') border-red-500 @enderror">{{ old('notes', $table->notes) }}</textarea>
            @error('notes')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <div class="flex items-center gap-3 pt-4 border-t border-gray-200">
            <a href="{{ route('tables.index', ['branch_id' => $table->branch_id]) }}" class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">Cancel</a>
            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700">Update Table</button>
        </div>
    </form>
</div>
@endsection
