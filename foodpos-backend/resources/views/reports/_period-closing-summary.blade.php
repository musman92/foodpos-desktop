<dl class="space-y-2 text-sm">
    <div class="flex justify-between gap-3">
        <dt class="text-gray-700">Total sale</dt>
        <dd class="font-semibold text-gray-900 tabular-nums">{{ format_currency($closing['total_sale']) }}</dd>
    </div>
    <div class="flex justify-between gap-3">
        <dt class="text-gray-700">COGS</dt>
        <dd class="font-medium text-gray-900 tabular-nums">{{ format_currency($closing['cogs_total'] ?? $closing['purchase_total']) }}</dd>
    </div>
    <div class="flex justify-between gap-3">
        <dt class="text-gray-700">Expenses</dt>
        <dd class="font-medium text-gray-900 tabular-nums">{{ format_currency($closing['expense_total']) }}</dd>
    </div>
    <div class="flex justify-between gap-3 pt-2 border-t border-gray-200">
        <dt class="font-semibold text-gray-900">Total</dt>
        <dd class="font-bold tabular-nums {{ $closing['pnl'] >= 0 ? 'text-green-700' : 'text-red-700' }}">{{ format_currency($closing['pnl']) }}</dd>
    </div>
    @if($closing['stock_in_hand'] !== null)
        <div class="flex justify-between gap-3">
            <dt class="text-gray-700">Stock in hand</dt>
            <dd class="font-medium text-gray-900 tabular-nums">{{ format_currency($closing['stock_in_hand']) }}</dd>
        </div>
        <div class="flex justify-between gap-3 pt-2 border-t-2 border-indigo-200 bg-indigo-50 -mx-2 px-2 py-2 rounded-lg">
            <dt class="font-semibold text-indigo-900">Closing amount</dt>
            <dd class="font-bold text-indigo-900 tabular-nums">{{ format_currency($closing['closing_amount']) }}</dd>
        </div>
    @endif
</dl>
