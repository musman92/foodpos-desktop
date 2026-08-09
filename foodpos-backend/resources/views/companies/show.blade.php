@extends('layouts.app')

@section('title', $company->name)

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $company->name }}</h1>
            <p class="mt-1 text-sm text-gray-500">Company Details</p>
        </div>
        <div class="flex items-center space-x-3">
            @if($company->demo)
                <form action="{{ route('companies.reset-demo-data', $company) }}" method="POST" class="inline" onsubmit="return confirm('Reset all demo data and load fresh Pizza Shop dataset (last 30 days)?');">
                    @csrf
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-amber-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 transition">
                        <i class="fas fa-sync-alt mr-2"></i>
                        Reset demo data
                    </button>
                </form>
            @endif
            <a href="{{ route('companies.edit', $company) }}" 
               class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
                <i class="fas fa-edit mr-2"></i>
                Edit Company
            </a>
            <a href="{{ route('companies.index') }}" 
               class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition">
                <i class="fas fa-arrow-left mr-2"></i>
                Back to List
            </a>
        </div>
    </div>

    <!-- Company Information -->
    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Company Information</h2>
        </div>
        <div class="p-6">
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <dt class="text-sm font-medium text-gray-500">Company Name</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $company->name }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Slug</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $company->slug }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Email</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $company->email }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Phone</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $company->phone ?? 'N/A' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Address</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $company->address ?? 'N/A' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Tax ID</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $company->tax_id ?? 'N/A' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Currency</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $company->currency ?? 'USD' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Timezone</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $company->timezone ?? 'America/New_York' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Status</dt>
                    <dd class="mt-1">
                        @if($company->demo)
                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-amber-100 text-amber-800 mr-1">Demo</span>
                        @endif
                        @if($company->status === 'active')
                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                Active
                            </span>
                        @elseif($company->status === 'suspended')
                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                Suspended
                            </span>
                        @else
                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                                Inactive
                            </span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Billing status</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $company->billingStatusLabel() }}</dd>
                </div>
                @if(! $company->demo && $company->billing_enabled)
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Plan</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            {{ format_platform_currency((float) ($company->billing_amount ?? 0), $company->billingCurrency()) }}
                            / {{ $company->billingIntervalLabel() }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Billing due (paid through)</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            {{ $company->billing_due_date ? $company->billing_due_date->format('M j, Y') : 'Not set' }}
                        </dd>
                    </div>
                    @if($company->trial_ends_at)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Trial ends</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $company->trial_ends_at->format('M j, Y') }}</dd>
                        </div>
                    @endif
                    @if($company->billing_starts_at)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Charging starts</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $company->billing_starts_at->format('M j, Y') }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Outstanding balance</dt>
                        <dd class="mt-1 text-sm font-semibold {{ $outstandingBalance > 0 ? 'text-amber-700' : 'text-green-700' }}">
                            {{ format_platform_currency($outstandingBalance, $company->billingCurrency()) }}
                        </dd>
                    </div>
                @endif
                <div>
                    <dt class="text-sm font-medium text-gray-500">Created At</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $company->created_at->format('Y-m-d H:i:s') }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Updated At</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $company->updated_at->format('Y-m-d H:i:s') }}</dd>
                </div>
                <div class="md:col-span-2">
                    <dt class="text-sm font-medium text-gray-500 mb-2">Add-ons</dt>
                    <dd class="mt-1 flex flex-wrap gap-2">
                        @php
                            $enabledAddons = collect(\App\Support\CompanyAddons::definitions())
                                ->filter(fn ($def, $key) => app(\App\Services\CompanyAddonService::class)->enabled($company, $key));
                        @endphp
                        @forelse($enabledAddons as $key => $addon)
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                                {{ $addon['label'] }}
                            </span>
                        @empty
                            <span class="text-sm text-gray-500">None enabled</span>
                        @endforelse
                    </dd>
                </div>
            </dl>
        </div>
    </div>

    <!-- Branding / receipt logo -->
    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Receipt logo</h2>
                <p class="text-sm text-gray-500 mt-0.5">Thermal receipts use a high-contrast black &amp; white copy of the company logo.</p>
            </div>
            @if($company->logo)
                <form action="{{ route('companies.generate-print-logo', $company) }}" method="POST" class="inline"
                      onsubmit="return confirm('Generate a new receipt print logo from the current company logo?');">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition">
                        <i class="fas fa-receipt mr-2"></i>
                        Generate print logo
                    </button>
                </form>
            @endif
        </div>
        <div class="p-6">
            @if(! $company->logo)
                <p class="text-sm text-gray-500">No logo uploaded for this tenant.</p>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 max-w-2xl">
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">Current logo</p>
                        <div class="border border-gray-200 rounded-lg p-4 bg-gray-50 flex items-center justify-center min-h-[6rem]">
                            <img src="{{ $company->logo_url }}" alt="" class="max-h-16 max-w-full object-contain">
                        </div>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">Receipt print logo</p>
                        <div class="border border-gray-200 rounded-lg p-4 bg-white flex items-center justify-center min-h-[6rem]">
                            @if($company->logo_print)
                                <img src="{{ $company->receipt_logo_url }}" alt="" class="max-h-16 max-w-full object-contain">
                            @else
                                <span class="text-sm text-amber-700 text-center">
                                    Not generated yet — receipts use a CSS filter on the color logo.
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
                @if($company->logo_print)
                    <p class="mt-4 text-sm text-green-700">
                        <i class="fas fa-check-circle mr-1"></i> Print logo is ready for thermal receipts.
                    </p>
                @else
                    <p class="mt-4 text-sm text-gray-600">
                        Click <strong>Generate print logo</strong> if the tenant reports a faint or missing logo on printed bills.
                    </p>
                @endif
            @endif
        </div>
    </div>

    <!-- Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white shadow rounded-lg p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 bg-indigo-500 rounded-md p-3">
                    <i class="fas fa-code-branch text-white text-xl"></i>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">Branches</dt>
                        <dd class="text-lg font-medium text-gray-900">{{ $company->branches->count() }}</dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="bg-white shadow rounded-lg p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 bg-green-500 rounded-md p-3">
                    <i class="fas fa-users text-white text-xl"></i>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">Users</dt>
                        <dd class="text-lg font-medium text-gray-900">{{ $company->users->count() }}</dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="bg-white shadow rounded-lg p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 bg-purple-500 rounded-md p-3">
                    <i class="fas fa-user-friends text-white text-xl"></i>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">Customers</dt>
                        <dd class="text-lg font-medium text-gray-900">{{ $company->customers->count() }}</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    @include('companies._transactional_reset')

    <!-- Branches List -->
    @if($company->branches->count() > 0)
        <div class="bg-white shadow rounded-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Branches</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="listing-table min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Branch Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Code</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($company->branches as $branch)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    {{ $branch->name }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $branch->code ?? 'N/A' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $branch->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                        {{ ucfirst($branch->status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <a href="{{ route('branches.show', $branch) }}" 
                                       class="text-indigo-600 hover:text-indigo-900">
                                        View
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection

