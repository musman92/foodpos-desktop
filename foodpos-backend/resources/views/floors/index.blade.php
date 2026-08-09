@extends('layouts.app')

@section('title', 'Floors')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Floors</h1>
            <p class="mt-1 text-sm text-gray-500">Define levels or areas for each branch (e.g. Ground, Rooftop)</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            @if(show_branch_ui() && $branches->isNotEmpty())
                <form method="get" action="{{ route('floors.index') }}" class="flex items-center gap-2">
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
            <a href="{{ route('floors.create', ['branch_id' => $branchId]) }}"
               class="inline-flex items-center px-4 py-2 h-12 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                <i class="fas fa-plus mr-2"></i> Add Floor
            </a>
        </div>
    </div>

    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="listing-table min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Floor</th>
                        @if(show_branch_ui())<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Branch</th>@endif
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tables</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Sort</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Active</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($floors as $floor)
                        <tr>
                            <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $floor->name }}</td>
                            @if(show_branch_ui())
                            <td class="px-6 py-4 text-sm text-gray-600">{{ $floor->branch->name ?? '—' }}</td>
                            @endif
                            <td class="px-6 py-4 text-sm text-gray-600">{{ $floor->tables_count }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ $floor->sort_order }}</td>
                            <td class="px-6 py-4">
                                @if($floor->is_active)
                                    <span class="text-xs font-medium text-green-700">Yes</span>
                                @else
                                    <span class="text-xs text-gray-400">No</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right text-sm font-medium whitespace-nowrap">
                                <a href="{{ route('floors.show', $floor) }}" class="text-gray-600 hover:text-gray-900 mr-3">View</a>
                                <a href="{{ route('floors.edit', $floor) }}" class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</a>
                                <form action="{{ route('floors.destroy', $floor) }}" method="POST" class="inline" onsubmit="return confirm('Delete this floor? Tables will be unlinked.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                @if($branches->isEmpty())
                                    Create a branch first, then add floors for that location.
                                @else
                                    No floors for this branch yet.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($floors->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">{{ $floors->links() }}</div>
        @endif
    </div>
</div>
@endsection
