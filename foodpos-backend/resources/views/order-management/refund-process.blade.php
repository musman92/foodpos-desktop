@extends('layouts.app')

@section('title', 'Refund — '.$order->order_number)

@section('content')
@php
    $refundProcessConfig = [
        'currency' => $currencyMeta,
        'orderSubtotal' => $orderSubtotal,
        'orderTax' => $orderTax,
    ];
@endphp
<script>
window.refundProcessConfig = @json($refundProcessConfig);
</script>

<div class="space-y-6 max-w-4xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Refund order {{ $order->order_number }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ $order->branch->name ?? '' }} · Tick lines to refund, set quantities, add notes, then submit. Stock and payments are fully reversed for refunded quantities.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('order-management.refunds.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">Refunds</a>
            <a href="{{ route('order-management.show', $order) }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">Order detail</a>
        </div>
    </div>
    <form id="refund-process-form" method="POST" action="{{ route('order-management.refund', $order) }}" class="space-y-6">
        @csrf

        <div class="bg-white shadow rounded-lg divide-y divide-gray-200 border border-gray-100">
            @foreach($refundRows as $idx => $row)
                @php
                    $bill = (float) $row['billable'];
                    $startQty = $bill < 1.0001 ? round($bill, 2) : 1.0;
                @endphp
                <div
                    class="refund-line px-4 sm:px-6 py-4 space-y-3"
                    data-index="{{ $idx }}"
                    data-billable="{{ $row['billable'] }}"
                    data-unit="{{ $row['unit_value'] }}"
                    data-start-qty="{{ $startQty }}"
                >
                    <input type="hidden" name="lines[{{ $idx }}][order_item_id]" value="{{ $row['order_item_id'] }}">
                    <input type="hidden" name="lines[{{ $idx }}][quantity]" class="js-h-qty" value="0" autocomplete="off">

                    <div class="flex flex-wrap items-start gap-3">
                        <label class="inline-flex items-center gap-2 mt-1 cursor-pointer shrink-0">
                            <input type="checkbox" class="js-line-check h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="text-sm font-medium text-gray-900">{{ $row['name'] }}</span>
                            @if($row['is_deal'])
                                <span class="text-xs text-gray-500">(deal)</span>
                            @endif
                            @if($row['is_recipe'] && ! $row['is_deal'])
                                <span class="text-xs text-gray-500">(recipe)</span>
                            @endif
                        </label>
                        <div class="js-qty-wrap flex flex-wrap items-center gap-2 sm:ml-auto opacity-50 pointer-events-none">
                            <span class="text-xs text-gray-500">Refund qty</span>
                            <div class="inline-flex items-center rounded-lg border border-gray-300 bg-white">
                                <button type="button" class="js-qty-minus px-2.5 py-1.5 text-gray-600 hover:bg-gray-50 rounded-l-lg border-r border-gray-200" aria-label="Decrease">−</button>
                                <input type="number" step="0.01" min="0.01" max="{{ $row['billable'] }}" class="js-qty-vis w-20 text-center text-sm border-0 focus:ring-0 py-1.5" value="{{ $startQty }}" autocomplete="off">
                                <button type="button" class="js-qty-plus px-2.5 py-1.5 text-gray-600 hover:bg-gray-50 rounded-r-lg border-l border-gray-200" aria-label="Increase">+</button>
                            </div>
                            <span class="text-xs text-gray-500">max {{ $row['billable'] }}</span>
                        </div>
                    </div>

                    <div class="pl-7">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Line note (optional)</label>
                        <input type="text" name="lines[{{ $idx }}][line_notes]" value="{{ old('lines.'.$idx.'.line_notes') }}" class="w-full max-w-lg rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500" placeholder="Reason for this line" autocomplete="off">
                    </div>
                </div>
            @endforeach
        </div>

        <div class="bg-indigo-50 border border-indigo-100 rounded-lg px-4 py-4 sm:px-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <p class="text-sm font-medium text-indigo-900">Refund total (estimate)</p>
                <p id="refund-total-display" class="text-2xl font-bold text-indigo-950">—</p>
                <p class="text-xs text-indigo-800 mt-1">
                    Subtotal <span id="refund-sub-display">—</span>
                    + tax <span id="refund-tax-display">—</span>
                    — final amounts follow server allocation.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" id="refund-btn-full" class="inline-flex items-center justify-center px-4 py-2.5 bg-white border border-indigo-200 text-indigo-800 text-sm font-medium rounded-lg hover:bg-indigo-100">Full refund</button>
                <button type="submit" class="inline-flex items-center justify-center px-4 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Partial refund</button>
            </div>
        </div>

        <div class="bg-white shadow rounded-lg p-4 sm:p-6 border border-gray-100">
            <label for="refund_notes" class="block text-sm font-medium text-gray-700 mb-1">Refund notes <span class="text-red-500">*</span></label>
            <textarea name="notes" id="refund_notes" rows="3" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500" placeholder="Required summary for this refund">{{ old('notes') }}</textarea>
            @error('notes')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            @error('lines')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    </form>
