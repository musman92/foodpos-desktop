@php
    $shift = $report['shift'];
    $sales = $report['sales'];
    $isPdf = $isPdf ?? false;
    $fmt = $isPdf ? 'format_currency_for_pdf' : 'format_currency';
@endphp

<div class="z-report-body">
    <div class="report-meta">
        <p><strong>Branch:</strong> {{ $shift->branch->name }}</p>
        <p><strong>Shift date:</strong> {{ format_date($shift->shift_date) }}</p>
        <p><strong>Opened:</strong> {{ $shift->opened_at->format('Y-m-d H:i') }} by {{ $shift->openedBy->name }}</p>
        @if($shift->isClosed())
            <p><strong>Closed:</strong> {{ $shift->closed_at->format('Y-m-d H:i') }} by {{ $shift->closedBy?->name ?? '—' }}</p>
        @endif
        <p><strong>Generated:</strong> {{ $report['generated_at'] }}</p>
        @if($report['is_interim'])
            <p class="interim-note"><strong>Interim report</strong> — shift is still active.</p>
        @endif
    </div>

    <h3 class="section-title">Sales Summary</h3>
    <table class="data-table summary-table">
        <tbody>
            <tr><td>Completed orders</td><td class="amount">{{ number_format($sales['order_count']) }}</td></tr>
            <tr><td>Cancelled orders</td><td class="amount">{{ number_format($sales['cancelled_count']) }}</td></tr>
            <tr><td>Gross sales (subtotal)</td><td class="amount">{{ $fmt($sales['gross_sales']) }}</td></tr>
            <tr><td>Discounts</td><td class="amount">{{ $fmt($sales['discounts']) }}</td></tr>
            <tr><td>Tax</td><td class="amount">{{ $fmt($sales['tax']) }}</td></tr>
            <tr><td>Service charge</td><td class="amount">{{ $fmt($sales['service_charge']) }}</td></tr>
            <tr><td>Delivery fees</td><td class="amount">{{ $fmt($sales['delivery_fees']) }}</td></tr>
            <tr><td>Gross total</td><td class="amount"><strong>{{ $fmt($sales['gross_total']) }}</strong></td></tr>
            <tr><td>Refunds ({{ $sales['refund_count'] }})</td><td class="amount">{{ $fmt($sales['refunds']) }}</td></tr>
            <tr class="highlight"><td>Net sales</td><td class="amount"><strong>{{ $fmt($sales['net_sales']) }}</strong></td></tr>
            <tr><td>Average ticket</td><td class="amount">{{ $fmt($sales['average_ticket']) }}</td></tr>
        </tbody>
    </table>

    @if($report['order_types']->isNotEmpty())
        <h3 class="section-title">Orders by Type</h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Type</th>
                    <th class="amount">Orders</th>
                    <th class="amount">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($report['order_types'] as $row)
                    <tr>
                        <td>{{ $row['label'] }}</td>
                        <td class="amount">{{ number_format($row['count']) }}</td>
                        <td class="amount">{{ $fmt($row['amount']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if($report['payment_methods']->isNotEmpty())
        <h3 class="section-title">Payment Methods</h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Method</th>
                    <th class="amount">Sales In</th>
                    <th class="amount">Refunds Out</th>
                    <th class="amount">Net</th>
                </tr>
            </thead>
            <tbody>
                @foreach($report['payment_methods'] as $row)
                    <tr>
                        <td>{{ $row['label'] }}</td>
                        <td class="amount">{{ $fmt($row['sales']) }}</td>
                        <td class="amount">{{ $fmt($row['refunds']) }}</td>
                        <td class="amount">{{ $fmt($row['net']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h3 class="section-title">Cash Drawer Reconciliation</h3>
    <table class="data-table summary-table">
        <tbody>
            <tr><td>Expected cash</td><td class="amount">{{ $fmt($report['cash_summary']['expected']) }}</td></tr>
            @if($report['cash_summary']['actual'] !== null)
                <tr><td>Actual cash counted</td><td class="amount">{{ $fmt($report['cash_summary']['actual']) }}</td></tr>
                <tr class="highlight">
                    <td>Cash over / short</td>
                    <td class="amount {{ ($report['cash_summary']['difference'] ?? 0) >= 0 ? 'positive' : 'negative' }}">
                        {{ $fmt($report['cash_summary']['difference'] ?? 0) }}
                    </td>
                </tr>
            @else
                <tr><td>Actual cash counted</td><td class="amount">—</td></tr>
            @endif
        </tbody>
    </table>

    <h3 class="section-title">Money Sources</h3>
    <table class="data-table">
        <thead>
            <tr>
                <th>Source</th>
                <th class="amount">Opening</th>
                <th class="amount">Expected</th>
                @if($shift->isClosed())
                    <th class="amount">Closing</th>
                    <th class="amount">Difference</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach($report['money_sources'] as $row)
                <tr>
                    <td>
                        {{ $row['name'] }}
                        <span class="muted">({{ $row['type'] }})</span>
                    </td>
                    <td class="amount">{{ $fmt($row['opening']) }}</td>
                    <td class="amount">{{ $fmt($row['expected']) }}</td>
                    @if($shift->isClosed())
                        <td class="amount">{{ $row['closing'] !== null ? $fmt($row['closing']) : '—' }}</td>
                        <td class="amount {{ ($row['difference'] ?? 0) >= 0 ? 'positive' : 'negative' }}">
                            {{ $fmt($row['difference'] ?? 0) }}
                        </td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>

    @if($report['fund_movements']->isNotEmpty())
        <h3 class="section-title">Fund Movements</h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>From</th>
                    <th>To</th>
                    <th class="amount">Amount</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                @foreach($report['fund_movements'] as $row)
                    <tr>
                        <td>{{ $row['date'] }}</td>
                        <td>{{ $row['type'] }}</td>
                        <td>{{ $row['from'] }}</td>
                        <td>{{ $row['to'] }}</td>
                        <td class="amount">{{ $fmt($row['amount']) }}</td>
                        <td>{{ $row['notes'] ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h3 class="section-title">Transaction Activity</h3>
    <table class="data-table summary-table">
        <tbody>
            <tr><td>Sale transactions</td><td class="amount">{{ number_format($report['transaction_summary']['sales']) }}</td></tr>
            <tr><td>Refund transactions</td><td class="amount">{{ number_format($report['transaction_summary']['refunds']) }}</td></tr>
            <tr><td>Customer payments</td><td class="amount">{{ number_format($report['transaction_summary']['customer_payments']) }}</td></tr>
            <tr><td>Other transactions</td><td class="amount">{{ number_format($report['transaction_summary']['other']) }}</td></tr>
        </tbody>
    </table>
</div>
