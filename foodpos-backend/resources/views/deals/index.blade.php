@extends('layouts.app')

@section('title', 'Deals')

@php
    use Illuminate\Support\Facades\Storage;
@endphp

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Deals</h1>
            <p class="mt-1 text-sm text-gray-500">Combo offers and promotions with discounted prices</p>
        </div>
        <a href="{{ route('deals.create') }}"
           class="inline-flex items-center px-4 py-2 h-12 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
            <i class="fas fa-plus mr-2"></i>
            Add Deal
        </a>
    </div>

    <div class="bg-white shadow rounded-lg overflow-hidden">
        @include('partials.listing-per-page-bar', [
            'action' => route('deals.index'),
            'paginator' => $deals,
            'perPage' => $perPage,
        ])

        <div class="overflow-x-auto">
            <table class="listing-table min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">SN</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Deal</th>
                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Price</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Products</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Valid</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Status</th>
                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($deals as $deal)
                        <tr>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-500">{{ $deals->firstItem() + $loop->index }}</td>
                            <td class="px-3 py-3">
                                <div class="flex items-center gap-3">
                                    @if($deal->image)
                                        <img src="{{ Storage::url($deal->image) }}" alt="" class="h-10 w-10 rounded-lg object-cover flex-shrink-0">
                                    @else
                                        <div class="h-10 w-10 rounded-lg bg-gradient-to-br from-amber-400 to-orange-500 flex items-center justify-center flex-shrink-0">
                                            <i class="fas fa-tags text-white text-sm"></i>
                                        </div>
                                    @endif
                                    <div class="min-w-0">
                                        <div class="font-medium text-gray-900">{{ $deal->title }}</div>
                                        @if($deal->description)
                                            <p class="text-xs text-gray-500 line-clamp-1">{{ Str::limit($deal->description, 50) }}</p>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-right text-indigo-600 font-medium tabular-nums">{{ format_currency($deal->price) }}</td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-600">{{ $deal->menu_items_count }} item(s)</td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-600">
                                @if($deal->start_date || $deal->end_date)
                                    {{ $deal->start_date?->format('M j') ?? '—' }} – {{ $deal->end_date?->format('M j') ?? '—' }}
                                @else
                                    Always
                                @endif
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap">
                                @if($deal->is_active)
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Active</span>
                                @else
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">Inactive</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('deals.edit', $deal) }}" class="text-blue-600 hover:text-blue-800" title="Edit"><i class="fas fa-edit"></i></a>
                                    <form action="{{ route('deals.destroy', $deal) }}" method="POST" class="inline" onsubmit="return confirm('Delete this deal?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-800" title="Delete"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center">
                                    <i class="fas fa-tags text-gray-400 text-4xl mb-4"></i>
                                    <h3 class="text-sm font-medium text-gray-900 mb-1">No deals found</h3>
                                    <p class="text-sm text-gray-500 mb-4">Create a combo offer to sell multiple products at a discounted price.</p>
                                    <a href="{{ route('deals.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                                        <i class="fas fa-plus mr-2"></i>
                                        Add Deal
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @include('partials.listing-table-pagination', ['paginator' => $deals])
    </div>
</div>
@endsection
