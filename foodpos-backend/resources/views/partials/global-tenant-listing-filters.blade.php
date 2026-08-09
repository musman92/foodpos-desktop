@props([
    'action',
    'search' => '',
    'searchPlaceholder' => 'Search by name…',
    'searchLabel' => 'Search',
])

<div class="bg-white shadow rounded-lg p-4 sm:p-6">
    <form method="GET" action="{{ $action }}" class="flex flex-col gap-4 lg:flex-row lg:flex-wrap lg:items-end">
        <div class="flex-1 min-w-[200px]">
            <label for="search" class="block text-sm font-medium text-gray-700 mb-1">{{ $searchLabel }}</label>
            <input type="search"
                   name="search"
                   id="search"
                   value="{{ $search }}"
                   placeholder="{{ $searchPlaceholder }}"
                   class="block w-full filter-control">
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="submit"
                    class="inline-flex items-center justify-center h-11 px-4 rounded-lg bg-indigo-600 text-sm font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                <i class="fas fa-filter mr-2"></i>
                Apply
            </button>
            <a href="{{ $action }}"
               class="inline-flex items-center justify-center h-11 px-4 rounded-lg border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                Clear
            </a>
        </div>
    </form>
</div>
