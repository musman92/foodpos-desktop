@php
    $addonDefinitions = $addonDefinitions ?? \App\Support\CompanyAddons::definitions();
    $companyAddons = $companyAddons ?? [];
    $inputPrefix = $inputPrefix ?? 'addons';
@endphp

<div class="border-t border-gray-200 pt-6">
    <h2 class="text-lg font-semibold text-gray-900 mb-1">Add-ons</h2>
    <p class="text-sm text-gray-500 mb-4">Optional paid features for this tenant. Disabled add-ons do not affect normal POS workflow.</p>

    <div class="space-y-4">
        @foreach($addonDefinitions as $key => $addon)
            @php
                $checked = (bool) old($inputPrefix.'.'.$key, $companyAddons[$key] ?? false);
            @endphp
            <label class="flex items-start gap-3 p-4 rounded-lg border border-gray-200 bg-gray-50/80 hover:bg-gray-50 cursor-pointer">
                <input type="hidden" name="{{ $inputPrefix }}[{{ $key }}]" value="0">
                <input type="checkbox"
                       name="{{ $inputPrefix }}[{{ $key }}]"
                       value="1"
                       @checked($checked)
                       class="mt-1 h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                <span class="min-w-0">
                    <span class="block text-sm font-semibold text-gray-900">{{ $addon['label'] }}</span>
                    <span class="block text-sm text-gray-600 mt-0.5">{{ $addon['description'] }}</span>
                </span>
            </label>
        @endforeach
    </div>
</div>
