@php
    use Illuminate\Support\Facades\Storage;
    $deal = $deal ?? null;
    $isEdit = $deal && $deal->exists;
    $formAction = $isEdit ? route('deals.update', $deal) : route('deals.store');
    $formMethod = $isEdit ? 'PUT' : 'POST';
    $title = $isEdit ? 'Edit Deal' : 'Create Deal';
    $subtitle = $isEdit ? 'Update combo offer' : 'Create a combo offer with multiple products at a discounted price';
    $buttonText = $isEdit ? 'Update Deal' : 'Create Deal';
    $dealItems = $isEdit ? $deal->menuItems->map(fn($m) => [
        'menu_item_id' => $m->id,
        'quantity' => $m->pivot->quantity,
        'variant_id' => $m->pivot->variant_id,
        'option_name' => $m->pivot->option_name,
        'unit_price' => $m->pivot->unit_price ? (float) $m->pivot->unit_price : null,
    ])->values()->toArray() : [];
@endphp

<div class="max-w-4xl mx-auto" x-data="dealForm({{ json_encode($dealItems) }}, '{{ route('deals.menu-item-variants', ['menuItem' => '__ID__']) }}')">
    <div class="bg-white shadow rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <h1 class="text-xl font-semibold text-gray-900">{{ $title }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ $subtitle }}</p>
        </div>

        <form action="{{ $formAction }}" method="POST" class="p-6 space-y-6" enctype="multipart/form-data">
            @csrf
            @if($isEdit) @method('PUT') @endif

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="title" class="block text-sm font-medium text-gray-700 mb-2">Title <span class="text-red-500">*</span></label>
                    <input type="text" name="title" id="title" value="{{ old('title', optional($deal)->title ?? '') }}" required
                           class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('title') border-red-500 @enderror"
                           placeholder="e.g. Lunch Combo, Happy Hour Deal">
                    @error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="price" class="block text-sm font-medium text-gray-700 mb-2">Deal Price <span class="text-red-500">*</span></label>
                    <input type="number" name="price" id="price" value="{{ old('price', optional($deal)->price ?? '') }}" step="0.01" min="0" required
                           class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('price') border-red-500 @enderror">
                    <p class="mt-1 text-xs text-gray-500">Total price for this combo. Regular prices are shown per product below.</p>
                    <p class="mt-2 text-sm font-medium text-gray-700" x-show="itemsTotal > 0">
                        Total regular amount: <span x-text="formatRowPrice(itemsTotal)" class="text-indigo-600"></span>
                    </p>
                    @error('price') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                <textarea name="description" id="description" rows="3" class="block w-full px-4 py-2 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('description') border-red-500 @enderror" placeholder="Optional description for this deal">{{ old('description', optional($deal)->description ?? '') }}</textarea>
                @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="image" class="block text-sm font-medium text-gray-700 mb-2">Image</label>
                @if($isEdit && optional($deal)->image)
                    <div class="mb-2">
                        <img src="{{ Storage::url(optional($deal)->image) }}" alt="" class="h-24 rounded-lg object-cover border border-gray-200">
                        <p class="text-xs text-gray-500 mt-1">Current image. Upload a new one to replace.</p>
                    </div>
                @endif
                <input type="file" name="image" id="image" accept="image/jpeg,image/png,image/gif,image/webp"
                       class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                @error('image') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="start_date" class="block text-sm font-medium text-gray-700 mb-2">Start Date</label>
                    <input type="date" name="start_date" id="start_date" value="{{ old('start_date', optional($deal)->start_date?->format('Y-m-d') ?? '') }}"
                           class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <p class="mt-1 text-xs text-gray-500">Leave empty for no start limit</p>
                </div>
                <div>
                    <label for="end_date" class="block text-sm font-medium text-gray-700 mb-2">End Date</label>
                    <input type="date" name="end_date" id="end_date" value="{{ old('end_date', optional($deal)->end_date?->format('Y-m-d') ?? '') }}"
                           class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="start_time" class="block text-sm font-medium text-gray-700 mb-2">Start Time (e.g. 2:00 PM)</label>
                    <input type="time" name="start_time" id="start_time" value="{{ old('start_time', optional($deal)->start_time ?? '') }}"
                           class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <p class="mt-1 text-xs text-gray-500">Leave empty for all-day</p>
                </div>
                <div>
                    <label for="end_time" class="block text-sm font-medium text-gray-700 mb-2">End Time (e.g. 6:00 PM)</label>
                    <input type="time" name="end_time" id="end_time" value="{{ old('end_time', optional($deal)->end_time ?? '') }}"
                           class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
            </div>

            <div class="flex items-center">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', optional($deal)->is_active ?? true) ? 'checked' : '' }}
                       class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                <label for="is_active" class="ml-2 block text-sm text-gray-700">Active (visible in POS)</label>
            </div>

            <div class="border-t border-gray-200 pt-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-2">Products in this deal</h2>
                <p class="text-sm text-gray-500 mb-4">Select product and variant (if any). Regular price is shown so you can set the deal price above easily.</p>
                <div class="space-y-4">
                    <template x-for="(row, index) in items" :key="index">
                        <div class="p-4 bg-gray-50 rounded-lg border border-gray-200 space-y-3">
                            <div class="flex flex-wrap items-end gap-3">
                                <div class="flex-1 min-w-[200px]">
                                    <label class="block text-xs font-medium text-gray-500 mb-1">Product</label>
                                    <select :name="'items[' + index + '][menu_item_id]'" x-model="row.menu_item_id" @change="onProductChange(index)" class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                        <option value="">— Select product —</option>
                                        @foreach($menuItems as $mi)
                                            <option value="{{ $mi->id }}">{{ $mi->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div x-show="row.productData && row.productData.variants && row.productData.variants.length > 0" class="flex-1 min-w-[200px]">
                                    <label class="block text-xs font-medium text-gray-500 mb-1">Variant <span class="text-gray-400">(e.g. Large Pizza)</span></label>
                                    <select x-model="row.variant_key" @change="onVariantChange(index)" :disabled="row.loadingVariants" class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">— Base price —</option>
                                        <template x-for="opt in getFlattenedVariantOptions(row)" :key="opt.key">
                                            <option :value="opt.key" x-text="opt.label"></option>
                                        </template>
                                    </select>
                                    <p x-show="row.loadingVariants" class="text-xs text-gray-500 mt-1">Loading variants...</p>
                                    <input type="hidden" :name="'items[' + index + '][variant_id]'" :value="row.variant_id || ''">
                                </div>
                                <div class="w-24">
                                    <label class="block text-xs font-medium text-gray-500 mb-1">Qty</label>
                                    <input type="number" :name="'items[' + index + '][quantity]'" x-model="row.quantity" min="1" class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                </div>
                                <div class="min-w-[100px]">
                                    <span class="block text-xs font-medium text-gray-500 mb-1">Regular price</span>
                                    <span class="block text-sm font-semibold text-gray-700" x-text="row.unit_price != null && row.unit_price !== '' ? formatRowPrice(row.unit_price) : '—'"></span>
                                    <input type="hidden" :name="'items[' + index + '][unit_price]'" :value="row.unit_price">
                                    <input type="hidden" :name="'items[' + index + '][option_name]'" :value="row.option_name || ''">
                                </div>
                                <button type="button" @click="removeItem(index)" class="p-2 text-red-600 hover:bg-red-50 rounded-lg" title="Remove">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
                <button type="button" @click="addItem()" class="mt-3 inline-flex items-center px-3 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    <i class="fas fa-plus mr-2"></i> Add product
                </button>
            </div>

            <div class="flex items-center justify-end gap-3 pt-6 border-t border-gray-200">
                <a href="{{ route('deals.index') }}" class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">Cancel</a>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700">
                    <i class="fas fa-save mr-2"></i>{{ $buttonText }}
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('dealForm', (initialItems, variantsUrlTemplate) => {
        const variantsUrl = (id) => (variantsUrlTemplate || '').replace('__ID__', id);
        return {
            items: Array.isArray(initialItems) && initialItems.length > 0
                ? initialItems.map(i => ({
                    menu_item_id: i.menu_item_id,
                    quantity: i.quantity || 1,
                    variant_id: i.variant_id || '',
                    option_name: i.option_name || '',
                    unit_price: i.unit_price != null ? i.unit_price : null,
                    variant_key: (i.variant_id && i.option_name) ? (i.variant_id + '__' + i.option_name) : '',
                    productData: null,
                    loadingVariants: false
                }))
                : [{ menu_item_id: '', quantity: 1, variant_id: '', option_name: '', unit_price: null, variant_key: '', productData: null, loadingVariants: false }],
            get itemsTotal() {
                return this.items.reduce((sum, row) => {
                    if (!row.menu_item_id) return sum;
                    const price = row.unit_price != null && row.unit_price !== '' ? parseFloat(row.unit_price) : 0;
                    const qty = parseInt(row.quantity, 10) || 0;
                    return sum + (price * qty);
                }, 0);
            },
            async loadProductVariants(index) {
                const row = this.items[index];
                if (!row.menu_item_id) {
                    row.productData = null;
                    row.unit_price = null;
                    row.variant_id = '';
                    row.option_name = '';
                    row.variant_key = '';
                    return;
                }
                const url = variantsUrl(row.menu_item_id);
                if (!url || url.includes('__ID__')) return;
                row.loadingVariants = true;
                row.productData = null;
                try {
                    const res = await fetch(url, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    const data = await res.json();
                    row.productData = data;
                    row.unit_price = (data.price != null && data.price !== '') ? parseFloat(data.price) : null;
                    row.variant_id = '';
                    row.option_name = '';
                    row.variant_key = '';
                    if (row.variant_key_restore) {
                        const opts = this.getFlattenedVariantOptions(row);
                        const opt = opts.find(o => o.key === row.variant_key_restore);
                        if (opt) {
                            row.variant_key = opt.key;
                            row.variant_id = opt.variant_id;
                            row.option_name = opt.option_name;
                            row.unit_price = opt.price;
                        }
                        row.variant_key_restore = null;
                    }
                } catch (e) {
                    console.warn('Failed to load variants', e);
                    row.productData = { id: row.menu_item_id, name: '', price: null, variants: [] };
                }
                row.loadingVariants = false;
            },
            getProduct(row) {
                return (row && row.productData) ? row.productData : {};
            },
            getFlattenedVariantOptions(row) {
                const p = this.getProduct(row);
                if (!p.variants || !p.variants.length) return [];
                const out = [];
                (p.variants || []).forEach(v => {
                    (v.options || []).forEach(opt => {
                        const price = parseFloat(opt.price) || 0;
                        out.push({
                            key: v.id + '__' + opt.name,
                            variant_id: v.id,
                            option_name: opt.name,
                            price: price,
                            label: v.name + ': ' + opt.name + ' — ' + this.formatRowPrice(price)
                        });
                    });
                });
                return out;
            },
            onProductChange(index) {
                const row = this.items[index];
                row.variant_id = '';
                row.option_name = '';
                row.variant_key = '';
                this.loadProductVariants(index);
            },
            onVariantChange(index) {
                const row = this.items[index];
                const key = row.variant_key;
                if (!key) {
                    const p = this.getProduct(row);
                    row.variant_id = '';
                    row.option_name = '';
                    row.unit_price = (p.price != null && p.price !== '') ? parseFloat(p.price) : null;
                    return;
                }
                const opts = this.getFlattenedVariantOptions(row);
                const opt = opts.find(o => o.key === key);
                if (opt) {
                    row.variant_id = opt.variant_id;
                    row.option_name = opt.option_name;
                    row.unit_price = opt.price;
                }
            },
            formatRowPrice(price) {
                const n = parseFloat(price);
                if (isNaN(n)) return '—';
                return n.toFixed(2);
            },
            addItem() {
                this.items.push({
                    menu_item_id: '', quantity: 1, variant_id: '', option_name: '', unit_price: null, variant_key: '',
                    productData: null, loadingVariants: false
                });
            },
            removeItem(index) {
                this.items.splice(index, 1);
                if (this.items.length === 0) this.items.push({
                    menu_item_id: '', quantity: 1, variant_id: '', option_name: '', unit_price: null, variant_key: '',
                    productData: null, loadingVariants: false
                });
            },
            init() {
                const self = this;
                this.items.forEach((row, index) => {
                    if (row.menu_item_id && (row.variant_id || row.option_name)) {
                        row.variant_key_restore = (row.variant_id || '') + '__' + (row.option_name || '');
                    }
                    if (row.menu_item_id) {
                        self.loadProductVariants(index);
                    }
                });
            }
        };
    });
});
</script>
