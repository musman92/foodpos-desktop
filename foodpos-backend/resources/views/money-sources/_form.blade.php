@php
    $isEdit = isset($moneySource) && $moneySource->exists;
    $formAction = $isEdit ? route('money-sources.update', $moneySource) : route('money-sources.store');
    $formMethod = $isEdit ? 'PUT' : 'POST';
    $moneySourceData = $isEdit ? $moneySource->toArray() : [];
    $title = $isEdit ? 'Edit Money Source' : 'Create New Money Source';
    $subtitle = $isEdit ? 'Update money source information' : 'Add a new payment source (Cash, Bank, or App)';
    $buttonText = $isEdit ? 'Update Money Source' : 'Create Money Source';
@endphp

<div class="max-w-2xl mx-auto" x-data="moneySourceForm({{ json_encode($moneySourceData) }}, {{ $isEdit ? 'true' : 'false' }})">
    <div class="bg-white shadow rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <h1 class="text-xl font-semibold text-gray-900">{{ $title }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ $subtitle }}</p>
        </div>

        <form action="{{ $formAction }}" method="POST" class="p-6 space-y-6">
            @csrf
            @if($isEdit)
                @method('PUT')
            @endif

            <!-- Money Source Information -->
            <div class="space-y-6">
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                    <div class="flex items-start">
                        <i class="fas fa-info-circle text-blue-600 mt-1 mr-3"></i>
                        <div class="text-sm text-blue-800">
                            <p class="font-semibold mb-1">What is a Money Source?</p>
                            <p>A Money Source represents a <strong>physical location</strong> where money is stored or received (e.g., Cash Register, Bank Account, PayPal). This is different from an Account, which is a <strong>category</strong> for accounting purposes (e.g., Sales, Purchase, Salary).</p>
                        </div>
                    </div>
                </div>
                
                <h2 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2">Money Source Information</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Name -->
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                            Name <span class="text-red-500">*</span>
                            <i class="fas fa-question-circle text-gray-400 ml-1" 
                               title="Give this money source a descriptive name (e.g., 'Main Cash Register', 'Chase Business Account')"></i>
                        </label>
                        <input type="text" 
                               name="name" 
                               id="name" 
                               x-model="formData.name"
                               value="{{ old('name', $isEdit ? $moneySource->name : '') }}"
                               required
                               class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('name') border-red-500 @enderror"
                               placeholder="Main Cash, Bank Account, Mobile Wallet...">
                        @error('name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Type -->
                    <div>
                        <label for="type" class="block text-sm font-medium text-gray-700 mb-2">
                            Type <span class="text-red-500">*</span>
                            <i class="fas fa-question-circle text-gray-400 ml-1" 
                               title="CASH = Physical cash, BANK = Bank account/card, APP = Digital wallet (PayPal, Stripe, etc.)"></i>
                        </label>
                        <select name="type" 
                                id="type" 
                                x-model="formData.type"
                                required
                                class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('type') border-red-500 @enderror">
                            <option value="">Select Type</option>
                            <option value="CASH" {{ old('type', $isEdit ? $moneySource->type : '') == 'CASH' ? 'selected' : '' }}>Cash</option>
                            <option value="BANK" {{ old('type', $isEdit ? $moneySource->type : '') == 'BANK' ? 'selected' : '' }}>Bank</option>
                            <option value="APP" {{ old('type', $isEdit ? $moneySource->type : '') == 'APP' ? 'selected' : '' }}>App (Digital Wallet)</option>
                        </select>
                        @error('type')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Opening Balance (only shown when creating) -->
                @if(!$isEdit)
                <div>
                    <label for="opening_balance" class="block text-sm font-medium text-gray-700 mb-2">
                        Opening Balance <span class="text-red-500">*</span>
                        <i class="fas fa-question-circle text-gray-400 ml-1" 
                           title="The starting balance when you first set up this money source. Current balance will be calculated from transactions."></i>
                    </label>
                    <input type="number" 
                           name="opening_balance" 
                           id="opening_balance" 
                           step="0.01"
                           min="0"
                           value="{{ old('opening_balance', 0) }}"
                           required
                           class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('opening_balance') border-red-500 @enderror"
                           placeholder="0.00">
                    @error('opening_balance')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-gray-500">The initial balance for this money source</p>
                </div>
                @endif

                @if($isEdit && isset($branches))
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Branches
                            <i class="fas fa-question-circle text-gray-400 ml-1"
                               title="Select which branches can use this money source in POS, shifts, and transactions."></i>
                        </label>
                        @if($branches->isEmpty())
                            <p class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-4 py-3">
                                No active branches are available for your account.
                            </p>
                        @else
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 rounded-lg border border-gray-200 p-4 bg-gray-50">
                                @foreach($branches as $branch)
                                    <label class="flex items-center gap-2 text-sm text-gray-700">
                                        <input type="checkbox"
                                               name="branch_ids[]"
                                               value="{{ $branch->id }}"
                                               @checked(collect($selectedBranchIds ?? [])->contains($branch->id))
                                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <span>{{ $branch->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                        @error('branch_ids')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        @error('branch_ids.*')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                @elseif(!$isEdit)
                    <div class="rounded-lg border px-4 py-3 text-sm
                        @if(isset($activeBranch) && $activeBranch) bg-emerald-50 border-emerald-200 text-emerald-800
                        @else bg-amber-50 border-amber-200 text-amber-800 @endif">
                        @if(isset($activeBranch) && $activeBranch)
                            <i class="fas fa-store mr-1"></i>
                            This money source will be assigned to your current branch:
                            <strong>{{ $activeBranch->name }}</strong>.
                            Use edit later to assign additional branches.
                        @else
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            No active branch is selected. Choose a branch in the app header, or edit this money source after creation to assign branches.
                        @endif
                    </div>
                @endif

                <!-- Status -->
                <div>
                    <label class="flex items-center">
                        <input type="checkbox" 
                               name="active" 
                               value="1"
                               @if(old('active', $isEdit ? $moneySource->active : true)) checked @endif
                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <span class="ml-2 text-sm text-gray-600">Active (Money source will be available for shifts and transactions)</span>
                    </label>
                </div>

                <div>
                    <label class="flex items-start">
                        <input type="checkbox"
                               name="exclude_from_dashboard_profit"
                               value="1"
                               @if(old('exclude_from_dashboard_profit', $isEdit ? $moneySource->exclude_from_dashboard_profit : false)) checked @endif
                               class="mt-1 rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <span class="ml-2 text-sm text-gray-600">
                            Exclude from dashboard net profit
                            <span class="block text-xs text-gray-500 mt-0.5">All payments from this source (expenses, purchases, etc.) are ignored when calculating dashboard Net Profit. Shift balances and P&amp;L report are unchanged.</span>
                        </span>
                    </label>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="flex items-center justify-end space-x-3 pt-6 border-t border-gray-200">
                <a href="{{ route('money-sources.index') }}"
                   class="px-4 py-2 h-12 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Cancel
                </a>
                <button type="submit"
                        class="px-4 py-2 h-12 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <i class="fas fa-save mr-2"></i>
                    {{ $buttonText }}
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function moneySourceForm(moneySourceData = null, isEdit = false) {
    return {
        formData: {
            name: moneySourceData?.name || '',
            type: moneySourceData?.type || '',
            opening_balance: moneySourceData?.opening_balance || 0,
            active: moneySourceData?.active ?? true,
        },
    }
}
</script>

