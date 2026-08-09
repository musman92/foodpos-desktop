@extends('layouts.app')

@section('title', 'Branch Details')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $branch->name }}</h1>
            <p class="mt-1 text-sm text-gray-500">Branch details and information</p>
        </div>
        <div class="flex items-center space-x-3">
            <a href="{{ route('branches.index') }}" 
               class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                <i class="fas fa-arrow-left mr-2"></i>
                Back to List
            </a>
            @if(auth()->user()->isSuperAdmin() || auth()->user()->isCompanyAdmin())
                <a href="{{ route('branches.edit', $branch) }}" 
                   class="px-4 py-2 bg-indigo-600 border border-transparent rounded-lg text-sm font-medium text-white hover:bg-indigo-700">
                    <i class="fas fa-edit mr-2"></i>
                    Edit Branch
                </a>
            @endif
        </div>
    </div>

    <!-- Branch Information Cards -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Information -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Basic Information -->
            <div class="bg-white shadow rounded-lg p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-info-circle mr-2 text-indigo-600"></i>
                    Basic Information
                </h2>
                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Branch Name</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $branch->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Branch Code</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            @if($branch->code)
                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">
                                    {{ $branch->code }}
                                </span>
                            @else
                                <span class="text-gray-400">N/A</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Company</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $branch->company->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Status</dt>
                        <dd class="mt-1">
                            <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $branch->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ ucfirst($branch->status) }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Timezone</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $branch->timezone }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Created</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $branch->created_at->format('M d, Y') }}</dd>
                    </div>
                </dl>
            </div>

            <!-- Contact Information -->
            <div class="bg-white shadow rounded-lg p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-address-book mr-2 text-green-600"></i>
                    Contact Information
                </h2>
                <dl class="space-y-4">
                    @if($branch->email)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Email</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                <a href="mailto:{{ $branch->email }}" class="text-indigo-600 hover:text-indigo-800">
                                    {{ $branch->email }}
                                </a>
                            </dd>
                        </div>
                    @endif
                    @if($branch->phone)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Phone</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                <a href="tel:{{ $branch->phone }}" class="text-indigo-600 hover:text-indigo-800">
                                    {{ $branch->phone }}
                                </a>
                            </dd>
                        </div>
                    @endif
                    @if($branch->address)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Address</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $branch->address }}</dd>
                        </div>
                    @endif
                    @if(!$branch->email && !$branch->phone && !$branch->address)
                        <p class="text-sm text-gray-400">No contact information available</p>
                    @endif
                </dl>
            </div>
        </div>

        <!-- Statistics Sidebar -->
        <div class="space-y-6">
            <!-- Quick Stats -->
            <div class="bg-white shadow rounded-lg p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Statistics</h2>
                <dl class="space-y-4">
                    <div class="flex items-center justify-between">
                        <dt class="text-sm font-medium text-gray-500">Total Users</dt>
                        <dd class="text-sm font-semibold text-gray-900">
                            {{ $branch->users->count() }}
                        </dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-sm font-medium text-gray-500">Total Tables</dt>
                        <dd class="text-sm font-semibold text-gray-900">
                            {{ $branch->tables->count() }}
                        </dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-sm font-medium text-gray-500">Total Orders</dt>
                        <dd class="text-sm font-semibold text-gray-900">
                            {{ $branch->orders->count() }}
                        </dd>
                    </div>
                </dl>
            </div>

            <!-- Actions -->
            @if(auth()->user()->isSuperAdmin() || auth()->user()->isCompanyAdmin())
                <div class="bg-white shadow rounded-lg p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Actions</h2>
                    <div class="space-y-2">
                        <a href="{{ route('branches.edit', $branch) }}" 
                           class="w-full flex items-center justify-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                            <i class="fas fa-edit mr-2"></i>
                            Edit Branch
                        </a>
                        <form action="{{ route('branches.destroy', $branch) }}" 
                              method="POST" 
                              onsubmit="return confirm('Are you sure you want to delete this branch? This action cannot be undone.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" 
                                    class="w-full flex items-center justify-center px-4 py-2 border border-red-300 rounded-lg text-sm font-medium text-red-700 bg-white hover:bg-red-50">
                                <i class="fas fa-trash mr-2"></i>
                                Delete Branch
                            </button>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

