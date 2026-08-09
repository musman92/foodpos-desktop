@php
    $compact = $compact ?? false;
    $totalBalance = collect($moneySourceBalances)->sum(fn ($row) => (float) ($row['balance'] ?? 0));
    $typeStyles = [
        'CASH' => [
            'icon' => 'fa-money-bill-wave',
            'badge' => 'bg-emerald-100 text-emerald-700',
            'icon_bg' => 'bg-emerald-50',
            'icon_text' => 'text-emerald-600',
            'glow' => 'bg-emerald-100',
        ],
        'BANK' => [
            'icon' => 'fa-university',
            'badge' => 'bg-sky-100 text-sky-700',
            'icon_bg' => 'bg-sky-50',
            'icon_text' => 'text-sky-600',
            'glow' => 'bg-sky-100',
        ],
        'APP' => [
            'icon' => 'fa-mobile-alt',
            'badge' => 'bg-violet-100 text-violet-700',
            'icon_bg' => 'bg-violet-50',
            'icon_text' => 'text-violet-600',
            'glow' => 'bg-violet-100',
        ],
    ];
@endphp

<div class="bg-white rounded-xl shadow-sm border border-gray-200 {{ $compact ? 'p-4 sm:p-5 h-full' : 'p-5 sm:p-6' }}">
    @if($compact)
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-4">
            <div class="flex items-center gap-2 min-w-0">
                <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                    <i class="fas fa-wallet text-sm"></i>
                </span>
                <div class="min-w-0">
                    <h2 class="text-base font-semibold text-gray-900">Funds Overview</h2>
                    <p class="text-xs text-gray-500 truncate">Payment source balances</p>
                </div>
            </div>
            <div class="flex items-center justify-between sm:justify-end gap-3 rounded-lg bg-gray-50 border border-gray-100 px-3 py-2 sm:min-w-[9.5rem]">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 sm:hidden">Total</p>
                <p class="text-lg font-bold tabular-nums {{ $totalBalance < 0 ? 'text-red-600' : 'text-gray-900' }}">
                    {{ format_currency($totalBalance) }}
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-2">
            @foreach($moneySourceBalances as $balanceData)
                @php
                    $moneySource = $balanceData['money_source'];
                    $balance = $balanceData['balance'];
                    $style = $typeStyles[$moneySource->type] ?? $typeStyles['CASH'];
                @endphp
                <div class="flex items-center gap-2.5 rounded-lg border border-gray-200 bg-gray-50/50 px-2.5 py-2 hover:bg-white hover:shadow-sm transition-colors">
                    <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md {{ $style['icon_bg'] }}">
                        <i class="fas {{ $style['icon'] }} {{ $style['icon_text'] }} text-xs"></i>
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-1.5">
                            <p class="text-xs font-medium text-gray-700 truncate" title="{{ $moneySource->name }}">{{ $moneySource->name }}</p>
                            <span class="inline-flex shrink-0 rounded px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide {{ $style['badge'] }}">
                                {{ $moneySource->type }}
                            </span>
                        </div>
                        <p class="text-sm font-bold tabular-nums truncate {{ $balance >= 0 ? 'text-gray-900' : 'text-red-600' }}">
                            {{ format_currency($balance) }}
                        </p>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between mb-6">
            <div class="flex-1">
                <div class="flex items-center gap-2">
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                        <i class="fas fa-wallet text-sm"></i>
                    </span>
                    <h2 class="text-lg font-semibold text-gray-900">Funds Overview</h2>
                </div>
                <p class="text-sm text-gray-500 mt-2 ml-11">Current balances across your payment sources</p>
            </div>
            <div class="sm:text-right rounded-xl bg-gray-50 border border-gray-100 px-4 py-3 sm:min-w-[10rem]">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Total available</p>
                <p class="text-2xl font-bold tabular-nums mt-1 {{ $totalBalance < 0 ? 'text-red-600' : 'text-gray-900' }}">
                    {{ format_currency($totalBalance) }}
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
            @foreach($moneySourceBalances as $balanceData)
                @php
                    $moneySource = $balanceData['money_source'];
                    $balance = $balanceData['balance'];
                    $style = $typeStyles[$moneySource->type] ?? $typeStyles['CASH'];
                @endphp
                <div class="group relative overflow-hidden rounded-xl border border-gray-200 bg-white p-4 transition-shadow hover:shadow-md">
                    <div class="absolute -right-6 -top-6 h-20 w-20 rounded-full {{ $style['glow'] }} opacity-60"></div>

                    <div class="relative flex items-start justify-between gap-3">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl {{ $style['icon_bg'] }}">
                            <i class="fas {{ $style['icon'] }} {{ $style['icon_text'] }}"></i>
                        </div>
                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide {{ $style['badge'] }}">
                            {{ $moneySource->type }}
                        </span>
                    </div>

                    <div class="relative mt-4 min-w-0">
                        <p class="text-sm font-medium text-gray-600 truncate" title="{{ $moneySource->name }}">
                            {{ $moneySource->name }}
                        </p>
                        <p class="mt-2 text-2xl font-bold tabular-nums truncate {{ $balance >= 0 ? 'text-gray-900' : 'text-red-600' }}">
                            {{ format_currency($balance) }}
                        </p>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