</div>

<script>
(function () {
    const cfg = window.refundProcessConfig || { currency: { symbol: '', position: 'before', decimals: 2 }, orderSubtotal: 0, orderTax: 0 };

    function round2(v) {
        return Math.round(Number(v) * 100) / 100;
    }

    function formatMoney(n) {
        const d = cfg.currency.decimals ?? 2;
        const num = Number(n).toFixed(d);
        const sym = cfg.currency.symbol ?? '';
        if (cfg.currency.position === 'after') {
            return num + ' ' + sym;
        }
        return sym + num;
    }

    function parseBillable(line) {
        return Number(line.dataset.billable);
    }

    function parseUnit(line) {
        return Number(line.dataset.unit);
    }

    function defaultStartQty(line) {
        const v = line.dataset.startQty;
        return v !== undefined && v !== '' ? Number(v) : 1;
    }

    function syncLine(line) {
        const chk = line.querySelector('.js-line-check');
        const hQty = line.querySelector('.js-h-qty');
        const vis = line.querySelector('.js-qty-vis');
        const wrap = line.querySelector('.js-qty-wrap');
        if (!chk || !hQty || !vis || !wrap) return;

        if (!chk.checked) {
            hQty.value = '0';
            wrap.classList.add('opacity-50', 'pointer-events-none');
            return;
        }

        wrap.classList.remove('opacity-50', 'pointer-events-none');
        const max = parseBillable(line);
        let q = round2(Number(vis.value));
        if (!Number.isFinite(q) || q < 0.01) q = 0.01;
        if (q > max) q = max;
        vis.value = String(q);
        hQty.value = String(q);
    }

    function updateTotals() {
        const lines = document.querySelectorAll('.refund-line');
        let sub = 0;
        lines.forEach((line) => {
            const chk = line.querySelector('.js-line-check');
            const hQty = line.querySelector('.js-h-qty');
            if (!chk || !hQty || !chk.checked) return;
            const q = Number(hQty.value);
            if (!Number.isFinite(q) || q <= 0) return;
            sub += q * parseUnit(line);
        });
        sub = round2(sub);

        let tax = 0;
        const os = Number(cfg.orderSubtotal);
        const ot = Number(cfg.orderTax);
        if (os > 0.0001) {
            tax = round2(Math.min(ot, ot * (sub / os)));
        }
        const total = round2(sub + tax);

        const elSub = document.getElementById('refund-sub-display');
        const elTax = document.getElementById('refund-tax-display');
        const elTot = document.getElementById('refund-total-display');
        if (elSub) elSub.textContent = formatMoney(sub);
        if (elTax) elTax.textContent = formatMoney(tax);
        if (elTot) elTot.textContent = formatMoney(total);
    }

    const form = document.getElementById('refund-process-form');
    if (!form) return;

    form.querySelectorAll('.refund-line').forEach((line) => {
        const chk = line.querySelector('.js-line-check');
        const vis = line.querySelector('.js-qty-vis');
        const hQty = line.querySelector('.js-h-qty');

        chk.addEventListener('change', () => {
            if (chk.checked) {
                const start = defaultStartQty(line);
                const max = parseBillable(line);
                vis.value = String(Math.min(start, max));
            }
            syncLine(line);
            updateTotals();
        });

        vis.addEventListener('input', () => {
            syncLine(line);
            updateTotals();
        });
        vis.addEventListener('change', () => {
            syncLine(line);
            updateTotals();
        });

        line.querySelector('.js-qty-minus')?.addEventListener('click', () => {
            if (!chk.checked) chk.checked = true;
            const max = parseBillable(line);
            let q = round2(Number(vis.value) - 1);
            if (q <= 0) {
                chk.checked = false;
                hQty.value = '0';
                line.querySelector('.js-qty-wrap')?.classList.add('opacity-50', 'pointer-events-none');
                updateTotals();
                return;
            }
            vis.value = String(Math.min(max, q));
            syncLine(line);
            updateTotals();
        });

        line.querySelector('.js-qty-plus')?.addEventListener('click', () => {
            if (!chk.checked) chk.checked = true;
            const max = parseBillable(line);
            let q = round2(Number(vis.value) + 1);
            if (q > max) q = max;
            vis.value = String(q);
            syncLine(line);
            updateTotals();
        });
    });

    document.getElementById('refund-btn-full')?.addEventListener('click', () => {
        form.querySelectorAll('.refund-line').forEach((line) => {
            const chk = line.querySelector('.js-line-check');
            const vis = line.querySelector('.js-qty-vis');
            if (!chk || !vis) return;
            chk.checked = true;
            vis.value = String(parseBillable(line));
            syncLine(line);
        });
        updateTotals();
    });

    form.addEventListener('submit', () => {
        form.querySelectorAll('.refund-line').forEach((line) => {
            syncLine(line);
        });
    });

    updateTotals();
})();
</script>
@endsection
