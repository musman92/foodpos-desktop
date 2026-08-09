<div>
    <label for="printer_title" class="block text-sm font-medium text-gray-700 mb-2">
        Printer name <span class="text-red-500">*</span>
    </label>
    <input type="text"
           name="title"
           id="printer_title"
           required
           class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
           placeholder="e.g. Kitchen printer 1">
</div>

@php $offline = offline_edition(); @endphp
<div class="grid grid-cols-1 {{ $offline ? '' : 'md:grid-cols-2' }} gap-6">
    <div>
        <label for="printer_role" class="block text-sm font-medium text-gray-700 mb-2">
            Role <span class="text-red-500">*</span>
        </label>
        <select name="role"
                id="printer_role"
                x-model="printerRole"
                class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="kitchen">Kitchen</option>
            <option value="receipt">Receipt</option>
        </select>
    </div>
    @if($offline)
        <input type="hidden" name="printing_mode" value="browser">
    @else
    <div>
        <span class="block text-sm font-medium text-gray-700 mb-2">Print option <span class="text-red-500">*</span></span>
        <div class="space-y-2">
            <label class="flex items-start gap-3 rounded-lg border border-gray-200 px-4 py-3 cursor-pointer hover:bg-gray-50"
                   :class="printingMode === 'browser' ? 'border-indigo-500 bg-indigo-50' : ''">
                <input type="radio"
                       name="printing_mode"
                       value="browser"
                       x-model="printingMode"
                       class="mt-1 h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-500">
                <span>
                    <span class="block text-sm font-medium text-gray-900">Browser popup print</span>
                    <span class="block text-xs text-gray-500 mt-0.5">Opens the browser print dialog from POS.</span>
                </span>
            </label>
            <label class="flex items-start gap-3 rounded-lg border border-gray-200 px-4 py-3 cursor-pointer hover:bg-gray-50"
                   :class="printingMode === 'desktop' ? 'border-indigo-500 bg-indigo-50' : ''">
                <input type="radio"
                       name="printing_mode"
                       value="desktop"
                       x-model="printingMode"
                       class="mt-1 h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-500">
                <span>
                    <span class="block text-sm font-medium text-gray-900">Direct print</span>
                    <span class="block text-xs text-gray-500 mt-0.5">Silent print via theFoodPOS Print APP — no popup.</span>
                </span>
            </label>
        </div>
    </div>
    @endif
</div>

@unless($offline)
<div x-show="printingMode === 'desktop'" x-cloak>
    <label for="printer_device_name" class="block text-sm font-medium text-gray-700 mb-2">
        OS printer name <span class="text-red-500">*</span>
    </label>
    <input type="text"
           name="device_name"
           id="printer_device_name"
           x-bind:required="printingMode === 'desktop'"
           class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
           placeholder="Exact name from your computer's printer list">
    <p class="mt-1 text-xs text-gray-500">Required for direct print. Must match the printer name in the desktop app.</p>
</div>
@endunless

<div class="flex flex-wrap items-center gap-6 pt-1">
    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
        <input type="checkbox" name="is_default" value="1" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
        Default for this role
    </label>
    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
        <input type="checkbox" name="is_active" value="1" checked class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
        Active
    </label>
</div>
