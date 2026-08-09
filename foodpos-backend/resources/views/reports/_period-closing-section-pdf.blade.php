@php $currencySymbol = pdf_currency_symbol(); @endphp
<div class="period-section">
    <div class="period-header">
        <p class="period-title">{{ $section['label'] }}</p>
        <p class="period-meta">{{ format_date($section['from']) }} – {{ format_date($section['to']) }}</p>
        @if($selectedBranch)
            <p class="period-meta">{{ $selectedBranch->name }}</p>
        @elseif($availableBranches->count() > 1)
            <p class="period-meta">All branches</p>
        @endif
    </div>

    @if($section['show_stock'] ?? true)
        <div class="block-section">
            <p class="col-title">Available stock</p>
            <table class="data-table" width="100%" cellpadding="0" cellspacing="0">
                <thead>
                    <tr>
                        <th class="col-sno">S/No</th>
                        <th>Product</th>
                        <th class="amount">Rate ({{ $currencySymbol }})</th>
                        <th class="amount">Qty</th>
                        <th class="amount">Amount ({{ $currencySymbol }})</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($section['stock'] as $line)
                        <tr class="{{ $loop->even ? 'stripe' : '' }}">
                            <td class="col-sno">{{ $line['sno'] }}</td>
                            <td>{{ $line['product'] }}</td>
                            <td class="amount">{{ format_amount($line['rate']) }}</td>
                            <td class="amount">{{ format_quantity($line['qty']) }}</td>
                            <td class="amount">{{ format_amount($line['amount']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="empty-cell">No available stock.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if(count($section['stock']) > 0)
                    <tfoot>
                        <tr class="total-row">
                            <td colspan="4" class="amount">Total</td>
                            <td class="amount">{{ format_amount($section['stock_total']) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    @endif

    <div class="block-section daily-block">
        <p class="col-title">Daily sales ({{ $currencySymbol }})</p>
        @include('reports._period-closing-daily-sales-table-pdf', [
            'section' => $section,
            'paymentColumns' => $paymentColumns,
        ])
    </div>

    <div class="block-section closing-block">
        <p class="col-title">Closing ({{ $currencySymbol }})</p>
        @include('reports._period-closing-summary-pdf', ['closing' => $section['closing']])
    </div>
</div>
