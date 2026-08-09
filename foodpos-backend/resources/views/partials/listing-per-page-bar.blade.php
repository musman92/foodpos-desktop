@props([
    'action',
    'paginator',
    'perPage',
])

<div class="px-4 py-3 border-b border-gray-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
    <form method="GET" action="{{ $action }}" class="flex items-center gap-2 text-sm text-gray-600">
        @foreach(request()->except(['per_page', 'page']) as $key => $value)
            @if(is_array($value))
                @foreach($value as $item)
                    <input type="hidden" name="{{ $key }}[]" value="{{ $item }}">
                @endforeach
            @else
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endif
        @endforeach
        <label for="per_page" class="whitespace-nowrap">Entries</label>
        <select name="per_page"
                id="per_page"
                onchange="this.form.submit()"
                class="rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            @foreach(\App\Support\ListingPerPage::OPTIONS as $size)
                <option value="{{ $size }}" @selected((int) $perPage === $size)>{{ $size }}</option>
            @endforeach
        </select>
    </form>
    <p class="text-sm text-gray-500">
        Showing {{ $paginator->firstItem() ?? 0 }}–{{ $paginator->lastItem() ?? 0 }} of {{ $paginator->total() }}
    </p>
</div>
