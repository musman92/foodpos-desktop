@extends('layouts.app')

@section('title', 'Edit User')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="bg-white shadow rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <h1 class="text-xl font-semibold text-gray-900">Edit User</h1>
            <p class="mt-1 text-sm text-gray-500">Update user information</p>
        </div>

        @php
            $accountTypeValue = old('type', in_array($user->type, \App\Models\User::ACCOUNT_TYPES, true) ? $user->type : 'staff');
            $canLoginDefault = filter_var(old('can_login', $user->can_login ?? true), FILTER_VALIDATE_BOOLEAN);
            $oldBranchIds = array_values(array_map('strval', old('branches', $selectedBranches ?? [])));
            $defaultCompanyId = old('company_id', (string) ($user->company_id ?? (auth()->user()->isSuperAdmin() ? '' : auth()->user()->company_id)));
            $defaultPrimaryBranchId = (string) old('primary_branch_id', $primaryBranchId ?? '');
        @endphp
        <form action="{{ route('users.update', $user) }}" method="POST" class="p-6 space-y-6" x-data="{
            accountType: '{{ $accountTypeValue }}',
            canLogin: {{ $canLoginDefault ? 'true' : 'false' }},
            companyId: @js((string) $defaultCompanyId),
            selectedBranches: @js($oldBranchIds),
            primaryBranchId: @js($defaultPrimaryBranchId),
            roleRequired() {
                if (this.accountType === 'staff') return true;
                return this.canLogin && ['waiter', 'rider', 'waiter_rider'].includes(this.accountType);
            },
            branchVisible(branchCompanyId) {
                if (!this.companyId) return true;
                return String(branchCompanyId) === String(this.companyId);
            },
            onCompanyChange() {
                const keep = [];
                this.$refs.branchList?.querySelectorAll('[data-branch-id]').forEach((row) => {
                    const id = row.getAttribute('data-branch-id');
                    const company = row.getAttribute('data-company-id');
                    if (this.selectedBranches.includes(String(id)) && this.branchVisible(company)) {
                        keep.push(String(id));
                    }
                });
                this.selectedBranches = keep;
                if (this.primaryBranchId && !this.selectedBranches.includes(String(this.primaryBranchId))) {
                    this.primaryBranchId = '';
                }
            },
            toggleBranch(id, checked) {
                const key = String(id);
                if (checked) {
                    if (!this.selectedBranches.includes(key)) this.selectedBranches.push(key);
                } else {
                    this.selectedBranches = this.selectedBranches.filter((v) => v !== key);
                    if (String(this.primaryBranchId) === key) this.primaryBranchId = '';
                }
            }
        }">
            @csrf
            @method('PUT')

            <!-- Company Selection (Super Admin only) -->
            @if(auth()->user()->isSuperAdmin())
                <div>
                    <label for="company_id" class="block text-sm font-medium text-gray-700 mb-2">
                        Company
                    </label>
                    <select name="company_id"
                            id="company_id"
                            x-model="companyId"
                            @change="onCompanyChange()"
                            class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('company_id') border-red-500 @enderror">
                        <option value="">Select a company</option>
                        @foreach($companies as $company)
                            <option value="{{ $company->id }}" {{ (string) old('company_id', $user->company_id) === (string) $company->id ? 'selected' : '' }}>
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

            <!-- Branch Selection (Multiple) -->
            @if(auth()->user()->isSuperAdmin() || auth()->user()->isCompanyAdmin())
                <div>
                    <span class="block text-sm font-medium text-gray-700 mb-2">Branches</span>
                    <div x-ref="branchList" class="rounded-lg border border-gray-300 divide-y divide-gray-100 max-h-56 overflow-y-auto bg-white @error('branches') border-red-500 @enderror">
                        @forelse($branches as $branch)
                            <label
                                class="flex items-start gap-3 px-4 py-2.5 hover:bg-gray-50 cursor-pointer"
                                data-branch-id="{{ $branch->id }}"
                                data-company-id="{{ $branch->company_id }}"
                                x-show="branchVisible('{{ $branch->company_id }}')"
                                x-cloak>
                                <input type="checkbox"
                                       name="branches[]"
                                       value="{{ $branch->id }}"
                                       class="mt-0.5 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                       :checked="selectedBranches.includes('{{ $branch->id }}')"
                                       :disabled="!branchVisible('{{ $branch->company_id }}')"
                                       @change="toggleBranch({{ $branch->id }}, $event.target.checked)">
                                <span class="min-w-0">
                                    <span class="block text-sm font-medium text-gray-900">{{ $branch->name }}</span>
                                    @if(auth()->user()->isSuperAdmin())
                                        <span class="block text-xs text-gray-500">{{ $branch->company->name ?? '' }}</span>
                                    @endif
                                </span>
                            </label>
                        @empty
                            <p class="px-4 py-3 text-sm text-gray-500">No branches available.</p>
                        @endforelse
                    </div>
                    <p class="mt-1 text-xs text-gray-500">Check every branch this user should access.</p>
                    @error('branches')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    @error('branches.*')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="primary_branch_id" class="block text-sm font-medium text-gray-700 mb-2">
                        Primary Branch
                    </label>
                    <select name="primary_branch_id"
                            id="primary_branch_id"
                            x-model="primaryBranchId"
                            class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('primary_branch_id') border-red-500 @enderror">
                        <option value="">Select primary branch (optional)</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}"
                                    data-company-id="{{ $branch->company_id }}"
                                    x-show="selectedBranches.includes('{{ $branch->id }}') && branchVisible('{{ $branch->company_id }}')"
                                    x-cloak>
                                {{ $branch->name }}@if(auth()->user()->isSuperAdmin()) ({{ $branch->company->name ?? '' }})@endif
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-500">Default branch for this user. Choose from the branches checked above.</p>
                    @error('primary_branch_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            @else
                <input type="hidden" name="branches[]" value="{{ auth()->user()->branch_id }}">
                <input type="hidden" name="primary_branch_id" value="{{ auth()->user()->branch_id }}">
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Name -->
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                        Full Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" 
                           name="name" 
                           id="name" 
                           value="{{ old('name', $user->name) }}"
                           required
                           class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('name') border-red-500 @enderror"
                           placeholder="John Doe">
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
                           value="{{ old('email', $user->email) }}"
                           required
                           class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('email') border-red-500 @enderror"
                           placeholder="user@example.com">
                    @error('email')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Can sign in -->
            <div>
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox"
                           name="can_login"
                           value="1"
                           x-model="canLogin"
                           @if($user->id === auth()->id()) disabled @endif
                           class="mt-1 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 disabled:opacity-50">
                    <span>
                        <span class="block text-sm font-medium text-gray-900">Can sign in</span>
                        <span class="block text-xs text-gray-500 mt-0.5">
                            @if($user->id === auth()->id())
                                You cannot change sign-in access on your own account.
                            @else
                                When unchecked, sign-in is blocked and the password is reset to a random value.
                            @endif
                        </span>
                    </span>
                </label>
                @if($user->id === auth()->id())
                    <input type="hidden" name="can_login" value="1">
                @endif
                @error('can_login')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6" x-show="canLogin" x-cloak>
                <!-- Password -->
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                        Password <span class="text-gray-500 text-xs">(leave blank to keep current)</span>
                    </label>
                    <input type="password" 
                           name="password" 
                           id="password" 
                           class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('password') border-red-500 @enderror"
                           placeholder="••••••••">
                    @error('password')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Password Confirmation -->
                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-2">
                        Confirm Password
                    </label>
                    <input type="password" 
                           name="password_confirmation" 
                           id="password_confirmation" 
                           class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                           placeholder="••••••••">
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
                           value="{{ old('phone', $user->phone) }}"
                           class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('phone') border-red-500 @enderror"
                           placeholder="+1234567890">
                    @error('phone')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Account level (type) -->
                <div>
                    <label for="type" class="block text-sm font-medium text-gray-700 mb-2">
                        Account level <span class="text-red-500">*</span>
                    </label>
                    <select name="type"
                            id="type"
                            x-model="accountType"
                            required
                            class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('type') border-red-500 @enderror">
                        <option value="">Select account level</option>
                        @if(auth()->user()->isSuperAdmin())
                            <option value="super_admin" {{ $accountTypeValue == 'super_admin' ? 'selected' : '' }}>Super Admin</option>
                        @endif
                        @if(auth()->user()->isSuperAdmin() || auth()->user()->isCompanyAdmin())
                            <option value="company_admin" {{ $accountTypeValue == 'company_admin' ? 'selected' : '' }}>Company Admin</option>
                            <option value="branch_manager" {{ $accountTypeValue == 'branch_manager' ? 'selected' : '' }}>Branch Manager</option>
                        @endif
                        <option value="staff" {{ $accountTypeValue == 'staff' ? 'selected' : '' }}>Staff</option>
                        <option value="waiter" {{ $accountTypeValue == 'waiter' ? 'selected' : '' }}>Waiter</option>
                        <option value="rider" {{ $accountTypeValue == 'rider' ? 'selected' : '' }}>Rider</option>
                        <option value="waiter_rider" {{ $accountTypeValue == 'waiter_rider' ? 'selected' : '' }}>Waiter / Rider</option>
                    </select>
                    <p class="mt-1 text-xs text-gray-500">Access tier and floor job. Cashiers use Staff + a role. Waiter/Rider appear in POS assignment lists. Use “Can sign in” if they need app access.</p>
                    @error('type')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Salary -->
                <div>
                    <label for="salary" class="block text-sm font-medium text-gray-700 mb-2">
                        Salary
                    </label>
                    <input type="number" 
                           name="salary" 
                           id="salary" 
                           step="0.01"
                           min="0"
                           value="{{ old('salary', $user->salary) }}"
                           class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('salary') border-red-500 @enderror"
                           placeholder="0.00">
                    @error('salary')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Balance -->
                <div>
                    <label for="balance" class="block text-sm font-medium text-gray-700 mb-2">
                        Balance
                    </label>
                    <input type="number" 
                           name="balance" 
                           id="balance" 
                           step="0.01"
                           min="0"
                           value="{{ old('balance', $user->balance ?? 0) }}"
                           class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('balance') border-red-500 @enderror"
                           placeholder="0.00">
                    @error('balance')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Status -->
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-2">
                        Status <span class="text-red-500">*</span>
                    </label>
                    <select name="status" 
                            id="status" 
                            required
                            class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('status') border-red-500 @enderror">
                        <option value="active" {{ old('status', $user->status) == 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ old('status', $user->status) == 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                    @error('status')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Role (Spatie permissions) -->
                <div>
                    <label for="role" class="block text-sm font-medium text-gray-700 mb-2">
                        Role <span class="text-red-500" x-show="roleRequired()">*</span>
                    </label>
                    <select name="role"
                            id="role"
                            :required="roleRequired()"
                            class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('role') border-red-500 @enderror">
                        <option value="" x-text="roleRequired() ? 'Select role' : 'No role (optional)'"></option>
                        @foreach($roles ?? [] as $r)
                            <option value="{{ $r->name }}" {{ old('role', $user->roles->first()?->name) === $r->name ? 'selected' : '' }}>{{ $r->name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-500">Job role controls POS and menu permissions (e.g. Cashier, Order Taker). Required for Staff, and for Waiter/Rider only if they can sign in.</p>
                    @error('role')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Form Actions -->
            <div class="flex items-center justify-end space-x-3 pt-6 border-t border-gray-200">
                <a href="{{ route('users.index') }}" 
                   class="px-4 py-2 h-12 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Cancel
                </a>
                <button type="submit" 
                        class="px-4 py-2 h-12 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <i class="fas fa-save mr-2"></i>
                    Update User
                </button>
            </div>
        </form>
    </div>
</div>

@endsection

