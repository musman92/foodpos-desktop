@props(['routeName'])

<div class="relative" x-data="{ exportOpen: false }">
    <button type="button"
            @click="exportOpen = !exportOpen"
            class="inline-flex items-center px-4 py-2 h-12 border border-gray-300 rounded-lg font-semibold text-xs text-gray-700 uppercase tracking-widest bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
        <i class="fas fa-file-export mr-2"></i>
        Export
        <i class="fas fa-chevron-down ml-2 text-[10px] text-gray-400"></i>
    </button>

    <div x-show="exportOpen"
         x-cloak
         @click.away="exportOpen = false"
         x-transition:enter="transition ease-out duration-100"
         x-transition:enter-start="transform opacity-0 scale-95"
         x-transition:enter-end="transform opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-75"
         x-transition:leave-start="transform opacity-100 scale-100"
         x-transition:leave-end="transform opacity-0 scale-95"
         class="absolute right-0 mt-2 w-48 rounded-lg shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50">
        <div class="py-1">
            <a href="{{ route($routeName, ['format' => 'xlsx']) }}"
               class="flex items-center px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50">
                <i class="fas fa-file-excel mr-3 text-green-600 w-4 text-center"></i>
                Excel (.xlsx)
            </a>
            <a href="{{ route($routeName, ['format' => 'csv']) }}"
               class="flex items-center px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50">
                <i class="fas fa-file-csv mr-3 text-indigo-600 w-4 text-center"></i>
                CSV (.csv)
            </a>
        </div>
    </div>
</div>
