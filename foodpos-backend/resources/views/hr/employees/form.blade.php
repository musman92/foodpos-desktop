@extends('layouts.app')

@php
    $editing = $employeeProfile && $employeeProfile->exists;
    $days = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
    $selectedDays = old('working_days', $employeeProfile?->workingDays() ?? \App\Models\EmployeeProfile::DEFAULT_WORKING_DAYS);
@endphp

@section('title', $editing ? 'Edit Employee' : 'Add Employee')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ $editing ? 'Edit employee' : 'Add employee' }}</h1>
        <p class="mt-1 text-sm text-gray-500">Create the employee here. Grant login later from Users if they need system access.</p>
    </div>

    @if($errors->any())
        <div class="rounded-lg bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <form method="POST" enctype="multipart/form-data" action="{{ $editing ? route('hr.employees.update', $employeeProfile) : route('hr.employees.store') }}" class="bg-white shadow rounded-lg p-6 space-y-6">
        @csrf
        @if($editing) @method('PUT') @endif

        <div>
            <h2 class="font-semibold text-gray-900 mb-4">Employee details</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Full name *</label>
                    <input name="name" required value="{{ old('name', $employeeProfile?->user?->name) }}" class="w-full h-11 px-3 rounded-lg border-gray-300" placeholder="Employee name">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                    <input type="email" name="email" required value="{{ old('email', $employeeProfile?->user?->email) }}" class="w-full h-11 px-3 rounded-lg border-gray-300" placeholder="Used later if login is enabled">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                    <input name="phone" value="{{ old('phone', $employeeProfile?->user?->phone) }}" class="w-full h-11 px-3 rounded-lg border-gray-300">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Primary branch *</label>
                    <select name="branch_id" required class="w-full h-11 px-3 rounded-lg border-gray-300">
                        <option value="">Select branch</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((int) old('branch_id', $employeeProfile?->user?->branch_id ?? current_branch_id()) === (int) $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Employee number</label>
                    <input name="employee_number" value="{{ old('employee_number', $employeeProfile?->employee_number) }}" class="w-full h-11 px-3 rounded-lg border-gray-300" placeholder="Auto-generated if blank">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Designation</label>
                    <input name="designation" value="{{ old('designation', $employeeProfile?->designation) }}" class="w-full h-11 px-3 rounded-lg border-gray-300" placeholder="Chef, Cashier, Waiter…">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">POS work type *</label>
                    <select name="operational_type" required class="w-full h-11 px-3 rounded-lg border-gray-300">
                        <option value="staff" @selected(old('operational_type', $employeeProfile?->user?->type ?? 'staff') === 'staff')>Staff</option>
                        <option value="waiter" @selected(old('operational_type', $employeeProfile?->user?->type) === 'waiter')>Waiter</option>
                        <option value="rider" @selected(old('operational_type', $employeeProfile?->user?->type) === 'rider')>Rider</option>
                        <option value="waiter_rider" @selected(old('operational_type', $employeeProfile?->user?->type) === 'waiter_rider')>Waiter / Rider</option>
                    </select>
                    <p class="mt-1 text-xs text-gray-500">Controls whether this employee appears in POS waiter and rider lists. Login is still optional.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                    <input name="department" value="{{ old('department', $employeeProfile?->department) }}" class="w-full h-11 px-3 rounded-lg border-gray-300" placeholder="Kitchen, Service, Accounts…">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Hire date</label>
                    <input type="date" name="hire_date" value="{{ old('hire_date', $employeeProfile?->hire_date?->format('Y-m-d')) }}" class="w-full h-11 px-3 rounded-lg border-gray-300">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Employment status *</label>
                    <select name="employment_status" required class="w-full h-11 px-3 rounded-lg border-gray-300">
                        @foreach(\App\Models\EmployeeProfile::EMPLOYMENT_STATUSES as $status)
                            <option value="{{ $status }}" @selected(old('employment_status', $employeeProfile?->employment_status ?? 'active') === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            @if($editing && $employeeProfile->user)
                <p class="mt-3 text-sm text-gray-500">
                    Login access:
                    @if($employeeProfile->user->can_login)
                        <span class="text-green-700 font-medium">Enabled</span>
                        — manage from <a href="{{ route('users.edit', $employeeProfile->user) }}" class="text-indigo-700">Users</a>
                    @else
                        <span class="text-amber-700 font-medium">Not enabled</span>
                        — create/enable from Users and select this employee
                    @endif
                </p>
            @endif
        </div>

        <div class="border-t pt-6">
            <h2 class="font-semibold text-gray-900 mb-4">Pay and working hours</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Pay cycle *</label>
                    <select name="pay_frequency" required class="w-full h-11 px-3 rounded-lg border-gray-300">
                        @foreach(\App\Models\EmployeeProfile::PAY_FREQUENCIES as $frequency)
                            <option value="{{ $frequency }}" @selected(old('pay_frequency', $employeeProfile?->pay_frequency ?? 'monthly') === $frequency)>{{ $frequency === 'fortnight' ? 'Fortnight' : ucfirst($frequency) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Pay rate for cycle *</label>
                    <input type="number" step="0.01" min="0" name="pay_rate" required value="{{ old('pay_rate', $employeeProfile?->pay_rate ?? 0) }}" class="w-full h-11 px-3 rounded-lg border-gray-300">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Standard hours/day *</label>
                    <input type="number" step="0.25" min="0.25" max="24" name="standard_hours_per_day" required value="{{ old('standard_hours_per_day', $employeeProfile?->standard_hours_per_day ?? 8) }}" class="w-full h-11 px-3 rounded-lg border-gray-300">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Overtime rate/hour *</label>
                    <input type="number" step="0.01" min="0" name="overtime_rate" required value="{{ old('overtime_rate', $employeeProfile?->overtime_rate ?? 0) }}" class="w-full h-11 px-3 rounded-lg border-gray-300">
                    <p class="mt-1 text-xs text-gray-500">Use 0 to track overtime hours without paying overtime.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Short-hours policy *</label>
                    <select name="short_hours_policy" required class="w-full h-11 px-3 rounded-lg border-gray-300">
                        <option value="full_day" @selected(old('short_hours_policy', $employeeProfile?->short_hours_policy ?? 'full_day') === 'full_day')>Pay full day when present</option>
                        <option value="pro_rata" @selected(old('short_hours_policy', $employeeProfile?->short_hours_policy) === 'pro_rata')>Pay proportionally by hours</option>
                    </select>
                </div>
                @unless($editing)
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Opening balance</label>
                    <input type="number" step="0.01" name="opening_balance" value="{{ old('opening_balance', 0) }}" class="w-full h-11 px-3 rounded-lg border-gray-300" placeholder="0.00">
                    <p class="mt-1 text-xs text-gray-500">{{ \App\Support\PartyBalance::employeeOpeningHint() }}</p>
                    @error('opening_balance')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                @endunless
            </div>
            <div class="mt-5">
                <label class="block text-sm font-medium text-gray-700 mb-2">Scheduled working days *</label>
                <div class="flex flex-wrap gap-3">
                    @foreach($days as $number => $label)
                        <label class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-200">
                            <input type="checkbox" name="working_days[]" value="{{ $number }}" @checked(in_array($number, array_map('intval', $selectedDays))) class="rounded border-gray-300 text-indigo-600">
                            <span class="text-sm">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="border-t pt-6 grid grid-cols-1 md:grid-cols-2 gap-5">
            <div><label class="block text-sm font-medium mb-1">National ID</label><input name="national_id" value="{{ old('national_id', $employeeProfile?->national_id) }}" class="w-full h-11 px-3 rounded-lg border-gray-300"></div>
            <div><label class="block text-sm font-medium mb-1">End date</label><input type="date" name="end_date" value="{{ old('end_date', $employeeProfile?->end_date?->format('Y-m-d')) }}" class="w-full h-11 px-3 rounded-lg border-gray-300"></div>
            <div>
                <label class="block text-sm font-medium mb-1">CNIC attachment</label>
                <input type="file" name="cnic_attachment" accept=".jpg,.jpeg,.png,.webp,.pdf" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                <p class="mt-1 text-xs text-gray-500">Photo or PDF, maximum 5 MB.</p>
                @if($employeeProfile?->cnic_attachment_path)
                    <a href="{{ route('hr.employees.documents.download', [$employeeProfile, 'cnic']) }}" class="mt-1 inline-flex text-sm text-indigo-700">
                        <i class="fas fa-paperclip mr-1 mt-0.5"></i>{{ $employeeProfile->cnic_attachment_name ?: 'Download current CNIC' }}
                    </a>
                @endif
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Other attachment</label>
                <input type="file" name="other_attachment" accept=".jpg,.jpeg,.png,.webp,.pdf" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                <p class="mt-1 text-xs text-gray-500">Any supporting photo or PDF, maximum 5 MB.</p>
                @if($employeeProfile?->other_attachment_path)
                    <a href="{{ route('hr.employees.documents.download', [$employeeProfile, 'other']) }}" class="mt-1 inline-flex text-sm text-indigo-700">
                        <i class="fas fa-paperclip mr-1 mt-0.5"></i>{{ $employeeProfile->other_attachment_name ?: 'Download current attachment' }}
                    </a>
                @endif
            </div>
            <div><label class="block text-sm font-medium mb-1">Emergency contact</label><input name="emergency_contact_name" value="{{ old('emergency_contact_name', $employeeProfile?->emergency_contact_name) }}" class="w-full h-11 px-3 rounded-lg border-gray-300"></div>
            <div><label class="block text-sm font-medium mb-1">Emergency phone</label><input name="emergency_contact_phone" value="{{ old('emergency_contact_phone', $employeeProfile?->emergency_contact_phone) }}" class="w-full h-11 px-3 rounded-lg border-gray-300"></div>
            <div><label class="block text-sm font-medium mb-1">Bank name</label><input name="bank_name" value="{{ old('bank_name', $employeeProfile?->bank_name) }}" class="w-full h-11 px-3 rounded-lg border-gray-300"></div>
            <div><label class="block text-sm font-medium mb-1">Bank account</label><input name="bank_account" value="{{ old('bank_account', $employeeProfile?->bank_account) }}" class="w-full h-11 px-3 rounded-lg border-gray-300"></div>
            <div class="md:col-span-2"><label class="block text-sm font-medium mb-1">Address</label><textarea name="address" rows="2" class="w-full px-3 py-2 rounded-lg border-gray-300">{{ old('address', $employeeProfile?->address) }}</textarea></div>
            <div class="md:col-span-2"><label class="block text-sm font-medium mb-1">Notes</label><textarea name="notes" rows="2" class="w-full px-3 py-2 rounded-lg border-gray-300">{{ old('notes', $employeeProfile?->notes) }}</textarea></div>
        </div>

        <div class="flex justify-end gap-3 border-t pt-5">
            <a href="{{ route('hr.employees.index') }}" class="h-11 px-4 border rounded-lg flex items-center">Cancel</a>
            <button class="h-11 px-5 rounded-lg bg-indigo-600 text-white font-medium">{{ $editing ? 'Update employee' : 'Create employee' }}</button>
        </div>
    </form>
</div>
@endsection
