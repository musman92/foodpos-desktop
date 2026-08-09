@extends('layouts.app')

@section('title', 'Create Company')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="bg-white shadow rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <h1 class="text-xl font-semibold text-gray-900">Create New Company</h1>
            <p class="mt-1 text-sm text-gray-500">Add a new company to the system</p>
        </div>

        <form action="{{ route('companies.store') }}" method="POST" class="p-6 space-y-6">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Company Name -->
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                        Company Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" 
                           name="name" 
                           id="name" 
                           value="{{ old('name') }}"
                           required
                           class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('name') border-red-500 @enderror"
                           placeholder="e.g., Acme Restaurant Group">
                    @error('name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Slug -->
                <div>
                    <label for="slug" class="block text-sm font-medium text-gray-700 mb-2">
                        Slug
                    </label>
                    <input type="text" 
                           name="slug" 
                           id="slug" 
                           value="{{ old('slug') }}"
                           class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('slug') border-red-500 @enderror"
                           placeholder="auto-generated from name">
                    <p class="mt-1 text-xs text-gray-500">Leave empty to auto-generate from company name</p>
                    @error('slug')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Email -->
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                        Email <span class="text-red-500">*</span>
                    </label>
                    <input type="email" 
                           name="email" 
                           id="email" 
                           value="{{ old('email') }}"
                           required
                           class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('email') border-red-500 @enderror"
                           placeholder="company@example.com">
                    @error('email')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Phone -->
                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">
                        Phone
                    </label>
                    <input type="text" 
                           name="phone" 
                           id="phone" 
                           value="{{ old('phone') }}"
                           class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('phone') border-red-500 @enderror"
                           placeholder="+1234567890">
                    @error('phone')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Address -->
            <div>
                <label for="address" class="block text-sm font-medium text-gray-700 mb-2">
                    Address
                </label>
                <textarea name="address" 
                          id="address" 
                          rows="3"
                          class="block w-full px-4 py-3 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('address') border-red-500 @enderror"
                          placeholder="Street address, City, State, ZIP">{{ old('address') }}</textarea>
                @error('address')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Tax ID -->
                <div>
                    <label for="tax_id" class="block text-sm font-medium text-gray-700 mb-2">
                        Tax ID
                    </label>
                    <input type="text" 
                           name="tax_id" 
                           id="tax_id" 
                           value="{{ old('tax_id') }}"
                           class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('tax_id') border-red-500 @enderror"
                           placeholder="Tax identification number">
                    @error('tax_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Currency -->
                <div>
                    <label for="currency" class="block text-sm font-medium text-gray-700 mb-2">
                        Currency
                    </label>
                    <input type="text" 
                           name="currency" 
                           id="currency" 
                           value="{{ old('currency', 'USD') }}"
                           maxlength="3"
                           class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('currency') border-red-500 @enderror"
                           placeholder="USD">
                    <p class="mt-1 text-xs text-gray-500">3-letter currency code (e.g., USD, EUR, GBP)</p>
                    @error('currency')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Timezone -->
                <div>
                    <label for="timezone" class="block text-sm font-medium text-gray-700 mb-2">
                        Timezone
                    </label>
                    <select name="timezone" 
                            id="timezone" 
                            class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('timezone') border-red-500 @enderror">
                        @php
                            $timezones = [
                                'America/New_York' => 'Eastern Time (ET)',
                                'America/Chicago' => 'Central Time (CT)',
                                'America/Denver' => 'Mountain Time (MT)',
                                'America/Los_Angeles' => 'Pacific Time (PT)',
                                'America/Phoenix' => 'Arizona Time',
                                'America/Anchorage' => 'Alaska Time',
                                'Pacific/Honolulu' => 'Hawaii Time',
                                'America/Toronto' => 'Toronto (ET)',
                                'America/Vancouver' => 'Vancouver (PT)',
                                'Europe/London' => 'London (GMT)',
                                'Europe/Paris' => 'Paris (CET)',
                                'Europe/Berlin' => 'Berlin (CET)',
                                'Asia/Dubai' => 'Dubai (GST)',
                                'Asia/Riyadh' => 'Riyadh (AST)',
                                'Asia/Karachi' => 'Karachi (PKT)',
                                'Asia/Kolkata' => 'Mumbai/New Delhi (IST)',
                                'Asia/Singapore' => 'Singapore (SGT)',
                                'Asia/Tokyo' => 'Tokyo (JST)',
                                'Asia/Hong_Kong' => 'Hong Kong (HKT)',
                                'Australia/Sydney' => 'Sydney (AEDT)',
                                'UTC' => 'UTC',
                            ];
                        @endphp
                        @foreach($timezones as $tz => $label)
                            <option value="{{ $tz }}" {{ old('timezone', 'America/New_York') == $tz ? 'selected' : '' }}>
                                {{ $label }} ({{ $tz }})
                            </option>
                        @endforeach
                    </select>
                    @error('timezone')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Status -->
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-2">
                        Status <span class="text-red-500">*</span>
                    </label>
                    <select name="status" 
                            id="status" 
                            required
                            class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('status') border-red-500 @enderror">
                        <option value="active" {{ old('status', 'active') == 'active' ? 'selected' : '' }}>Active</option>
                        <option value="suspended" {{ old('status') == 'suspended' ? 'selected' : '' }}>Suspended</option>
                        <option value="inactive" {{ old('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                    @error('status')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Subscription / trial & billing -->
            <div class="border-t border-gray-200 pt-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-1">Subscription & billing</h2>
                <p class="text-sm text-gray-500 mb-4">Offer a free trial for new tenants. Charging begins after the trial unless you set amount to 0 with a long due date.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="trial_days" class="block text-sm font-medium text-gray-700 mb-2">Free trial period</label>
                    <select name="trial_days" id="trial_days" class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach($trialOptions as $days => $label)
                            <option value="{{ $days }}" @selected(old('trial_days', $defaultTrialDays) == $days)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-500">Billing due date and charge start are set to the trial end date automatically.</p>
                </div>

                <div>
                    <label for="billing_due_date" class="block text-sm font-medium text-gray-700 mb-2">Billing due date (optional override)</label>
                    <input type="date" name="billing_due_date" id="billing_due_date" value="{{ old('billing_due_date') }}"
                           class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <p class="mt-1 text-xs text-gray-500">Leave blank to use trial end. Set far future (e.g. +2 years) for complimentary access with $0 amount.</p>
                </div>
            </div>

            @include('companies._billing-fields-create')

            @include('companies._addons', ['companyAddons' => []])

            <!-- Divider -->
            <div class="border-t border-gray-200 pt-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Company Admin User</h2>
                <p class="text-sm text-gray-500 mb-6">Create an admin user who can login and manage this company.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Admin Name -->
                <div>
                    <label for="admin_name" class="block text-sm font-medium text-gray-700 mb-2">
                        Admin Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" 
                           name="admin_name" 
                           id="admin_name" 
                           value="{{ old('admin_name') }}"
                           required
                           class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('admin_name') border-red-500 @enderror"
                           placeholder="e.g., John Doe">
                    @error('admin_name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Admin Email -->
                <div>
                    <label for="admin_email" class="block text-sm font-medium text-gray-700 mb-2">
                        Admin Email <span class="text-red-500">*</span>
                    </label>
                    <input type="email" 
                           name="admin_email" 
                           id="admin_email" 
                           value="{{ old('admin_email') }}"
                           required
                           class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('admin_email') border-red-500 @enderror"
                           placeholder="admin@example.com">
                    @error('admin_email')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Admin Password -->
                <div>
                    <label for="admin_password" class="block text-sm font-medium text-gray-700 mb-2">
                        Admin Password <span class="text-red-500">*</span>
                    </label>
                    <input type="password" 
                           name="admin_password" 
                           id="admin_password" 
                           required
                           minlength="8"
                           class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('admin_password') border-red-500 @enderror"
                           placeholder="Minimum 8 characters">
                    @error('admin_password')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Admin Password Confirmation -->
                <div>
                    <label for="admin_password_confirmation" class="block text-sm font-medium text-gray-700 mb-2">
                        Confirm Password <span class="text-red-500">*</span>
                    </label>
                    <input type="password" 
                           name="admin_password_confirmation" 
                           id="admin_password_confirmation" 
                           required
                           minlength="8"
                           class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                           placeholder="Confirm password">
                </div>
            </div>

            <!-- Create Default Branch -->
            <div class="flex items-center">
                <input type="checkbox" 
                       name="create_default_branch" 
                       id="create_default_branch" 
                       value="1"
                       {{ old('create_default_branch', true) ? 'checked' : '' }}
                       class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                <label for="create_default_branch" class="ml-2 block text-sm text-gray-900">
                    Create a default branch for this company
                </label>
            </div>
            <p class="text-xs text-gray-500 ml-6">A default branch will be created automatically with the company name. You can add more branches later.</p>

            <!-- Form Actions -->
            <div class="flex items-center justify-end space-x-3 pt-6 border-t border-gray-200">
                <a href="{{ route('companies.index') }}" 
                   class="px-4 py-2 h-12 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Cancel
                </a>
                <button type="submit" 
                        class="px-4 py-2 h-12 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <i class="fas fa-save mr-2"></i>
                    Create Company
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

