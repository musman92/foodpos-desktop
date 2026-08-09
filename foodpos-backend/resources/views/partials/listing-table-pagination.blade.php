@props(['paginator'])

@if($paginator->hasPages())
    <div class="px-6 py-4 border-t border-gray-200">
        {{ $paginator->links() }}
    </div>
@endif
