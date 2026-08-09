@extends('layouts.app')

@section('title', $layoutTitle ?? 'Money Sources')

@section('content')
@php
    $user = auth()->user();
    $canTransfer = $user->isSuperAdmin() || $user->isCompanyAdmin() || $user->hasAppPermission('money-sources.transfer');
    $canOwnerWithdrawal = $user->isSuperAdmin() || $user->isCompanyAdmin() || $user->hasAppPermission('money-sources.owner-withdrawal');
    $canReports = $user->isSuperAdmin() || $user->isCompanyAdmin() || $user->hasAppPermission('money-sources.reports');
    $activeNav = $activeNav ?? 'sources';
@endphp

<div class="w-full min-w-0 max-w-7xl mx-auto">
    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
            <h1 class="text-xl font-semibold text-gray-900">{{ $layoutHeading ?? 'Money Sources' }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ $layoutSubtitle ?? 'Manage payment sources, transfers, and owner withdrawals' }}</p>
        </div>

        <div class="flex flex-col md:flex-row md:items-stretch min-w-0">
            <aside class="w-full md:w-56 lg:w-64 flex-shrink-0 border-b md:border-b-0 md:border-r border-gray-200 bg-gray-50">
                <nav class="p-3 md:p-4 space-y-1 md:min-h-full" aria-label="Money Sources Navigation">
                    <a href="{{ route('money-sources.index') }}"
                       class="flex items-center px-3 md:px-4 py-2.5 md:py-3 text-sm font-medium rounded-lg transition-colors {{ $activeNav === 'sources' ? 'bg-indigo-50 text-indigo-700 border-l-4 border-indigo-600' : 'text-gray-700 hover:bg-gray-100 hover:text-gray-900' }}">
                        <i class="fas fa-wallet w-5 mr-3 {{ $activeNav === 'sources' ? 'text-indigo-600' : 'text-gray-400' }}"></i>
                        Sources
                    </a>

                    @if($canTransfer)
                        <a href="{{ route('money-sources.transfer.create') }}"
                           class="flex items-center px-3 md:px-4 py-2.5 md:py-3 text-sm font-medium rounded-lg transition-colors {{ $activeNav === 'transfer' ? 'bg-indigo-50 text-indigo-700 border-l-4 border-indigo-600' : 'text-gray-700 hover:bg-gray-100 hover:text-gray-900' }}">
                            <i class="fas fa-exchange-alt w-5 mr-3 {{ $activeNav === 'transfer' ? 'text-indigo-600' : 'text-gray-400' }}"></i>
                            Transfer
                        </a>
                    @endif

                    @if($canOwnerWithdrawal)
                        <a href="{{ route('money-sources.owner-withdrawal.create') }}"
                           class="flex items-center px-3 md:px-4 py-2.5 md:py-3 text-sm font-medium rounded-lg transition-colors {{ $activeNav === 'owner-withdrawal' ? 'bg-indigo-50 text-indigo-700 border-l-4 border-indigo-600' : 'text-gray-700 hover:bg-gray-100 hover:text-gray-900' }}">
                            <i class="fas fa-hand-holding-usd w-5 mr-3 {{ $activeNav === 'owner-withdrawal' ? 'text-indigo-600' : 'text-gray-400' }}"></i>
                            Owner withdrawal
                        </a>
                    @endif

                    @if($canReports)
                        <a href="{{ route('money-sources.reports') }}"
                           class="flex items-center px-3 md:px-4 py-2.5 md:py-3 text-sm font-medium rounded-lg transition-colors {{ $activeNav === 'reports' ? 'bg-indigo-50 text-indigo-700 border-l-4 border-indigo-600' : 'text-gray-700 hover:bg-gray-100 hover:text-gray-900' }}">
                            <i class="fas fa-list-alt w-5 mr-3 {{ $activeNav === 'reports' ? 'text-indigo-600' : 'text-gray-400' }}"></i>
                            Reports
                        </a>
                    @endif
                </nav>
            </aside>

            <div class="flex-1 min-w-0 p-4 md:p-6">
                @yield('money_sources_content')
            </div>
        </div>
    </div>
</div>
@endsection
