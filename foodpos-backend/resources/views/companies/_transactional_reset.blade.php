@php
    use App\Support\TenantTransactionalResetOptions;

    $primaryOptions = collect($transactionalResetOptions)->filter(fn ($opt) => $opt['primary']);
    $dependentOptions = collect($transactionalResetOptions)->reject(fn ($opt) => $opt['primary']);
@endphp

<div class="bg-white shadow rounded-lg overflow-hidden border border-amber-200">
    <div class="px-6 py-4 border-b border-amber-100 bg-amber-50/60">
        <h2 class="text-lg font-semibold text-gray-900">Reset transactional data</h2>
        <p class="mt-1 text-sm text-amber-900">
            Clear test history for this tenant while keeping menu, categories, ingredients, recipes, branches, users, and suppliers.
        </p>
    </div>

    <div class="p-6 space-y-6">
        <div class="rounded-lg border border-amber-100 bg-amber-50/40 px-4 py-3 text-sm text-amber-900">
            <p class="font-medium">Catalog data is never deleted here</p>
            <p class="mt-1">Menu items, ingredients, recipes, categories, units, deals, supplier records, and money source accounts stay intact unless you choose to reset money source balances below.</p>
        </div>

        <form id="transactional-reset-form"
              action="{{ route('companies.reset-transactional-data', $company) }}"
              method="POST"
              class="space-y-6">
            @csrf

            <div>
                <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wide">Primary data</h3>
                <div class="mt-3 space-y-3">
                    @foreach($primaryOptions as $key => $option)
                        <label class="flex items-start gap-3 rounded-lg border border-gray-200 p-4 hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox"
                                   name="options[]"
                                   value="{{ $key }}"
                                   class="transactional-reset-option mt-1 h-4 w-4 rounded border-gray-300 text-amber-600 focus:ring-amber-500"
                                   data-reset-key="{{ $key }}"
                                   @checked(in_array($key, old('options', []), true))>
                            <span>
                                <span class="block text-sm font-medium text-gray-900">{{ $option['label'] }}</span>
                                <span class="block text-sm text-gray-500 mt-0.5">{{ $option['description'] }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div>
                <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wide">Related data</h3>
                <p class="mt-1 text-sm text-gray-500">Auto-selected when needed for consistency. You can adjust these before confirming.</p>
                <div class="mt-3 space-y-3">
                    @foreach($dependentOptions as $key => $option)
                        <label class="flex items-start gap-3 rounded-lg border border-dashed border-gray-200 p-4 hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox"
                                   name="options[]"
                                   value="{{ $key }}"
                                   class="transactional-reset-option mt-1 h-4 w-4 rounded border-gray-300 text-amber-600 focus:ring-amber-500"
                                   data-reset-key="{{ $key }}"
                                   @checked(in_array($key, old('options', []), true))>
                            <span>
                                <span class="block text-sm font-medium text-gray-900">{{ $option['label'] }}</span>
                                <span class="block text-sm text-gray-500 mt-0.5">{{ $option['description'] }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>

            @error('options')
                <p class="text-sm text-red-600">{{ $message }}</p>
            @enderror
            @error('options.*')
                <p class="text-sm text-red-600">{{ $message }}</p>
            @enderror

            <div class="flex items-center justify-end">
                <button type="button"
                        id="transactional-reset-open-modal"
                        class="inline-flex items-center px-4 py-2 bg-amber-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 transition">
                    <i class="fas fa-broom mr-2"></i>
                    Reset selected data
                </button>
            </div>
        </form>
    </div>
</div>

@push('modals')
<div id="transactional-reset-modal"
     class="fixed inset-0 z-[100] hidden items-center justify-center bg-black/50 p-4"
     role="dialog"
     aria-modal="true"
     aria-labelledby="transactional-reset-modal-title">
    <div class="w-full max-w-lg rounded-lg bg-white shadow-xl">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 id="transactional-reset-modal-title" class="text-lg font-semibold text-gray-900">Reset transactional data</h3>
            <p class="mt-1 text-sm text-gray-500">This permanently deletes the selected data for {{ $company->name }}.</p>
        </div>
        <div class="p-6 space-y-4">
            <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-900">
                <p class="font-semibold">Warning</p>
                <p class="mt-1">Selected transactional data cannot be recovered. Menu, ingredients, and recipes are not affected.</p>
                <ul id="transactional-reset-summary" class="mt-3 list-disc list-inside space-y-1 text-red-800"></ul>
            </div>
            <div>
                <label for="confirm_reset" class="block text-sm font-medium text-gray-700 mb-2">
                    Type <span class="font-mono font-semibold">RESET</span> to confirm
                </label>
                <input type="text"
                       name="confirm_reset"
                       id="confirm_reset"
                       form="transactional-reset-form"
                       required
                       autocomplete="off"
                       class="block w-full h-11 px-4 rounded-lg border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500">
                @error('confirm_reset')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div class="flex items-center justify-end gap-3 pt-2">
                <button type="button"
                        data-transactional-reset-cancel
                        class="px-4 py-2 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-100">
                    Cancel
                </button>
                <button type="submit"
                        form="transactional-reset-form"
                        class="px-4 py-2 rounded-lg text-sm font-semibold bg-red-600 text-white hover:bg-red-700">
                    Reset transactional data
                </button>
            </div>
        </div>
    </div>
</div>
@endpush

@push('scripts')
<script>
(function () {
    const dependencies = @json($transactionalResetDependencies);
    const requiredBy = @json($transactionalResetRequiredBy);
    const labels = @json(collect($transactionalResetOptions)->mapWithKeys(fn ($opt, $key) => [$key => $opt['label']]));

    const modal = document.getElementById('transactional-reset-modal');
    const form = document.getElementById('transactional-reset-form');
    const openButton = document.getElementById('transactional-reset-open-modal');
    const summaryEl = document.getElementById('transactional-reset-summary');
    const confirmInput = document.getElementById('confirm_reset');
    const checkboxes = Array.from(document.querySelectorAll('.transactional-reset-option'));

    function selectedKeys() {
        return checkboxes.filter((box) => box.checked).map((box) => box.getAttribute('data-reset-key'));
    }

    function setChecked(key, checked) {
        const box = checkboxes.find((item) => item.getAttribute('data-reset-key') === key);
        if (box) {
            box.checked = checked;
        }
    }

    function expandSelection(keys) {
        const selected = new Set(keys);
        let changed = true;

        while (changed) {
            changed = false;
            selected.forEach(function (key) {
                (dependencies[key] || []).forEach(function (dependency) {
                    if (!selected.has(dependency)) {
                        selected.add(dependency);
                        changed = true;
                    }
                });
            });
        }

        return Array.from(selected);
    }

    function pruneDependents(removedKey, selected) {
        selected.delete(removedKey);
        (dependencies[removedKey] || []).forEach(function (dependency) {
            const parents = requiredBy[dependency] || [];
            const stillNeeded = parents.some(function (parent) {
                return selected.has(parent);
            });

            if (!stillNeeded && selected.has(dependency)) {
                pruneDependents(dependency, selected);
            }
        });
    }

    function syncFromCheckbox(changedKey, isChecked) {
        let selected = new Set(selectedKeys());

        if (isChecked) {
            selected = new Set(expandSelection(Array.from(selected)));
        } else {
            pruneDependents(changedKey, selected);
        }

        checkboxes.forEach(function (box) {
            const key = box.getAttribute('data-reset-key');
            box.checked = selected.has(key);
        });
    }

    checkboxes.forEach(function (box) {
        box.addEventListener('change', function () {
            syncFromCheckbox(box.getAttribute('data-reset-key'), box.checked);
        });
    });

    function openModal() {
        const keys = expandSelection(selectedKeys());
        if (keys.length === 0) {
            window.alert('Select at least one reset option.');
            return;
        }

        checkboxes.forEach(function (box) {
            const key = box.getAttribute('data-reset-key');
            box.checked = keys.includes(key);
        });

        summaryEl.innerHTML = '';
        keys.forEach(function (key) {
            const item = document.createElement('li');
            item.textContent = labels[key] || key;
            summaryEl.appendChild(item);
        });

        if (confirmInput) {
            confirmInput.value = '';
        }

        modal.classList.remove('hidden');
        modal.classList.add('flex');
        confirmInput?.focus();
    }

    function closeModal() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    openButton?.addEventListener('click', openModal);

    document.querySelectorAll('[data-transactional-reset-cancel]').forEach(function (button) {
        button.addEventListener('click', closeModal);
    });

    modal?.addEventListener('click', function (event) {
        if (event.target === modal) {
            closeModal();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
            closeModal();
        }
    });

    @if($errors->has('confirm_reset') || $errors->has('options') || $errors->has('options.*'))
        openModal();
    @endif
})();
</script>
@endpush
