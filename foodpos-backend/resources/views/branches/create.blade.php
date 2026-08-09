@extends('layouts.app')

@section('title', 'Create Branch')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="bg-white shadow rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <h1 class="text-xl font-semibold text-gray-900">Create New Branch</h1>
            <p class="mt-1 text-sm text-gray-500">Add a new branch to your restaurant chain</p>
        </div>

        <form action="{{ route('branches.store') }}" method="POST" class="p-6 space-y-6">
            @csrf

            <!-- Company Selection (Super Admin only) -->
            @if(auth()->user()->isSuperAdmin())
                <div>
                    <label for="company_id" class="block text-sm font-medium text-gray-700 mb-2">
                        Company <span class="text-red-500">*</span>
                    </label>
                    <select name="company_id" 
                            id="company_id" 
                            required
                            class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('company_id') border-red-500 @enderror">
                        <option value="">Select a company</option>
                        @foreach($companies as $company)
                            <option value="{{ $company->id }}" {{ old('company_id') == $company->id ? 'selected' : '' }}>
                                {{ $company->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('company_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            @else
                <input type="hidden" name="company_id" value="{{ auth()->user()->company_id }}">
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Branch Name -->
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                        Branch Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" 
                           name="name" 
                           id="name" 
                           value="{{ old('name') }}"
                           required
                           class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('name') border-red-500 @enderror"
                           placeholder="e.g., Downtown Branch">
                    @error('name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Branch Code -->
                <div>
                    <label for="code" class="block text-sm font-medium text-gray-700 mb-2">
                        Branch Code
                    </label>
                    <input type="text" 
                           name="code" 
                           id="code" 
                           value="{{ old('code') }}"
                           class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('code') border-red-500 @enderror"
                           placeholder="e.g., DT001">
                    <p class="mt-1 text-xs text-gray-500">Unique code for this branch (optional)</p>
                    @error('code')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Email -->
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                        Email
                    </label>
                    <input type="email" 
                           name="email" 
                           id="email" 
                           value="{{ old('email') }}"
                           class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('email') border-red-500 @enderror"
                           placeholder="branch@example.com">
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
                <!-- Timezone -->
                <div>
                    <label for="timezone" class="block text-sm font-medium text-gray-700 mb-2">
                        Timezone <span class="text-red-500">*</span>
                    </label>
                    <select name="timezone" 
                            id="timezone" 
                            required
                            class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('timezone') border-red-500 @enderror">
                        <option value="">Select timezone</option>
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
                            <option value="{{ $tz }}" {{ old('timezone', $defaultTimezone ?? 'America/New_York') == $tz ? 'selected' : '' }}>
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
                        <option value="inactive" {{ old('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                    @error('status')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Form Actions -->
            <div class="flex items-center justify-end space-x-3 pt-6 border-t border-gray-200">
                <a href="{{ route('branches.index') }}" 
                   class="px-4 py-2 h-12 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Cancel
                </a>
                <button type="submit" 
                        class="px-4 py-2 h-12 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <i class="fas fa-save mr-2"></i>
                    Create Branch
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

