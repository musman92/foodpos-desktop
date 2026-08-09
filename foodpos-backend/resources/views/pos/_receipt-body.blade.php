@php
    use App\Support\ReceiptSections;

    $receiptLayout = $receiptLayout ?? receipt_layout_settings();
    $sections = $sections ?? ($receiptLayout['sections'] ?? ReceiptSections::defaults());
    $isArray = is_array($order);

    $company = $isArray ? ($order['company'] ?? null) : $order->company;
    $branch = $isArray ? ($order['branch'] ?? null) : $order->branch;
    $cashier = $isArray ? ($order['cashier'] ?? null) : $order->cashier;
    $table = $isArray ? ($order['table'] ?? null) : $order->table;
    $items = $isArray ? ($order['items'] ?? []) : $order->items;
    $payments = $isArray ? ($order['payments'] ?? []) : ($order->relationLoaded('payments') ? $order->payments : collect());

    $companyName = $isArray ? ($company['name'] ?? 'COMPANY NAME') : ($company?->name ?? 'COMPANY NAME');
    $logoUrl = $isArray ? ($company['logo_url'] ?? null) : ($company?->receipt_logo_url ?? null);
    $usesLogoFilter = ! $isArray && $company?->usesReceiptLogoFallbackFilter();

    $branchName = $isArray ? ($branch['name'] ?? null) : ($branch?->name ?? null);
    $companyNameForCompare = $isArray ? ($company['name'] ?? null) : ($company?->name ?? null);
    $showBranchName = $branchName && $companyNameForCompare && strcasecmp($branchName, $companyNameForCompare) !== 0;

    $address = $isArray
        ? ($company['address'] ?? $branch['address'] ?? null)
        : ($company?->address ?: $branch?->address);
    $phone = $isArray
        ? ($company['phone'] ?? $branch['phone'] ?? null)
        : ($company?->phone ?: $branch?->phone);

    $orderNumber = $isArray ? ($order['order_number'] ?? '') : $order->order_number;
    $createdAt = $isArray ? ($order['created_at'] ?? null) : $order->created_at;
    if (is_string($createdAt)) {
        $createdAt = \Carbon\Carbon::parse($createdAt);
    }
    $dateFormatted = $createdAt ? $createdAt->format('d/m/Y H:i') : '';

    $orderType = $isArray ? ($order['type'] ?? null) : $order->type;
    $orderTypeLabel = $orderType ? ucfirst(str_replace('_', ' ', $orderType)) : '';

    $paymentMethod = $isArray ? ($order['payment_method'] ?? null) : $order->payment_method;
    $paymentStatus = $isArray ? ($order['payment_status'] ?? null) : $order->payment_status;
    $paymentMethodLabel = $paymentMethod === 'split'
        ? 'Split payment'
        : ucfirst(str_replace('_', ' ', $paymentMethod ?? 'N/A'));
    $paymentStatusLabel = ucfirst($paymentStatus ?? 'N/A');

    $showOrderDetails = ReceiptSections::enabled($sections, 'order_type')
        || (ReceiptSections::enabled($sections, 'table') && $table);
    $showCustomer = ReceiptSections::enabled($sections, 'customer_block') && (
        ($isArray && (($order['customer_name'] ?? null) || ($order['customer_phone'] ?? null) || ($order['customer_email'] ?? null) || ($order['customer_address'] ?? null)))
        || (! $isArray && ($order->customer_name || $order->customer_phone || $order->customer_email || $order->customer_address))
    );

    $showSubtotal = ReceiptSections::shouldShowSubtotal($sections, $order);
    $discount = (float) ($isArray ? ($order['discount_amount'] ?? 0) : $order->discount_amount);
    $serviceCharge = (float) ($isArray ? ($order['service_charge'] ?? 0) : $order->service_charge);
    $deliveryFee = (float) ($isArray ? ($order['delivery_fee'] ?? 0) : $order->delivery_fee);
    $taxAmount = (float) ($isArray ? ($order['tax_amount'] ?? 0) : $order->tax_amount);
    $subtotal = (float) ($isArray ? ($order['subtotal'] ?? 0) : $order->subtotal);
    $totalAmount = (float) ($isArray ? ($order['total_amount'] ?? 0) : $order->total_amount);
    $notes = $isArray ? ($order['notes'] ?? null) : $order->notes;

    $amountPad = (int) ($receiptLayout['amount_pad_right_mm'] ?? 0);
    $amountPadStyle = $amountPad > 0 ? "padding-right: {$amountPad}mm;" : '';
    $colItem = (int) ($receiptLayout['col_item_pct'] ?? 54);
    $colQty = (int) ($receiptLayout['col_qty_pct'] ?? 14);
    $colPrice = (int) ($receiptLayout['col_price_pct'] ?? 32);
@endphp

