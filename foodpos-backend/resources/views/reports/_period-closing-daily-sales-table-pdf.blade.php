{{-- Compact day rows (DomPDF-friendly; no thead page-break traps) --}}
@foreach($section['daily_sales'] as $day)
    <table class="day-card" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td class="day-card-head" colspan="2">
                <strong class="day-card-title">{{ $day['label'] }}</strong>
                <span class="day-card-date">{{ format_date($day['date']) }}</span>
            </td>
        </tr>
        <tr>
            <td class="day-label strong">Total daily sale</td>
            <td class="amount strong">{{ format_amount($day['total_sale']) }}</td>
        </tr>
        @foreach($day['payments'] as $payment)
            <tr>
                <td class="day-label">{{ $payment['label'] }}</td>
                <td class="amount">{{ format_amount($payment['amount']) }}</td>
            </tr>
        @endforeach
        <tr>
            <td class="day-label">Cash receivable</td>
            <td class="amount">{{ format_amount($day['cash_receivable'] ?? 0) }}</td>
        </tr>
        <tr>
            <td class="day-label">Total receivable</td>
            <td class="amount">{{ format_amount($day['total_receivable'] ?? 0) }}</td>
        </tr>
        <tr>
            <td class="day-label">Expenses</td>
            <td class="amount">{{ format_amount($day['expense_total'] ?? 0) }}</td>
        </tr>
        @foreach(($day['expense_lines'] ?? []) as $line)
            <tr>
                <td class="day-label day-expense-line">
                    {{ $line['label'] }}
                    @if(!empty($line['detail']))
                        <span class="day-expense-detail"> — {{ $line['detail'] }}</span>
                    @endif
                </td>
                <td class="amount day-expense-line">{{ format_amount($line['amount']) }}</td>
            </tr>
        @endforeach
        <tr class="day-cash-row">
            <td class="day-label strong">Cash in hand</td>
            <td class="amount strong">{{ format_amount($day['cash_in_hand'] ?? 0) }}</td>
        </tr>
    </table>
@endforeach
