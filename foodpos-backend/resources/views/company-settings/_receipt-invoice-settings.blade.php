@php
    use App\Support\ReceiptSections;
    use App\Services\CompanyConfigService;

    $receiptSectionLabels = ReceiptSections::labels();
    $receiptSectionGroups = ReceiptSections::groups();
    $receiptSections = ReceiptSections::normalize(
        old('receipt_sections', $company->settings['receipt_sections'] ?? null)
    );
    $receiptSampleOrder = ReceiptSections::sampleOrder();

    $receiptLayoutVariants = [];
    foreach ([10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20] as $size) {
        foreach ([58, 80] as $paper) {
            $receiptLayoutVariants["{$size}_{$paper}"] = CompanyConfigService::receiptLayoutSettings([
                'receipt_font_size' => $size,
                'receipt_paper_width_mm' => $paper,
                'receipt_sections' => ReceiptSections::defaults(),
            ]);
        }
    }
@endphp

<div id="receipt-printing"
     x-data="{
         sections: @js($receiptSections),
         sample: @js($receiptSampleOrder),
         layoutVariants: @js($receiptLayoutVariants),
         fontSize: {{ (int) $receiptFontSize }},
         paperWidth: {{ (int) $receiptPaperWidth }},
         currentLayout() {
             const key = this.fontSize + '_' + this.paperWidth;
             return this.layoutVariants[key] || this.layoutVariants['14_80'] || {};
         },
         previewRootStyle() {
             const r = this.currentLayout();
             return {
                 fontFamily: 'Arial, Helvetica, sans-serif',
                 fontSize: (r.font_size_px || 14) + 'px',
                 fontWeight: 700,
                 lineHeight: 1.35,
                 width: (r.paper_width_mm || 80) + 'mm',
                 maxWidth: '100%',
                 margin: '0 auto',
                 padding: '1.5mm ' + (r.pad_right_mm || 2) + 'mm 1.5mm ' + (r.pad_left_mm || 1) + 'mm',
                 color: '#000',
                 boxSizing: 'border-box',
             };
         },
         sectionEnabled(key) {
             return !!this.sections[key];
         },
         previewBranchName() {
             const branch = this.sample.branch?.name;
             const company = this.sample.company?.name;
             if (!branch || !company || branch.toLowerCase() === company.toLowerCase()) {
                 return null;
             }
             return branch;
         },
         previewAddress() {
             return this.sample.branch?.address || this.sample.company?.address || null;
         },
         previewPhone() {
             return this.sample.branch?.phone || this.sample.company?.phone || null;
         },
         previewShowOrderDetails() {
             return this.sectionEnabled('order_type') || (this.sectionEnabled('table') && this.sample.table);
         },
         previewShowSubtotal() {
             return false;
         },
     }">

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-8">
        <div class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="receipt_font_size" class="block text-sm font-medium text-gray-700 mb-2">Receipt font size</label>
                    <select name="receipt_font_size"
                            id="receipt_font_size"
                            x-model.number="fontSize"
                            class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 bg-white @error('receipt_font_size') border-red-500 @enderror">
                        @foreach([10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20] as $size)
                            <option value="{{ $size }}" {{ (int) $receiptFontSize === $size ? 'selected' : '' }}>
                                {{ $size }}px{{ $size === 14 ? ' (default)' : '' }}
                            </option>
                        @endforeach
                    </select>
                    @error('receipt_font_size')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="receipt_paper_width_mm" class="block text-sm font-medium text-gray-700 mb-2">Receipt paper width</label>
                    <select name="receipt_paper_width_mm"
                            id="receipt_paper_width_mm"
                            x-model.number="paperWidth"
                            class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 bg-white @error('receipt_paper_width_mm') border-red-500 @enderror">
                        <option value="80" {{ (int) $receiptPaperWidth === 80 ? 'selected' : '' }}>80mm (default)</option>
                        <option value="58" {{ (int) $receiptPaperWidth === 58 ? 'selected' : '' }}>58mm</option>
                    </select>
                    @error('receipt_paper_width_mm')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <h4 class="text-sm font-semibold text-gray-800 mb-1">Invoice content</h4>
                <p class="text-xs text-gray-500 mb-4">Uncheck lines you want to remove from the receipt (e.g. thank-you note, address, footer branding).</p>

                <div class="space-y-4 max-h-[32rem] overflow-y-auto pr-1">
                    @foreach($receiptSectionGroups as $group)
                        <div class="rounded-lg border border-gray-200 p-3 bg-gray-50">
                            <p class="text-sm font-medium text-gray-900 mb-1">{{ $group['label'] }}</p>
                            @if(! empty($group['description']))
                                <p class="text-xs text-gray-500 mb-2">{{ $group['description'] }}</p>
                            @endif
                            <div class="space-y-2">
                                @foreach($group['keys'] as $sectionKey)
                                    <label class="flex items-start gap-3 cursor-pointer">
                                        <input type="hidden"
                                               name="receipt_sections[{{ $sectionKey }}]"
                                               :value="sections['{{ $sectionKey }}'] ? '1' : '0'">
                                        <input type="checkbox"
                                               value="1"
                                               x-model="sections['{{ $sectionKey }}']"
                                               class="mt-0.5 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                        <span class="text-sm text-gray-700">{{ $receiptSectionLabels[$sectionKey] ?? $sectionKey }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="xl:sticky xl:top-4 xl:self-start">
            <h4 class="text-sm font-semibold text-gray-800 mb-3">Live preview</h4>
            <div class="bg-gray-100 rounded-lg p-4 border border-gray-200 overflow-x-auto">
                <div :style="previewRootStyle()" class="bg-white shadow-md mx-auto">
                    <div style="text-align: center; margin-bottom: 8px; border-bottom: 1px dashed #000; padding-bottom: 8px;">
                        <div x-show="sectionEnabled('logo') && sample.company?.logo_url" style="margin-bottom: 6px;">
                            <img :src="sample.company.logo_url" alt="" style="max-height: 48px; max-width: 140px; object-fit: contain;">
                        </div>
                        <div style="font-size: 1.15em; font-weight: bold; text-transform: uppercase;" x-text="sample.company?.name || 'COMPANY NAME'"></div>
                        <div x-show="sectionEnabled('branch_name') && previewBranchName()"
                             style="font-size: 0.9em; margin-top: 2px;"
                             x-text="previewBranchName()"></div>
                        <div x-show="sectionEnabled('address') && previewAddress()"
                             style="font-size: 0.9em; margin-top: 2px;"
                             x-text="previewAddress()"></div>
                        <div x-show="sectionEnabled('phone') && previewPhone()"
                             style="font-size: 0.9em; margin-top: 2px;"
                             x-text="'Tel: ' + previewPhone()"></div>
                    </div>

                    <div x-show="sectionEnabled('invoice_title')"
                         style="font-size: 1.05em; font-weight: bold; text-align: center; margin: 0.5em 0;">INVOICE</div>

                    <div x-show="sectionEnabled('order_number') || sectionEnabled('date_cashier')"
                         style="text-align: center; font-size: 0.9em; margin-bottom: 8px; border-bottom: 1px dashed #000; padding-bottom: 8px;">
                        <p x-show="sectionEnabled('order_number')" style="margin: 2px 0;">
                            <strong>Order #:</strong> <span x-text="sample.order_number"></span>
                        </p>
                        <p x-show="sectionEnabled('date_cashier')" style="margin: 2px 0;">
                            <strong>Date:</strong> 12/07/2026 11:42
                            <span> · <strong>Cashier:</strong> <span x-text="sample.cashier?.name"></span></span>
                        </p>
                    </div>

                    <div x-show="previewShowOrderDetails()" style="margin: 0.5em 0; font-size: 0.9em;">
                        <div style="font-weight: bold; text-transform: uppercase; border-bottom: 1px solid #000; padding-bottom: 2px;">Order Details</div>
                        <p x-show="sectionEnabled('order_type')" style="margin: 2px 0;">Type: Takeaway</p>
                    </div>

                    <div style="border-top: 1px dashed #000; margin: 6px 0;"></div>

                    <table style="width: 100%; table-layout: fixed; border-collapse: collapse; font-size: 0.9em;">
                        <thead x-show="sectionEnabled('items_header')">
                            <tr>
                                <th style="text-align: left; padding: 3px 0; border-bottom: 1px dashed #000;">Item</th>
                                <th style="text-align: center; padding: 3px 0; border-bottom: 1px dashed #000; width: 14%;">Qty</th>
                                <th style="text-align: right; padding: 3px 0; border-bottom: 1px dashed #000; width: 32%;">Price</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style="padding: 3px 0; border-bottom: 1px dotted #ccc;">
                                    <div style="font-weight: bold;">Chicken Fajita Pizza</div>
                                    <div x-show="sectionEnabled('item_variants')" style="font-size: 0.88em;">Size: Small 6"</div>
                                </td>
                                <td style="text-align: center; padding: 3px 0; border-bottom: 1px dotted #ccc;">1</td>
                                <td style="text-align: right; padding: 3px 0; border-bottom: 1px dotted #ccc; white-space: nowrap;">Rs600.00</td>
                            </tr>
                            <tr>
                                <td style="padding: 3px 0; border-bottom: 1px dotted #ccc;">
                                    <div style="font-weight: bold;">Lunch Combo</div>
                                    <template x-if="sectionEnabled('deal_items')">
                                        <div style="font-size: 0.88em; color: #000;">
                                            <div>· Burger x1</div>
                                            <div>· Fries x1</div>
                                            <div>· Cola x1</div>
                                        </div>
                                    </template>
                                </td>
                                <td style="text-align: center; padding: 3px 0; border-bottom: 1px dotted #ccc;">1</td>
                                <td style="text-align: right; padding: 3px 0; border-bottom: 1px dotted #ccc; white-space: nowrap;">Rs500.00</td>
                            </tr>
                        </tbody>
                    </table>

                    <div style="margin-top: 6px; border-top: 1px dashed #000; padding-top: 4px; font-size: 0.9em;">
                        <div x-show="previewShowSubtotal()" style="display: flex; justify-content: space-between; margin: 2px 0;">
                            <span>Subtotal:</span><span>Rs600.00</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; border-top: 1px solid #000; border-bottom: 1px solid #000; padding: 0.35em 0; margin: 0.35em 0; font-weight: bold; font-size: 1.1em;">
                            <span>TOTAL:</span><span>Rs1,100.00</span>
                        </div>
                    </div>

                    <div x-show="sectionEnabled('payment_info')" style="margin-top: 6px; font-size: 0.9em; text-align: center;">
                        <p style="margin: 2px 0;"><strong>Payment:</strong> Cash · Paid</p>
                    </div>

                    <div style="text-align: center; margin-top: 0.75em; padding-top: 0.5em; border-top: 1px dashed #000; font-size: 0.88em;">
                        <p x-show="sectionEnabled('thank_you')">Thank you for your business!</p>
                        <div style="margin-top: 6px; padding-top: 6px; border-top: 1px dashed #ccc;">
                            <p style="font-size: 0.85em;">Powered by {{ config('app.name') }} thefoodpos.com</p>
                        </div>
                    </div>
                </div>
            </div>
            <p class="mt-2 text-xs text-gray-500">Sample receipt — amounts use your currency when printing.</p>
        </div>
    </div>
</div>