<div class="receipt-body">
    <div class="invoice-header">
        @if(ReceiptSections::enabled($sections, 'logo') && $logoUrl)
            <div class="invoice-logo">
                <img src="{{ $logoUrl }}" alt=""
                     @class(['invoice-logo--filtered' => $usesLogoFilter])>
            </div>
        @endif
        <div class="company-name">{{ $companyName }}</div>
        @if(ReceiptSections::enabled($sections, 'branch_name') && $showBranchName)
            <div class="company-info">{{ $branchName }}</div>
        @endif
        @if(ReceiptSections::enabled($sections, 'address') && $address)
            <div class="company-info">{{ $address }}</div>
        @endif
        @if(ReceiptSections::enabled($sections, 'phone') && $phone)
            <div class="company-info">Tel: {{ $phone }}</div>
        @endif
    </div>

    @if(ReceiptSections::enabled($sections, 'invoice_title'))
        <div class="invoice-title">INVOICE</div>
    @endif

    @if(($isArray && ($order['status'] ?? null) === 'open' && ($order['payment_status'] ?? null) === 'unpaid')
        || (! $isArray && $order->status === 'open' && $order->payment_status === 'unpaid'))
        <div class="invoice-draft">Draft</div>
    @endif

    @if(ReceiptSections::enabled($sections, 'order_number') || ReceiptSections::enabled($sections, 'date_cashier'))
        <div class="invoice-meta">
            @if(ReceiptSections::enabled($sections, 'order_number'))
                <p><strong>Order #:</strong> {{ $orderNumber }}</p>
            @endif
            @if(ReceiptSections::enabled($sections, 'date_cashier'))
                @php
                    $cashierName = $isArray ? ($cashier['name'] ?? null) : ($cashier?->name ?? null);
                @endphp
                <p>
                    <strong>Date:</strong> {{ $dateFormatted }}
                    @if($cashierName)
                        <span class="meta-sep">·</span> <strong>Cashier:</strong> {{ $cashierName }}
                    @endif
                </p>
            @endif
        </div>
    @endif

    @if($showOrderDetails)
        <div class="section">
            <div class="section-title">Order Details</div>
            <div class="section-content">
                @if(ReceiptSections::enabled($sections, 'order_type') && $orderTypeLabel)
                    <p>Type: {{ $orderTypeLabel }}</p>
                @endif
                @if(ReceiptSections::enabled($sections, 'table') && $table)
                    <p>Table: {{ $isArray ? ($table['name'] ?? '') : $table->name }}</p>
                @endif
            </div>
        </div>
    @endif

    @if($showCustomer)
        <div class="section">
            <div class="section-title">Customer</div>
            <div class="section-content">
                @if($isArray ? ($order['customer_name'] ?? null) : $order->customer_name)
                    <p>Name: {{ $isArray ? $order['customer_name'] : $order->customer_name }}</p>
                @endif
                @if($isArray ? ($order['customer_phone'] ?? null) : $order->customer_phone)
                    <p>Phone: {{ $isArray ? $order['customer_phone'] : $order->customer_phone }}</p>
                @endif
                @if($isArray ? ($order['customer_email'] ?? null) : $order->customer_email)
                    <p>Email: {{ $isArray ? $order['customer_email'] : $order->customer_email }}</p>
                @endif
                @if($isArray ? ($order['customer_address'] ?? null) : $order->customer_address)
                    <p>Address: {{ $isArray ? $order['customer_address'] : $order->customer_address }}</p>
                @endif
            </div>
        </div>
    @endif

    <div class="separator"></div>

    <table class="items-table">
        @if(ReceiptSections::enabled($sections, 'items_header'))
            <thead>
                <tr>
                    <th>Item</th>
                    <th class="item-qty">Qty</th>
                    <th class="item-price">Price</th>
                </tr>
            </thead>
        @endif
        <tbody>
            @foreach($items as $item)
                @php
                    $itemName = $isArray ? ($item['item_name'] ?? '') : $item->item_name;
                    $qty = (float) ($isArray ? ($item['quantity'] ?? 0) : $item->quantity);
                    $lineTotal = (float) ($isArray ? ($item['total_price'] ?? 0) : $item->total_price);
                    $variants = $isArray ? ($item['variants'] ?? null) : $item->variants;
                    $addons = $isArray ? ($item['addons'] ?? []) : ($item->addons ?? []);
                    $instructions = $isArray ? ($item['special_instructions'] ?? null) : $item->special_instructions;
                    $dealComponents = [];
                    if (ReceiptSections::enabled($sections, 'deal_items')) {
                        if ($isArray) {
                            $dealMenuItems = $item['deal']['menu_items'] ?? $item['deal']['menuItems'] ?? [];
                            $dealLineQty = (float) ($item['quantity'] ?? 1);
                            foreach ($dealMenuItems as $component) {
                                $pivotQty = (float) (($component['pivot']['quantity'] ?? null) ?? 1);
                                $option = trim((string) (($component['pivot']['option_name'] ?? null) ?? ''));
                                $componentName = (string) ($component['name'] ?? 'Item');
                                if ($option !== '') {
                                    $componentName .= ' ('.$option.')';
                                }
                                $dealComponents[] = [
                                    'name' => $componentName,
                                    'quantity' => round($pivotQty * $dealLineQty, 2),
                                ];
                            }
                        } elseif ($item->deal_id && $item->deal) {
                            $dealComponents = $item->deal->invoiceComponentLines($qty);
                        }
                    }
                @endphp
                <tr>
                    <td>
                        <div class="item-name">{{ $itemName }}</div>
                        @if(ReceiptSections::enabled($sections, 'item_variants') && $variants && is_array($variants))
                            @php
                                $vn = $variants['variant_name'] ?? '';
                                $on = $variants['option_name'] ?? '';
                            @endphp
                            @if($vn || $on)
                                <div class="item-meta">{{ $vn ? $vn.': ' : '' }}{{ $on ?: $vn }}</div>
                            @endif
                        @endif
                        @if(ReceiptSections::enabled($sections, 'item_addons') && is_array($addons) && count($addons) > 0)
                            <div class="item-meta">
                                @foreach($addons as $addon)
                                    + {{ $addon['name'] ?? 'Addon' }}@if(($addon['quantity'] ?? 1) > 1) x{{ format_quantity((float) $addon['quantity']) }}@endif<br>
                                @endforeach
                            </div>
                        @endif
                        @if(ReceiptSections::enabled($sections, 'item_notes') && $instructions)
                            <div class="item-meta item-meta--note">{{ $instructions }}</div>
                        @endif
                        @foreach($dealComponents as $component)
                            <div class="item-meta">· {{ $component['name'] }} x{{ format_quantity($component['quantity']) }}</div>
                        @endforeach
                    </td>
                    <td class="item-qty">{{ format_quantity($qty) }}</td>
                    <td class="item-price amount-cell">{{ format_currency($lineTotal) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        @if($showSubtotal)
            <div class="total-row">
                <span>Subtotal:</span>
                <span class="amount-cell">{{ format_currency($subtotal) }}</span>
            </div>
        @endif
        @if(ReceiptSections::enabled($sections, 'discount') && $discount > 0)
            <div class="total-row">
                <span>Discount:</span>
                <span class="amount-cell">-{{ format_currency($discount) }}</span>
            </div>
        @endif
        @if(ReceiptSections::enabled($sections, 'service_charge') && $serviceCharge > 0)
            <div class="total-row">
                <span>Service Charge:</span>
                <span class="amount-cell">{{ format_currency($serviceCharge) }}</span>
            </div>
        @endif
        @if(ReceiptSections::enabled($sections, 'delivery_fee') && $deliveryFee > 0)
            <div class="total-row">
                <span>Delivery Fee:</span>
                <span class="amount-cell">{{ format_currency($deliveryFee) }}</span>
            </div>
        @endif
        @if(ReceiptSections::enabled($sections, 'tax') && $taxAmount > 0)
            <div class="total-row">
                <span>Tax:</span>
                <span class="amount-cell">{{ format_currency($taxAmount) }}</span>
            </div>
        @endif
        <div class="total-row grand-total">
            <span>TOTAL:</span>
            <span class="amount-cell">{{ format_currency($totalAmount) }}</span>
        </div>
    </div>

    @if(ReceiptSections::enabled($sections, 'payment_info'))
        <div class="payment-info">
            <p><strong>Payment:</strong> {{ $paymentMethodLabel }}<span class="meta-sep">·</span> {{ $paymentStatusLabel }}</p>
            @if($paymentMethod === 'split' && count($payments) > 0)
                @foreach($payments as $payment)
                    @php
                        $payAmount = (float) ($isArray ? ($payment['amount'] ?? 0) : $payment->amount);
                        $sourceName = $isArray
                            ? ($payment['money_source']['name'] ?? 'Payment')
                            : ($payment->moneySource?->name ?? 'Payment');
                    @endphp
                    <p class="payment-split-line">{{ $sourceName }}: {{ format_currency($payAmount) }}</p>
                @endforeach
            @endif
        </div>
    @endif

    @if(ReceiptSections::enabled($sections, 'order_notes') && $notes)
        <div class="section">
            <div class="section-title">Notes</div>
            <div class="section-content">{{ $notes }}</div>
        </div>
    @endif

    <div class="footer">
        @if(ReceiptSections::enabled($sections, 'thank_you'))
            <p>Thank you for your business!</p>
        @endif
        <div class="footer-brand-block">
            <p class="footer-brand">Powered by {{ config('app.name') }} thefoodpos.com</p>
            <p class="footer-brand">0306 5918 097/0312 7032 292</p>
        </div>
    </div>
</div>
