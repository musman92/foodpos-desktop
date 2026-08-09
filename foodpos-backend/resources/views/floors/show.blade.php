@extends('layouts.app')

@section('title', $floor->name)

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-start justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $floor->name }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ $floor->branch->name ?? '' }} · {{ $floor->is_active ? 'Active' : 'Inactive' }}</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('floors.edit', $floor) }}" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700">Edit</a>
            <a href="{{ route('floors.index', ['branch_id' => $floor->branch_id]) }}" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">Back</a>
        </div>
    </div>

    <div class="bg-white shadow rounded-lg p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Tables on this floor</h2>
        @if($floor->tables->isEmpty())
            <p class="text-sm text-gray-500">No tables assigned. <a href="{{ route('tables.create', ['branch_id' => $floor->branch_id]) }}" class="text-indigo-600 hover:underline">Add a table</a></p>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach($floor->tables as $t)
                    <li class="py-3 flex justify-between text-sm">
                        <span class="font-medium text-gray-900">{{ $t->name }}</span>
                        <span class="text-gray-500">{{ $t->capacity }} seats · {{ $t->status }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
@endsection
