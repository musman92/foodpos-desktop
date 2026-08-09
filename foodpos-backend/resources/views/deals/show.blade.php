@extends('layouts.app')

@section('title', $deal->title)

@php use Illuminate\Support\Facades\Storage; @endphp

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <a href="{{ route('deals.index') }}" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium"><i class="fas fa-arrow-left mr-1"></i> Back to Deals</a>
        <a href="{{ route('deals.edit', $deal) }}" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700">Edit</a>
    </div>
    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="p-6">
            <div class="flex gap-6">
                @if($deal->image)
                    <img src="{{ Storage::url($deal->image) }}" alt="" class="h-32 w-32 rounded-lg object-cover flex-shrink-0">
                @else
                    <div class="h-32 w-32 rounded-lg bg-gradient-to-br from-amber-400 to-orange-500 flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-tags text-white text-4xl"></i>
                    </div>
                @endif
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">{{ $deal->title }}</h1>
                    <p class="text-lg font-semibold text-indigo-600 mt-1">{{ number_format($deal->price, 2) }}</p>
                    @if($deal->description)
                        <p class="mt-2 text-gray-600">{{ $deal->description }}</p>
                    @endif
                    <p class="mt-2">
                        @if($deal->is_active)
                            <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">Active</span>
                        @else
                            <span class="px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-800">Inactive</span>
                        @endif
                    </p>
                </div>
            </div>
            <div class="mt-6 pt-6 border-t border-gray-200">
                <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wider mb-2">Products in this deal</h2>
                <ul class="space-y-2">
                    @foreach($deal->menuItems as $mi)
                        <li class="flex justify-between text-sm">
                            <span>
                                {{ $mi->name }}
                                @if($mi->pivot->option_name)
                                    <span class="text-gray-500">({{ $mi->pivot->option_name }})</span>
                                @endif
                            </span>
                            <span class="text-gray-500">
                                Qty: {{ $mi->pivot->quantity }}
                                @if($mi->pivot->unit_price !== null)
                                    · {{ number_format($mi->pivot->unit_price, 2) }} each
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
