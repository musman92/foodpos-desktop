<template x-if="!loadingInvoice && invoiceData">
    <div :style="receiptPreviewRootStyle()">
        <div style="text-align: center; margin-bottom: 8px; border-bottom: 1px dashed #000; padding-bottom: 8px;">
            <template x-if="receiptSection('logo') && invoiceData.company?.logo_url">
                <div style="margin-bottom: 6px;">
                    <img :src="invoiceData.company.logo_url" alt="" width="160" height="56" style="max-height: 56px; max-width: 160px; width: auto; height: auto; object-fit: contain; display: inline-block; vertical-align: middle;">
                </div>
            </template>
            <div style="font-size: 1.15em; font-weight: bold; margin-bottom: 0.2em; text-transform: uppercase;" x-text="invoiceData.company?.name || 'COMPANY NAME'"></div>
            <template x-if="receiptSection('branch_name') && invoiceBranchName()">
                <div style="font-size: 0.9em; margin: 2px 0;" x-text="invoiceBranchName()"></div>
            </template>
            <template x-if="receiptSection('address') && invoiceContactAddress()">
                <div style="font-size: 0.9em; margin: 2px 0;" x-text="invoiceContactAddress()"></div>
            </template>
            <template x-if="receiptSection('phone') && invoiceContactPhone()">
                <div style="font-size: 0.9em; margin: 2px 0;" x-text="'Tel: ' + invoiceContactPhone()"></div>
            </template>
        </div>

        <template x-if="receiptSection('invoice_title')">
            <div style="font-size: 1.05em; font-weight: bold; margin: 0.5em 0 0.35em 0; text-align: center;">INVOICE</div>
        </template>
        <template x-if="invoiceData.status === 'open' && invoiceData.payment_status === 'unpaid'">
            <div style="font-size: 0.85em; text-align: center; margin-bottom: 6px; letter-spacing: 0.06em; text-transform: uppercase; font-weight: bold;">Draft</div>
        </template>

        <template x-if="receiptSection('order_number') || receiptSection('date_cashier')">
            <div style="text-align: center; font-size: 0.9em; margin-bottom: 8px; border-bottom: 1px dashed #000; padding-bottom: 8px;">
                <template x-if="receiptSection('order_number')">
                    <p style="margin: 2px 0;"><strong>Order #:</strong> <span x-text="invoiceData.order_number"></span></p>
                </template>
                <template x-if="receiptSection('date_cashier')">
                    <p style="margin: 2px 0;">
                        <strong>Date:</strong>
                        <span x-text="new Date(invoiceData.created_at).toLocaleString('en-GB', {day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'})"></span>
                        <template x-if="invoiceData.cashier">
                            <span> · <strong>Cashier:</strong> <span x-text="invoiceData.cashier.name"></span></span>
                        </template>
                    </p>
                </template>
            </div>
        </template>

        <template x-if="receiptShowOrderDetails()">
            <div style="margin: 0.5em 0; font-size: 0.9em;">
                <div style="font-weight: bold; margin-bottom: 2px; text-transform: uppercase; border-bottom: 1px solid #000; padding-bottom: 2px;">Order Details</div>
                <div style="margin: 2px 0;">
                    <template x-if="receiptSection('order_type') && invoiceData.type">
                        <p style="margin: 2px 0;" x-text="'Type: ' + (invoiceData.type ? invoiceData.type.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()) : '')"></p>
                    </template>
                    <template x-if="receiptSection('table') && invoiceData.table">
                        <p style="margin: 2px 0;" x-text="'Table: ' + invoiceData.table.name"></p>
                    </template>
                </div>
            </div>
        </template>

        <template x-if="receiptSection('customer_block') && (invoiceData.customer_name || invoiceData.customer_phone || invoiceData.customer_email || invoiceData.customer_address)">
            <div style="margin: 0.5em 0; font-size: 0.9em;">
                <div style="font-weight: bold; margin-bottom: 2px; text-transform: uppercase; border-bottom: 1px solid #000; padding-bottom: 2px;">Customer</div>
                <div style="margin: 2px 0;">
                    <template x-if="invoiceData.customer_name">
                        <p style="margin: 2px 0;" x-text="'Name: ' + invoiceData.customer_name"></p>
                    </template>
                    <template x-if="invoiceData.customer_phone">
                        <p style="margin: 2px 0;" x-text="'Phone: ' + invoiceData.customer_phone"></p>
                    </template>
                    <template x-if="invoiceData.customer_email">
                        <p style="margin: 2px 0;" x-text="'Email: ' + invoiceData.customer_email"></p>
                    </template>
                    <template x-if="invoiceData.customer_address">
                        <p style="margin: 2px 0;" x-text="'Address: ' + invoiceData.customer_address"></p>
                    </template>
                </div>
            </div>
        </template>

        <div style="border-top: 1px dashed #000; margin: 6px 0;"></div>

        <table :style="'width: 100%; table-layout: fixed; border-collapse: collapse; margin: 0.5em 0; font-size: 0.9em;'">
            <template x-if="receiptSection('items_header')">
                <thead>
                    <tr>
                        <th :style="'text-align: left; padding: 3px 0; border-bottom: 1px dashed #000; font-weight: bold; width: ' + receiptPrint.col_item_pct + '%;'">Item</th>
                        <th :style="'text-align: center; padding: 3px 0; border-bottom: 1px dashed #000; font-weight: bold; width: ' + receiptPrint.col_qty_pct + '%;'">Qty</th>
                        <th :style="'text-align: right; padding: 3px 0; border-bottom: 1px dashed #000; font-weight: bold; width: ' + receiptPrint.col_price_pct + '%;'">Price</th>
                    </tr>
                </thead>
            </template>
            <tbody>
                <template x-for="(item, index) in invoiceData.items" :key="index">
                    <tr>
                        <td style="padding: 3px 0; border-bottom: 1px dotted #ccc; word-wrap: break-word;">
                            <div style="font-weight: bold;" x-text="item.item_name"></div>
                            <template x-if="receiptSection('item_variants') && item.variants && (item.variants.option_name || item.variants.variant_name)">
                                <div style="font-size: 0.88em; color: #000;" x-text="(item.variants.variant_name ? item.variants.variant_name + ': ' : '') + (item.variants.option_name || item.variants.variant_name || '')"></div>
                            </template>
                            <template x-if="receiptSection('item_addons') && item.addons && item.addons.length">
                                <div style="font-size: 0.88em; color: #000;" x-text="addonsLabel(item.addons)"></div>
                            </template>
                            <template x-if="receiptSection('item_notes') && item.special_instructions">
                                <div style="font-size: 0.88em; color: #000;" x-text="item.special_instructions"></div>
                            </template>
                            <template x-if="receiptSection('deal_items') && dealInvoiceComponents(item).length">
                                <div style="font-size: 0.88em; color: #000;">
                                    <template x-for="(comp, cIdx) in dealInvoiceComponents(item)" :key="'deal-comp-' + index + '-' + cIdx">
                                        <div x-text="'· ' + comp.name + ' x' + formatQuantity(comp.quantity)"></div>
                                    </template>
                                </div>
                            </template>
                        </td>
                        <td style="text-align: center; padding: 3px 0; border-bottom: 1px dotted #ccc;" x-text="formatQuantity(parseFloat(item.quantity))"></td>
                        <td :style="receiptPriceCellStyle()" x-text="formatCurrency(parseFloat(item.total_price))"></td>
                    </tr>
                </template>
            </tbody>
        </table>

        <div style="margin-top: 6px; border-top: 1px dashed #000; padding-top: 4px; font-size: 0.9em;">
            <template x-if="receiptShowSubtotal()">
                <div style="display: flex; justify-content: space-between; margin: 2px 0;">
                    <span>Subtotal:</span>
                    <span :style="receiptAmountCellStyle()" x-text="formatCurrency(parseFloat(invoiceData.subtotal))"></span>
                </div>
            </template>
            <template x-if="receiptSection('discount') && parseFloat(invoiceData.discount_amount) > 0">
                <div style="display: flex; justify-content: space-between; margin: 2px 0;">
                    <span>Discount:</span>
                    <span x-text="'-' + formatCurrency(parseFloat(invoiceData.discount_amount))"></span>
                </div>
            </template>
            <template x-if="receiptSection('service_charge') && parseFloat(invoiceData.service_charge) > 0">
                <div style="display: flex; justify-content: space-between; margin: 2px 0;">
                    <span>Service Charge:</span>
                    <span x-text="formatCurrency(parseFloat(invoiceData.service_charge))"></span>
                </div>
            </template>
            <template x-if="receiptSection('delivery_fee') && parseFloat(invoiceData.delivery_fee) > 0">
                <div style="display: flex; justify-content: space-between; margin: 2px 0;">
                    <span>Delivery Fee:</span>
                    <span x-text="formatCurrency(parseFloat(invoiceData.delivery_fee))"></span>
                </div>
            </template>
            <template x-if="receiptSection('tax') && parseFloat(invoiceData.tax_amount) > 0">
                <div style="display: flex; justify-content: space-between; margin: 2px 0;">
                    <span>Tax:</span>
                    <span x-text="formatCurrency(parseFloat(invoiceData.tax_amount))"></span>
                </div>
            </template>
            <div style="display: flex; justify-content: space-between; border-top: 1px solid #000; border-bottom: 1px solid #000; padding: 0.35em 0; margin: 0.35em 0; font-weight: bold; font-size: 1.1em;">
                <span>TOTAL:</span>
                <span :style="receiptAmountCellStyle()" x-text="formatCurrency(parseFloat(invoiceData.total_amount))"></span>
            </div>
        </div>

        <template x-if="receiptSection('payment_info')">
            <div style="margin-top: 6px; padding: 4px 0; font-size: 0.9em; text-align: center;">
                <p style="margin: 2px 0;" x-text="'Payment: ' + invoicePaymentMethodDisplay() + ' · ' + invoicePaymentStatusDisplay()"></p>
                <template x-if="invoiceData && invoiceData.payment_method === 'split' && invoiceData.payments && invoiceData.payments.length">
                    <template x-for="(payment, pIdx) in invoiceData.payments" :key="'inv-pay-' + pIdx">
                        <p style="margin: 2px 0; font-size: 0.88em;" x-text="(payment.money_source?.name || 'Payment') + ': ' + formatCurrency(parseFloat(payment.amount))"></p>
                    </template>
                </template>
            </div>
        </template>

        <template x-if="receiptSection('order_notes') && invoiceData.notes">
            <div style="margin: 0.5em 0; font-size: 0.9em;">
                <div style="font-weight: bold; margin-bottom: 2px; text-transform: uppercase; border-bottom: 1px solid #000; padding-bottom: 2px;">Notes</div>
                <div style="margin: 2px 0;" x-text="invoiceData.notes"></div>
            </div>
        </template>

        <div style="text-align: center; margin-top: 0.75em; padding-top: 0.5em; border-top: 1px dashed #000; font-size: 0.88em;">
            <template x-if="receiptSection('thank_you')">
                <p>Thank you for your business!</p>
            </template>
            <div style="margin-top: 6px; padding-top: 6px; border-top: 1px dashed #ccc;">
                <p style="font-size: 0.85em; color: #000;">Powered by {{ config('app.name') }} (thefoodpos.com)</p>
                <p style="font-size: 0.85em; color: #000; margin-top: 2px;">for all kind of softwares/marketing <br/>0306 5918 097 / 0312 7032 292</p>
            </div>
        </div>
    </div>
</template>
