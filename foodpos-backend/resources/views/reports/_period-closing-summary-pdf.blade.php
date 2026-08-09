<table class="closing-summary" width="100%" cellpadding="0" cellspacing="0">
    <tr>
        <td class="closing-label">Total sale</td>
        <td class="closing-value">{{ format_currency_for_pdf($closing['total_sale']) }}</td>
    </tr>
    <tr class="stripe">
        <td class="closing-label">COGS</td>
        <td class="closing-value">{{ format_currency_for_pdf($closing['cogs_total'] ?? $closing['purchase_total']) }}</td>
    </tr>
    <tr>
        <td class="closing-label">Expenses</td>
        <td class="closing-value">{{ format_currency_for_pdf($closing['expense_total']) }}</td>
    </tr>
    <tr class="closing-divider stripe">
        <td class="closing-label strong">Total</td>
        <td class="closing-value strong {{ $closing['pnl'] >= 0 ? 'positive' : 'negative' }}">{{ format_currency_for_pdf($closing['pnl']) }}</td>
    </tr>
    @if($closing['stock_in_hand'] !== null)
        <tr>
            <td class="closing-label">Stock in hand</td>
            <td class="closing-value">{{ format_currency_for_pdf($closing['stock_in_hand']) }}</td>
        </tr>
        <tr class="closing-highlight">
            <td class="closing-label strong">Closing amount</td>
            <td class="closing-value strong">{{ format_currency_for_pdf($closing['closing_amount']) }}</td>
        </tr>
    @endif
</table>
