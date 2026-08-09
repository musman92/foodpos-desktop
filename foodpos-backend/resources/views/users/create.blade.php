@extends('layouts.app')

@section('title', 'Create User')

@section('content')
@php
    $oldBranchIds = array_values(array_map('strval', old('branches', [])));
    $defaultCompanyId = old('company_id', auth()->user()->isSuperAdmin() ? '' : (string) auth()->user()->company_id);
    $employeeOptions = ($employees ?? collect())->map(function ($profile) {
        return [
            'id' => (int) $profile->id,
            'name' => $profile->user->name.($profile->employee_number ? ' — '.$profile->employee_number : ''),
            'code' => $profile->employee_number,
            'search_text' => trim($profile->user->name.' '.$profile->user->email.' '.($profile->employee_number ?? '')),
            'full_name' => $profile->user->name,
            'email' => $profile->user->email,
            'phone' => $profile->user->phone,
            'branch_id' => (string) $profile->user->branch_id,
            'type' => $profile->user->type,
        ];
    })->values();
@endphp
<div class="max-w-4xl mx-auto">
    <div class="bg-white shadow rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <h1 class="text-xl font-semibold text-gray-900">Create New User</h1>
            <p class="mt-1 text-sm text-gray-500">Grant login access. Link an existing employee, or create a login-only account.</p>
        </div>

        <form action="{{ route('users.store') }}"
              method="POST"
              class="p-6 space-y-6"
              x-data="{
                  accountType: @js((string) old('type', 'staff')),
                  canLogin: @js(filter_var(old('can_login', true), FILTER_VALIDATE_BOOLEAN)),
                  companyId: @js((string) $defaultCompanyId),
                  selectedBranches: @js($oldBranchIds),
                  primaryBranchId: @js((string) old('primary_branch_id', '')),
                  employeeProfileId: @js((string) old('employee_profile_id', '')),
                  employeesById: @js($employeeOptions->keyBy('id')),
                  init() {
                      this.$watch('employeeProfileId', (id) => this.fillFromEmployee(id));
                  },
                  roleRequired() {
                      if (this.accountType === 'staff') return true;
                      return this.canLogin && ['waiter', 'rider', 'waiter_rider'].includes(this.accountType);
                  },
                  branchVisible(branchCompanyId) {
                      if (!this.companyId) return true;
                      return String(branchCompanyId) === String(this.companyId);
                  },
                  fillFromEmployee(employeeId) {
                      if (!employeeId) return;
                      const employee = this.employeesById[String(employeeId)];
                      if (!employee) return;
                      this.canLogin = true;
                      this.accountType = employee.type || 'staff';
                      this.$refs.nameInput.value = employee.full_name || '';
                      this.$refs.emailInput.value = employee.email || '';
                      if (this.$refs.phoneInput) this.$refs.phoneInput.value = employee.phone || '';
                      if (employee.branch_id) {
                          this.selectedBranches = [String(employee.branch_id)];
                          this.primaryBranchId = String(employee.branch_id);
                      }
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
            <input type="hidden" name="employee_profile_id" :value="employeeProfileId">

            @if(($employees ?? collect())->isNotEmpty())
                <div class="min-w-0"
                     x-data="searchableSelect({
                         options: @js($employeeOptions),
                         value: employeeProfileId,
                         maxResults: 150,
                         placeholder: 'Search employees…',
                         emptyMessage: 'No employees without login found',
                         onChange: (value) => {
                             employeeProfileId = value ? String(value) : '';
                         },
                     })"
                     x-init="init(); $watch('selectedValue', (value) => { employeeProfileId = value ? String(value) : ''; })">
                    <x-searchable-select
                        label="Link existing employee (optional)"
                        compact
                        useButtonOptions
                        id="employee_search"
                    />
                    <p class="mt-1 text-xs text-gray-500">Selecting an employee fills name, email, and phone, and enables login on that person.</p>
                    @error('employee_profile_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            @endif

            @if(auth()->user()->isSuperAdmin())
                <div>
                    <label for="company_id" class="block text-sm font-medium text-gray-700 mb-2">
                        Company
                    </label>
                    <select name="company_id"
                            id="company_id"
                            x-model="companyId"
                            @change="onCompanyChange()"
                            class="block w-full filter-control @error('company_id') border-red-500 @enderror">
                        <option value="">Select a company</option>
                        @foreach($companies as $company)
                            <option value="{{ $company->id }}" {{ (string) old('company_id') === (string) $company->id ? 'selected' : '' }}>
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
                            class="block w-full filter-control @error('primary_branch_id') border-red-500 @enderror">
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
                    @error('primary_branch_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            @else
                <input type="hidden" name="branches[]" value="{{ auth()->user()->branch_id }}">
                <input type="hidden" name="primary_branch_id" value="{{ auth()->user()->branch_id }}">
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                        Full Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text"
                           name="name"
                           id="name"
                           x-ref="nameInput"
                           value="{{ old('name') }}"
                           required
                           class="block w-full filter-control @error('name') border-red-500 @enderror"
                           placeholder="John Doe">
                    @error('name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                        Email <span class="text-red-500">*</span>
                    </label>
                    <input type="email"
                           name="email"
                           id="email"
                           x-ref="emailInput"
                           value="{{ old('email') }}"
                           required
                           class="block w-full filter-control @error('email') border-red-500 @enderror"
                           placeholder="user@example.com">
                    @error('email')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">
                        Phone
                    </label>
                    <input type="text"
                           name="phone"
                           id="phone"
                           x-ref="phoneInput"
                           value="{{ old('phone') }}"
                           class="block w-full filter-control @error('phone') border-red-500 @enderror"
                           placeholder="+1234567890">
                    @error('phone')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="type" class="block text-sm font-medium text-gray-700 mb-2">
                        Account level <span class="text-red-500">*</span>
                    </label>
                    <select name="type"
                            id="type"
                            x-model="accountType"
                            required
                            class="block w-full filter-control @error('type') border-red-500 @enderror">
                        <option value="">Select account level</option>
                        @if(auth()->user()->isSuperAdmin())
                            <option value="super_admin" {{ old('type') == 'super_admin' ? 'selected' : '' }}>Super Admin</option>
                        @endif
                        @if(auth()->user()->isSuperAdmin() || auth()->user()->isCompanyAdmin())
                            <option value="company_admin" {{ old('type') == 'company_admin' ? 'selected' : '' }}>Company Admin</option>
                            <option value="branch_manager" {{ old('type') == 'branch_manager' ? 'selected' : '' }}>Branch Manager</option>
                        @endif
                        <option value="staff" {{ old('type', 'staff') == 'staff' ? 'selected' : '' }}>Staff</option>
                        <option value="waiter" {{ old('type') == 'waiter' ? 'selected' : '' }}>Waiter</option>
                        <option value="rider" {{ old('type') == 'rider' ? 'selected' : '' }}>Rider</option>
                        <option value="waiter_rider" {{ old('type') == 'waiter_rider' ? 'selected' : '' }}>Waiter / Rider</option>
                    </select>
                    <p class="mt-1 text-xs text-gray-500">Filled from the employee when linked. Floor types also appear in POS assignment lists.</p>
                    @error('type')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div>
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox"
                           name="can_login"
                           value="1"
                           x-model="canLogin"
                           class="mt-1 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <span>
                        <span class="block text-sm font-medium text-gray-900">Can sign in</span>
                        <span class="block text-xs text-gray-500 mt-0.5">Allow login with email and password. Uncheck to block sign-in (e.g. temporarily disable access).</span>
                    </span>
                </label>
                @error('can_login')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6" x-show="canLogin" x-cloak>
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                        Password <span class="text-red-500">*</span>
                    </label>
                    <input type="password"
                           name="password"
                           id="password"
                           :required="canLogin"
                           class="block w-full filter-control @error('password') border-red-500 @enderror"
                           placeholder="••••••••">
                    @error('password')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-2">
                        Confirm Password <span class="text-red-500">*</span>
                    </label>
                    <input type="password"
                           name="password_confirmation"
                           id="password_confirmation"
                           :required="canLogin"
                           class="block w-full filter-control"
                           placeholder="••••••••">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-2">
                        Status <span class="text-red-500">*</span>
                    </label>
                    <select name="status"
                            id="status"
                            required
                            class="block w-full filter-control @error('status') border-red-500 @enderror">
                        <option value="active" {{ old('status', 'active') == 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ old('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                    @error('status')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="role" class="block text-sm font-medium text-gray-700 mb-2">
                        Role <span class="text-red-500" x-show="roleRequired()">*</span>
                    </label>
                    <select name="role"
                            id="role"
                            :required="roleRequired()"
                            class="block w-full filter-control @error('role') border-red-500 @enderror">
                        <option value="" x-text="roleRequired() ? 'Select role' : 'No role (optional)'"></option>
                        @foreach($roles ?? [] as $r)
                            <option value="{{ $r->name }}" {{ old('role') === $r->name ? 'selected' : '' }}>{{ $r->name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-500">Controls app permissions (e.g. Cashier, Manager).</p>
                    @error('role')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="flex items-center justify-end space-x-3 pt-6 border-t border-gray-200">
                <a href="{{ route('users.index') }}"
                   class="inline-flex items-center h-10 px-4 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    Cancel
                </a>
                <button type="submit"
                        class="inline-flex items-center h-10 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                    <i class="fas fa-save mr-2"></i>
                    Create User
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
