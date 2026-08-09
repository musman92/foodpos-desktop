@extends('layouts.app')

@section('title', 'Tables')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Tables</h1>
            <p class="mt-1 text-sm text-gray-500">Manage dine-in tables per branch and floor</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            @if(show_branch_ui() && $branches->isNotEmpty())
                <form method="get" action="{{ route('tables.index') }}" class="flex items-center gap-2">
                    <label for="branch_id" class="text-sm text-gray-600 whitespace-nowrap">Branch</label>
                    <select name="branch_id" id="branch_id" onchange="this.form.submit()"
                            class="block filter-control min-w-[12rem]">
                        @foreach($branches as $b)
                            <option value="{{ $b->id }}" @selected((int) $branchId === (int) $b->id)>
                                {{ $b->name }}@if(auth()->user()->isSuperAdmin() && $b->company) — {{ $b->company->name }}@endif
                            </option>
                        @endforeach
                    </select>
                </form>
            @endif
            <a href="{{ $branchId ? route('tables.create', ['branch_id' => $branchId]) : '#' }}"
               @if(!$branchId) onclick="return false;" class="inline-flex items-center px-4 py-2 h-12 bg-gray-300 rounded-lg font-semibold text-xs text-white cursor-not-allowed"
               @else class="inline-flex items-center px-4 py-2 h-12 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700" @endif>
                <i class="fas fa-plus mr-2"></i> Add Table
            </a>
        </div>
    </div>

    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="listing-table min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Table</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Slug</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Floor</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Capacity</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($tables as $table)
                        <tr>
                            <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $table->name }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600"><code class="text-xs bg-gray-50 px-1 rounded">{{ $table->slug }}</code></td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ $table->code ?? '—' }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ $table->floor->name ?? '—' }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ $table->capacity }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600 capitalize">{{ str_replace('_', ' ', $table->status) }}</td>
                            <td class="px-6 py-4 text-right text-sm font-medium whitespace-nowrap">
                                <a href="{{ route('tables.show', $table) }}" class="text-gray-600 hover:text-gray-900 mr-3">View</a>
                                <a href="{{ route('tables.edit', $table) }}" class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</a>
                                <form action="{{ route('tables.destroy', $table) }}" method="POST" class="inline" onsubmit="return confirm('Delete this table?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                @if($branches->isEmpty())
                                    Create a branch first, then add tables.
                                @else
                                    No tables for this branch. Create floors first, then add tables.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($tables->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">{{ $tables->links() }}</div>
        @endif
    </div>
</div>
@endsection
