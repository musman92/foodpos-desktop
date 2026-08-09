@extends('layouts.app')

@section('title', 'User Details')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">User Details</h1>
            <p class="mt-1 text-sm text-gray-500">View user information and permissions</p>
        </div>
        <div class="flex items-center space-x-3">
            <a href="{{ route('users.index') }}" 
               class="px-4 py-2 h-12 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                <i class="fas fa-arrow-left mr-2"></i>
                Back to List
            </a>
            @if(auth()->user()->isSuperAdmin() || (auth()->user()->isCompanyAdmin() && auth()->user()->company_id === $user->company_id))
                <a href="{{ route('users.edit', $user) }}" 
                   class="px-4 py-2 h-12 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                    <i class="fas fa-edit mr-2"></i>
                    Edit User
                </a>
            @endif
        </div>
    </div>

    <!-- User Information Card -->
    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-indigo-500 to-purple-600">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="h-16 w-16 rounded-full bg-white flex items-center justify-center">
                        <span class="text-indigo-600 font-bold text-2xl">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                    </div>
                </div>
                <div class="ml-4">
                    <h2 class="text-xl font-semibold text-white">{{ $user->name }}</h2>
                    <p class="text-indigo-100">{{ $user->email }}</p>
                </div>
                <div class="ml-auto">
                    <span class="px-3 py-1 text-xs font-semibold rounded-full {{ $user->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                        {{ ucfirst($user->status) }}
                    </span>
                </div>
            </div>
        </div>

        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Basic Information -->
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-user mr-2 text-indigo-600"></i>
                        Basic Information
                    </h3>
                    <dl class="space-y-3">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Full Name</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $user->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Email</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $user->email }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Can sign in</dt>
                            <dd class="mt-1">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $user->can_login ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                    {{ $user->can_login ? 'Yes' : 'No' }}
                                </span>
                            </dd>
                        </div>
                        @if($user->phone)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Phone</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $user->phone }}</dd>
                            </div>
                        @endif
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Account level</dt>
                            <dd class="mt-1">
                                @php
                                    $typeColors = [
                                        'super_admin' => 'bg-purple-100 text-purple-800',
                                        'company_admin' => 'bg-blue-100 text-blue-800',
                                        'branch_manager' => 'bg-green-100 text-green-800',
                                        'staff' => 'bg-gray-100 text-gray-800',
                                        'waiter' => 'bg-amber-100 text-amber-800',
                                        'rider' => 'bg-sky-100 text-sky-800',
                                        'waiter_rider' => 'bg-violet-100 text-violet-800',
                                    ];
                                @endphp
                                <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $typeColors[$user->type] ?? 'bg-gray-100 text-gray-800' }}">
                                    {{ $user->accountTypeLabel() }}
                                </span>
                            </dd>
                        </div>
                        @if($user->primaryRoleName())
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Role</dt>
                                <dd class="mt-1">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                        {{ $user->primaryRoleName() }}
                                    </span>
                                </dd>
                            </div>
                        @endif
                    </dl>
                </div>

                <!-- Organization Information -->
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-building mr-2 text-green-600"></i>
                        Organization
                    </h3>
                    <dl class="space-y-3">
                        @if(auth()->user()->isSuperAdmin() && $user->company)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Company</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $user->company->name }}</dd>
                            </div>
                        @endif
                        @if($user->branches->count() > 0)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Branches</dt>
                                <dd class="mt-1">
                                    <div class="flex flex-wrap gap-2">
                                        @foreach($user->branches as $branch)
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium {{ $branch->pivot->is_primary ? 'bg-indigo-100 text-indigo-800 border-2 border-indigo-300' : 'bg-gray-100 text-gray-800' }}" title="{{ $branch->pivot->is_primary ? 'Primary Branch' : '' }}">
                                                {{ $branch->name }}
                                                @if($branch->pivot->is_primary)
                                                    <i class="fas fa-star ml-1 text-xs"></i>
                                                @endif
                                            </span>
                                        @endforeach
                                    </div>
                                </dd>
                            </div>
                        @endif
                        @if($user->salary)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Salary</dt>
                                <dd class="mt-1 text-sm text-gray-900 font-semibold">
                                    {{ format_currency($user->salary) }}
                                </dd>
                            </div>
                        @endif
                        @if($user->balance !== null)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Balance</dt>
                                <dd class="mt-1 text-sm font-semibold {{ $user->balance >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ format_currency($user->balance) }}
                                </dd>
                            </div>
                        @endif
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Status</dt>
                            <dd class="mt-1">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $user->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ ucfirst($user->status) }}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Created At</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ format_datetime($user->created_at) }}</dd>
                        </div>
                        @if($user->updated_at)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Last Updated</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ format_datetime($user->updated_at) }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </div>

            <!-- Roles & Permissions -->
            @if($user->roles->count() > 0)
                <div class="mt-6 pt-6 border-t border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-shield-alt mr-2 text-purple-600"></i>
                        Roles & Permissions
                    </h3>
                    <div class="flex flex-wrap gap-2">
                        @foreach($user->roles as $role)
                            <span class="px-3 py-1 text-sm font-semibold rounded-full bg-purple-100 text-purple-800">
                                {{ $role->name }}
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

