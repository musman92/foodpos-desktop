@extends('layouts.app')

@section('title', 'Platform Media Library')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Platform Media Library</h1>
            <p class="mt-1 text-sm text-gray-500">Upload popular product images by category — tenants browse by category when adding menu items.</p>
        </div>
    </div>

    <div class="bg-white shadow rounded-lg p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Upload image</h2>
        <form action="{{ route('platform-media.store') }}" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
            @csrf
            <div class="lg:col-span-2">
                <label for="image" class="block text-sm font-medium text-gray-700 mb-2">Image file <span class="text-red-500">*</span></label>
                <input type="file" name="image" id="image" accept="image/*" required
                       class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                <p class="mt-1 text-xs text-gray-500">JPG, PNG, GIF, or WebP up to 2MB. Stored as 1024×1024 WebP for fast POS and future tenant websites.</p>
                @error('image')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="title" class="block text-sm font-medium text-gray-700 mb-2">Title</label>
                <input type="text" name="title" id="title" value="{{ old('title') }}" placeholder="Margherita Pizza"
                       class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label for="category" class="block text-sm font-medium text-gray-700 mb-2">Category <span class="text-red-500">*</span></label>
                <select name="category" id="category" required
                        class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Select category</option>
                    @foreach($categoryOptions as $option)
                        <option value="{{ $option }}" @selected(old('category', $categoryFilter) === $option)>{{ $option }}</option>
                    @endforeach
                </select>
                @error('category')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div class="md:col-span-2 lg:col-span-4 flex items-center justify-between">
                <label class="flex items-center text-sm text-gray-600">
                    <input type="checkbox" name="is_active" value="1" checked class="rounded border-gray-300 text-indigo-600 mr-2">
                    Active (visible to tenants)
                </label>
                <button type="submit" class="inline-flex items-center px-4 py-2 h-12 bg-indigo-600 text-white rounded-lg font-semibold text-sm hover:bg-indigo-700">
                    <i class="fas fa-upload mr-2"></i> Upload
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 space-y-3">
            <h2 class="text-lg font-semibold text-gray-900">{{ $media->total() }} images</h2>
            <div class="flex gap-2 flex-wrap">
                <a href="{{ route('platform-media.index') }}"
                   class="px-3 py-1.5 rounded-full text-sm font-medium {{ ! $categoryFilter ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                    All
                </a>
                @foreach($categoriesWithCounts as $cat)
                    <a href="{{ route('platform-media.index', ['category' => $cat['name']]) }}"
                       class="px-3 py-1.5 rounded-full text-sm font-medium {{ $categoryFilter === $cat['name'] ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                        {{ $cat['name'] }} ({{ $cat['count'] }})
                    </a>
                @endforeach
            </div>
        </div>
        @if($media->isEmpty())
            <div class="px-6 py-16 text-center text-sm text-gray-500">No images in this category yet.</div>
        @else
            <div class="p-6 grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
                @foreach($media as $item)
                    <div class="border border-gray-200 rounded-lg overflow-hidden bg-gray-50">
                        <div class="aspect-square bg-gray-100">
                            <img src="{{ $item->url }}" alt="{{ $item->alt_text ?? $item->title }}" class="w-full h-full object-cover">
                        </div>
                        <div class="p-2 space-y-2">
                            <p class="text-xs font-medium text-gray-900 truncate" title="{{ $item->title }}">{{ $item->title }}</p>
                            @if($item->category)
                                <span class="inline-block px-2 py-0.5 text-xs font-medium rounded-full bg-indigo-100 text-indigo-800">{{ $item->category }}</span>
                            @endif
                            <div class="flex items-center justify-between">
                                <span class="text-xs {{ $item->is_active ? 'text-green-600' : 'text-red-600' }}">{{ $item->is_active ? 'Active' : 'Hidden' }}</span>
                                <form action="{{ route('platform-media.destroy', $item) }}" method="POST" onsubmit="return confirm('Delete this image from the library?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-800 text-xs" title="Delete"><i class="fas fa-trash"></i></button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            @if($media->hasPages())
                <div class="px-6 py-4 border-t border-gray-200">{{ $media->links() }}</div>
            @endif
        @endif
    </div>
</div>
@endsection
