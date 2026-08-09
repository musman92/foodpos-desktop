@extends('layouts.app')

@section('title', 'Edit Floor')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Edit Floor</h1>
        <p class="mt-1 text-sm text-gray-500">{{ $floor->name }}</p>
    </div>

    <form action="{{ route('floors.update', $floor) }}" method="POST" class="bg-white shadow rounded-lg p-6 space-y-6">
        @csrf
        @method('PUT')
        @if(show_branch_ui())
        <div>
            <label for="branch_id" class="block text-sm font-medium text-gray-700 mb-2">Branch <span class="text-red-500">*</span></label>
            <select name="branch_id" id="branch_id" required
                    class="block w-full max-w-md h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('branch_id') border-red-500 @enderror">
                @foreach($branches as $b)
                    <option value="{{ $b->id }}" @selected((int) old('branch_id', $floor->branch_id) === (int) $b->id)>
                        {{ $b->name }}@if(auth()->user()->isSuperAdmin() && $b->company) — {{ $b->company->name }}@endif
                    </option>
                @endforeach
            </select>
            @error('branch_id')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
        @else
            <input type="hidden" name="branch_id" value="{{ old('branch_id', $floor->branch_id) }}">
        @endif
        <div>
            <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Floor name <span class="text-red-500">*</span></label>
            <input type="text" name="name" id="name" value="{{ old('name', $floor->name) }}" required
                   class="block w-full max-w-md h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('name') border-red-500 @enderror">
            @error('name')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="sort_order" class="block text-sm font-medium text-gray-700 mb-2">Sort order</label>
            <input type="number" name="sort_order" id="sort_order" value="{{ old('sort_order', $floor->sort_order) }}" min="0"
                   class="block w-full max-w-md h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('sort_order') border-red-500 @enderror">
            @error('sort_order')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <div class="flex items-center">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" id="is_active" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" @checked(old('is_active', $floor->is_active))>
            <label for="is_active" class="ml-2 text-sm text-gray-700">Active</label>
        </div>
        <div class="flex items-center gap-3 pt-4 border-t border-gray-200">
            <a href="{{ route('floors.index', ['branch_id' => $floor->branch_id]) }}" class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">Cancel</a>
            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700">Update Floor</button>
        </div>
    </form>
</div>
@endsection
