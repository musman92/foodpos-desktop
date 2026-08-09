@extends('layouts.app')

@section('title', 'Create Role')

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Create Role</h1>
        <p class="mt-1 text-sm text-gray-500">Add a new role and assign permissions</p>
    </div>

    <form action="{{ route('roles.store') }}" method="POST" class="bg-white shadow rounded-lg p-6 space-y-6">
        @csrf
        <div>
            <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Role name</label>
            <input type="text" name="name" id="name" value="{{ old('name') }}" required
                   class="block w-full max-w-md h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('name') border-red-500 @enderror"
                   placeholder="e.g. Manager, Cashier">
            @error('name')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Permissions</label>
            <p class="text-xs text-gray-500 mb-3">Select which permissions this role has</p>
            <div class="space-y-4 border border-gray-200 rounded-lg p-4 bg-gray-50">
                @foreach($permissions as $group => $perms)
                    <div>
                        <p class="text-xs font-semibold text-gray-600 uppercase mb-2">{{ $group }}</p>
                        <div class="flex flex-wrap gap-3">
                            @foreach($perms as $permission)
                                <label class="inline-flex items-center" title="{{ $permission['name'] }}">
                                    <input type="checkbox" name="permissions[]" value="{{ $permission['name'] }}"
                                           {{ in_array($permission['name'], old('permissions', [])) ? 'checked' : '' }}
                                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                    <span class="ml-2 text-sm text-gray-700">{{ $permission['label'] }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200">
            <a href="{{ route('roles.index') }}" class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">Cancel</a>
            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                <i class="fas fa-save mr-2"></i> Create Role
            </button>
        </div>
    </form>
</div>
@endsection
