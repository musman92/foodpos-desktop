@extends('layouts.app')

@section('title', 'Users')

@section('content')
@php
    $columnCount = 7;
    if (auth()->user()->isSuperAdmin()) {
        $columnCount = 9;
    } elseif (auth()->user()->isCompanyAdmin()) {
        $columnCount = 8;
    }
@endphp
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Users</h1>
            <p class="mt-1 text-sm text-gray-500">Manage system users and their permissions</p>
        </div>
        @if(auth()->user()->isSuperAdmin() || auth()->user()->isCompanyAdmin())
            <a href="{{ route('users.create') }}"
               class="inline-flex items-center px-4 py-2 h-12 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
                <i class="fas fa-plus mr-2"></i>
                Add User
            </a>
        @endif
    </div>

    <div class="bg-white shadow rounded-lg overflow-hidden">
        @include('partials.listing-per-page-bar', [
            'action' => route('users.index'),
            'paginator' => $users,
            'perPage' => $perPage,
        ])

        <div class="overflow-x-auto">
            <table class="listing-table min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">SN</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Name</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Email</th>
                        @if(auth()->user()->isSuperAdmin())
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Company</th>
                        @endif
                        @if(auth()->user()->isSuperAdmin() || auth()->user()->isCompanyAdmin())
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Branches</th>
                        @endif
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Type</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Phone</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Status</th>
                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($users as $user)
                        <tr>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-500">
                                {{ $users->firstItem() + $loop->index }}
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-900">
                                <span class="font-medium">{{ $user->name }}</span>
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-600">
                                {{ $user->email }}
                            </td>
                            @if(auth()->user()->isSuperAdmin())
                                <td class="px-3 py-3 whitespace-nowrap text-gray-600">
                                    {{ $user->company->name ?? '—' }}
                                </td>
                            @endif
                            @if(auth()->user()->isSuperAdmin() || auth()->user()->isCompanyAdmin())
                                <td class="px-3 py-3 whitespace-nowrap text-gray-600">
                                    @if($user->branches->count() > 0)
                                        {{ $user->branches->pluck('name')->join(', ') }}
                                    @else
                                        —
                                    @endif
                                </td>
                            @endif
                            <td class="px-3 py-3 whitespace-nowrap">
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
                                @if($user->primaryRoleName())
                                    <span class="ml-1 px-2 py-1 text-xs font-medium rounded-full bg-yellow-100 text-yellow-800">
                                        {{ $user->primaryRoleName() }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-600">
                                {{ $user->phone ?? '—' }}
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $user->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ ucfirst($user->status) }}
                                </span>
                                @if(!$user->can_login)
                                    <span class="ml-1 px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-600" title="Cannot sign in">
                                        No login
                                    </span>
                                @endif
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('users.show', $user) }}"
                                       class="text-indigo-600 hover:text-indigo-800"
                                       title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    @if(auth()->user()->isSuperAdmin() || (auth()->user()->isCompanyAdmin() && auth()->user()->company_id === $user->company_id))
                                        <a href="{{ route('users.edit', $user) }}"
                                           class="text-blue-600 hover:text-blue-800"
                                           title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        @if(auth()->user()->id !== $user->id)
                                            <form action="{{ route('users.destroy', $user) }}"
                                                  method="POST"
                                                  class="inline"
                                                  onsubmit="return confirm('Are you sure you want to delete this user?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        class="text-red-600 hover:text-red-800"
                                                        title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $columnCount }}" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center">
                                    <i class="fas fa-users text-gray-400 text-4xl mb-4"></i>
                                    <h3 class="text-sm font-medium text-gray-900 mb-1">No users found</h3>
                                    <p class="text-sm text-gray-500 mb-4">Get started by creating a new user.</p>
                                    @if(auth()->user()->isSuperAdmin() || auth()->user()->isCompanyAdmin())
                                        <a href="{{ route('users.create') }}"
                                           class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                                            <i class="fas fa-plus mr-2"></i>
                                            Add User
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @include('partials.listing-table-pagination', ['paginator' => $users])
    </div>
</div>
@endsection
