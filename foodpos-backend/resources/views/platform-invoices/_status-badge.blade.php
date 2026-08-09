@php
    $classes = match ($status) {
        'draft' => 'bg-gray-100 text-gray-800',
        'sent' => 'bg-blue-100 text-blue-800',
        'partial' => 'bg-amber-100 text-amber-800',
        'paid' => 'bg-green-100 text-green-800',
        'void' => 'bg-red-100 text-red-800',
        default => 'bg-gray-100 text-gray-800',
    };
@endphp
<span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full {{ $classes }}">
    {{ config('platform_billing.statuses.'.$status, ucfirst($status)) }}
    @if(! empty($overdue))
        <span class="ml-1 text-red-700">· Overdue</span>
    @endif
</span>
