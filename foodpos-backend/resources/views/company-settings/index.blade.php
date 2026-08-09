@extends('layouts.app')

@section('title', 'Company Settings')

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="bg-white shadow rounded-lg overflow-hidden">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200">
            <h1 class="text-xl font-semibold text-gray-900">Company Settings</h1>
            <p class="mt-1 text-sm text-gray-500">Manage your company configuration and preferences</p>
        </div>

        <div class="flex flex-col md:flex-row">
            <!-- Sidebar Navigation -->
            <div class="w-full md:w-64 border-b md:border-b-0 md:border-r border-gray-200 bg-gray-50">
                <nav class="p-4 space-y-1" aria-label="Settings Navigation">
                    @foreach($sections as $sectionKey => $section)
                        <a href="{{ route('company-settings.index', ['section' => $sectionKey]) }}" 
                           class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-colors {{ $activeSection === $sectionKey ? 'bg-indigo-50 text-indigo-700 border-l-4 border-indigo-600' : 'text-gray-700 hover:bg-gray-100 hover:text-gray-900' }}">
                            <span>{{ $section['title'] }}</span>
                        </a>
                    @endforeach
                </nav>
            </div>

            <!-- Content Area -->
            <div class="flex-1 p-6">
                @if(! in_array($activeSection, ['receipt', 'pos'], true))
                    <div class="mb-6 rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-900">
                        <i class="fas fa-receipt mr-2"></i>
                        <strong>Invoice print options</strong> (logo, address, thank-you note, footer, etc.) are under
                        <a href="{{ route('company-settings.index', ['section' => 'receipt']) }}" class="font-semibold underline hover:text-indigo-700">Invoice / Receipt</a>.
                    </div>
                @endif
                <!-- General Section -->
                @if($activeSection === 'general')
                <form action="{{ route('company-settings.update.general', $company) }}" method="POST" enctype="multipart/form-data" class="space-y-6">
                    @csrf
                    @method('PUT')
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 mb-2 flex items-center">
                            <i class="fas fa-building mr-2 text-indigo-600"></i>
                            Company Information
                        </h2>
                        <p class="text-sm text-gray-500 mb-6">{{ $sections['general']['description'] }}</p>
                        
                        <div class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Company Name -->
                                <div>
                                    <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                                        Company Name <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" 
                                           name="name" 
                                           id="name" 
                                           value="{{ old('name', $company->name) }}"
                                           required
                                           class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('name') border-red-500 @enderror"
                                           placeholder="Company Name">
                                    @error('name')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Email -->
                                <div>
                                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                                        Email <span class="text-red-500">*</span>
                                    </label>
                                    <input type="email" 
                                           name="email" 
                                           id="email" 
                                           value="{{ old('email', $company->email) }}"
                                           required
                                           class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('email') border-red-500 @enderror"
                                           placeholder="company@example.com">
                                    @error('email')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Phone -->
                                <div>
                                    <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">
                                        Phone
                                    </label>
                                    <input type="text" 
                                           name="phone" 
                                           id="phone" 
                                           value="{{ old('phone', $company->phone) }}"
                                           class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('phone') border-red-500 @enderror"
                                           placeholder="+1234567890">
                                    @error('phone')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Tax ID -->
                                <div>
                                    <label for="tax_id" class="block text-sm font-medium text-gray-700 mb-2">
                                        Tax ID
                                    </label>
                                    <input type="text" 
                                           name="tax_id" 
                                           id="tax_id" 
                                           value="{{ old('tax_id', $company->tax_id) }}"
                                           class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('tax_id') border-red-500 @enderror"
                                           placeholder="Tax Identification Number">
                                    @error('tax_id')
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
                                          placeholder="Street address, City, State, ZIP">{{ old('address', $company->address) }}</textarea>
                                @error('address')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Branding Section -->
                            <div class="border-t pt-6 mt-6">
                                <h3 class="text-md font-semibold text-gray-800 mb-4 flex items-center">
                                    <i class="fas fa-palette mr-2 text-purple-600"></i>
                                    Branding & Visual Identity
                                </h3>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <!-- Logo Upload -->
                                    <div>
                                        <label for="logo" class="block text-sm font-medium text-gray-700 mb-2">
                                            Company Logo
                                        </label>
                                        <div class="flex items-center space-x-4">
                                            @if($company->logo)
                                                <div class="flex-shrink-0">
                                                    <img src="{{ Storage::url($company->logo) }}" 
                                                         alt="Company Logo" 
                                                         class="h-16 w-16 object-contain rounded-lg border border-gray-200">
                                                </div>
                                            @endif
                                            <div class="flex-1">
                                                <input type="file" 
                                                       name="logo" 
                                                       id="logo" 
                                                       accept="image/*"
                                                       class="block w-full h-12 px-4 py-2 text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 border-gray-300 rounded-lg focus:border-indigo-500 focus:ring-indigo-500 @error('logo') border-red-500 @enderror">
                                                <p class="mt-1 text-xs text-gray-500">PNG, JPG, GIF, SVG or WEBP (Max 2MB)</p>
                                            </div>
                                        </div>
                                        @error('logo')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <!-- Favicon Upload -->
                                    <div>
                                        <label for="favicon" class="block text-sm font-medium text-gray-700 mb-2">
                                            Favicon
                                        </label>
                                        <div class="flex items-center space-x-4">
                                            @if($company->favicon)
                                                <div class="flex-shrink-0">
                                                    <img src="{{ Storage::url($company->favicon) }}" 
                                                         alt="Favicon" 
                                                         class="h-8 w-8 object-contain rounded border border-gray-200">
                                                </div>
                                            @endif
                                            <div class="flex-1">
                                                <input type="file" 
                                                       name="favicon" 
                                                       id="favicon" 
                                                       accept="image/*,.ico"
                                                       class="block w-full h-12 px-4 py-2 text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 border-gray-300 rounded-lg focus:border-indigo-500 focus:ring-indigo-500 @error('favicon') border-red-500 @enderror">
                                                <p class="mt-1 text-xs text-gray-500">ICO, PNG or JPG (Max 512KB)</p>
                                            </div>
                                        </div>
                                        @error('favicon')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="flex items-center justify-end space-x-3 pt-6 border-t border-gray-200 mt-6">
                            <a href="{{ route('dashboard') }}" 
                               class="px-4 py-2 h-12 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Cancel
                            </a>
                            <button type="submit" 
                                    class="px-4 py-2 h-12 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                <i class="fas fa-save mr-2"></i>
                                Save Settings
                            </button>
                        </div>
                    </form>
                @endif

                <!-- Preferences Section -->
                @if($activeSection === 'preferences')
                <form action="{{ route('company-settings.update.preferences', $company) }}" method="POST" class="space-y-6">
                    @csrf
                    @method('PUT')
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 mb-2 flex items-center">
                            <i class="fas fa-cog mr-2 text-green-600"></i>
                            Global Settings & Preferences
                        </h2>
                        <p class="text-sm text-gray-500 mb-6">{{ $sections['preferences']['description'] }}</p>
                        
                        <!-- Currency and Timezone -->
                        <div class="mb-8">
                            <h3 class="text-md font-semibold text-gray-800 mb-4">Global Settings</h3>
                            <p class="text-sm text-gray-500 mb-4">These settings will be used as defaults for new branches and throughout the application</p>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Currency -->
                                <div>
                                    <label for="currency" class="block text-sm font-medium text-gray-700 mb-2">
                                        Currency <span class="text-red-500">*</span>
                                    </label>
                                    <select name="currency" 
                                            id="currency" 
                                            required
                                            class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('currency') border-red-500 @enderror">
                                        @php
                                            $currencies = [
                                                'USD' => 'US Dollar ($)',
                                                'EUR' => 'Euro (€)',
                                                'GBP' => 'British Pound (£)',
                                                'JPY' => 'Japanese Yen (¥)',
                                                'AUD' => 'Australian Dollar (A$)',
                                                'CAD' => 'Canadian Dollar (C$)',
                                                'CHF' => 'Swiss Franc (CHF)',
                                                'CNY' => 'Chinese Yuan (¥)',
                                                'INR' => 'Indian Rupee (₹)',
                                                'AED' => 'UAE Dirham (د.إ)',
                                                'SAR' => 'Saudi Riyal (﷼)',
                                                'PKR' => 'Pakistani Rupee (₨)',
                                                'BHD' => 'Bahraini Dinar (.د.ب)',
                                                'KWD' => 'Kuwaiti Dinar (د.ك)',
                                                'OMR' => 'Omani Rial (﷼)',
                                                'QAR' => 'Qatari Riyal (﷼)',
                                            ];
                                        @endphp
                                        @foreach($currencies as $code => $label)
                                            <option value="{{ $code }}" {{ old('currency', $company->currency ?? 'USD') == $code ? 'selected' : '' }}>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('currency')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Timezone -->
                                <div>
                                    <label for="timezone" class="block text-sm font-medium text-gray-700 mb-2">
                                        Timezone <span class="text-red-500">*</span>
                                    </label>
                                    <select name="timezone" 
                                            id="timezone" 
                                            required
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
                                            <option value="{{ $tz }}" {{ old('timezone', $company->timezone ?? 'America/New_York') == $tz ? 'selected' : '' }}>
                                                {{ $label }} ({{ $tz }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('timezone')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Week starts on -->
                                <div class="md:col-span-2">
                                    <label for="week_starts_on" class="block text-sm font-medium text-gray-700 mb-2">
                                        Business week starts on <span class="text-red-500">*</span>
                                    </label>
                                    @php
                                        $weekStartsOn = old('week_starts_on', $company->settings['week_starts_on'] ?? 'monday');
                                        $weekdayLabels = [
                                            'monday' => 'Monday',
                                            'tuesday' => 'Tuesday',
                                            'wednesday' => 'Wednesday',
                                            'thursday' => 'Thursday',
                                            'friday' => 'Friday',
                                            'saturday' => 'Saturday',
                                            'sunday' => 'Sunday',
                                        ];
                                    @endphp
                                    <select name="week_starts_on"
                                            id="week_starts_on"
                                            required
                                            class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('week_starts_on') border-red-500 @enderror">
                                        @foreach($weekdayLabels as $value => $label)
                                            <option value="{{ $value }}" {{ $weekStartsOn === $value ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <p class="mt-1 text-xs text-gray-500">Used for weekly and monthly closing reports.</p>
                                    @error('week_starts_on')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Display Configuration -->
                        <div class="border-t pt-6">
                            <h3 class="text-md font-semibold text-gray-800 mb-4">Display Configuration</h3>
                            <p class="text-sm text-gray-500 mb-4">Configure how currency, dates, and times are displayed throughout the application</p>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Listing page size -->
                                <div>
                                    <label for="listing_per_page" class="block text-sm font-medium text-gray-700 mb-2">
                                        List entries per page <span class="text-red-500">*</span>
                                    </label>
                                    @php
                                        $listingPerPage = old('listing_per_page', $company->settings['listing_per_page'] ?? listing_per_page());
                                    @endphp
                                    <select name="listing_per_page"
                                            id="listing_per_page"
                                            required
                                            class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('listing_per_page') border-red-500 @enderror">
                                        @foreach(\App\Support\ListingPerPage::OPTIONS as $size)
                                            <option value="{{ $size }}" {{ (int) $listingPerPage === $size ? 'selected' : '' }}>{{ $size }} rows</option>
                                        @endforeach
                                    </select>
                                    <p class="mt-1 text-xs text-gray-500">Default for ingredients, categories, customers, and other list screens. Users can still change it on each page.</p>
                                    @error('listing_per_page')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Currency Position -->
                                <div>
                                    <label for="currency_position" class="block text-sm font-medium text-gray-700 mb-2">
                                        Currency Position <span class="text-red-500">*</span>
                                    </label>
                                    <select name="currency_position" 
                                            id="currency_position" 
                                            required
                                            onchange="updateCurrencyPreview()"
                                            class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('currency_position') border-red-500 @enderror">
                                        @php
                                            $currentPosition = old('currency_position', $company->settings['currency_position'] ?? 'left');
                                            $currentCurrency = old('currency', $company->currency ?? 'USD');
                                            $currentDecimals = old('decimal_points', $company->settings['decimal_points'] ?? 2);
                                            $currencySymbol = get_currency_symbol($currentCurrency);
                                            $exampleAmount = number_format(100.00, $currentDecimals, '.', ',');
                                        @endphp
                                        <option value="left" {{ $currentPosition == 'left' ? 'selected' : '' }}>Left (e.g., {{ $currencySymbol }}{{ $exampleAmount }})</option>
                                        <option value="right" {{ $currentPosition == 'right' ? 'selected' : '' }}>Right (e.g., {{ $exampleAmount }} {{ $currencySymbol }})</option>
                                    </select>
                                    <p class="mt-1 text-xs text-gray-500">
                                        Preview: <span id="currency-preview" class="font-semibold">{{ $currentPosition == 'left' ? $currencySymbol . $exampleAmount : $exampleAmount . ' ' . $currencySymbol }}</span>
                                    </p>
                                    @error('currency_position')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Decimal Points -->
                                <div>
                                    <label for="decimal_points" class="block text-sm font-medium text-gray-700 mb-2">
                                        Decimal Points <span class="text-red-500">*</span>
                                    </label>
                                    <select name="decimal_points" 
                                            id="decimal_points" 
                                            required
                                            onchange="updateCurrencyPreview()"
                                            class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('decimal_points') border-red-500 @enderror">
                                        @php
                                            $currentDecimals = old('decimal_points', $company->settings['decimal_points'] ?? 2);
                                        @endphp
                                        <option value="0" {{ $currentDecimals == 0 ? 'selected' : '' }}>0 (e.g., 100)</option>
                                        <option value="1" {{ $currentDecimals == 1 ? 'selected' : '' }}>1 (e.g., 100.0)</option>
                                        <option value="2" {{ $currentDecimals == 2 ? 'selected' : '' }}>2 (e.g., 100.00)</option>
                                        <option value="3" {{ $currentDecimals == 3 ? 'selected' : '' }}>3 (e.g., 100.000)</option>
                                        <option value="4" {{ $currentDecimals == 4 ? 'selected' : '' }}>4 (e.g., 100.0000)</option>
                                    </select>
                                    @error('decimal_points')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Time Format -->
                                <div>
                                    <label for="time_format" class="block text-sm font-medium text-gray-700 mb-2">
                                        Time Format <span class="text-red-500">*</span>
                                    </label>
                                    <select name="time_format" 
                                            id="time_format" 
                                            required
                                            class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('time_format') border-red-500 @enderror">
                                        @php
                                            $currentTimeFormat = old('time_format', $company->settings['time_format'] ?? '12');
                                        @endphp
                                        <option value="12" {{ $currentTimeFormat == '12' ? 'selected' : '' }}>12-hour (e.g., 3:45 PM)</option>
                                        <option value="24" {{ $currentTimeFormat == '24' ? 'selected' : '' }}>24-hour (e.g., 15:45)</option>
                                    </select>
                                    @error('time_format')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Date Format -->
                                <div>
                                    <label for="date_format" class="block text-sm font-medium text-gray-700 mb-2">
                                        Date Format <span class="text-red-500">*</span>
                                    </label>
                                    <select name="date_format" 
                                            id="date_format" 
                                            required
                                            class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('date_format') border-red-500 @enderror">
                                        @php
                                            $currentDateFormat = old('date_format', $company->settings['date_format'] ?? 'Y-m-d');
                                            $dateFormats = [
                                                'Y-m-d' => 'YYYY-MM-DD (2024-12-25)',
                                                'd-m-Y' => 'DD-MM-YYYY (25-12-2024)',
                                                'm-d-Y' => 'MM-DD-YYYY (12-25-2024)',
                                                'd/m/Y' => 'DD/MM/YYYY (25/12/2024)',
                                                'm/d/Y' => 'MM/DD/YYYY (12/25/2024)',
                                                'Y/m/d' => 'YYYY/MM/DD (2024/12/25)',
                                            ];
                                        @endphp
                                        @foreach($dateFormats as $format => $label)
                                            <option value="{{ $format }}" {{ $currentDateFormat == $format ? 'selected' : '' }}>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('date_format')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        @php
                            $strictDirectPayRate = old('strict_direct_pay_rate', $company->settings['strict_direct_pay_rate'] ?? false);
                        @endphp
                        <div class="mb-8 border-t border-gray-200 pt-8">
                            <h3 class="text-md font-semibold text-gray-800 mb-4">HR / Employee payments</h3>
                            <div class="space-y-4 max-w-2xl">
                                <label class="flex items-start gap-3 cursor-pointer">
                                    <input type="hidden" name="strict_direct_pay_rate" value="0">
                                    <input type="checkbox"
                                           name="strict_direct_pay_rate"
                                           value="1"
                                           {{ filter_var($strictDirectPayRate, FILTER_VALIDATE_BOOLEAN) ? 'checked' : '' }}
                                           class="mt-1 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    <span>
                                        <span class="block text-sm font-medium text-gray-900">Track pay rate on direct wages</span>
                                        <span class="block text-xs text-gray-500 mt-0.5">When enabled, you can pay any amount; the difference from the employee pay rate (plus selected bonuses/deductions) updates their balance. Underpay increases payable balance; overpay reduces it. When disabled, direct wages stay balance-neutral.</span>
                                    </span>
                                </label>
                            </div>
                        </div>

                        <div class="mb-8 pt-6 border-t border-gray-200">
                            <h3 class="text-md font-semibold text-gray-800 mb-2">Activity logging</h3>
                            <p class="text-sm text-gray-500 mb-4">
                                When enabled, the system records shift open/close money snapshots, orders, payments,
                                transfers, withdrawals, reconciliations, and money-source changes for debugging.
                            </p>
                            @php
                                $activityLoggingEnabled = old(
                                    'activity_logging_enabled',
                                    $company->settings['activity_logging_enabled'] ?? false
                                );
                            @endphp
                            <label class="flex items-start gap-3 cursor-pointer max-w-2xl">
                                <input type="hidden" name="activity_logging_enabled" value="0">
                                <input type="checkbox"
                                       name="activity_logging_enabled"
                                       value="1"
                                       {{ filter_var($activityLoggingEnabled, FILTER_VALIDATE_BOOLEAN) ? 'checked' : '' }}
                                       class="mt-1 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                <span>
                                    <span class="block text-sm font-medium text-gray-900">Enable activity logs</span>
                                    <span class="block text-sm text-gray-500">Leave off unless you need a full trail for this company.</span>
                                </span>
                            </label>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex items-center justify-end space-x-3 pt-6 border-t border-gray-200 mt-6">
                        <a href="{{ route('dashboard') }}" 
                           class="px-4 py-2 h-12 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Cancel
                        </a>
                        <button type="submit" 
                                class="px-4 py-2 h-12 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            <i class="fas fa-save mr-2"></i>
                            Save Preferences
                        </button>
                    </div>
                </form>
                @endif

                <!-- Point of Sale Section -->
                @if($activeSection === 'pos')
                <form action="{{ route('company-settings.update.pos', $company) }}" method="POST" class="space-y-6">
                    @csrf
                    @method('PUT')
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 mb-2 flex items-center">
                            <i class="fas fa-cash-register mr-2 text-indigo-600"></i>
                            Point of Sale
                        </h2>
                        <p class="text-sm text-gray-500 mb-6">{{ $sections['pos']['description'] }}</p>

                        @php
                            $allowPosCredit = old('allow_pos_credit_sales', $company->settings['allow_pos_credit_sales'] ?? false);
                            $directPosPrint = old('direct_pos_print', $company->settings['direct_pos_print'] ?? false);
                            $showPosAutoBillToggle = old('show_pos_auto_bill_toggle', $company->settings['show_pos_auto_bill_toggle'] ?? false);
                            $posLayout = old('pos_layout', $company->settings['pos_layout'] ?? \App\Support\PosLayout::LAYOUT_CLASSIC);
                            $posProductDensity = old('pos_product_density', $company->settings['pos_product_density'] ?? \App\Support\PosLayout::DENSITY_COMFORTABLE);
                            $posOrderContextStyle = old('pos_order_context_style', $company->settings['pos_order_context_style'] ?? \App\Support\PosLayout::ORDER_CONTEXT_LABELED);
                            $posCategorySize = old('pos_category_size', $company->settings['pos_category_size'] ?? \App\Support\PosLayout::CATEGORY_SIZE_NORMAL);
                            $posCategoryLayout = old('pos_category_layout', $company->settings['pos_category_layout'] ?? \App\Support\PosLayout::CATEGORY_LAYOUT_STRIP);
                        @endphp

                        <div class="mb-8">
                            <h3 class="text-md font-semibold text-gray-800 mb-4">Checkout behaviour</h3>
                            <div class="space-y-4 max-w-2xl">
                                <label class="flex items-start gap-3 cursor-pointer">
                                    <input type="hidden" name="allow_pos_credit_sales" value="0">
                                    <input type="checkbox"
                                           name="allow_pos_credit_sales"
                                           value="1"
                                           {{ filter_var($allowPosCredit, FILTER_VALIDATE_BOOLEAN) ? 'checked' : '' }}
                                           class="mt-1 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    <span>
                                        <span class="block text-sm font-medium text-gray-900">Allow sell on credit</span>
                                        <span class="block text-xs text-gray-500 mt-0.5">Cashiers can complete checkout when payment is less than the order total. A registered customer must be selected; the unpaid amount is added to that customer&apos;s balance.</span>
                                    </span>
                                </label>
                                <label class="flex items-start gap-3 cursor-pointer">
                                    <input type="hidden" name="direct_pos_print" value="0">
                                    <input type="checkbox"
                                           name="direct_pos_print"
                                           value="1"
                                           {{ filter_var($directPosPrint, FILTER_VALIDATE_BOOLEAN) ? 'checked' : '' }}
                                           class="mt-1 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    <span>
                                        <span class="block text-sm font-medium text-gray-900">Direct POS print</span>
                                        <span class="block text-xs text-gray-500 mt-0.5">After Pay now, Checkout, or Print bill, send the receipt straight to the printer without showing the invoice preview modal.</span>
                                    </span>
                                </label>
                                <label class="flex items-start gap-3 cursor-pointer">
                                    <input type="hidden" name="show_pos_auto_bill_toggle" value="0">
                                    <input type="checkbox"
                                           name="show_pos_auto_bill_toggle"
                                           value="1"
                                           {{ filter_var($showPosAutoBillToggle, FILTER_VALIDATE_BOOLEAN) ? 'checked' : '' }}
                                           class="mt-1 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    <span>
                                        <span class="block text-sm font-medium text-gray-900">Show Auto Bill toggle on POS</span>
                                        <span class="block text-xs text-gray-500 mt-0.5">Cashiers can turn Auto Bill off so Checkout still takes payment but does not auto-print the invoice or kitchen ticket. Print and Kitchen buttons still work as usual.</span>
                                    </span>
                                </label>
                            </div>
                        </div>

                        <div class="border-t pt-6 mb-8">
                            <h3 class="text-md font-semibold text-gray-800 mb-4">Screen layout</h3>
                            <p class="text-sm text-gray-500 mb-4">Customize the POS screen layout for your team. Classic keeps today&apos;s default look.</p>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 max-w-2xl">
                                <div>
                                    <label for="pos_layout" class="block text-sm font-medium text-gray-700 mb-2">
                                        POS layout
                                    </label>
                                    <select name="pos_layout"
                                            id="pos_layout"
                                            class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('pos_layout') border-red-500 @enderror">
                                        @foreach (\App\Support\PosLayout::layoutPresets() as $value => $preset)
                                            <option value="{{ $value }}"
                                                    {{ $posLayout === $value ? 'selected' : '' }}
                                                    @disabled(! $preset['available'])>
                                                {{ $preset['label'] }}{{ $value === \App\Support\PosLayout::LAYOUT_CLASSIC ? ' (default)' : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <p class="mt-1 text-xs text-gray-500">{{ \App\Support\PosLayout::layoutPresets()[\App\Support\PosLayout::LAYOUT_CLASSIC]['description'] }}</p>
                                    @error('pos_layout')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label for="pos_product_density" class="block text-sm font-medium text-gray-700 mb-2">
                                        Product card size
                                    </label>
                                    <select name="pos_product_density"
                                            id="pos_product_density"
                                            class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('pos_product_density') border-red-500 @enderror">
                                        @foreach (\App\Support\PosLayout::productDensities() as $value => $density)
                                            <option value="{{ $value }}" {{ $posProductDensity === $value ? 'selected' : '' }}>
                                                {{ $density['label'] }}{{ $value === \App\Support\PosLayout::DENSITY_COMFORTABLE ? ' (default)' : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <p class="mt-1 text-xs text-gray-500">{{ \App\Support\PosLayout::productDensities()[$posProductDensity]['description'] ?? \App\Support\PosLayout::productDensities()[\App\Support\PosLayout::DENSITY_COMFORTABLE]['description'] }}</p>
                                    @error('pos_product_density')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div class="md:col-span-2">
                                    <label for="pos_order_context_style" class="block text-sm font-medium text-gray-700 mb-2">
                                        Order details (cart header)
                                    </label>
                                    <select name="pos_order_context_style"
                                            id="pos_order_context_style"
                                            class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('pos_order_context_style') border-red-500 @enderror">
                                        @foreach (\App\Support\PosLayout::orderContextStyles() as $value => $style)
                                            <option value="{{ $value }}" {{ $posOrderContextStyle === $value ? 'selected' : '' }}>
                                                {{ $style['label'] }}{{ $value === \App\Support\PosLayout::ORDER_CONTEXT_LABELED ? ' (default)' : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <p class="mt-1 text-xs text-gray-500">{{ \App\Support\PosLayout::orderContextStyles()[$posOrderContextStyle]['description'] ?? \App\Support\PosLayout::orderContextStyles()[\App\Support\PosLayout::ORDER_CONTEXT_LABELED]['description'] }}</p>
                                    @error('pos_order_context_style')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="border-t pt-6 mb-8">
                            <h3 class="text-md font-semibold text-gray-800 mb-4">Category bar</h3>
                            <p class="text-sm text-gray-500 mb-4">How category pills appear above the product grid on the POS screen.</p>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 max-w-2xl">
                                <div>
                                    <label for="pos_category_size" class="block text-sm font-medium text-gray-700 mb-2">
                                        Category pill size
                                    </label>
                                    <select name="pos_category_size"
                                            id="pos_category_size"
                                            class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('pos_category_size') border-red-500 @enderror">
                                        @foreach (\App\Support\PosLayout::categorySizes() as $value => $size)
                                            <option value="{{ $value }}" {{ $posCategorySize === $value ? 'selected' : '' }}>
                                                {{ $size['label'] }}{{ $value === \App\Support\PosLayout::CATEGORY_SIZE_NORMAL ? ' (default)' : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <p class="mt-1 text-xs text-gray-500">{{ \App\Support\PosLayout::categorySizes()[$posCategorySize]['description'] ?? \App\Support\PosLayout::categorySizes()[\App\Support\PosLayout::CATEGORY_SIZE_NORMAL]['description'] }}</p>
                                    @error('pos_category_size')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label for="pos_category_layout" class="block text-sm font-medium text-gray-700 mb-2">
                                        Category layout
                                    </label>
                                    <select name="pos_category_layout"
                                            id="pos_category_layout"
                                            class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('pos_category_layout') border-red-500 @enderror">
                                        @foreach (\App\Support\PosLayout::categoryLayouts() as $value => $layout)
                                            <option value="{{ $value }}" {{ $posCategoryLayout === $value ? 'selected' : '' }}>
                                                {{ $layout['label'] }}{{ $value === \App\Support\PosLayout::CATEGORY_LAYOUT_STRIP ? ' (default)' : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <p class="mt-1 text-xs text-gray-500">{{ \App\Support\PosLayout::categoryLayouts()[$posCategoryLayout]['description'] ?? \App\Support\PosLayout::categoryLayouts()[\App\Support\PosLayout::CATEGORY_LAYOUT_STRIP]['description'] }}</p>
                                    @error('pos_category_layout')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end space-x-3 pt-6 border-t border-gray-200 mt-6">
                        <a href="{{ route('dashboard') }}"
                           class="px-4 py-2 h-12 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Cancel
                        </a>
                        <button type="submit"
                                class="px-4 py-2 h-12 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            <i class="fas fa-save mr-2"></i>
                            Save POS Settings
                        </button>
                    </div>
                </form>
                @endif

                <!-- Invoice / Receipt Section -->
                @if($activeSection === 'receipt')
                <form action="{{ route('company-settings.update.receipt', $company) }}" method="POST" class="space-y-6">
                    @csrf
                    @method('PUT')
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 mb-2 flex items-center">
                            <i class="fas fa-receipt mr-2 text-indigo-600"></i>
                            Invoice / Receipt
                        </h2>
                        <p class="text-sm text-gray-500 mb-6">{{ $sections['receipt']['description'] }}</p>

                        @php
                            $receiptFontSize = old('receipt_font_size', $company->settings['receipt_font_size'] ?? 14);
                            $receiptPaperWidth = old('receipt_paper_width_mm', $company->settings['receipt_paper_width_mm'] ?? 80);
                        @endphp

                        @include('company-settings._receipt-invoice-settings', [
                            'company' => $company,
                            'receiptFontSize' => $receiptFontSize,
                            'receiptPaperWidth' => $receiptPaperWidth,
                        ])
                    </div>

                    <div class="flex items-center justify-end space-x-3 pt-6 border-t border-gray-200 mt-6">
                        <a href="{{ route('dashboard') }}"
                           class="px-4 py-2 h-12 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Cancel
                        </a>
                        <button type="submit"
                                class="px-4 py-2 h-12 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            <i class="fas fa-save mr-2"></i>
                            Save Receipt Settings
                        </button>
                    </div>
                </form>
                @endif
            </div>
        </div>
    </div>
</div>

<script>
function updateCurrencyPreview() {
    const currencySelect = document.getElementById('currency');
    const positionSelect = document.getElementById('currency_position');
    const decimalsSelect = document.getElementById('decimal_points');
    const preview = document.getElementById('currency-preview');
    
    if (!currencySelect || !positionSelect || !decimalsSelect || !preview) return;
    
    const currency = currencySelect.value;
    const position = positionSelect.value;
    const decimals = parseInt(decimalsSelect.value);
    
    const symbols = {
        'USD': '$', 'EUR': '€', 'GBP': '£', 'JPY': '¥', 'AUD': 'A$', 'CAD': 'C$',
        'CHF': 'CHF', 'CNY': '¥', 'INR': '₹', 'AED': 'د.إ', 'SAR': '﷼', 'PKR': '₨',
        'BHD': '.د.ب', 'KWD': 'د.ك', 'OMR': '﷼', 'QAR': '﷼'
    };
    
    const symbol = symbols[currency] || currency;
    const amount = (100.00).toFixed(decimals);
    const formatted = parseFloat(amount).toLocaleString('en-US', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    });
    
    preview.textContent = position === 'left' 
        ? symbol + formatted 
        : formatted + ' ' + symbol;
}

// Update preview when currency, position, or decimals change
document.addEventListener('DOMContentLoaded', function() {
    const currencySelect = document.getElementById('currency');
    const positionSelect = document.getElementById('currency_position');
    const decimalsSelect = document.getElementById('decimal_points');
    
    if (currencySelect) {
        currencySelect.addEventListener('change', updateCurrencyPreview);
    }
    if (positionSelect) {
        positionSelect.addEventListener('change', updateCurrencyPreview);
    }
    if (decimalsSelect) {
        decimalsSelect.addEventListener('change', updateCurrencyPreview);
    }
});
</script>
@endsection
