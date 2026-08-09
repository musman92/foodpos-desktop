<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
@php
    $receiptLayout = receipt_layout_settings();
    $posLayoutConfig = pos_layout_settings();
    $usesCompactOrderContext = ! empty($posLayoutConfig['uses_compact_order_context']);
    $categoryBar = $posLayoutConfig['category_bar'];
@endphp
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>POS - {{ get_company_name() }}</title>
    @if(get_favicon())
        <link rel="icon" type="image/x-icon" href="{{ get_favicon() }}">
    @endif
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        [x-cloak] { display: none !important; }

        body input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]):not([type="range"]):not([type="button"]):not([type="submit"]):not([type="reset"]):not([type="image"]),
        body select,
        body textarea {
            border-style: solid;
            border-width: 1px;
            border-color: rgb(209 213 219);
        }

        .form-label {
            display: block;
            font-size: 0.875rem;
            line-height: 1.25rem;
            font-weight: 500;
            color: rgb(55 65 81);
            margin-bottom: 0.5rem;
        }

        .form-control {
            display: block;
            width: 100%;
            height: 3rem;
            padding-left: 1rem;
            padding-right: 1rem;
            border-radius: 0.5rem;
            border: 1px solid rgb(209 213 219);
            background-color: #fff;
            font-size: 0.875rem;
            line-height: 1.25rem;
            box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
        }

        .form-control:focus {
            outline: none;
            border-color: rgb(99 102 241);
            box-shadow: 0 0 0 1px rgb(99 102 241);
        }

        .form-control:disabled {
            background-color: rgb(243 244 246);
            cursor: not-allowed;
        }

        .form-control-textarea {
            display: block;
            width: 100%;
            min-height: 5rem;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            border: 1px solid rgb(209 213 219);
            background-color: #fff;
            font-size: 0.875rem;
            line-height: 1.25rem;
            box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
        }

        .form-control-textarea:focus {
            outline: none;
            border-color: rgb(99 102 241);
            box-shadow: 0 0 0 1px rgb(99 102 241);
        }

        .btn-form-secondary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 3rem;
            padding-left: 1rem;
            padding-right: 1rem;
            border-radius: 0.5rem;
            border: 1px solid rgb(209 213 219);
            background-color: #fff;
            font-size: 0.875rem;
            font-weight: 500;
            color: rgb(55 65 81);
        }

        .btn-form-secondary:hover {
            background-color: rgb(249 250 251);
        }

        .btn-form-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 3rem;
            padding-left: 1rem;
            padding-right: 1rem;
            border-radius: 0.5rem;
            border: 1px solid transparent;
            background-color: rgb(79 70 229);
            font-size: 0.875rem;
            font-weight: 500;
            color: #fff;
            box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
        }

        .btn-form-primary:hover {
            background-color: rgb(67 56 202);
        }

        .btn-form-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .menu-item-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }
        .menu-item-card {
            transition: all 0.2s ease;
        }
        .toast-enter {
            animation: slideInRight 0.3s ease-out;
        }
        .toast-leave {
            animation: slideOutRight 0.3s ease-in;
        }
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
        .pos-category-strip {
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
            scrollbar-color: #cbd5e1 transparent;
        }
        .pos-category-strip::-webkit-scrollbar {
            height: 6px;
        }
        .pos-category-strip::-webkit-scrollbar-thumb {
            background-color: #cbd5e1;
            border-radius: 3px;
        }
        @supports (padding: max(0px)) {
            .pos-safe-bottom {
                padding-bottom: max(0.375rem, env(safe-area-inset-bottom, 0px));
            }
        }
        @media print {
            @page {
                size: {{ $receiptLayout['paper_width_mm'] }}mm auto;
                margin: 0;
            }
            html, body {
                width: {{ $receiptLayout['paper_width_mm'] }}mm !important;
                max-width: {{ $receiptLayout['paper_width_mm'] }}mm !important;
                height: auto !important;
                margin: 0 !important;
                padding: 0 !important;
                overflow: visible !important;
                background: #fff !important;
            }
            body * {
                visibility: hidden;
            }
            #invoice-content, #invoice-content * {
                visibility: visible;
            }
            #invoice-content {
                position: absolute;
                left: 0;
                top: 0;
                width: {{ $receiptLayout['paper_width_mm'] }}mm !important;
                max-width: {{ $receiptLayout['paper_width_mm'] }}mm !important;
                margin: 0 !important;
                padding: 1.5mm {{ $receiptLayout['pad_right_mm'] }}mm 1.5mm {{ $receiptLayout['pad_left_mm'] }}mm !important;
                overflow: visible !important;
            }
        }
    </style>
</head>
<body class="bg-gray-100 overflow-hidden">
    <div class="h-screen flex flex-col pos-layout-{{ $posLayoutConfig['layout'] }} pos-density-{{ $posLayoutConfig['product_density'] }}" x-data="posSystem()">
        <!-- Toast Container (above nav & modals) -->
        <div class="fixed top-[var(--pos-nav-h,3.5rem)] left-3 right-3 sm:left-auto sm:right-4 z-[200] space-y-2 max-w-md sm:max-w-md sm:ml-auto pointer-events-none" x-show="toasts.length > 0">
            <template x-for="(toast, index) in toasts" :key="toast.id">
                <div 
                    x-show="toast.visible"
                    x-transition:enter="toast-enter"
                    x-transition:leave="toast-leave"
                    :class="toast.type === 'success' ? 'bg-green-500' : toast.type === 'error' ? 'bg-red-500' : 'bg-blue-500'"
                    class="pointer-events-auto w-full sm:min-w-80 sm:w-auto max-w-full px-4 py-3 rounded-lg shadow-lg text-white flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <i :class="toast.type === 'success' ? 'fas fa-check-circle' : toast.type === 'error' ? 'fas fa-exclamation-circle' : 'fas fa-info-circle'"></i>
                        <span x-text="toast.message" class="font-medium"></span>
                    </div>
                    <button @click="removeToast(toast.id)" class="ml-4 text-white hover:text-gray-200">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </template>
        </div>

        <!-- Top Bar: POS | Table view | Saved / Dine-in | Takeaway | Delivery | Today (history) -->
        <div id="pos-top-nav" class="fixed top-0 left-0 right-0 z-[120] bg-white border-b border-gray-200 shadow-sm">
            <div class="lg:hidden flex flex-col gap-2 px-3 py-2">
                <div class="flex items-center gap-1.5 overflow-x-auto pb-0.5 -mx-1 px-1 scrollbar-thin">
                    <button
                        type="button"
                        @click.stop="openPos()"
                        :class="activePosNav === 'pos' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-800 border-gray-300 hover:bg-gray-50'"
                        class="inline-flex shrink-0 items-center gap-1.5 px-2.5 py-2 rounded-lg text-xs font-semibold border touch-manipulation">
                        <i class="fas fa-cash-register text-[10px]"></i>
                        POS
                    </button>
                    <button
                        type="button"
                        @click.stop="openTableViewModal()"
                        :class="activePosNav === 'table' ? 'bg-indigo-600 text-white border-indigo-600' : 'border-indigo-200 bg-indigo-50 text-indigo-900 hover:bg-indigo-100'"
                        class="inline-flex shrink-0 items-center gap-1.5 px-2.5 py-2 rounded-lg text-xs font-medium border touch-manipulation">
                        <i class="fas fa-chair text-[10px]"></i>
                        Tables
                    </button>
                    <button
                        type="button"
                        @click.stop="openOrderQueueModal('dine_in')"
                        :class="activePosNav === 'dine_in' ? 'bg-indigo-600 text-white border-indigo-600' : 'border-amber-200 bg-amber-50 text-amber-950 hover:bg-amber-100'"
                        class="inline-flex shrink-0 items-center gap-1.5 px-2.5 py-2 rounded-lg text-xs font-medium border touch-manipulation whitespace-nowrap">
                        <i class="fas fa-utensils text-[10px]"></i>
                        Dine-in
                    </button>
                    <button
                        type="button"
                        @click.stop="openOrderQueueModal('takeaway')"
                        :class="activePosNav === 'takeaway' ? 'bg-indigo-600 text-white border-indigo-600' : 'border-gray-300 bg-white text-gray-800 hover:bg-gray-50'"
                        class="inline-flex shrink-0 items-center gap-1.5 px-2.5 py-2 rounded-lg text-xs font-medium border touch-manipulation whitespace-nowrap">
                        <i class="fas fa-shopping-bag text-[10px]"></i>
                        Takeaway
                    </button>
                    <button
                        type="button"
                        @click.stop="openOrderQueueModal('delivery')"
                        :class="activePosNav === 'delivery' ? 'bg-indigo-600 text-white border-indigo-600' : 'border-gray-300 bg-white text-gray-800 hover:bg-gray-50'"
                        class="inline-flex shrink-0 items-center gap-1.5 px-2.5 py-2 rounded-lg text-xs font-medium border touch-manipulation whitespace-nowrap">
                        <i class="fas fa-motorcycle text-[10px]"></i>
                        Delivery
                    </button>
                    <button
                        type="button"
                        x-show="addonKitchenTracking"
                        x-cloak
                        @click.stop="openKitchenQueueModal()"
                        :class="activePosNav === 'kitchen' ? 'bg-indigo-600 text-white border-indigo-600' : 'border-orange-200 bg-orange-50 text-orange-950 hover:bg-orange-100'"
                        class="inline-flex shrink-0 items-center gap-1.5 px-2.5 py-2 rounded-lg text-xs font-medium border touch-manipulation whitespace-nowrap">
                        <i class="fas fa-fire text-[10px]"></i>
                        Kitchen
                    </button>
                    <button
                        type="button"
                        @click.stop="openOrderQueueModal('today')"
                        :class="activePosNav === 'today' ? 'bg-indigo-600 text-white border-indigo-600' : 'border-gray-300 bg-white text-gray-800 hover:bg-gray-50'"
                        class="inline-flex shrink-0 items-center gap-1.5 px-2.5 py-2 rounded-lg text-xs font-medium border touch-manipulation whitespace-nowrap">
                        <i class="fas fa-history text-[10px]"></i>
                        History
                    </button>
                </div>
                <div class="flex items-center justify-between gap-2">
                <div x-show="posSessionReady" x-cloak class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        @click="posTab = 'products'"
                        :class="posTab === 'products' ? 'bg-gray-800 text-white border-gray-800' : 'bg-gray-100 text-gray-800 border-gray-200 hover:bg-gray-200'"
                        class="px-3 py-2 rounded-lg text-sm font-medium border touch-manipulation">
                        <i class="fas fa-th mr-1.5 text-xs"></i>Products
                    </button>
                    <button
                        type="button"
                        @click="posTab = 'order'"
                        :class="posTab === 'order' ? 'bg-gray-800 text-white border-gray-800' : 'bg-gray-100 text-gray-800 border-gray-200 hover:bg-gray-200'"
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium border touch-manipulation">
                        <i class="fas fa-receipt mr-1.5 text-xs"></i>Order
                        <span
                            x-show="cart.length > 0"
                            class="min-w-[1.25rem] text-center text-[10px] font-bold px-1 py-0.5 rounded-full bg-white/25"
                            x-text="cart.length"></span>
                    </button>
                </div>
                <div class="flex items-center gap-1 shrink-0">
                    <div x-show="posSessionReady" x-cloak class="flex items-center gap-1">
                        <label x-show="showAutoBillToggle"
                               x-cloak
                               class="inline-flex items-center gap-1.5 px-2 py-1.5 rounded-lg border border-gray-200 bg-white touch-manipulation"
                               title="When off, Checkout takes payment without auto-printing">
                            <span class="text-[10px] font-semibold text-gray-600 whitespace-nowrap">Auto Bill</span>
                            <button type="button"
                                    role="switch"
                                    :aria-checked="autoBillEnabled"
                                    @click="setAutoBillEnabled(!autoBillEnabled)"
                                    :class="autoBillEnabled ? 'bg-indigo-600' : 'bg-gray-300'"
                                    class="relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition-colors">
                                <span :class="autoBillEnabled ? 'translate-x-4' : 'translate-x-0.5'"
                                      class="inline-block h-3.5 w-3.5 rounded-full bg-white shadow transition-transform"></span>
                            </button>
                        </label>
                        <button type="button" @click="startNewOrder()" class="p-2 rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200 border border-gray-200 touch-manipulation" title="New order">
                            <i class="fas fa-plus"></i>
                        </button>
                        <button type="button" @click="clearCart()" class="p-2 rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200 border border-gray-200 touch-manipulation" title="Clear cart">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                    <a href="{{ route('dashboard') }}" class="p-2 rounded-lg text-gray-700 hover:bg-gray-100 border border-gray-200 touch-manipulation" title="Back">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                </div>
                </div>
            </div>
            <div class="hidden lg:flex px-4 py-2 flex-nowrap items-center gap-3 min-w-0">
                <div class="flex flex-wrap items-center gap-2 sm:gap-3 min-w-0 flex-1">
                    <button
                        type="button"
                        @click.stop="openPos()"
                        :class="activePosNav === 'pos' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-800 border-gray-300 hover:bg-gray-50'"
                        class="inline-flex shrink-0 items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-semibold border touch-manipulation whitespace-nowrap">
                        <i class="fas fa-cash-register text-xs"></i>
                        POS
                    </button>
                    <button
                        type="button"
                        @click.stop="openTableViewModal()"
                        :class="activePosNav === 'table' ? 'bg-indigo-600 text-white border-indigo-600' : 'border-indigo-200 bg-indigo-50 text-indigo-900 hover:bg-indigo-100'"
                        class="inline-flex shrink-0 items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-semibold border touch-manipulation whitespace-nowrap">
                        <i class="fas fa-chair text-xs"></i>
                        Table view
                    </button>
                    <button
                        type="button"
                        @click.stop="openOrderQueueModal('dine_in')"
                        :class="activePosNav === 'dine_in' ? 'bg-indigo-600 text-white border-indigo-600' : 'border-amber-200 bg-amber-50 text-amber-950 hover:bg-amber-100'"
                        class="inline-flex shrink-0 items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-semibold border touch-manipulation whitespace-nowrap">
                        <i class="fas fa-utensils text-xs"></i>
                        Saved / Dine-in
                    </button>
                    <button
                        type="button"
                        @click.stop="openOrderQueueModal('takeaway')"
                        :class="activePosNav === 'takeaway' ? 'bg-indigo-600 text-white border-indigo-600' : 'border-gray-300 bg-white text-gray-800 hover:bg-gray-50'"
                        class="inline-flex shrink-0 items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-semibold border touch-manipulation whitespace-nowrap">
                        <i class="fas fa-shopping-bag text-xs"></i>
                        Takeaway
                    </button>
                    <button
                        type="button"
                        @click.stop="openOrderQueueModal('delivery')"
                        :class="activePosNav === 'delivery' ? 'bg-indigo-600 text-white border-indigo-600' : 'border-gray-300 bg-white text-gray-800 hover:bg-gray-50'"
                        class="inline-flex shrink-0 items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-semibold border touch-manipulation whitespace-nowrap">
                        <i class="fas fa-motorcycle text-xs"></i>
                        Delivery
                    </button>
                    <button
                        type="button"
                        x-show="addonKitchenTracking"
                        x-cloak
                        @click.stop="openKitchenQueueModal()"
                        :class="activePosNav === 'kitchen' ? 'bg-indigo-600 text-white border-indigo-600' : 'border-orange-200 bg-orange-50 text-orange-950 hover:bg-orange-100'"
                        class="inline-flex shrink-0 items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-semibold border touch-manipulation whitespace-nowrap">
                        <i class="fas fa-fire text-xs"></i>
                        Kitchen
                    </button>
                    <button
                        type="button"
                        @click.stop="openOrderQueueModal('today')"
                        :class="activePosNav === 'today' ? 'bg-indigo-600 text-white border-indigo-600' : 'border-gray-300 bg-white text-gray-800 hover:bg-gray-50'"
                        class="inline-flex shrink-0 items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-semibold border touch-manipulation whitespace-nowrap">
                        <i class="fas fa-history text-xs"></i>
                        Today (history)
                    </button>
                    @if(show_branch_ui() && $branches->count() > 1)
                    <div class="flex items-center gap-2 min-w-0 sm:min-w-[10rem] lg:max-w-[14rem]">
                        <label class="text-xs font-medium text-gray-600 shrink-0 whitespace-nowrap">Branch</label>
                        <select x-model.number="selectedBranchId" @change="loadBranchData()" class="w-full min-w-0 px-2 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}" {{ $selectedBranchId == $branch->id ? 'selected' : '' }}>
                                    {{ $branch->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                    <span x-show="posSessionReady && activeOrderNumber" x-cloak class="text-xs text-gray-500 whitespace-nowrap">
                        Order <span class="font-mono font-semibold text-gray-800" x-text="activeOrderNumber"></span>
                    </span>
                </div>
                <div class="inline-flex items-center gap-2 shrink-0 border-l border-gray-200 pl-3">
                    <div x-show="posSessionReady" x-cloak class="inline-flex items-center gap-2">
                        <label x-show="showAutoBillToggle"
                               x-cloak
                               class="inline-flex items-center gap-2 px-2.5 py-1.5 rounded-lg border border-gray-200 bg-white touch-manipulation"
                               title="When off, Checkout takes payment without auto-printing">
                            <span class="text-xs font-semibold text-gray-700 whitespace-nowrap">Auto Bill</span>
                            <button type="button"
                                    role="switch"
                                    :aria-checked="autoBillEnabled"
                                    @click="setAutoBillEnabled(!autoBillEnabled)"
                                    :class="autoBillEnabled ? 'bg-indigo-600' : 'bg-gray-300'"
                                    class="relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition-colors">
                                <span :class="autoBillEnabled ? 'translate-x-4' : 'translate-x-0.5'"
                                      class="inline-block h-3.5 w-3.5 rounded-full bg-white shadow transition-transform"></span>
                            </button>
                        </label>
                        <button type="button" @click="startNewOrder()" class="px-3 py-2 text-sm font-medium text-indigo-700 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 whitespace-nowrap">
                            <i class="fas fa-plus mr-2"></i>New order
                        </button>
                        <button type="button" @click="clearCart()" class="px-3 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 text-sm font-medium whitespace-nowrap">
                            <i class="fas fa-trash mr-2"></i>Clear
                        </button>
                    </div>
                    <a href="{{ route('dashboard') }}" class="px-3 py-2 text-sm font-medium text-gray-700 hover:text-gray-900 whitespace-nowrap">
                        <i class="fas fa-arrow-left mr-2"></i>Back
                    </a>
                </div>
            </div>
        </div>
        <div id="pos-nav-spacer" class="shrink-0" style="height: var(--pos-nav-h, 3.5rem);" aria-hidden="true"></div>

        <!-- POS session: service type + dine-in floor/table before selling -->
        <div
            x-show="!posSessionReady"
            x-cloak
            class="fixed left-0 right-0 bottom-0 z-[100] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
            style="top: var(--pos-nav-h, 3.5rem);"
            aria-modal="true"
            role="dialog">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-h-[92vh] overflow-y-auto p-6 sm:p-8 space-y-6"
                 :class="sessionStep === 2 && orderType === 'dine_in' ? 'max-w-4xl' : (sessionStep === 3 ? 'max-w-xl' : 'max-w-lg')">
                <div class="relative">
                    <a href="{{ route('dashboard') }}"
                       class="absolute left-0 top-0 z-10 inline-flex h-11 w-11 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-600 shadow-sm hover:text-indigo-600 hover:border-indigo-300 hover:bg-indigo-50 transition touch-manipulation"
                       title="Dashboard"
                       aria-label="Go to dashboard">
                        <i class="fas fa-home text-lg" aria-hidden="true"></i>
                    </a>
                    <div class="text-center space-y-1 px-12 sm:px-14">
                    <h2 class="text-xl font-bold text-gray-900" x-text="sessionStep === 3 && orderType === 'delivery' ? 'Delivery customer' : 'Start selling'"></h2>
                    <p class="text-sm text-gray-600" x-text="sessionStep === 3 && orderType === 'delivery' ? 'Search by mobile or email, pick a saved address, or create a new customer.' : 'Choose how this order is served. You can save an open tab and checkout later.'"></p>
                    </div>
                </div>

                <template x-if="sessionStep === 1">
                    <div class="space-y-3">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide text-center">Service type</p>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <button type="button" @click="pickServiceType('dine_in')" class="flex flex-col items-center gap-2 p-4 rounded-xl border-2 border-gray-200 hover:border-indigo-500 hover:bg-indigo-50 transition touch-manipulation">
                                <i class="fas fa-utensils text-2xl text-indigo-600"></i>
                                <span class="font-semibold text-gray-900">Dine in</span>
                                <span class="text-[11px] text-gray-500 text-center">Choose a table</span>
                            </button>
                            <button type="button" @click="pickServiceType('takeaway')" class="flex flex-col items-center gap-2 p-4 rounded-xl border-2 border-gray-200 hover:border-indigo-500 hover:bg-indigo-50 transition touch-manipulation">
                                <i class="fas fa-shopping-bag text-2xl text-indigo-600"></i>
                                <span class="font-semibold text-gray-900">Take away</span>
                                <span class="text-[11px] text-gray-500 text-center">Pay now or tab</span>
                            </button>
                            <button type="button" @click="pickServiceType('delivery')" class="flex flex-col items-center gap-2 p-4 rounded-xl border-2 border-gray-200 hover:border-indigo-500 hover:bg-indigo-50 transition touch-manipulation">
                                <i class="fas fa-motorcycle text-2xl text-indigo-600"></i>
                                <span class="font-semibold text-gray-900">Delivery</span>
                                <span class="text-[11px] text-gray-500 text-center">Pay now or tab</span>
                            </button>
                        </div>
                        <div class="border-t border-gray-200 pt-4">
                            <button type="button" @click="openOrderQueueModal('dine_in')" class="w-full py-3 px-4 rounded-xl border-2 border-amber-200 bg-amber-50 text-amber-950 font-semibold text-sm hover:bg-amber-100 transition touch-manipulation inline-flex items-center justify-center gap-2">
                                <i class="fas fa-utensils"></i>
                                Saved dine-in tabs
                            </button>
                            <p class="text-[11px] text-gray-500 text-center mt-2">Takeaway, delivery, and today&apos;s history are in the top bar</p>
                        </div>
                    </div>
                </template>

                <template x-if="sessionStep === 2 && orderType === 'dine_in'">
                    <div class="space-y-4">
                        <button type="button" @click="sessionStep = 1" class="text-sm text-indigo-600 font-medium hover:text-indigo-800">
                            <i class="fas fa-arrow-left mr-1"></i> Back
                        </button>
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Choose floor, then table</p>
                        <div class="space-y-2" x-show="floors.length > 0">
                            <div class="flex flex-wrap gap-1 sm:gap-0 border-b border-gray-200" role="tablist" aria-label="Floors">
                                <template x-for="floor in floors" :key="floor.id === null || floor.id === undefined ? 'none' : floor.id">
                                    <button
                                        type="button"
                                        role="tab"
                                        :aria-selected="String(selectedFloorForSessionId) === String(floorSessionId(floor))"
                                        @click="selectedFloorForSessionId = floorSessionId(floor)"
                                        class="px-3 py-2 text-sm font-medium rounded-t-lg border-b-2 -mb-px transition touch-manipulation min-h-[44px] sm:min-h-0"
                                        :class="String(selectedFloorForSessionId) === String(floorSessionId(floor))
                                            ? 'border-indigo-600 text-indigo-800 bg-indigo-50 z-[1]'
                                            : 'border-transparent text-gray-600 hover:text-gray-900 hover:border-gray-300 bg-transparent'">
                                        <span x-text="floor.name"></span>
                                        <span class="text-gray-400 font-normal" x-text="' (' + (floor.tables?.length || 0) + ')'"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                        <div class="space-y-2" x-show="selectedFloorForSessionId !== null && selectedFloorForSessionId !== undefined && selectedFloorForSessionId !== ''">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <label class="text-xs font-medium text-gray-700">Table</label>
                                <div class="flex flex-wrap items-center gap-2 text-[10px] text-gray-600">
                                    <span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span> Available</span>
                                    <span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span> Occupied</span>
                                    <span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full bg-blue-500"></span> Reserved</span>
                                    <span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full bg-orange-500"></span> Dirty</span>
                                    <span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full bg-gray-400"></span> Closed</span>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2.5">
                                <template x-for="tbl in tablesForSelectedSessionFloor()" :key="tbl.id">
                                    <button
                                        type="button"
                                        @click="confirmDineInTable(tbl.id)"
                                        class="px-3 py-3 rounded-xl border-2 text-sm font-medium transition touch-manipulation text-left min-h-[4.25rem]"
                                        :class="[
                                            sessionTableCardClass(tbl),
                                            Number(selectedTableId) === Number(tbl.id) ? 'ring-2 ring-indigo-500 ring-offset-1' : '',
                                        ]">
                                        <span class="block font-semibold text-gray-900" x-text="tbl.name"></span>
                                        <span class="mt-1 inline-flex items-center text-[10px] font-bold uppercase tracking-wide px-1.5 py-0.5 rounded"
                                              :class="sessionTableStatusBadgeClass(tbl)"
                                              x-text="sessionTableStatusLabel(tbl)"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                        <p x-show="floors.length === 0" class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-3">
                            No floors or tables for this branch. Add them under Administration → Floors / Tables.
                        </p>
                    </div>
                </template>

                <template x-if="sessionStep === 3 && orderType === 'delivery'">
                    <div class="space-y-4">
                        <button type="button" @click="backFromDeliveryWizard()" class="text-sm text-indigo-600 font-medium hover:text-indigo-800">
                            <i class="fas fa-arrow-left mr-1"></i> Back
                        </button>

                        <template x-if="deliveryWizardView === 'search'">
                            <div class="space-y-4">
                                <div>
                                    <label class="form-label">Customer</label>
                                    <div class="relative">
                                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm pointer-events-none"></i>
                                        <input type="text" x-model="deliverySearchQuery"
                                               @input="scheduleDeliveryCustomerSearch()"
                                               @keydown.enter.prevent="fetchDeliveryCustomers()"
                                               placeholder="Filter by code, name, mobile, or email…"
                                               class="form-control w-full pl-9 pr-10">
                                        <span x-show="deliverySearchLoading" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400">
                                            <i class="fas fa-spinner fa-spin text-sm"></i>
                                        </span>
                                    </div>
                                </div>
                                <button type="button" @click="openNewCustomerForDelivery()"
                                        class="w-full py-2.5 border-2 border-dashed border-gray-300 rounded-xl text-sm font-medium text-gray-700 hover:border-indigo-400 hover:bg-indigo-50 touch-manipulation">
                                    <i class="fas fa-user-plus mr-2 text-indigo-600"></i>Create new customer
                                </button>
                                <div x-show="deliverySearchDone && deliverySearchResults.length === 0" x-cloak
                                     class="text-sm text-gray-600 bg-gray-50 border border-gray-200 rounded-lg p-3">
                                    No customer found. Try another filter or create a new customer.
                                </div>
                                <div class="space-y-2 max-h-56 overflow-y-auto">
                                    <template x-for="c in deliverySearchResults" :key="c.id">
                                        <button type="button" @click="selectDeliveryCustomer(c)"
                                                class="w-full text-left p-3 rounded-lg border border-gray-200 hover:border-indigo-400 hover:bg-indigo-50 transition touch-manipulation">
                                            <div class="font-semibold text-gray-900" x-text="c.name"></div>
                                            <div class="text-xs text-gray-600 mt-0.5">
                                                <span x-text="c.phone || '—'"></span>
                                                <span x-show="c.email"> · </span>
                                                <span x-text="c.email || ''"></span>
                                            </div>
                                            <div class="text-[11px] text-gray-500 mt-1 line-clamp-2" x-text="customerPrimaryAddressLabel(c)"></div>
                                            <div class="text-[11px] text-indigo-600 mt-1" x-show="c.addresses && c.addresses.length > 1" x-text="c.addresses.length + ' saved addresses — pick on next step'"></div>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </template>

                        <template x-if="deliveryWizardView === 'addresses' && deliveryPendingCustomer">
                            <div class="space-y-3">
                                <p class="text-sm text-gray-700">Select delivery address for <span class="font-semibold" x-text="deliveryPendingCustomer.name"></span></p>
                                <div class="grid gap-2 max-h-64 overflow-y-auto">
                                    <template x-for="addr in deliveryPendingCustomer.addresses" :key="addr.id">
                                        <button type="button" @click="confirmDeliveryAddress(addr)"
                                                class="text-left p-3 rounded-lg border border-gray-200 hover:border-indigo-500 hover:bg-indigo-50 transition touch-manipulation">
                                            <div class="text-xs font-semibold text-gray-500" x-text="addr.label || 'Address'"></div>
                                            <div class="text-sm text-gray-900 mt-1" x-text="addr.full_address || addr.address_line_1 || ''"></div>
                                        </button>
                                    </template>
                                </div>
                                <button type="button" @click="deliveryWizardView = 'search'; deliveryPendingCustomer = null"
                                        class="text-sm text-gray-600 hover:text-gray-900 font-medium">Back to search</button>
                            </div>
                        </template>

                        <template x-if="deliveryWizardView === 'no_addresses' && deliveryPendingCustomer">
                            <div class="space-y-3">
                                <p class="text-sm text-gray-700">No saved address for <span class="font-semibold" x-text="deliveryPendingCustomer.name"></span>. Enter delivery address:</p>
                                <textarea x-model="deliveryManualAddress" rows="3" placeholder="Street, area, city…" class="form-control-textarea"></textarea>
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" @click="confirmDeliveryManualAddress()"
                                            class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 touch-manipulation">
                                        Continue to POS
                                    </button>
                                    <button type="button" @click="deliveryWizardView = 'search'; deliveryPendingCustomer = null; deliveryManualAddress = ''"
                                            class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 touch-manipulation">
                                        Back
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </div>

        <!-- Checkout modal (open tab payment) -->
        <div x-show="showCheckoutModal" x-cloak class="fixed inset-0 z-[90] flex items-center justify-center p-4 bg-black/40" @keydown.escape.window="showCheckoutModal = false">
            <div class="bg-white rounded-xl shadow-xl max-w-lg w-full max-h-[90vh] overflow-y-auto p-6 space-y-4" @click.outside="showCheckoutModal = false">
                <h3 class="text-lg font-bold text-gray-900">Checkout</h3>
                <p class="text-sm text-gray-600">Take payment for order <span class="font-mono font-semibold" x-text="activeOrderNumber || ''"></span></p>
                <div class="space-y-3 border border-gray-200 rounded-lg p-3 bg-gray-50/80">
                    <p class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Customer <span class="text-gray-400 font-normal normal-case">(optional)</span></p>
                    <div>
                        <label class="form-label">Name</label>
                        <input type="text" x-model="customerName" autocomplete="name" class="form-control" placeholder="If the customer wants to share">
                    </div>
                    <div>
                        <label class="form-label">Mobile</label>
                        <input type="tel" x-model="customerPhone" autocomplete="tel" class="form-control" placeholder="If the customer wants to share">
                    </div>
                    <div>
                        <label class="form-label">Email <span class="text-gray-400 font-normal">(optional — offers and updates)</span></label>
                        <input type="email" x-model="customerEmail" autocomplete="email" class="form-control" placeholder="If the customer wants to share">
                    </div>
                </div>
                <div>
                    <label class="form-label">Payment</label>
                    <select x-model="checkoutPaymentSelection" @change="handleCheckoutPaymentSelectionChange()" class="form-control">
                        <option value="">Select…</option>
                        <option x-show="allowPosCreditSales" value="credit">Credit</option>
                        <option x-show="canPosFoc" value="foc">FOC</option>
                        <template x-for="source in moneySources" :key="source.id">
                            <option :value="String(source.id)" x-text="source.name + ' (' + source.type + ')'"></option>
                        </template>
                    </select>
                </div>
                <p x-show="isCheckoutFocPaymentSelected()" class="text-xs text-amber-700 font-medium" x-cloak>Complimentary bill — full amount posted to FOC expense. No cash collected.</p>
                <div x-show="!isCheckoutFocPaymentSelected() && (isCheckoutCreditPaymentSelected() || checkoutPaymentMethod === 'cash')" x-cloak>
                    <label class="form-label" x-text="isCheckoutCreditPaymentSelected() ? 'Amount received' : 'Amount received'"></label>
                    <input type="number" step="0.01" min="0" x-model="checkoutPaidAmount" class="form-control font-semibold tabular-nums" placeholder="0.00">
                    <p x-show="isCheckoutCreditPaymentSelected() && checkoutCreditDue > 0" class="mt-1 text-xs text-amber-700 font-medium" x-text="'On credit: ' + formatCurrency(checkoutCreditDue)"></p>
                    <p x-show="hasSelectedCustomer() && customerCreditAvailable() > 0 && !isCheckoutCreditPaymentSelected()" class="mt-1 text-xs text-emerald-700 font-medium" x-text="'Customer credit available: ' + formatCurrency(customerCreditAvailable())"></p>
                </div>
                <p x-show="isCheckoutCreditPaymentSelected() && checkoutCreditDue > 0 && !hasSelectedCustomer()" class="text-xs text-red-600">Select a registered customer to sell on credit.</p>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" @click="showCheckoutModal = false" class="btn-form-secondary">Cancel</button>
                    <button type="button" @click="submitCheckout()" :disabled="checkoutSubmitting" class="btn-form-primary font-semibold">
                        <span x-show="!checkoutSubmitting">Complete payment</span>
                        <span x-show="checkoutSubmitting"><i class="fas fa-spinner fa-spin"></i></span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Split payment modal -->
        <div x-show="showSplitPaymentModal" x-cloak class="fixed inset-0 z-[95] flex items-center justify-center p-2 sm:p-4 bg-black/50">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-5xl max-h-[95vh] overflow-hidden flex flex-col">
                <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 shrink-0">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">Split payment</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Add payment from different money sources until due is zero.</p>
                    </div>
                    <button type="button" @click="closeSplitPaymentModal()" class="p-2 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100" aria-label="Close">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="flex flex-col lg:flex-row flex-1 min-h-0 overflow-hidden">
                    <div class="lg:w-44 shrink-0 border-b lg:border-b-0 lg:border-r border-gray-200 bg-gray-50 overflow-x-auto lg:overflow-y-auto">
                        <div class="flex lg:flex-col gap-1 p-2 min-w-max lg:min-w-0">
                            <template x-for="source in moneySources" :key="'split-src-' + source.id">
                                <button type="button"
                                        @click="selectSplitMoneySource(source)"
                                        class="px-3 py-2.5 text-left text-sm font-medium rounded-lg transition-colors whitespace-nowrap lg:whitespace-normal"
                                        :class="Number(splitActiveSourceId) === Number(source.id) ? 'bg-indigo-600 text-white shadow-sm' : 'bg-white text-gray-700 border border-gray-200 hover:bg-gray-100'">
                                    <span x-text="source.name"></span>
                                </button>
                            </template>
                        </div>
                    </div>

                    <div class="flex-1 flex flex-col min-h-0 overflow-y-auto p-4 space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-[1fr_auto] gap-3 max-w-xl">
                            <div>
                                <label class="form-label">Amount</label>
                                <input type="number" step="0.01" min="0" x-model="splitLineAmount" class="form-control tabular-nums font-semibold text-lg" placeholder="0.00">
                            </div>
                            <div class="flex items-end">
                                <button type="button" @click="addSplitPaymentLine()" class="w-full sm:w-auto h-11 px-6 bg-gray-800 text-white text-sm font-semibold rounded-lg hover:bg-gray-900">
                                    Add
                                </button>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <template x-for="quick in splitQuickAmounts" :key="'split-q-' + quick">
                                <button type="button" @click="applySplitQuickAmount(quick)" class="px-3 py-1.5 text-sm font-medium rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 tabular-nums" x-text="quick"></button>
                            </template>
                            <button type="button" @click="fillSplitRemainingDue()" class="px-3 py-1.5 text-sm font-medium rounded-lg border border-indigo-300 text-indigo-700 hover:bg-indigo-50">Fill due</button>
                            <button type="button" @click="clearSplitEntryFields()" class="px-3 py-1.5 text-sm font-medium rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-50">Clear fields</button>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                            <div class="border border-gray-200 rounded-lg overflow-hidden">
                                <div class="px-3 py-2 bg-gray-50 border-b border-gray-200 text-xs font-semibold uppercase tracking-wide text-gray-600">Added payments</div>
                                <div class="divide-y divide-gray-100 max-h-48 overflow-y-auto">
                                    <template x-if="splitLines.length === 0">
                                        <p class="px-3 py-6 text-sm text-gray-400 text-center">No payments added yet.</p>
                                    </template>
                                    <template x-for="(line, idx) in splitLines" :key="'split-line-' + idx">
                                        <div class="flex items-center justify-between gap-2 px-3 py-2.5">
                                            <div class="min-w-0">
                                                <p class="text-sm font-medium text-gray-900 truncate" x-text="line.name"></p>
                                            </div>
                                            <div class="flex items-center gap-2 shrink-0">
                                                <span class="text-sm font-semibold text-gray-900 tabular-nums" x-text="formatCurrency(line.amount)"></span>
                                                <button type="button" @click="removeSplitPaymentLine(idx)" class="p-1.5 rounded-lg text-red-600 hover:bg-red-50" title="Remove">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <div class="border border-gray-200 rounded-lg p-4 bg-gray-50/80 space-y-3">
                                <div class="flex justify-between items-baseline">
                                    <span class="text-sm font-medium text-gray-600">Payable</span>
                                    <span class="text-xl font-bold text-gray-900 tabular-nums" x-text="formatCurrency(totalAmount)"></span>
                                </div>
                                <div class="flex justify-between items-baseline">
                                    <span class="text-sm font-medium text-gray-600">Paid</span>
                                    <span class="text-xl font-bold text-emerald-700 tabular-nums" x-text="formatCurrency(splitPaidTotal)"></span>
                                </div>
                                <div class="flex justify-between items-baseline border-t border-gray-200 pt-3">
                                    <span class="text-sm font-semibold text-gray-700">Due</span>
                                    <span class="text-2xl font-bold tabular-nums" :class="splitDue <= 0.009 ? 'text-emerald-700' : 'text-red-600'" x-text="formatCurrency(splitDue)"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-end gap-2 px-4 py-3 border-t border-gray-200 shrink-0 bg-white">
                    <button type="button" @click="clearSplitPaymentLines()" class="btn-form-secondary" x-show="splitLines.length > 0">Clear all</button>
                    <button type="button" @click="closeSplitPaymentModal()" class="btn-form-secondary">Cancel</button>
                    <button type="button" @click="submitSplitPayment()" :disabled="splitSubmitting || splitDue > 0.009 || splitLines.length === 0" class="btn-form-primary font-semibold min-w-[8rem]">
                        <span x-show="!splitSubmitting">Checkout</span>
                        <span x-show="splitSubmitting"><i class="fas fa-spinner fa-spin"></i></span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Order queue panel (saved dine-in, takeaway, delivery, today history) -->
        <div x-show="showOrderQueueModal" x-cloak class="fixed left-0 right-0 bottom-0 z-[105] flex flex-col bg-black/40" style="top: var(--pos-nav-h, 3.5rem);" @keydown.escape.window="if (!showOrderDetailsModal) { showOrderQueueModal = false; activePosNav = posSessionReady ? 'pos' : ''; }">
            <div class="relative flex flex-1 flex-col min-h-0 mx-2 mb-2 mt-1 sm:mx-4 sm:mb-3 lg:mx-6 lg:mb-4 bg-white rounded-xl shadow-2xl overflow-hidden" @click.outside="handlePosPanelOutsideClick($event, 'queue')">
                <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 border-b border-gray-200 shrink-0 bg-white">
                    <div class="min-w-0 flex-1">
                        <h3 class="text-lg font-bold text-gray-900" x-text="orderQueueTitle()"></h3>
                        <p class="text-xs text-gray-500 mt-0.5" x-text="orderQueueSubtitle()"></p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 text-[11px] font-semibold order-3 w-full sm:order-none sm:w-auto" x-show="orderQueueFilteredList().length > 0 && orderQueueChannel !== 'today'">
                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-amber-100 text-amber-900">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                            <span x-text="orderQueueSummary().serving + ' serving'"></span>
                        </span>
                        <span class="text-gray-500 font-normal" x-text="orderQueueSummary().total + ' orders'"></span>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <label x-show="orderQueueChannel === 'today'" class="flex items-center gap-1.5 text-xs font-medium text-gray-600">
                            <i class="fas fa-calendar-day text-gray-400"></i>
                            <input
                                type="date"
                                x-model="orderQueueHistoryDate"
                                @change="orderQueueHistoryTypeFilter = 'all'; orderQueueHistorySearch = ''; loadOrderQueueData()"
                                :max="orderQueueHistoryMaxDate()"
                                class="rounded-lg border border-gray-200 px-2 py-1.5 text-sm text-gray-800 bg-white hover:bg-gray-50 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                title="Select date">
                        </label>
                        <button type="button" @click="loadOrderQueueData()" :disabled="orderQueueBusy" class="p-2 rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50 disabled:opacity-50" title="Refresh">
                            <i class="fas fa-sync-alt text-sm" :class="orderQueueBusy ? 'fa-spin' : ''"></i>
                        </button>
                        <button type="button" @click="showOrderQueueModal = false; activePosNav = posSessionReady ? 'pos' : ''" class="p-2 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100" aria-label="Close">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>

                <div x-show="orderQueueChannel === 'today' && !orderQueueBusy && orderQueueList.length > 0" x-cloak class="flex justify-between items-baseline px-4 py-2 border-b border-gray-100 bg-gray-50/80 shrink-0">
                    <div class="flex flex-wrap items-stretch gap-2">
                        <template x-for="typeRow in orderQueueHistoryTypeRows()" :key="typeRow.key">
                            <div class="inline-flex flex-col min-w-[7rem] flex-1 sm:flex-none px-2.5 py-1.5 rounded-lg border border-gray-200 bg-white shadow-sm">
                                <span class="text-[10px] font-semibold uppercase tracking-wide text-gray-500" x-text="resumeOrderTypeLabel(typeRow.key)"></span>
                                <span class="text-xs font-bold text-gray-900 tabular-nums" x-text="typeRow.count + (typeRow.count === 1 ? ' order' : ' orders')"></span>
                                <span class="text-xs font-bold text-indigo-700 tabular-nums" x-text="formatCurrency(typeRow.amount)"></span>
                            </div>
                        </template>
                        <div class="inline-flex flex-col min-w-[7rem] flex-1 sm:flex-none px-2.5 py-1.5 rounded-lg border-2 border-indigo-200 bg-indigo-50 shadow-sm">
                            <span class="text-[10px] font-semibold uppercase tracking-wide text-indigo-700">Total</span>
                            <span class="text-xs font-bold text-gray-900 tabular-nums" x-text="orderQueueHistoryBreakdown().total.count + (orderQueueHistoryBreakdown().total.count === 1 ? ' order' : ' orders')"></span>
                            <span class="text-xs font-bold text-indigo-700 tabular-nums" x-text="formatCurrency(orderQueueHistoryBreakdown().total.amount)"></span>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="relative shrink-0">
                            <select
                                x-model="orderQueueHistoryTypeFilter"
                                class="appearance-none rounded-lg border border-gray-200 bg-white pl-3 pr-8 py-1.5 text-sm text-gray-800 hover:bg-gray-50 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="all">All types</option>
                                <option value="dine_in">Dine in</option>
                                <option value="takeaway">Take away</option>
                                <option value="delivery">Delivery</option>
                            </select>
                            <i class="fas fa-chevron-down pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-[10px] text-gray-400"></i>
                        </div>
                        <div class="relative flex-1 min-w-[10rem]">
                            <i class="fas fa-search pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs text-gray-400"></i>
                            <input
                                type="search"
                                x-model="orderQueueHistorySearch"
                                placeholder="Search by bill no."
                                class="w-full rounded-lg border border-gray-200 bg-white pl-9 pr-3 py-1.5 text-sm text-gray-800 placeholder:text-gray-400 hover:bg-gray-50 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <button
                            type="button"
                            x-show="orderQueueHistoryTypeFilter !== 'all' || (orderQueueHistorySearch || '').trim()"
                            @click="orderQueueHistoryTypeFilter = 'all'; orderQueueHistorySearch = ''"
                            class="shrink-0 px-3 py-1.5 rounded-lg border border-gray-200 bg-white text-xs font-medium text-gray-600 hover:bg-gray-50">
                            Clear
                        </button>
                        <p class="shrink-0 ml-auto text-xs text-gray-500 whitespace-nowrap">
                            Showing <span class="font-semibold text-gray-700" x-text="orderQueueFilteredList().length"></span>
                            of <span class="font-semibold text-gray-700" x-text="orderQueueList.length"></span> orders
                        </p>
                    </div>
                </div>

                <template x-if="orderQueueBusy && orderQueueList.length === 0">
                    <div class="flex-1 flex items-center justify-center text-gray-500 p-8">
                        <div class="text-center">
                            <i class="fas fa-spinner fa-spin text-2xl mb-2"></i>
                            <p class="text-sm">Loading orders…</p>
                        </div>
                    </div>
                </template>

                <template x-if="!orderQueueBusy && orderQueueList.length === 0">
                    <div class="flex-1 flex items-center justify-center p-8">
                        <p class="text-sm text-gray-600 text-center max-w-md" x-text="orderQueueEmptyMessage()"></p>
                    </div>
                </template>

                <div x-show="orderQueueList.length > 0" class="flex flex-1 flex-col min-h-0">
                    <div class="flex-1 min-h-0 overflow-y-auto p-4">
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3">
                            <template x-for="row in orderQueueFilteredList()" :key="row.id">
                                <div
                                    class="rounded-xl border-2 p-3 flex flex-col min-h-[11.5rem] transition"
                                    :class="orderQueueCardClass(row)">
                                    <div class="flex items-start justify-between gap-1 mb-1">
                                        <div class="min-w-0">
                                            <p class="text-base font-bold text-gray-900 truncate" x-text="orderQueueCardHeading(row)"></p>
                                            <p class="text-[10px] text-gray-500 flex items-center gap-1 mt-0.5 truncate">
                                                <i class="fas text-[9px]" :class="orderQueueCardIcon(row)"></i>
                                                <span x-text="orderQueueCardMeta(row)"></span>
                                            </p>
                                        </div>
                                        <div class="shrink-0 flex flex-col items-end gap-1">
                                            <span class="text-[9px] font-bold uppercase px-1.5 py-0.5 rounded"
                                                :class="orderQueueRowBadgeClass(row)"
                                                x-text="orderQueueRowBadgeLabel(row)"></span>
                                            <button type="button"
                                                x-show="canReprintOrderQueueKot(row)"
                                                x-cloak
                                                @click.stop="reprintOrderQueueKot(row)"
                                                :disabled="orderQueueBusy || orderQueueKotReprintingId === row.id"
                                                class="text-[8px] leading-tight font-semibold uppercase px-1.5 py-0.5 rounded border border-orange-300 bg-orange-50 text-orange-800 hover:bg-orange-100 disabled:opacity-50 whitespace-nowrap touch-manipulation">
                                                <span x-show="orderQueueKotReprintingId !== row.id">KOT Re Print</span>
                                                <span x-show="orderQueueKotReprintingId === row.id"><i class="fas fa-spinner fa-spin"></i></span>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="flex-1 text-[11px] text-gray-700 space-y-0.5 mt-1 min-h-0">
                                        <p class="font-mono font-semibold text-gray-900" x-text="row.order_number"></p>
                                        <p x-show="orderQueueChannel === 'today'" class="text-indigo-700 font-medium" x-text="resumeOrderTypeLabel(row.type)"></p>
                                        <p class="text-lg font-bold text-indigo-700 tabular-nums" x-text="formatCurrency(row.total_amount)"></p>
                                        <p class="text-gray-500" x-text="(row.items_count || 0) + ' items'"></p>
                                        <p x-show="row.table?.name" class="text-gray-500 truncate" x-text="'Table ' + row.table.name"></p>
                                        <p x-show="row.waiter?.name" class="text-gray-500 truncate" x-text="'Waiter: ' + row.waiter.name"></p>
                                        <p x-show="row.delivery_rider?.name" class="text-gray-500 truncate" x-text="'Rider: ' + row.delivery_rider.name"></p>
                                        <p x-show="row.customer_name" class="text-gray-500 truncate" x-text="row.customer_name"></p>
                                        <p x-show="row.customer_phone" class="text-gray-500 truncate" x-text="row.customer_phone"></p>
                                        <p x-show="orderQueueChannel === 'delivery' && row.customer_address" class="text-gray-500 line-clamp-2" x-text="row.customer_address"></p>
                                        <p class="text-gray-400" x-text="orderQueueOrderAge(row)"></p>
                                    </div>

                                    <div class="mt-auto pt-2 border-t border-black/5 shrink-0">
                                        <div x-show="canOpenOrderQueueRow(row)" x-cloak class="grid gap-1" :class="canCancelOrderQueueRow(row) ? 'grid-cols-2' : 'grid-cols-3'">
                                            <button type="button" @click.stop="pickOrderQueueRow(row)"
                                                :disabled="orderQueueBusy"
                                                class="h-8 px-1 text-[9px] font-semibold rounded-md bg-indigo-600 text-white hover:bg-indigo-700 touch-manipulation shadow-sm disabled:opacity-50">
                                                <i class="fas fa-cash-register mr-0.5"></i>POS
                                            </button>
                                            <button type="button" @click.stop="viewOrderQueueBill(row)"
                                                class="h-8 px-1 text-[9px] font-semibold rounded-md border border-gray-300 bg-white text-gray-800 hover:bg-gray-50 touch-manipulation">
                                                <i class="fas fa-receipt mr-0.5"></i>Bill
                                            </button>
                                            <button type="button" @click.stop="viewOrderQueueDetails(row)"
                                                class="h-8 px-1 text-[9px] font-semibold rounded-md border border-gray-300 bg-white text-gray-800 hover:bg-gray-50 touch-manipulation">
                                                <i class="fas fa-list-alt mr-0.5"></i>Details
                                            </button>
                                            <button type="button"
                                                x-show="canCancelOrderQueueRow(row)"
                                                x-cloak
                                                @click.stop="cancelOrderQueueRow(row)"
                                                :disabled="orderQueueBusy || orderQueueCancellingId === row.id"
                                                class="h-8 px-1 text-[9px] font-semibold rounded-md border border-red-300 bg-red-50 text-red-800 hover:bg-red-100 touch-manipulation disabled:opacity-50">
                                                <span x-show="orderQueueCancellingId !== row.id"><i class="fas fa-ban mr-0.5"></i>Cancel</span>
                                                <span x-show="orderQueueCancellingId === row.id"><i class="fas fa-spinner fa-spin"></i></span>
                                            </button>
                                        </div>
                                        <div x-show="!canOpenOrderQueueRow(row)" x-cloak class="grid gap-1.5" :class="canCancelOrderQueueRow(row) ? 'grid-cols-3' : 'grid-cols-2'">
                                            <button type="button" @click.stop="viewOrderQueueBill(row)"
                                                class="h-8 px-1.5 text-[10px] font-semibold rounded-md border border-gray-300 bg-white text-gray-800 hover:bg-gray-50 touch-manipulation">
                                                <i class="fas fa-receipt mr-0.5"></i>Bill
                                            </button>
                                            <button type="button" @click.stop="viewOrderQueueDetails(row)"
                                                class="h-8 px-1.5 text-[10px] font-semibold rounded-md border border-gray-300 bg-white text-gray-800 hover:bg-gray-50 touch-manipulation">
                                                <i class="fas fa-list-alt mr-0.5"></i>Details
                                            </button>
                                            <button type="button"
                                                x-show="canCancelOrderQueueRow(row)"
                                                x-cloak
                                                @click.stop="cancelOrderQueueRow(row)"
                                                :disabled="orderQueueBusy || orderQueueCancellingId === row.id"
                                                class="h-8 px-1.5 text-[10px] font-semibold rounded-md border border-red-300 bg-red-50 text-red-800 hover:bg-red-100 touch-manipulation disabled:opacity-50">
                                                <span x-show="orderQueueCancellingId !== row.id"><i class="fas fa-ban mr-0.5"></i>Cancel</span>
                                                <span x-show="orderQueueCancellingId === row.id"><i class="fas fa-spinner fa-spin"></i></span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                        <p x-show="orderQueueFilteredList().length === 0 && !orderQueueBusy" class="text-sm text-gray-500 text-center py-12" x-text="orderQueueHistoryFilteredEmptyMessage()"></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kitchen queue panel (today's KOTs in generation order) -->
        <div x-show="showKitchenQueueModal" x-cloak class="fixed left-0 right-0 bottom-0 z-[105] flex flex-col bg-black/40" style="top: var(--pos-nav-h, 3.5rem);" @keydown.escape.window="if (!showOrderDetailsModal) { showKitchenQueueModal = false; activePosNav = posSessionReady ? 'pos' : ''; }">
            <div class="relative flex flex-1 flex-col min-h-0 mx-2 mb-2 mt-1 sm:mx-4 sm:mb-3 lg:mx-6 lg:mb-4 bg-white rounded-xl shadow-2xl overflow-hidden" @click.outside="handlePosPanelOutsideClick($event, 'kitchen')">
                <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 border-b border-gray-200 shrink-0 bg-white">
                    <div class="min-w-0 flex-1">
                        <h3 class="text-lg font-bold text-gray-900">Kitchen</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Pending tickets — kitchen not finished yet</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 text-[11px] font-semibold order-3 w-full sm:order-none sm:w-auto" x-show="kitchenQueueList.length > 0">
                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-orange-100 text-orange-900">
                            <span class="w-1.5 h-1.5 rounded-full bg-orange-500"></span>
                            <span x-text="kitchenQueueList.length + (kitchenQueueList.length === 1 ? ' KOT in line' : ' KOTs in line')"></span>
                        </span>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <button type="button" @click="loadKitchenQueueData()" :disabled="kitchenQueueBusy" class="p-2 rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50 disabled:opacity-50" title="Refresh">
                            <i class="fas fa-sync-alt text-sm" :class="kitchenQueueBusy ? 'fa-spin' : ''"></i>
                        </button>
                        <button type="button" @click="showKitchenQueueModal = false; activePosNav = posSessionReady ? 'pos' : ''" class="p-2 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100" aria-label="Close">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>

                <template x-if="kitchenQueueBusy && kitchenQueueList.length === 0">
                    <div class="flex-1 flex items-center justify-center text-gray-500 p-8">
                        <div class="text-center">
                            <i class="fas fa-spinner fa-spin text-2xl mb-2"></i>
                            <p class="text-sm">Loading kitchen queue…</p>
                        </div>
                    </div>
                </template>

                <template x-if="!kitchenQueueBusy && kitchenQueueList.length === 0">
                    <div class="flex-1 flex items-center justify-center p-8">
                        <p class="text-sm text-gray-600 text-center max-w-md">No tickets waiting in kitchen right now.</p>
                    </div>
                </template>

                <div x-show="kitchenQueueList.length > 0" class="flex flex-1 flex-col min-h-0">
                    <div class="flex-1 min-h-0 overflow-y-auto p-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                            <template x-for="kot in kitchenQueueList" :key="'kitchen-kot-' + kot.id">
                                <div class="rounded-xl border-2 border-orange-200 bg-orange-50/40 hover:border-orange-300 p-3 flex flex-col min-h-[9.5rem] transition">
                                    <div class="flex items-start justify-between gap-2 mb-1">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-gray-900 text-white text-[11px] font-bold shrink-0" x-text="'#' + kot.queue_position"></span>
                                            <div class="min-w-0">
                                                <p class="text-sm font-bold text-gray-900 leading-tight">
                                                    KOT <span x-text="kot.kot_number"></span>
                                                    · T<span x-text="kot.token_number"></span>
                                                </p>
                                                <p class="text-[10px] text-gray-500 font-mono truncate" x-text="kot.order?.order_number || '—'"></p>
                                            </div>
                                        </div>
                                        <span class="text-[9px] font-bold uppercase px-1.5 py-0.5 rounded shrink-0"
                                            :class="kitchenQueueTypeBadgeClass(kot)"
                                            x-text="kot.type_label"></span>
                                    </div>

                                    <div class="flex-1 text-[11px] text-gray-700 space-y-0.5 mt-1 min-h-0">
                                        <p class="text-indigo-700 font-medium" x-text="resumeOrderTypeLabel(kot.order?.type)"></p>
                                        <p x-show="kot.order?.table?.name" class="text-gray-600 truncate" x-text="'Table ' + kot.order.table.name"></p>
                                        <p x-show="kot.order?.waiter?.name" class="text-gray-600 truncate" x-text="'Waiter: ' + kot.order.waiter.name"></p>
                                        <p x-show="kot.order?.customer_name" class="text-gray-600 truncate" x-text="kot.order.customer_name"></p>
                                        <p class="text-gray-800 leading-snug line-clamp-3" x-text="kot.lines_summary || '—'"></p>
                                    </div>

                                    <div class="mt-auto pt-2 border-t border-black/5 flex items-center justify-between gap-2 text-[10px] text-gray-500 shrink-0">
                                        <span x-text="kot.printed_at_display || '—'"></span>
                                        <span x-show="kot.printed_by_name" class="truncate" x-text="kot.printed_by_name"></span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Order details modal (queue / history) — close only via explicit Close / × -->
        <div x-show="showOrderDetailsModal" x-cloak class="fixed inset-0 z-[110] overflow-y-auto bg-black/50" @click.stop>
            <div class="flex min-h-screen items-center justify-center p-3 sm:p-4">
                <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl max-h-[92vh] flex flex-col overflow-hidden">
                    <div class="flex items-center justify-between px-4 py-2.5 border-b border-gray-200 shrink-0">
                        <div class="min-w-0">
                            <h3 class="text-base font-bold text-gray-900">Order details</h3>
                            <p class="text-xs sm:text-sm text-gray-500 font-mono truncate" x-show="orderDetailsData?.order_number" x-text="orderDetailsData.order_number"></p>
                        </div>
                        <button type="button" @click="closeOrderDetailsModal()" class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 shrink-0" aria-label="Close">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <div class="flex-1 overflow-y-auto px-4 py-3">
                        <template x-if="loadingOrderDetails">
                            <div class="py-12 text-center text-gray-500">
                                <i class="fas fa-spinner fa-spin text-xl mb-2"></i>
                                <p class="text-sm">Loading order…</p>
                            </div>
                        </template>

                        <template x-if="!loadingOrderDetails && orderDetailsData">
                            <div>
                                <div class="text-xs sm:text-sm text-gray-800 space-y-1.5 pb-3 border-b border-gray-100">
                                    <div class="flex justify-between items-start gap-4">
                                        <span class="min-w-0"><span class="font-semibold text-gray-600">Order type:</span> <span x-text="orderDetailsTypeLabel(orderDetailsData)"></span></span>
                                        <span class="min-w-0 text-right"><span class="font-semibold text-gray-600">Status:</span> <span x-text="orderDetailsStatusLabel(orderDetailsData)"></span></span>
                                    </div>
                                    <div class="flex justify-between items-start gap-4">
                                        <span class="min-w-0"><span class="font-semibold text-gray-600">Waiter:</span> <span x-text="orderDetailsData.waiter?.name || '—'"></span></span>
                                        <span class="min-w-0 text-right"><span class="font-semibold text-gray-600">Cashier:</span> <span x-text="orderDetailsData.cashier?.name || '—'"></span></span>
                                    </div>
                                    <div class="flex justify-between items-start gap-4">
                                        <span class="min-w-0"><span class="font-semibold text-gray-600">Customer:</span> <span x-text="orderDetailsCustomerLabel(orderDetailsData)"></span></span>
                                        <span class="min-w-0 text-right"><span class="font-semibold text-gray-600">Table:</span> <span x-text="orderDetailsData.table?.name || '—'"></span></span>
                                    </div>
                                    <div x-show="orderDetailsData.delivery_rider?.name" class="flex justify-between items-start gap-4">
                                        <span class="min-w-0"><span class="font-semibold text-gray-600">Rider:</span> <span x-text="orderDetailsData.delivery_rider.name"></span></span>
                                    </div>
                                    <div x-show="orderDetailsData.customer_phone || orderDetailsData.customer_address" class="text-gray-600 min-w-0">
                                        <span x-show="orderDetailsData.customer_phone" x-text="orderDetailsData.customer_phone"></span>
                                        <span x-show="orderDetailsData.customer_phone && orderDetailsData.customer_address"> · </span>
                                        <span x-show="orderDetailsData.customer_address" x-text="orderDetailsData.customer_address"></span>
                                    </div>
                                    <div class="flex justify-end">
                                        <span class="text-gray-500" x-text="orderDetailsDateTime(orderDetailsData.created_at)"></span>
                                    </div>
                                </div>

                                <div class="mt-3 overflow-x-auto border border-gray-200 rounded-md">
                                    <table class="min-w-full text-xs sm:text-sm">
                                        <thead class="bg-gray-50 border-b border-gray-200">
                                            <tr>
                                                <th class="px-3 py-1.5 text-left font-semibold text-gray-600">Item</th>
                                                <th class="px-3 py-1.5 text-right font-semibold text-gray-600 w-20">Price</th>
                                                <th class="px-3 py-1.5 text-right font-semibold text-gray-600 w-12">Qty</th>
                                                <th class="px-3 py-1.5 text-right font-semibold text-gray-600 w-24">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100">
                                            <template x-for="(item, idx) in (orderDetailsData.items || [])" :key="'od-item-' + idx">
                                                <tr class="hover:bg-gray-50/50">
                                                    <td class="px-3 py-1.5 text-gray-900">
                                                        <span x-text="item.item_name || 'Item'"></span>
                                                        <span x-show="item.deal_id" class="text-[10px] text-gray-500"> (deal)</span>
                                                        <span x-show="item.special_instructions" class="block text-[10px] text-gray-500" x-text="'Note: ' + item.special_instructions"></span>
                                                    </td>
                                                    <td class="px-3 py-1.5 text-right tabular-nums text-gray-700" x-text="formatCurrency(parseFloat(item.unit_price) || 0)"></td>
                                                    <td class="px-3 py-1.5 text-right tabular-nums text-gray-700" x-text="item.quantity"></td>
                                                    <td class="px-3 py-1.5 text-right tabular-nums font-medium text-gray-900" x-text="formatCurrency(parseFloat(item.total_price) || 0)"></td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="mt-3 pt-2 border-t border-gray-100 text-xs sm:text-sm">
                                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-4 gap-y-1 text-gray-700">
                                        <div><span class="text-gray-500">Total items:</span> <span class="font-medium" x-text="orderDetailsItemCount(orderDetailsData)"></span></div>
                                        <div><span class="text-gray-500">Subtotal:</span> <span class="font-medium tabular-nums" x-text="formatCurrency(parseFloat(orderDetailsData.subtotal) || 0)"></span></div>
                                        <div><span class="text-gray-500">Discount:</span> <span class="font-medium tabular-nums" x-text="formatCurrency(parseFloat(orderDetailsData.discount_amount) || 0)"></span></div>
                                        <div x-show="parseFloat(orderDetailsData.tax_amount) > 0"><span class="text-gray-500">Tax:</span> <span class="font-medium tabular-nums" x-text="formatCurrency(parseFloat(orderDetailsData.tax_amount))"></span></div>
                                        <div x-show="parseFloat(orderDetailsData.service_charge) > 0"><span class="text-gray-500">Service:</span> <span class="font-medium tabular-nums" x-text="formatCurrency(parseFloat(orderDetailsData.service_charge))"></span></div>
                                        <div x-show="parseFloat(orderDetailsData.delivery_fee) > 0"><span class="text-gray-500">Delivery:</span> <span class="font-medium tabular-nums" x-text="formatCurrency(parseFloat(orderDetailsData.delivery_fee))"></span></div>
                                        <div><span class="text-gray-500">Payment:</span> <span class="font-medium" x-text="orderDetailsPaymentLabel(orderDetailsData)"></span></div>
                                        <div x-show="parseFloat(orderDetailsData.paid_amount) > 0"><span class="text-gray-500">Paid:</span> <span class="font-medium tabular-nums" x-text="formatCurrency(parseFloat(orderDetailsData.paid_amount))"></span></div>
                                        <div x-show="orderDetailsData.money_source?.name && orderDetailsData.payment_method !== 'split'"><span class="text-gray-500">Source:</span> <span class="font-medium" x-text="orderDetailsData.money_source.name"></span></div>
                                    </div>
                                    <template x-if="orderDetailsData.payment_method === 'split' && orderDetailsData.payments?.length">
                                        <div class="mt-1.5 flex flex-wrap gap-1.5">
                                            <template x-for="(payment, pIdx) in orderDetailsData.payments" :key="'od-pay-' + pIdx">
                                                <span class="inline-flex items-center gap-1 rounded bg-gray-100 px-2 py-0.5 text-[11px] text-gray-700">
                                                    <span x-text="payment.money_source?.name || 'Source'"></span>
                                                    <span class="font-semibold tabular-nums" x-text="formatCurrency(parseFloat(payment.amount) || 0)"></span>
                                                </span>
                                            </template>
                                        </div>
                                    </template>
                                    <div class="mt-2 py-2 px-3 bg-gray-100 rounded-md text-center">
                                        <span class="text-sm sm:text-base font-bold text-gray-900">Total payable</span>
                                        <span class="text-sm sm:text-base font-bold text-indigo-700 tabular-nums ml-2" x-text="formatCurrency(parseFloat(orderDetailsData.total_amount) || 0)"></span>
                                    </div>
                                </div>

                                <template x-if="orderDetailsData.kitchen_kots?.length">
                                    <div class="mt-3 pt-2 border-t border-gray-100 space-y-2">
                                        <div x-show="orderDetailsData.kitchen_sync?.sent_items?.length"
                                             class="rounded-md border px-2.5 py-2"
                                             :class="orderDetailsData.kitchen_sync?.in_sync ? 'border-emerald-200 bg-emerald-50/80' : 'border-amber-200 bg-amber-50/80'">
                                            <p class="text-[11px] font-semibold uppercase tracking-wide"
                                               :class="orderDetailsData.kitchen_sync?.in_sync ? 'text-emerald-800' : 'text-amber-800'">
                                                Currently sent to kitchen
                                            </p>
                                            <p class="mt-0.5 text-xs sm:text-sm font-medium text-gray-900" x-text="orderDetailsKitchenSentLabel(orderDetailsData)"></p>
                                            <p x-show="!orderDetailsData.kitchen_sync?.in_sync"
                                               class="mt-1 text-[11px] text-amber-900 leading-snug">
                                                The bill was changed after the last KOT. Send KOT again so the kitchen gets the update.
                                            </p>
                                        </div>
                                        <div>
                                            <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                                                Kitchen ticket history
                                                <span class="text-gray-400 font-normal normal-case" x-text="'(' + (orderDetailsData.kitchen_kots?.length || 0) + ')'"></span>
                                            </p>
                                            <p class="text-[10px] text-gray-500 mt-0.5 leading-snug">
                                                Every time staff taps KOT, a ticket is printed. Earlier tickets may include items that were later voided or changed.
                                            </p>
                                        </div>
                                        <div class="grid gap-1.5 sm:grid-cols-2">
                                            <template x-for="(kot, kIdx) in orderDetailsData.kitchen_kots" :key="'od-kot-' + kIdx">
                                                <div class="rounded border border-orange-200 bg-orange-50/80 px-2.5 py-1.5 text-[11px] sm:text-xs">
                                                    <p class="font-semibold text-gray-900 leading-tight">
                                                        KOT #<span x-text="kot.kot_number"></span>
                                                        · T#<span x-text="kot.token_number"></span>
                                                        · <span x-text="kot.type_label"></span>
                                                        <span x-show="kot.is_reprint" class="text-orange-700">(reprint)</span>
                                                    </p>
                                                    <p class="mt-0.5 text-gray-700 leading-snug" x-text="orderDetailsKotLines(kot)"></p>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </template>

                                <p x-show="orderDetailsData.notes" class="mt-2 pt-2 border-t border-gray-100 text-xs text-gray-600">
                                    <span class="font-semibold text-gray-500">Notes:</span>
                                    <span class="whitespace-pre-wrap" x-text="orderDetailsData.notes"></span>
                                </p>
                            </div>
                        </template>
                    </div>

                    <div class="flex justify-end gap-2 px-4 py-2.5 border-t border-gray-200 shrink-0 bg-gray-50/80">
                        <button type="button" x-show="orderDetailsData?.id" @click="viewOrderQueueBill({ id: orderDetailsData.id })" class="btn-form-primary text-sm py-2 px-3">
                            <i class="fas fa-receipt mr-1"></i> Bill
                        </button>
                        <button type="button" @click="closeOrderDetailsModal()" class="btn-form-secondary text-sm py-2 px-3">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Table view (panel below top nav) -->
        <div x-show="showTableViewModal" x-cloak class="fixed left-0 right-0 bottom-0 z-[105] flex flex-col bg-black/40" style="top: var(--pos-nav-h, 3.5rem);" @keydown.escape.window="showTableViewModal = false; activePosNav = posSessionReady ? 'pos' : ''">
            <div class="relative flex flex-1 flex-col min-h-0 mx-2 mb-2 mt-1 sm:mx-4 sm:mb-3 lg:mx-6 lg:mb-4 bg-white rounded-xl shadow-2xl overflow-hidden" @click.outside="handlePosPanelOutsideClick($event, 'table')">
                <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 border-b border-gray-200 shrink-0 bg-white">
                    <div class="min-w-0 flex-1">
                        <h3 class="text-lg font-bold text-gray-900">Table view</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Seat guests · check bills · open tabs in POS</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 text-[11px] font-semibold order-3 w-full sm:order-none sm:w-auto" x-show="tableViewSummary">
                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-emerald-100 text-emerald-800">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                            <span x-text="(tableViewSummary?.available || 0) + ' free'"></span>
                        </span>
                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-amber-100 text-amber-900">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                            <span x-text="(tableViewSummary?.open_tab || 0) + ' serving'"></span>
                        </span>
                        <span class="text-gray-500 font-normal" x-text="(tableViewSummary?.total || 0) + ' tables'"></span>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <button type="button" @click="loadTableView()" :disabled="tableViewLoading" class="p-2 rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50 disabled:opacity-50" title="Refresh">
                            <i class="fas fa-sync-alt text-sm" :class="tableViewLoading ? 'fa-spin' : ''"></i>
                        </button>
                        <button type="button" @click="showTableViewModal = false; activePosNav = posSessionReady ? 'pos' : ''" class="p-2 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100" aria-label="Close">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>

                <template x-if="tableViewLoading && tableViewFloors.length === 0">
                    <div class="flex-1 flex items-center justify-center text-gray-500 p-8">
                        <div class="text-center">
                            <i class="fas fa-spinner fa-spin text-2xl mb-2"></i>
                            <p class="text-sm">Loading tables…</p>
                        </div>
                    </div>
                </template>

                <template x-if="!tableViewLoading && tableViewFloors.length === 0">
                    <div class="flex-1 flex items-center justify-center p-8">
                        <p class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-4 max-w-md text-center">
                            No floors or tables for this branch. Add them under Administration → Floors / Tables.
                        </p>
                    </div>
                </template>

                <div x-show="tableViewFloors.length > 0" class="flex flex-1 flex-col min-h-0">
                    <div class="shrink-0 px-4 pt-3 border-b border-gray-100 bg-gray-50/50">
                        <div class="flex gap-1 overflow-x-auto pb-0" role="tablist" aria-label="Floors">
                            <template x-for="floor in tableViewFloors" :key="floor.id === null || floor.id === undefined ? 'none' : floor.id">
                                <button
                                    type="button"
                                    role="tab"
                                    :aria-selected="String(tableViewFloorId) === String(tableViewFloorKey(floor))"
                                    @click="tableViewFloorId = tableViewFloorKey(floor)"
                                    class="px-4 py-2 text-sm font-semibold rounded-t-lg border-b-2 -mb-px transition whitespace-nowrap touch-manipulation"
                                    :class="String(tableViewFloorId) === String(tableViewFloorKey(floor))
                                        ? 'border-indigo-600 text-indigo-800 bg-white'
                                        : 'border-transparent text-gray-600 hover:text-gray-900 hover:border-gray-300'">
                                    <span x-text="floor.name"></span>
                                    <span class="text-gray-400 font-normal ml-1" x-text="'(' + (floor.tables?.length || 0) + ')'"></span>
                                </button>
                            </template>
                        </div>
                    </div>

                    <div class="flex-1 min-h-0 overflow-y-auto p-4">
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3">
                            <template x-for="tbl in tablesForTableViewFloor()" :key="tbl.id">
                                <div
                                    class="rounded-xl border-2 p-3 flex flex-col min-h-[11.5rem] transition"
                                    :class="tableViewCardClass(tbl)">
                                    <div class="flex items-start justify-between gap-1 mb-1">
                                        <div class="min-w-0">
                                            <p class="text-base font-bold text-gray-900 truncate" x-text="tbl.name"></p>
                                            <p class="text-[10px] text-gray-500 flex items-center gap-1 mt-0.5">
                                                <i class="fas fa-users text-[9px]"></i>
                                                <span x-text="tbl.capacity + ' seats'"></span>
                                            </p>
                                        </div>
                                        <div class="shrink-0 flex flex-col items-end gap-1">
                                            <span class="text-[9px] font-bold uppercase px-1.5 py-0.5 rounded"
                                                :class="tableViewStatusBadgeClass(tbl)"
                                                x-text="tableViewStatusLabel(tbl)"></span>
                                            <button type="button"
                                                x-show="canReprintOrderQueueKot(tbl.open_order)"
                                                x-cloak
                                                @click.stop="reprintOrderQueueKot(tbl.open_order)"
                                                :disabled="tableViewLoading || orderQueueKotReprintingId === tbl.open_order?.id"
                                                class="text-[8px] leading-tight font-semibold uppercase px-1.5 py-0.5 rounded border border-orange-300 bg-orange-50 text-orange-800 hover:bg-orange-100 disabled:opacity-50 whitespace-nowrap touch-manipulation">
                                                <span x-show="orderQueueKotReprintingId !== tbl.open_order?.id">KOT Re Print</span>
                                                <span x-show="orderQueueKotReprintingId === tbl.open_order?.id"><i class="fas fa-spinner fa-spin"></i></span>
                                            </button>
                                        </div>
                                    </div>

                                    <template x-if="tbl.open_order">
                                        <div class="flex-1 text-[11px] text-gray-700 space-y-0.5 mt-1 min-h-0">
                                            <p class="font-mono font-semibold text-gray-900" x-text="tbl.open_order.order_number"></p>
                                            <p class="text-lg font-bold text-indigo-700 tabular-nums" x-text="formatCurrency(tbl.open_order.total_amount)"></p>
                                            <p class="text-gray-500" x-text="(tbl.open_order.items_count || 0) + ' items'"></p>
                                            <p x-show="tbl.open_order.waiter?.name" class="text-gray-500 truncate" x-text="'Waiter: ' + tbl.open_order.waiter.name"></p>
                                            <p x-show="tbl.open_order.customer_name" class="text-gray-500 truncate" x-text="tbl.open_order.customer_name"></p>
                                            <p class="text-gray-400" x-text="tableViewOrderAge(tbl.open_order)"></p>
                                        </div>
                                    </template>
                                    <p x-show="!tbl.open_order" x-cloak class="flex-1 text-[11px] text-gray-500 mt-2" x-text="tbl.status === 'available' ? 'Ready for guests' : (tbl.status === 'reserved' ? 'Reserved' : (tbl.status === 'dirty' ? 'Needs cleaning' : 'Unavailable'))"></p>

                                    <div class="mt-auto pt-2 border-t border-black/5 shrink-0">
                                        <div x-show="tbl.open_order" x-cloak class="grid gap-1.5" :class="canCancelOrderQueueRow(tbl.open_order) ? 'grid-cols-3' : 'grid-cols-2'">
                                            <button type="button" @click.stop="openTableOrderInPos(tbl)"
                                                class="h-8 px-1.5 text-[10px] font-semibold rounded-md bg-indigo-600 text-white hover:bg-indigo-700 touch-manipulation shadow-sm">
                                                <i class="fas fa-cash-register mr-0.5"></i>POS
                                            </button>
                                            <button type="button" @click.stop="viewTableBill(tbl)"
                                                class="h-8 px-1.5 text-[10px] font-semibold rounded-md border border-gray-300 bg-white text-gray-800 hover:bg-gray-50 touch-manipulation">
                                                <i class="fas fa-receipt mr-0.5"></i>Bill
                                            </button>
                                            <button type="button"
                                                x-show="canCancelOrderQueueRow(tbl.open_order)"
                                                x-cloak
                                                @click.stop="cancelOrderQueueRow(tbl.open_order)"
                                                :disabled="tableViewLoading || orderQueueCancellingId === tbl.open_order?.id"
                                                class="h-8 px-1.5 text-[10px] font-semibold rounded-md border border-red-300 bg-red-50 text-red-800 hover:bg-red-100 touch-manipulation disabled:opacity-50">
                                                <span x-show="orderQueueCancellingId !== tbl.open_order?.id"><i class="fas fa-ban mr-0.5"></i>Cancel</span>
                                                <span x-show="orderQueueCancellingId === tbl.open_order?.id"><i class="fas fa-spinner fa-spin"></i></span>
                                            </button>
                                        </div>
                                        <button x-show="!tbl.open_order && tableViewCanSeat(tbl)" x-cloak type="button" @click.stop="seatAtTable(tbl)"
                                            class="w-full h-8 px-2 text-[11px] font-semibold rounded-md bg-emerald-600 text-white hover:bg-emerald-700 touch-manipulation">
                                            <i class="fas fa-chair mr-1"></i>Seat guests
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>
                        <p x-show="tablesForTableViewFloor().length === 0" class="text-sm text-gray-500 text-center py-12">No tables on this floor.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main: mobile = column [browse | checkout | cart]; lg = grid [browse+checkout | cart] or [browse | cart+actions] -->
        <div x-show="posSessionReady" x-cloak class="flex-1 flex flex-col min-h-0 min-w-0 overflow-hidden {{ $posLayoutConfig['main_shell_grid'] }}" data-pos-layout="{{ $posLayoutConfig['layout'] }}">
            <!-- Browse: categories + menu (hidden below lg when Order tab) -->
            <div
                class="flex flex-1 flex-col min-w-0 min-h-0 overflow-hidden {{ $posLayoutConfig['browse_column'] }} lg:min-h-0 lg:flex lg:flex-col"
                :class="!isLargeScreen && posTab === 'order' ? 'hidden' : ''">
                <div class="flex-shrink-0 bg-white border-b border-gray-200 px-2 sm:px-3 py-1.5 sm:py-2 shadow-sm z-10">
                    <div class="{{ $categoryBar['container'] }}">
                        <button
                            type="button"
                            @click="showDealsSection = true; selectedCategoryId = 'deals'; loadDeals()"
                            :class="selectedCategoryId === 'deals' ? 'bg-amber-500 text-white shadow-sm' : 'bg-amber-50 text-amber-800 hover:bg-amber-100 border border-amber-200'"
                            class="{{ $categoryBar['button'] }}">
                            <i class="fas fa-tags {{ $categoryBar['icon_margin'] }}"></i>Deals
                        </button>
                        <button
                            type="button"
                            @click="showDealsSection = false; selectedCategoryId = null"
                            :class="selectedCategoryId === null ? 'bg-indigo-600 text-white shadow-sm' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 border border-gray-200'"
                            class="{{ $categoryBar['button'] }}">
                            <i class="fas fa-th {{ $categoryBar['icon_margin'] }}"></i>All Items
                        </button>
                        @foreach($categories as $category)
                            @if(isset($categoryFilterMap[$category->id]))
                                <button
                                    type="button"
                                    @click="showDealsSection = false; selectedCategoryId = {{ $category->id }}"
                                    :class="selectedCategoryId === {{ $category->id }} ? 'bg-indigo-600 text-white shadow-sm' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 border border-gray-200'"
                                    class="{{ $categoryBar['button'] }}">
                                    <i class="fas fa-folder {{ $categoryBar['icon_margin'] }}"></i>{{ $category->displayLabel() }}
                                </button>
                            @endif
                            @foreach($category->children as $child)
                                <button
                                    type="button"
                                    @click="showDealsSection = false; selectedCategoryId = {{ $child->id }}"
                                    :class="selectedCategoryId === {{ $child->id }} ? 'bg-indigo-500 text-white shadow-sm' : 'bg-gray-50 text-gray-600 hover:bg-gray-100 border border-gray-200'"
                                    class="{{ $categoryBar['button'] }}">
                                    <span class="text-gray-400 mr-0.5">↳</span>{{ $child->displayLabel() }}
                                </button>
                            @endforeach
                        @endforeach
                        @foreach($orphanCategories ?? [] as $category)
                            <button
                                type="button"
                                @click="showDealsSection = false; selectedCategoryId = {{ $category->id }}"
                                :class="selectedCategoryId === {{ $category->id }} ? 'bg-indigo-600 text-white shadow-sm' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 border border-gray-200'"
                                class="{{ $categoryBar['button'] }}">
                                <i class="fas fa-folder {{ $categoryBar['icon_margin'] }}"></i>{{ $category->displayLabel() }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto bg-gray-50 p-3 sm:p-4 lg:p-6 min-h-0 overscroll-contain">
                <!-- Deals Section (when Deals is selected) -->
                <div x-show="showDealsSection" class="mb-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Active Deals</h2>
                    <div x-show="dealsLoading" class="text-center py-12">
                        <i class="fas fa-spinner fa-spin text-4xl text-amber-500 mb-3"></i>
                        <p class="text-gray-500">Loading deals...</p>
                    </div>
                    <div :class="posProductGrid.grid" x-show="!dealsLoading && dealsJson && dealsJson.length > 0">
                        <template x-for="deal in dealsJson" :key="deal.id">
                            <div @click="addDealToCart(deal)" :class="posProductGrid.deal_card">
                                <div :class="posProductGrid.image">
                                    <template x-if="deal.image">
                                        <img :src="deal.image" :alt="deal.title" class="w-full h-full object-cover">
                                    </template>
                                    <template x-if="!deal.image">
                                        <div class="w-full h-full flex items-center justify-center bg-amber-50">
                                            <i class="fas fa-tags text-4xl text-amber-400"></i>
                                        </div>
                                    </template>
                                    <span :class="posProductGrid.deal_price_badge" x-text="formatCurrency(deal.price)"></span>
                                </div>
                                <h3 :class="posProductGrid.title" x-text="deal.title"></h3>
                            </div>
                        </template>
                    </div>
                    <div x-show="!dealsLoading && (!dealsJson || dealsJson.length === 0)" class="text-center py-12">
                        <i class="fas fa-tags text-6xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500">No active deals at the moment</p>
                    </div>
                </div>

                <!-- Menu Items Section -->
                <div x-show="!showDealsSection">
                <!-- Search Bar -->
                <div class="mb-6">
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-search text-gray-400"></i>
                        </div>
                        <input 
                            type="text" 
                            x-model="searchTerm"
                            @input="handleSearch()"
                            @keydown.enter.prevent="handleBarcodeSearch()"
                            placeholder="Search by name or scan barcode..."
                            class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                            autofocus>
                        <div x-show="searchTerm" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                            <button @click="searchTerm = ''; handleSearch()" class="text-gray-400 hover:text-gray-600">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-gray-500" x-show="searchTerm">
                        <span x-text="filteredMenuItems.length"></span> item(s) found
                    </p>
                </div>
                
                <div :class="posProductGrid.grid" x-show="filteredMenuItems.length > 0">
                    <template x-for="item in filteredMenuItems" :key="item.id">
                        <div @click="handleProductClick(item)" :class="posProductGrid.card">
                            <div :class="posProductGrid.image">
                                <template x-if="item.image">
                                    <img :src="item.image" :alt="item.name" class="w-full h-full object-cover">
                                </template>
                                <template x-if="!item.image">
                                    <div class="w-full h-full flex items-center justify-center">
                                        <i class="fas fa-utensils text-4xl text-gray-300"></i>
                                    </div>
                                </template>
                                <span
                                    :class="posProductGrid.price_badge"
                                    x-text="(item.has_variants || hasVariantOptions(item)) ? ('From ' + formatCurrency(item.price)) : formatCurrency(item.price)"></span>
                            </div>
                            <h3 :class="posProductGrid.title" x-text="item.name"></h3>
                        </div>
                    </template>
                </div>
                <div x-show="filteredMenuItems.length === 0" class="text-center py-12">
                    <i class="fas fa-utensils text-6xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500">No items found in this category</p>
                </div>
                </div>
                </div>
            </div>

            <!-- Checkout bar (classic layout on desktop; fixed bottom on mobile) -->
            <div
                class="flex-shrink-0 bg-white border-t border-gray-200 shadow-[0_-2px_4px_-1px_rgba(0,0,0,0.06)] pos-safe-bottom lg:col-start-1 lg:row-start-2 lg:relative lg:z-20"
                x-show="!isLargeScreen || !posLayout.uses_sidebar_checkout"
                :class="!isLargeScreen && posTab === 'order' ? 'fixed bottom-0 left-0 right-0 z-40 shadow-[0_-4px_14px_-2px_rgba(0,0,0,0.12)]' : 'relative z-20'">
                @include('pos.partials.fulfillment-checkout', ['placement' => 'bar'])
            </div>

            <!-- Cart: full-height on small Order tab; sidebar from lg -->
            <div
                class="flex flex-col min-h-0 min-w-0 w-full max-w-full border-t border-gray-200 lg:border-t-0 lg:border-l lg:border-gray-200 bg-white {{ $posLayoutConfig['cart_column'] }} lg:h-auto lg:flex"
                :class="{
                    'hidden': !isLargeScreen && posTab === 'products',
                    'flex-1': !isLargeScreen && posTab === 'order',
                    'pb-24 lg:pb-0': !isLargeScreen && posTab === 'order' && !posLayout.uses_sidebar_checkout,
                    'pb-28 lg:pb-0': !isLargeScreen && posTab === 'order' && posLayout.uses_sidebar_checkout,
                }">
                @include('pos.partials.order-context-bar', ['orderContextStyle' => $posLayoutConfig['order_context_style'] ?? \App\Support\PosLayout::ORDER_CONTEXT_LABELED])

                <!-- Cart Header -->
                <div class="px-2 py-1.5 border-b border-gray-200 shrink-0 {{ $usesCompactOrderContext ? '' : 'space-y-1.5' }}">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-sm font-semibold text-gray-900 truncate">Order summary</h2>
                        <span class="text-[10px] text-gray-500 shrink-0 tabular-nums" x-text="cart.length + ' item(s)'"></span>
                    </div>
                    @unless ($usesCompactOrderContext)
                        @include('pos.partials.order-context-kitchen-bar')
                    @endunless
                </div>

                <!-- Cart Items (uses remaining height) -->
                <div class="flex-1 min-h-0 overflow-y-auto overscroll-contain p-2 space-y-1.5 touch-pan-y">
                    <template x-for="(item, index) in cart" :key="index">
                        <div class="bg-gray-50 rounded-md p-2 border border-gray-200">
                            <div class="flex items-start justify-between gap-1 mb-1">
                                <div class="flex-1 min-w-0">
                                    <h4 class="text-xs font-semibold text-gray-900 leading-tight" x-text="item.name"></h4>
                                    <template x-if="item.variants && (item.variants.option_name || item.variants.variant_name)">
                                        <p class="text-[10px] text-indigo-600 font-medium leading-tight" x-text="(item.variants.variant_name ? item.variants.variant_name + ': ' : '') + (item.variants.option_name || item.variants.variant_name || '')"></p>
                                    </template>
                                    <template x-if="item.addons && item.addons.length">
                                        <p class="text-[10px] text-amber-700 font-medium leading-tight" x-text="addonsLabel(item.addons)"></p>
                                    </template>
                                    <p x-show="hasItemNote(item) && itemNoteEditorIndex !== index" class="text-[10px] text-amber-800 italic leading-tight truncate" x-text="item.special_instructions"></p>
                                </div>
                                <button @click="removeFromCart(index)" class="text-red-500 hover:text-red-700 shrink-0 p-0.5">
                                    <i class="fas fa-times text-[10px]"></i>
                                </button>
                            </div>
                            <div class="flex items-center justify-between gap-2 mb-1">
                                <div class="flex items-center gap-1">
                                    <button @click="updateQuantity(index, item.quantity - 1)" class="w-6 h-6 rounded bg-gray-200 hover:bg-gray-300 flex items-center justify-center">
                                        <i class="fas fa-minus text-[9px]"></i>
                                    </button>
                                    <input 
                                        type="number" 
                                        x-model="item.quantity" 
                                        @change="updateCartItem(index)"
                                        min="0.01"
                                        step="0.01"
                                        class="w-12 text-center border border-gray-300 rounded py-0.5 text-xs">
                                    <button @click="updateQuantity(index, item.quantity + 1)" class="w-6 h-6 rounded bg-gray-200 hover:bg-gray-300 flex items-center justify-center">
                                        <i class="fas fa-plus text-[9px]"></i>
                                    </button>
                                    <button
                                        type="button"
                                        @click="toggleItemNote(index)"
                                        :class="hasItemNote(item) ? 'text-amber-700 bg-amber-50 border-amber-300' : (itemNoteEditorIndex === index ? 'text-indigo-700 bg-indigo-50 border-indigo-300' : 'text-gray-500 bg-white border-gray-200 hover:bg-gray-50')"
                                        class="w-6 h-6 rounded border flex items-center justify-center touch-manipulation"
                                        :title="hasItemNote(item) ? 'Edit note' : 'Add note'">
                                        <i class="fas fa-sticky-note text-[9px]"></i>
                                    </button>
                                    <button
                                        type="button"
                                        x-show="menuItemHasAddons(item.menu_item_id)"
                                        @click="openAddonPicker(null, index)"
                                        class="w-6 h-6 rounded border flex items-center justify-center touch-manipulation text-amber-700 bg-amber-50 border-amber-300 hover:bg-amber-100"
                                        title="Edit extras">
                                        <i class="fas fa-plus-circle text-[9px]"></i>
                                    </button>
                                </div>
                                <span class="text-xs font-semibold text-gray-900 tabular-nums" x-text="formatCurrency(item.quantity * item.unit_price)"></span>
                            </div>
                            <div x-show="itemNoteEditorIndex === index" x-cloak class="mt-1.5 space-y-1">
                                <input
                                    type="text"
                                    :id="'item-note-input-' + index"
                                    x-model="item.special_instructions"
                                    @keydown.enter.prevent="closeItemNote()"
                                    @keydown.escape.prevent="closeItemNote()"
                                    placeholder="e.g. spicy, no onion"
                                    class="w-full px-1.5 py-1 text-[10px] border border-indigo-300 rounded bg-white focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500">
                                <div class="flex items-center justify-end gap-1.5">
                                    <button
                                        type="button"
                                        x-show="hasItemNote(item)"
                                        @click="clearItemNote(index)"
                                        class="text-[10px] font-medium text-red-600 hover:text-red-800 px-1">
                                        Clear
                                    </button>
                                    <button
                                        type="button"
                                        @click="closeItemNote()"
                                        class="text-[10px] font-semibold text-indigo-700 hover:text-indigo-900 px-1">
                                        Done
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>
                    <div x-show="cart.length === 0" class="text-center py-12">
                        <i class="fas fa-shopping-cart text-4xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500">Cart is empty</p>
                    </div>
                </div>

                <!-- Cart totals (payment & actions: classic = bottom bar; sidebar = below totals on desktop) -->
                <div class="flex-shrink-0 border-t border-gray-200 p-2 space-y-1 bg-gray-50/80 text-xs min-w-0 w-full max-w-full">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Subtotal</span>
                        <span class="font-medium tabular-nums" x-text="formatCurrency(subtotal)"></span>
                    </div>
                    <div class="space-y-1">
                        <div class="flex justify-between items-center gap-2">
                            <span class="text-gray-600 shrink-0">Discount</span>
                            <div class="flex items-center gap-1 flex-wrap justify-end">
                                <div class="inline-flex rounded border border-gray-300 overflow-hidden text-[10px] font-medium">
                                    <button type="button" @click="discountType = 'percentage'; updateDiscount()"
                                        :class="discountType === 'percentage' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                                        class="px-1.5 py-0.5">%</button>
                                    <button type="button" @click="discountType = 'fixed'; updateDiscount()"
                                        :class="discountType === 'fixed' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                                        class="px-1.5 py-0.5 border-l border-gray-300">Fixed</button>
                                </div>
                                <input
                                    type="number"
                                    x-model="discountValue"
                                    @input="updateDiscount()"
                                    step="0.01"
                                    min="0"
                                    :max="discountType === 'percentage' ? 100 : null"
                                    :placeholder="discountType === 'percentage' ? '0' : '0.00'"
                                    class="w-16 text-right border border-gray-300 rounded px-1.5 py-0.5 text-xs">
                                <button type="button" x-show="discountAmount > 0" @click="clearDiscount()" class="text-gray-400 hover:text-gray-600 px-0.5" title="Clear discount">
                                    <i class="fas fa-times text-[10px]"></i>
                                </button>
                            </div>
                        </div>
                        <div x-show="discountAmount > 0" class="flex justify-between text-emerald-700">
                            <span x-text="discountType === 'percentage' ? ('Discount (' + discountValue + '%)') : 'Discount applied'"></span>
                            <span class="font-medium tabular-nums" x-text="'-' + formatCurrency(discountAmount)"></span>
                        </div>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Tax ({{ $totalTaxPercentage }}%)</span>
                        <span class="font-medium tabular-nums" x-text="formatCurrency(taxAmount)"></span>
                    </div>
                    <div x-show="orderType === 'delivery'" class="flex justify-between items-center gap-2">
                        <span class="text-gray-600 shrink-0">Delivery</span>
                        <input
                            type="number"
                            x-model="deliveryFee"
                            @change="updateDeliveryFee()"
                            step="0.01"
                            min="0"
                            class="w-16 text-right border border-gray-300 rounded px-1.5 py-0.5 text-xs">
                    </div>
                    <div class="border-t border-gray-200 pt-1 flex justify-between items-baseline gap-2">
                        <span class="text-sm font-bold text-gray-900">Total</span>
                        <span class="text-base font-bold text-indigo-600 tabular-nums" x-text="formatCurrency(totalAmount)"></span>
                    </div>
                </div>

                <!-- Payment & actions in sidebar (sidebar_actions layout on desktop) -->
                <div x-show="isLargeScreen && posLayout.uses_sidebar_checkout" x-cloak class="flex-shrink-0">
                    @include('pos.partials.fulfillment-checkout', ['placement' => 'sidebar'])
                </div>
            </div>
        </div>

        <!-- New Customer Modal (POS) -->
        <div x-show="showNewCustomerModal"
         x-cloak
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-[120] overflow-y-auto"
         style="background-color: rgba(0, 0, 0, 0.5);"
         role="dialog"
         aria-modal="true"
         aria-labelledby="new-customer-modal-title"
         @click.self="closeNewCustomerModal()"
         @keydown.escape.window="showNewCustomerModal && closeNewCustomerModal()">
            <div class="flex items-center justify-center min-h-screen p-4">
                <div class="bg-white rounded-lg shadow-xl max-w-md w-full"
                     @click.stop
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 transform scale-95"
                     x-transition:enter-end="opacity-100 transform scale-100"
                     x-transition:leave="transition ease-in duration-200"
                     x-transition:leave-start="opacity-100 transform scale-100"
                     x-transition:leave-end="opacity-0 transform scale-95">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 id="new-customer-modal-title" class="text-lg font-semibold text-gray-900">Create new customer</h3>
                        <p class="text-sm text-gray-500 mt-1">Add a customer for this order.</p>
                    </div>
                    <div class="p-6 space-y-6">
                        <div>
                            <label for="new-customer-name" class="form-label">Name <span class="text-red-500">*</span></label>
                            <input type="text" id="new-customer-name" x-model="newCustomerName" placeholder="Full name" class="form-control">
                        </div>
                        <div>
                            <label for="new-customer-phone" class="form-label">Mobile <span class="text-red-500">*</span></label>
                            <input type="text" id="new-customer-phone" x-model="newCustomerPhone" placeholder="Phone number" class="form-control">
                        </div>
                        <div>
                            <label for="new-customer-email" class="form-label">Email <span class="text-gray-400 font-normal">(optional)</span></label>
                            <input type="email" id="new-customer-email" x-model="newCustomerEmail" placeholder="email@example.com" class="form-control">
                        </div>
                        <div>
                            <label for="new-customer-address" class="form-label">
                                Address
                                <span x-show="orderType === 'delivery' || newCustomerFromDeliveryWizard" class="text-red-500">*</span>
                                <span x-show="orderType !== 'delivery' && !newCustomerFromDeliveryWizard" class="text-gray-400 font-normal">(optional)</span>
                            </label>
                            <textarea id="new-customer-address" x-model="newCustomerAddress" rows="3" placeholder="Delivery address" class="form-control-textarea"></textarea>
                        </div>
                    </div>
                    <div class="px-6 py-4 border-t border-gray-200 flex justify-end gap-3">
                        <button type="button" @click="closeNewCustomerModal()" class="btn-form-secondary">
                            Cancel
                        </button>
                        <button type="button" @click="createNewCustomer()" :disabled="newCustomerSaving" class="btn-form-primary">
                            <span x-show="!newCustomerSaving">Create</span>
                            <span x-show="newCustomerSaving"><i class="fas fa-spinner fa-spin mr-1"></i> Saving...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Customer picker modal -->
        <div x-show="showCustomerSelectModal"
             x-cloak
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-[115] overflow-y-auto"
             style="background-color: rgba(0, 0, 0, 0.5);"
             role="dialog"
             aria-modal="true"
             aria-labelledby="customer-picker-title"
             @click.self="closeCustomerSelectModal()"
             @keydown.escape.window="showCustomerSelectModal && closeCustomerSelectModal()">
            <div class="flex items-center justify-center min-h-screen p-4">
                <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] flex flex-col"
                     @click.stop
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 transform scale-95"
                     x-transition:enter-end="opacity-100 transform scale-100"
                     x-transition:leave="transition ease-in duration-200"
                     x-transition:leave-start="opacity-100 transform scale-100"
                     x-transition:leave-end="opacity-0 transform scale-95">
                    <div class="p-4 border-b border-gray-200 flex-shrink-0">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h3 id="customer-picker-title" class="text-lg font-semibold text-gray-900" x-text="customerPickerRequired ? 'Select customer for credit' : 'Select customer'"></h3>
                                <p class="text-sm text-gray-500 mt-1" x-text="customerPickerRequired ? 'A registered customer is required for credit sales.' : 'Browse the list or type to filter by code, name, mobile, or email.'"></p>
                            </div>
                            <button type="button" @click="closeCustomerSelectModal()" class="text-gray-400 hover:text-gray-600 shrink-0">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="mt-4 relative">
                            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm pointer-events-none"></i>
                            <input type="text"
                                   id="customer-picker-search"
                                   x-model="customerSearchQuery"
                                   @input="scheduleCustomerPickerSearch()"
                                   @keydown.enter.prevent="fetchCustomersForPicker()"
                                   placeholder="Filter by code, name, mobile, or email…"
                                   class="form-control w-full pl-9 pr-10">
                            <span x-show="customerSearchLoading" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400">
                                <i class="fas fa-spinner fa-spin text-sm"></i>
                            </span>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <button type="button" x-show="!customerPickerRequired" @click="setWalkInCustomer()"
                                    class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-200 rounded-lg hover:bg-gray-200">
                                Walk-in (no customer)
                            </button>
                            <button type="button" @click="openCreateCustomerFromPicker()"
                                    class="px-3 py-1.5 text-sm font-medium text-indigo-700 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100">
                                <i class="fas fa-user-plus mr-1"></i>Create new
                            </button>
                        </div>
                    </div>
                    <div class="overflow-y-auto flex-1 min-h-[14rem] max-h-[min(60vh,32rem)]">
                        <div x-show="customerSearchLoading && customerSearchResults.length === 0" class="py-10 text-center text-sm text-gray-500">
                            <i class="fas fa-spinner fa-spin mr-2"></i>Loading customers…
                        </div>
                        <div x-show="!customerSearchLoading && customerSearchDone && customerSearchResults.length === 0" x-cloak
                             class="py-10 text-center text-sm text-gray-500">
                            No customers found. Try another filter or create a new customer.
                        </div>
                        <div x-show="customerSearchResults.length > 0" class="min-w-0">
                            <div class="sticky top-0 z-10 grid grid-cols-12 gap-3 px-4 py-2.5 bg-gray-50 border-b border-gray-200 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <div class="col-span-2">Code</div>
                                <div class="col-span-2">Name</div>
                                <div class="col-span-2">Mobile</div>
                                <div class="col-span-2">Email</div>
                                <div class="col-span-3">Address</div>
                                <div class="col-span-1 text-right"></div>
                            </div>
                            <template x-for="customer in customerSearchResults" :key="customer.id">
                                <div class="border-b border-gray-100 hover:bg-indigo-50/60">
                                    <div class="grid grid-cols-12 gap-3 items-start px-4 py-3 text-sm">
                                        <div class="col-span-2 font-mono text-xs text-gray-600 whitespace-nowrap" x-text="customer.code || '—'"></div>
                                        <div class="col-span-2 font-medium text-gray-900 min-w-0 break-words" x-text="customer.name"></div>
                                        <div class="col-span-2 text-gray-600 whitespace-nowrap" x-text="customer.phone || '—'"></div>
                                        <div class="col-span-2 text-gray-600 min-w-0 break-all" x-text="customer.email || '—'"></div>
                                        <div class="col-span-3 text-gray-600 min-w-0">
                                            <span class="line-clamp-2" x-text="customerPrimaryAddressLabel(customer)"></span>
                                        </div>
                                        <div class="col-span-1 flex justify-end">
                                            <button type="button"
                                                    @click="selectSearchCustomerWithAddress(customer, customerPickDefaultAddress(customer))"
                                                    class="px-3 py-1.5 bg-indigo-600 text-white text-xs font-semibold rounded-lg hover:bg-indigo-700 touch-manipulation whitespace-nowrap">
                                                Select
                                            </button>
                                        </div>
                                    </div>
                                    <div x-show="customer.addresses && customer.addresses.length > 1" class="px-4 pb-3">
                                        <p class="text-xs font-semibold text-gray-500 mb-2">Choose address</p>
                                        <div class="space-y-2 pl-3 border-l-2 border-indigo-100">
                                            <template x-for="addr in customer.addresses" :key="addr.id">
                                                <div class="flex items-start gap-2 p-2.5 bg-white border border-gray-200 rounded-lg hover:border-indigo-300">
                                                    <div class="flex-1 min-w-0">
                                                        <span class="text-xs font-medium text-gray-500" x-text="addr.label || 'Address'"></span>
                                                        <p class="text-sm text-gray-900 mt-0.5" x-text="addr.full_address || addr.address_line_1"></p>
                                                    </div>
                                                    <button type="button" @click="selectSearchCustomerWithAddress(customer, addr)"
                                                            class="flex-shrink-0 px-3 py-1.5 bg-indigo-600 text-white text-xs font-semibold rounded-lg hover:bg-indigo-700 touch-manipulation">
                                                        Select
                                                    </button>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Variant Selection Modal -->
        <div x-show="showVariantModal"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto"
         style="background-color: rgba(0, 0, 0, 0.5);"
         @click.self="showVariantModal = false; selectedItemForVariant = null;">
            <div class="flex items-center justify-center min-h-screen p-4">
                <div class="bg-white rounded-lg shadow-xl max-w-md w-full" 
                     @click.stop
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 transform scale-95"
                     x-transition:enter-end="opacity-100 transform scale-100"
                     x-transition:leave="transition ease-in duration-200"
                     x-transition:leave-start="opacity-100 transform scale-100"
                     x-transition:leave-end="opacity-0 transform scale-95">
                    <!-- Modal Header -->
                    <div class="flex items-center justify-between p-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900" x-text="selectedItemForVariant?.name || 'Select Option'"></h3>
                        <button type="button" @click="showVariantModal = false; selectedItemForVariant = null;" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <!-- Modal Body: show each variant and its options -->
                    <div class="p-4 max-h-96 overflow-y-auto">
                        <template x-if="selectedItemForVariant && selectedItemForVariant.variants">
                            <template x-for="variant in selectedItemForVariant.variants" :key="variant.id">
                                <div class="mb-6 last:mb-0">
                                    <p class="text-sm font-medium text-gray-700 mb-2" x-text="'Select ' + variant.name + ':'"></p>
                                    <div class="space-y-2">
                                        <template x-if="variant.options && variant.options.length > 0">
                                            <template x-for="option in variant.options" :key="option.name">
                                                <button 
                                                    @click="selectVariantOptionForCart(selectedItemForVariant, variant, option)"
                                                    class="w-full p-4 text-left border-2 border-gray-200 rounded-lg hover:border-indigo-500 hover:bg-indigo-50 transition-colors">
                                                    <div class="flex items-center justify-between">
                                                        <div>
                                                            <p class="font-semibold text-gray-900" x-text="option.name"></p>
                                                            <template x-if="option.code">
                                                                <p class="text-xs text-gray-500 mt-1" x-text="'Code: ' + option.code"></p>
                                                            </template>
                                                        </div>
                                                        <div class="text-right">
                                                            <p class="text-lg font-bold text-indigo-600" 
                                                               x-text="formatCurrency(parseFloat(option.price))"></p>
                                                        </div>
                                                    </div>
                                                </button>
                                            </template>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- Addon Selection Modal -->
        <div x-show="showAddonModal"
         x-cloak
         class="fixed inset-0 z-50 overflow-y-auto"
         style="background-color: rgba(0, 0, 0, 0.5);"
         @click.self="closeAddonModal()">
            <div class="flex items-center justify-center min-h-screen p-4">
                <div class="bg-white rounded-lg shadow-xl max-w-md w-full" @click.stop>
                    <div class="flex items-center justify-between p-4 border-b border-gray-200">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900" x-text="addonModalItem?.name || 'Extras'"></h3>
                            <p class="text-xs text-gray-500">Optional — tap + to add multiple</p>
                        </div>
                        <button type="button" @click="closeAddonModal()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="p-4 max-h-96 overflow-y-auto space-y-2">
                        <template x-for="addon in (addonModalItem?.addons || [])" :key="addon.id">
                            <div class="flex items-center justify-between gap-3 p-3 border border-gray-200 rounded-lg">
                                <div class="min-w-0 flex-1">
                                    <p class="font-medium text-gray-900" x-text="addon.name"></p>
                                    <p class="text-sm text-indigo-600 font-semibold" x-text="formatCurrency(parseFloat(addon.price))"></p>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <button type="button" @click="decrementAddonSelection(addon.id)" class="w-8 h-8 rounded bg-gray-100 hover:bg-gray-200">−</button>
                                    <span class="w-8 text-center font-semibold tabular-nums" x-text="addonSelectionQty(addon.id)"></span>
                                    <button type="button" @click="incrementAddonSelection(addon.id)" class="w-8 h-8 rounded bg-indigo-600 text-white hover:bg-indigo-700">+</button>
                                </div>
                            </div>
                        </template>
                    </div>
                    <div class="p-4 border-t border-gray-200 flex items-center justify-between gap-3">
                        <p class="text-sm text-gray-600">Extras total: <span class="font-semibold text-gray-900" x-text="formatCurrency(addonModalExtrasTotal())"></span></p>
                        <div class="flex gap-2">
                            <button type="button" @click="closeAddonModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-sm">Cancel</button>
                            <button type="button" @click="confirmAddonModal()" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700">Add to cart</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @include('pos.partials.kitchen-status-modal')

        <!-- Invoice Modal -->
        <div x-show="showInvoiceModal"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-[110] overflow-y-auto"
         style="background-color: rgba(0, 0, 0, 0.5);"
         @click.self="closeInvoiceModal()">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-[calc(100vw-2rem)]" 
                 :style="'width: ' + receiptPrint.paper_width_mm + 'mm'"
                 @click.stop
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 transform scale-95"
                 x-transition:enter-end="opacity-100 transform scale-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 transform scale-100"
                 x-transition:leave-end="opacity-0 transform scale-95">
                <!-- Modal Header -->
                <div class="flex items-center justify-between p-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">Invoice</h3>
                    <button type="button" @click="closeInvoiceModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <!-- Modal Body - Invoice Content -->
                <div class="px-2 py-3" id="invoice-content" style="max-height: 70vh; overflow-y: auto;">
                    <template x-if="loadingInvoice">
                        <div class="text-center py-8">
                            <i class="fas fa-spinner fa-spin text-2xl text-gray-400"></i>
                            <p class="mt-2 text-gray-600">Loading invoice...</p>
                        </div>
                    </template>
                    
                    @include('pos._receipt-body-alpine')
                </div>
                
                <!-- Modal Footer -->
                <div class="flex items-center justify-end space-x-3 p-4 border-t border-gray-200">
                    <button type="button" @click="closeInvoiceModal()" class="px-4 py-2 text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300 transition-colors">
                        Close
                    </button>
                    <button type="button" @click="printInvoice()" class="px-4 py-2 text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors">
                        <i class="fas fa-print mr-2"></i>Print
                    </button>
                </div>
            </div>
        </div>
        </div>
    </div>

    <script>
        function posSystem() {
            return {
                selectedCategoryId: null,
                selectedBranchId: @php
                    $defaultBranchId = $selectedBranchId ?? ($branches->count() > 0 ? $branches->first()->id : null);
                    echo $defaultBranchId !== null ? (int)$defaultBranchId : 'null';
                @endphp,
                selectedTableId: null,
                selectedCustomerId: null,
                selectedCustomerBalance: 0,
                customerName: '',
                customerPhone: '',
                customerEmail: '',
                customerAddress: '',
                orderType: 'dine_in',
                waiterId: null,
                deliveryRiderId: null,
                cart: [],
                moneySourceId: null,
                paymentSelection: '',
                paymentMethod: 'cash', // Derived from money source type or credit
                paidAmount: 0,
                notes: '',
                orderNotesOpen: false,
                deliveryFee: 0,
                discountType: 'fixed',
                discountValue: '',
                processing: false,
                taxPercentage: {{ $totalTaxPercentage }},
                toasts: [],
                showInvoiceModal: false,
                invoiceData: null,
                loadingInvoice: false,
                showOrderDetailsModal: false,
                orderDetailsData: null,
                loadingOrderDetails: false,
                searchTerm: '',
                                showVariantModal: false,
                selectedItemForVariant: null,
                showAddonModal: false,
                addonModalItem: null,
                addonModalCartIndex: null,
                addonModalVariantInfo: null,
                addonModalBasePrice: 0,
                addonSelections: {},
                pendingAddonAfterVariant: false,

                tables: @json($tablesJson),
                floors: @json($floorsJson ?? []),
                branchStaff: @json($branchStaffJson ?? []),

                posSessionReady: false,
                activePosNav: '',
                sessionStep: 1,
                selectedFloorForSessionId: '',
                activeOrderId: null,
                activeOrderNumber: '',
                orderKitchenKots: [],
                orderWorkflowStatus: 'open',
                orderAllowedNextStatuses: [],
                orderExpectedReadyAt: null,
                orderStatusLogs: [],
                orderStatusUpdating: false,
                showKitchenStatusModal: false,
                kitchenStatusPulse: false,
                showCheckoutModal: false,
                checkoutMoneySourceId: null,
                checkoutPaymentSelection: '',
                checkoutPaymentMethod: 'cash',
                checkoutPaidAmount: 0,
                checkoutSubmitting: false,
                showSplitPaymentModal: false,
                splitActiveSourceId: null,
                splitLineAmount: '',
                splitLines: [],
                splitSubmitting: false,
                splitQuickAmounts: [10, 20, 50, 100, 500, 1000],
                allowPosCreditSales: {{ (get_company_config()['allow_pos_credit_sales'] ?? false) ? 'true' : 'false' }},
                canPosFoc: {{ !empty($canPosFoc) ? 'true' : 'false' }},
                directPosPrint: {{ (get_company_config()['direct_pos_print'] ?? false) ? 'true' : 'false' }},
                showAutoBillToggle: {{ (get_company_config()['show_pos_auto_bill_toggle'] ?? false) ? 'true' : 'false' }},
                autoBillEnabled: true,
                receiptPrint: @json($receiptLayout),
                posLayout: @json($posLayoutConfig),
                posProductGrid: @json($posLayoutConfig['product_grid']),
                addonKitchenTracking: {{ ($addonKitchenTracking ?? false) ? 'true' : 'false' }},
                companyTimezone: @json($companyTimezone ?? 'UTC'),
                branchTimezones: @json($branchTimezonesJson ?? new \stdClass()),

                showOrderQueueModal: false,
                orderQueueChannel: null,
                orderQueueList: [],
                orderQueueBusy: false,
                orderQueueHistoryDate: @json($posToday ?? now()->toDateString()),
                orderQueueHistoryTimezone: @json($posTimezone ?? ($companyTimezone ?? 'UTC')),
                orderQueueHistoryTypeFilter: 'all',
                orderQueueHistorySearch: '',
                orderQueueKotReprintingId: null,
                orderQueueCancellingId: null,

                showKitchenQueueModal: false,
                kitchenQueueList: [],
                kitchenQueueBusy: false,

                showTableViewModal: false,
                tableViewFloors: [],
                tableViewSummary: null,
                tableViewLoading: false,
                tableViewFloorId: '',
                itemNoteEditorIndex: null,

                deliveryWizardView: 'search',
                deliverySearchQuery: '',
                deliverySearchLoading: false,
                deliverySearchDone: false,
                deliverySearchResults: [],
                deliverySearchDebounceTimer: null,
                deliveryPendingCustomer: null,
                deliveryManualAddress: '',
                newCustomerFromDeliveryWizard: false,

                menuItems: @json($menuItemsJson ?? []),
                categoryFilterMap: @json($categoryFilterMap ?? []),
                dealsJson: [],
                dealsLoading: false,
                showDealsSection: false,
                
                moneySources: @json($moneySourcesJson),
                
                customers: @json($customersJson ?? []),
                showNewCustomerModal: false,
                newCustomerName: '',
                newCustomerPhone: '',
                newCustomerEmail: '',
                newCustomerAddress: '',
                newCustomerSaving: false,
                customerSearchQuery: '',
                customerSearchResults: [],
                customerSearchLoading: false,
                customerSearchDone: false,
                customerPickerDebounceTimer: null,
                showCustomerSelectModal: false,
                customerPickerRequired: false,
                customerPickerReason: '',

                /** Layout: lg+ = side-by-side; below lg = tabbed products vs order (single DOM). */
                isLargeScreen: false,
                posTab: 'products',
                topBarExpanded: false,
                posLgMinWidth: 1024,

                syncPosViewport() {
                    this.isLargeScreen = window.innerWidth >= this.posLgMinWidth;
                    if (this.isLargeScreen) {
                        this.topBarExpanded = true;
                    }
                    this.syncPosNavSpacer();
                },

                syncPosNavSpacer() {
                    const nav = document.getElementById('pos-top-nav');
                    const spacer = document.getElementById('pos-nav-spacer');
                    if (!nav) {
                        return;
                    }
                    const height = nav.offsetHeight;
                    document.documentElement.style.setProperty('--pos-nav-h', height + 'px');
                    if (spacer) {
                        spacer.style.height = height + 'px';
                    }
                },
                
                init() {
                    this.syncPosViewport();
                    window.addEventListener('resize', () => this.syncPosViewport());
                    this.$nextTick(() => this.syncPosNavSpacer());
                    setTimeout(() => this.syncPosNavSpacer(), 100);
                    this.restoreAutoBillPreference();
                    if (this.floors && this.floors.length > 0) {
                        this.selectFirstFloorForSession();
                    }
                    // Debug: track what data the POS received
                    console.log('[POS] init: menuItems', this.menuItems?.length ?? 'not array', typeof this.menuItems);
                    if (this.menuItems && Array.isArray(this.menuItems)) {
                        console.log('[POS] menuItems count:', this.menuItems.length);
                        if (this.menuItems.length > 0) {
                            const first = this.menuItems[0];
                            console.log('[POS] first item:', { id: first.id, name: first.name, has_variants: first.has_variants, variants_count: first.variants?.length ?? 0 });
                        }
                    } else {
                        console.warn('[POS] menuItems is missing or not an array:', this.menuItems);
                    }
                    console.log('[POS] filteredMenuItems (getter will run on access):', this.filteredMenuItems?.length ?? 'N/A');
                    // Auto-select first CASH type money source if available
                    // Use setTimeout to ensure DOM and data are ready
                    setTimeout(() => {
                        this.selectDefaultPaymentSource();
                    }, 150);
                    if (this.hasOrderNotes()) {
                        this.orderNotesOpen = true;
                    }
                },

                autoBillStorageKey() {
                    return 'pos_auto_bill_enabled';
                },

                restoreAutoBillPreference() {
                    if (!this.showAutoBillToggle) {
                        this.autoBillEnabled = true;
                        return;
                    }
                    try {
                        const stored = localStorage.getItem(this.autoBillStorageKey());
                        if (stored === null) {
                            this.autoBillEnabled = true;
                            return;
                        }
                        this.autoBillEnabled = stored === '1' || stored === 'true';
                    } catch (e) {
                        this.autoBillEnabled = true;
                    }
                },

                setAutoBillEnabled(enabled) {
                    this.autoBillEnabled = !!enabled;
                    if (!this.showAutoBillToggle) {
                        return;
                    }
                    try {
                        localStorage.setItem(this.autoBillStorageKey(), this.autoBillEnabled ? '1' : '0');
                    } catch (e) {
                        // Ignore storage failures (private mode, etc.).
                    }
                },

                shouldAutoBillOnCheckout() {
                    if (!this.showAutoBillToggle) {
                        return true;
                    }
                    return !!this.autoBillEnabled;
                },

                hasOrderNotes() {
                    return String(this.notes ?? '').trim().length > 0;
                },

                toggleOrderNotes() {
                    this.orderNotesOpen = !this.orderNotesOpen;
                    if (this.orderNotesOpen) {
                        this.$nextTick(() => this.$refs.orderNotesInput?.focus());
                    }
                },

                syncOrderNotesOpen() {
                    this.orderNotesOpen = this.hasOrderNotes();
                },

                isCreditPaymentSelected() {
                    return this.paymentSelection === 'credit';
                },
                isFocPaymentSelected() {
                    return this.paymentSelection === 'foc';
                },
                isSplitPaymentSelected() {
                    return this.paymentSelection === 'split';
                },
                isCheckoutCreditPaymentSelected() {
                    return this.checkoutPaymentSelection === 'credit';
                },
                isCheckoutFocPaymentSelected() {
                    return this.checkoutPaymentSelection === 'foc';
                },
                selectedMoneySourceId() {
                    if (this.isCreditPaymentSelected() || this.isFocPaymentSelected()) {
                        return null;
                    }
                    const id = parseInt(this.paymentSelection, 10);
                    return isNaN(id) ? null : id;
                },
                checkoutSelectedMoneySourceId() {
                    if (this.isCheckoutCreditPaymentSelected() || this.isCheckoutFocPaymentSelected()) {
                        return null;
                    }
                    const id = parseInt(this.checkoutPaymentSelection, 10);
                    return isNaN(id) ? null : id;
                },
                paymentIntentPayload() {
                    if (this.isCreditPaymentSelected()) {
                        return {
                            payment_method: 'credit',
                            paid_amount: parseFloat(this.paidAmount) || 0,
                            customer_id: this.hasSelectedCustomer() ? parseInt(this.selectedCustomerId, 10) : null,
                        };
                    }
                    // FOC is settle-only — do not persist on open tabs.
                    return {
                        payment_method: null,
                        paid_amount: 0,
                        customer_id: this.hasSelectedCustomer() ? parseInt(this.selectedCustomerId, 10) : null,
                    };
                },
                selectDefaultPaymentSource() {
                    if (this.moneySources && Array.isArray(this.moneySources) && this.moneySources.length > 0) {
                        const cashSource = this.moneySources.find(source => source.type === 'CASH');
                        if (cashSource && cashSource.id) {
                            this.paymentSelection = String(cashSource.id);
                            this.moneySourceId = parseInt(cashSource.id, 10);
                            this.$nextTick(() => {
                                this.handleMoneySourceChange();
                            });
                        }
                    }
                },
                applyOrderPaymentFields(order) {
                    if (!order) {
                        return;
                    }
                    if (order.payment_method === 'credit') {
                        this.paymentSelection = 'credit';
                        this.paymentMethod = 'credit';
                        this.moneySourceId = null;
                        this.paidAmount = parseFloat(order.paid_amount) || 0;
                        return;
                    }
                    if (order.payment_method === 'foc' && this.canPosFoc) {
                        this.paymentSelection = 'foc';
                        this.paymentMethod = 'foc';
                        this.moneySourceId = null;
                        this.paidAmount = 0;
                        return;
                    }
                    if (order.money_source_id) {
                        this.paymentSelection = String(order.money_source_id);
                        this.moneySourceId = Number(order.money_source_id);
                        this.handleMoneySourceChange();
                        return;
                    }
                    this.selectDefaultPaymentSource();
                },
                handlePaymentSelectionChange() {
                    if (this.isSplitPaymentSelected()) {
                        this.paymentMethod = 'split';
                        this.moneySourceId = null;
                        this.paidAmount = 0;
                        this.openSplitPaymentModal();
                        return;
                    }
                    if (this.isFocPaymentSelected()) {
                        if (!this.canPosFoc) {
                            this.showToast('You do not have permission to use FOC.', 'error');
                            this.selectDefaultPaymentSource();
                            return;
                        }
                        this.paymentMethod = 'foc';
                        this.moneySourceId = null;
                        this.paidAmount = 0;
                        this.customerPickerReason = '';
                        return;
                    }
                    if (this.isCreditPaymentSelected()) {
                        this.paymentMethod = 'credit';
                        this.moneySourceId = null;
                        this.paidAmount = 0;
                        if (this.allowPosCreditSales && !this.hasSelectedCustomer()) {
                            this.customerPickerReason = 'credit';
                            this.openCustomerPickerModal({ required: true });
                        }
                        return;
                    }
                    this.customerPickerReason = '';
                    this.moneySourceId = this.selectedMoneySourceId();
                    this.handleMoneySourceChange();
                },
                handleCheckoutPaymentSelectionChange() {
                    if (this.isCheckoutFocPaymentSelected()) {
                        if (!this.canPosFoc) {
                            this.showToast('You do not have permission to use FOC.', 'error');
                            this.checkoutPaymentSelection = '';
                            return;
                        }
                        this.checkoutPaymentMethod = 'foc';
                        this.checkoutMoneySourceId = null;
                        this.checkoutPaidAmount = 0;
                        this.customerPickerReason = '';
                        return;
                    }
                    if (this.isCheckoutCreditPaymentSelected()) {
                        this.checkoutPaymentMethod = 'credit';
                        this.checkoutMoneySourceId = null;
                        this.checkoutPaidAmount = 0;
                        if (this.allowPosCreditSales && !this.hasSelectedCustomer()) {
                            this.customerPickerReason = 'credit';
                            this.openCustomerPickerModal({ required: true });
                        }
                        return;
                    }
                    this.customerPickerReason = '';
                    this.checkoutMoneySourceId = this.checkoutSelectedMoneySourceId();
                    this.handleCheckoutMoneySourceChange();
                },
                
                get splitPaidTotal() {
                    return this.roundMoney(this.splitLines.reduce((sum, line) => sum + (parseFloat(line.amount) || 0), 0));
                },
                get splitDue() {
                    return this.roundMoney(Math.max(0, this.totalAmount - this.splitPaidTotal));
                },

                openSplitPaymentModal() {
                    if (this.cart.length === 0 && !this.activeOrderId) {
                        this.showToast('Add items first.', 'error');
                        this.selectDefaultPaymentSource();
                        return;
                    }
                    if (this.cart.length > 0 && !this.orderStaffValid()) {
                        this.showToast('Complete order details first.', 'error');
                        this.selectDefaultPaymentSource();
                        return;
                    }
                    this.resetSplitEntryFields();
                    if (!this.splitActiveSourceId && this.moneySources.length > 0) {
                        const cashSource = this.moneySources.find(s => s.type === 'CASH') || this.moneySources[0];
                        this.splitActiveSourceId = cashSource.id;
                    }
                    this.fillSplitRemainingDue();
                    this.showSplitPaymentModal = true;
                },
                closeSplitPaymentModal() {
                    if (this.splitSubmitting) {
                        return;
                    }
                    this.showSplitPaymentModal = false;
                    if (this.isSplitPaymentSelected()) {
                        this.selectDefaultPaymentSource();
                    }
                },
                resetSplitEntryFields() {
                    this.splitLineAmount = '';
                },
                clearSplitEntryFields() {
                    this.resetSplitEntryFields();
                    this.fillSplitRemainingDue();
                },
                clearSplitPaymentLines() {
                    this.splitLines = [];
                    this.clearSplitEntryFields();
                },
                selectSplitMoneySource(source) {
                    this.splitActiveSourceId = source.id;
                    this.resetSplitEntryFields();
                    this.fillSplitRemainingDue();
                },
                splitActiveSource() {
                    return this.moneySources.find(s => Number(s.id) === Number(this.splitActiveSourceId)) || null;
                },
                fillSplitRemainingDue() {
                    if (this.splitDue > 0) {
                        this.splitLineAmount = this.splitDue;
                    }
                },
                applySplitQuickAmount(value) {
                    const due = this.splitDue > 0 ? this.splitDue : value;
                    this.splitLineAmount = Math.min(value, due);
                },
                addSplitPaymentLine() {
                    const source = this.splitActiveSource();
                    if (!source) {
                        this.showToast('Select a payment source.', 'error');
                        return;
                    }
                    const amount = this.roundMoney(parseFloat(this.splitLineAmount) || 0);
                    if (amount <= 0) {
                        this.showToast('Enter an amount to add.', 'error');
                        return;
                    }
                    if (amount - this.splitDue > 0.009) {
                        this.showToast('Amount exceeds remaining due.', 'error');
                        return;
                    }
                    this.splitLines.push({
                        money_source_id: source.id,
                        name: source.name,
                        amount,
                        given_amount: null,
                        change_amount: null,
                    });
                    this.resetSplitEntryFields();
                    if (this.splitDue > 0) {
                        this.fillSplitRemainingDue();
                    }
                },
                removeSplitPaymentLine(index) {
                    this.splitLines.splice(index, 1);
                    this.fillSplitRemainingDue();
                },
                splitPaymentsPayload() {
                    return this.splitLines.map(line => ({
                        money_source_id: parseInt(line.money_source_id, 10),
                        amount: this.roundMoney(parseFloat(line.amount) || 0),
                    }));
                },
                resetPosAfterCompletedOrder(orderId, dataBrowserPrint = true) {
                    this.showSplitPaymentModal = false;
                    this.clearSplitPaymentLines();
                    this.activeOrderId = null;
                    this.activeOrderNumber = '';
                    this.cart = [];
                    this.notes = '';
                    this.orderNotesOpen = false;
                    this.deliveryFee = 0;
                    this.waiterId = null;
                    this.deliveryRiderId = null;
                    this.clearDiscount();
                    this.clearCustomer();
                    this.paymentSelection = '';
                    this.paymentMethod = 'cash';
                    this.moneySourceId = null;
                    this.paidAmount = 0;
                    this.checkoutPaymentSelection = '';
                    this.checkoutMoneySourceId = null;
                    this.checkoutPaidAmount = 0;
                    this.selectDefaultPaymentSource();
                    if (orderId && dataBrowserPrint !== false) {
                        setTimeout(() => this.printOrderReceipt(orderId), 200);
                    }
                },
                async submitSplitPayment() {
                    if (this.splitSubmitting) {
                        return;
                    }
                    if (this.splitLines.length === 0) {
                        this.showToast('Add at least one payment.', 'error');
                        return;
                    }
                    if (this.splitDue > 0.009) {
                        this.showToast('Payment must cover the full total.', 'error');
                        return;
                    }
                    if (this.orderType === 'delivery' && !this.customerAddress) {
                        this.showToast('Select a customer with a delivery address.', 'error');
                        this.showSplitPaymentModal = false;
                        this.openCustomerPickerModal();
                        return;
                    }
                    if (!await this.ensureOrderSynced()) {
                        return;
                    }
                    if (!await this.ensurePrintReady(['receipt', 'kitchen'])) {
                        return;
                    }
                    if (!this.activeOrderId) {
                        this.showToast('Could not save order for checkout.', 'error');
                        return;
                    }

                    this.splitSubmitting = true;
                    try {
                        const url = '{{ url('/pos/orders') }}/' + this.activeOrderId + '/checkout';
                        const res = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                Accept: 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: JSON.stringify({
                                payment_method: 'split',
                                payment_splits: this.splitPaymentsPayload(),
                                paid_amount: this.totalAmount,
                                customer_id: this.hasSelectedCustomer() ? parseInt(this.selectedCustomerId, 10) : null,
                                customer_name: (this.customerName || '').trim() || null,
                                customer_phone: (this.customerPhone || '').trim() || null,
                                customer_email: (this.customerEmail || '').trim() || null,
                                customer_address: this.customerAddress,
                                payment_status: 'paid',
                                auto_bill: this.shouldAutoBillOnCheckout(),
                            }),
                        });
                        const data = await res.json();
                        if (!res.ok) {
                            let msg = data.message || 'Split checkout failed';
                            if (data.errors) {
                                msg = Object.values(data.errors).flat().join(' ');
                            }
                            this.showToast(msg, 'error');
                            return;
                        }
                        this.showToast('Payment completed.', 'success');
                        const orderId = data.order?.id;
                        if (data.browser_print === false && data.desktop_jobs > 0) {
                            this.showToast('Receipt sent for direct print.', 'success');
                        }
                        if ((data.kitchen_desktop_jobs || 0) > 0) {
                            this.showToast('KOT sent for direct print.', 'success');
                        }
                        this.resetPosAfterCompletedOrder(orderId, data.browser_print);
                    } catch (e) {
                        this.showToast('Split checkout failed.', 'error');
                    } finally {
                        this.splitSubmitting = false;
                    }
                },

                get filteredMenuItems() {
                    if (!this.menuItems || !Array.isArray(this.menuItems)) {
                        console.log('[POS] filteredMenuItems: no menuItems or not array, returning []');
                        return [];
                    }
                    let filtered = this.menuItems;
                    if (this.selectedCategoryId !== null && this.selectedCategoryId !== 'deals') {
                        const allowed = this.categoryFilterMap[this.selectedCategoryId]
                            ?? this.categoryFilterMap[String(this.selectedCategoryId)];
                        if (Array.isArray(allowed) && allowed.length > 0) {
                            filtered = filtered.filter(item => allowed.some(id => id == item.category_id));
                        } else {
                            filtered = filtered.filter(item => item.category_id == this.selectedCategoryId);
                        }
                    }
                    if (this.searchTerm && this.searchTerm.trim() !== '') {
                        const searchLower = this.searchTerm.toLowerCase().trim();
                        filtered = filtered.filter(item => {
                            const nameMatch = item.name.toLowerCase().includes(searchLower);
                            const skuMatch = item.sku && item.sku.toLowerCase().includes(searchLower);
                            return nameMatch || skuMatch;
                        });
                    }
                    if (filtered.length === 0 && this.menuItems.length > 0) {
                        console.log('[POS] filteredMenuItems: 0 items shown (categoryId=', this.selectedCategoryId, 'search=', this.searchTerm, ')');
                    }
                    return filtered;
                },
                
                handleSearch() {
                    // Search is handled automatically by the filteredMenuItems getter
                    // This method can be used for additional search logic if needed
                },
                
                handleBarcodeSearch() {
                    // When Enter is pressed, if search matches exactly one item, add it to cart
                    if (this.searchTerm && this.searchTerm.trim() !== '') {
                        const searchLower = this.searchTerm.toLowerCase().trim();
                        const exactMatch = this.menuItems.find(item => {
                            const skuMatch = item.sku && item.sku.toLowerCase() === searchLower;
                            const nameMatch = item.name.toLowerCase() === searchLower;
                            return skuMatch || nameMatch;
                        });
                        
                        if (exactMatch) {
                            this.addToCart(exactMatch);
                            this.searchTerm = '';
                            this.showToast(exactMatch.name + ' added to cart', 'success');
                        } else if (this.filteredMenuItems.length === 1) {
                            // If only one item matches, add it
                            this.addToCart(this.filteredMenuItems[0]);
                            this.searchTerm = '';
                            this.showToast(this.filteredMenuItems[0].name + ' added to cart', 'success');
                        }
                    }
                },
                
                get subtotal() {
                    return this.cart.reduce((sum, item) => sum + (item.quantity * item.unit_price), 0);
                },

                roundMoney(amount) {
                    return Math.round((parseFloat(amount) || 0) * 100) / 100;
                },

                get discountAmount() {
                    const sub = this.subtotal;
                    if (sub <= 0) {
                        return 0;
                    }
                    const val = parseFloat(this.discountValue) || 0;
                    if (val <= 0) {
                        return 0;
                    }
                    if (this.discountType === 'percentage') {
                        return this.roundMoney(sub * Math.min(val, 100) / 100);
                    }
                    return this.roundMoney(Math.min(val, sub));
                },

                get taxableSubtotal() {
                    return Math.max(0, this.roundMoney(this.subtotal - this.discountAmount));
                },
                
                get taxAmount() {
                    return this.roundMoney((this.taxableSubtotal * this.taxPercentage) / 100);
                },
                
                get totalAmount() {
                    return this.roundMoney(this.taxableSubtotal + this.taxAmount + parseFloat(this.deliveryFee || 0));
                },

                discountPayload() {
                    const amount = this.discountAmount;
                    return {
                        discount_type: amount > 0 ? this.discountType : null,
                        discount_value: amount > 0 ? (parseFloat(this.discountValue) || 0) : null,
                        discount_amount: amount,
                    };
                },

                applyDiscountFromOrder(order) {
                    const amount = parseFloat(order.discount_amount) || 0;
                    if (order.discount_type === 'percentage' && order.discount_value != null && parseFloat(order.discount_value) > 0) {
                        this.discountType = 'percentage';
                        this.discountValue = parseFloat(order.discount_value);
                        return;
                    }
                    if (amount > 0) {
                        this.discountType = 'fixed';
                        this.discountValue = order.discount_value != null ? parseFloat(order.discount_value) : amount;
                        return;
                    }
                    this.clearDiscount();
                },

                clearDiscount() {
                    this.discountType = 'fixed';
                    this.discountValue = '';
                },

                updateDiscount() {
                    if (this.discountType === 'percentage') {
                        const val = parseFloat(this.discountValue) || 0;
                        if (val > 100) {
                            this.discountValue = 100;
                        }
                    }
                    if (!this.isCreditPaymentSelected() && (this.paymentMethod === 'card' || this.paymentMethod === 'digital_wallet')) {
                        this.paidAmount = this.totalAmount;
                    }
                    if (this.showCheckoutModal && !this.isCheckoutCreditPaymentSelected()) {
                        if (this.checkoutPaymentMethod === 'card' || this.checkoutPaymentMethod === 'digital_wallet') {
                            this.checkoutPaidAmount = this.totalAmount;
                        }
                    }
                },
                
                get changeAmount() {
                    if (this.paymentMethod === 'cash' && this.paidAmount > 0) {
                        return this.paidAmount - this.totalAmount;
                    }
                    return 0;
                },

                get checkoutCreditDue() {
                    const paid = parseFloat(this.checkoutPaidAmount) || 0;
                    return Math.max(0, this.totalAmount - paid);
                },
                
                addDealToCart(deal) {
                    this.cart.push({
                        deal_id: deal.id,
                        menu_item_id: null,
                        name: deal.title,
                        quantity: 1,
                        unit_price: deal.price,
                        variants: null
                    });
                    this.showToast(deal.title + ' added to cart', 'success', 1500);
                },
                
                handleProductClick(item) {
                    if (!item) return;
                    const hasVariants = item.has_variants
                        || (item.variants && item.variants.length > 0)
                        || this.hasVariantOptions(item);
                    if (hasVariants) {
                        this.pendingAddonAfterVariant = !!item.has_addons;
                        this.selectedItemForVariant = item;
                        this.showVariantModal = true;
                        return;
                    }
                    if (item.has_addons) {
                        this.openAddonPicker(item);
                        return;
                    }
                    this.pushCartLine(item, parseFloat(item.price), null, []);
                    this.showToast(item.name + ' added to cart', 'success', 1500);
                },
                
                async loadDeals() {
                    this.dealsLoading = true;
                    try {
                        const url = '{{ route("pos.deals") }}';
                        const res = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                        const data = await res.json();
                        this.dealsJson = Array.isArray(data) ? data : [];
                    } catch (e) {
                        console.warn('Failed to load deals', e);
                        this.dealsJson = [];
                    }
                    this.dealsLoading = false;
                },
                
                addToCart(item) {
                    this.handleProductClick(item);
                },
                
                hasVariantOptions(item) {
                    const hasOptions = this.getFlattenedVariantOptions(item).length > 0;
                    if (item && item.has_variants && !hasOptions) {
                        console.log('[POS] hasVariantOptions: item has_variants=true but no options found', { id: item.id, name: item.name, variants: item.variants });
                    }
                    return hasOptions;
                },

                getFlattenedVariantOptions(item) {
                    try {
                        if (!item || !item.variants) return [];
                        const variants = Array.isArray(item.variants) ? item.variants : [];
                        const rows = [];
                        for (let i = 0; i < variants.length; i++) {
                            const variant = variants[i];
                            const opts = variant && Array.isArray(variant.options) ? variant.options : [];
                            for (let j = 0; j < opts.length; j++) {
                                const opt = opts[j];
                                const name = opt && opt.name ? String(opt.name) : '';
                                if (!name) continue;
                                const price = opt && (opt.price !== undefined && opt.price !== null) ? Number(opt.price) : 0;
                                const priceLabel = typeof this.formatCurrency === 'function' ? this.formatCurrency(price) : price.toFixed(2);
                                const vname = variant && variant.name ? String(variant.name) : '';
                                rows.push({
                                    value: (variant.id || i) + '|' + name,
                                    label: (vname ? vname + ' - ' : '') + name + ' · ' + priceLabel
                                });
                            }
                        }
                        if (variants.length > 0 && rows.length === 0) {
                            console.log('[POS] getFlattenedVariantOptions: item has variants but 0 options', item.id, item.name, 'variants:', variants);
                        }
                        return rows;
                    } catch (e) {
                        console.warn('[POS] getFlattenedVariantOptions error', item?.id, e);
                        return [];
                    }
                },
                
                addToCartWithVariant(item, selectedVariant) {
                    if (!selectedVariant) {
                        const basePrice = parseFloat(item.price);
                        this.pushCartLine(item, basePrice, null, []);
                        return;
                    }
                    this.addToCartWithVariantOption(item, selectedVariant, { name: selectedVariant.name, code: selectedVariant.code, price: selectedVariant.price });
                },
                
                addToCartWithVariantOption(item, variant, option) {
                    const basePrice = parseFloat(option.price || item.price);
                    const variantInfo = {
                        variant_id: variant.id,
                        variant_name: variant.name,
                        option_name: option.name,
                        option_code: option.code || null,
                    };
                    this.pushCartLine(item, basePrice, variantInfo, []);
                },

                selectVariantOptionForCart(item, variant, option) {
                    const basePrice = parseFloat(option.price || item.price);
                    const variantInfo = {
                        variant_id: variant.id,
                        variant_name: variant.name,
                        option_name: option.name,
                        option_code: option.code || null,
                    };
                    this.showVariantModal = false;
                    this.selectedItemForVariant = null;
                    this.pendingAddonAfterVariant = false;
                    if (item.has_addons) {
                        this.openAddonPicker(item, null, variantInfo, basePrice);
                        return;
                    }
                    this.pushCartLine(item, basePrice, variantInfo, []);
                    this.showToast(item.name + ' added to cart', 'success', 1500);
                },

                pushCartLine(item, basePrice, variantInfo, addons) {
                    const normalizedAddons = this.normalizeAddons(addons);
                    const unitPrice = this.computeLineUnitPrice(basePrice, normalizedAddons);
                    const existingItem = this.cart.find(cartItem => this.cartLinesMatch(cartItem, {
                        menu_item_id: item.id,
                        variants: variantInfo,
                        addons: normalizedAddons,
                    }));

                    if (existingItem) {
                        existingItem.quantity += 1;
                    } else {
                        this.cart.push({
                            menu_item_id: item.id,
                            name: item.name,
                            base_unit_price: basePrice,
                            unit_price: unitPrice,
                            quantity: 1,
                            variants: variantInfo,
                            addons: normalizedAddons,
                            special_instructions: ''
                        });
                    }

                    this.showVariantModal = false;
                    this.selectedItemForVariant = null;

                    if (this.paymentMethod === 'card' || this.paymentMethod === 'digital_wallet') {
                        this.paidAmount = this.totalAmount;
                    }
                },

                menuItemHasAddons(menuItemId) {
                    if (!menuItemId) return false;
                    const item = this.menuItems.find(i => i.id === menuItemId);
                    return !!(item && item.has_addons);
                },

                normalizeAddons(addons) {
                    if (!Array.isArray(addons)) return [];
                    const grouped = {};
                    for (const row of addons) {
                        const id = row?.id;
                        if (!id) continue;
                        const qty = parseFloat(row.quantity) || 0;
                        if (qty <= 0) continue;
                        if (!grouped[id]) {
                            grouped[id] = {
                                id: parseInt(id, 10),
                                name: row.name || '',
                                price: parseFloat(row.price) || 0,
                                code: row.code || null,
                                quantity: 0,
                            };
                        }
                        grouped[id].quantity += qty;
                    }
                    return Object.values(grouped).sort((a, b) => a.id - b.id);
                },

                addonsSignature(addons) {
                    return JSON.stringify(this.normalizeAddons(addons).map(a => ({ id: a.id, quantity: a.quantity })));
                },

                addonsLabel(addons) {
                    const normalized = this.normalizeAddons(addons);
                    if (!normalized.length) return '';
                    return '+ ' + normalized.map(a => {
                        let label = a.name || 'Extra';
                        if (a.quantity > 1) label += ' x' + a.quantity;
                        return label;
                    }).join(', ');
                },

                dealInvoiceComponents(item) {
                    if (!item || !(item.deal_id || item.deal)) {
                        return [];
                    }
                    const deal = item.deal || {};
                    const menuItems = deal.menu_items || deal.menuItems || [];
                    const dealQty = parseFloat(item.quantity) || 1;
                    return menuItems.map((component) => {
                        const pivot = component.pivot || {};
                        const pivotQty = parseFloat(pivot.quantity ?? 1) || 1;
                        const option = (pivot.option_name || '').toString().trim();
                        let name = (component.name || 'Item').toString();
                        if (option) {
                            name += ' (' + option + ')';
                        }
                        return {
                            name,
                            quantity: Math.round(pivotQty * dealQty * 100) / 100,
                        };
                    });
                },

                addonsTotal(addons) {
                    return this.normalizeAddons(addons).reduce((sum, a) => sum + (a.price * a.quantity), 0);
                },

                computeLineUnitPrice(basePrice, addons) {
                    return this.roundMoney((parseFloat(basePrice) || 0) + this.addonsTotal(addons));
                },

                cartLinesMatch(cartItem, target) {
                    if (cartItem.menu_item_id !== target.menu_item_id) return false;
                    const va = cartItem.variants || {};
                    const vb = target.variants || {};
                    if ((va.variant_id || null) !== (vb.variant_id || null)) return false;
                    if ((va.option_name || '') !== (vb.option_name || '')) return false;
                    return this.addonsSignature(cartItem.addons) === this.addonsSignature(target.addons);
                },

                openAddonPicker(item, cartIndex = null, variantInfo = null, basePrice = null) {
                    if (cartIndex !== null) {
                        const line = this.cart[cartIndex];
                        const menuItem = this.menuItems.find(i => i.id === line.menu_item_id);
                        if (!menuItem || !menuItem.has_addons) return;
                        this.addonModalItem = menuItem;
                        this.addonModalCartIndex = cartIndex;
                        this.addonModalVariantInfo = line.variants || null;
                        this.addonModalBasePrice = line.base_unit_price ?? (parseFloat(line.unit_price) - this.addonsTotal(line.addons));
                        this.addonSelections = {};
                        for (const a of (line.addons || [])) {
                            this.addonSelections[a.id] = parseFloat(a.quantity) || 0;
                        }
                        this.showAddonModal = true;
                        return;
                    }

                    if (!item || !item.has_addons) return;

                    if (cartIndex === null && this.hasVariantOptions(item) && !variantInfo) {
                        this.pendingAddonAfterVariant = true;
                        this.selectedItemForVariant = item;
                        this.showVariantModal = true;
                        return;
                    }

                    this.addonModalItem = item;
                    this.addonModalCartIndex = null;
                    this.addonModalVariantInfo = variantInfo;
                    this.addonModalBasePrice = basePrice ?? parseFloat(item.price);
                    this.addonSelections = {};
                    this.showAddonModal = true;
                },

                closeAddonModal() {
                    this.showAddonModal = false;
                    this.addonModalItem = null;
                    this.addonModalCartIndex = null;
                    this.addonModalVariantInfo = null;
                    this.addonModalBasePrice = 0;
                    this.addonSelections = {};
                    this.pendingAddonAfterVariant = false;
                },

                addonSelectionQty(addonId) {
                    return parseFloat(this.addonSelections[addonId]) || 0;
                },

                incrementAddonSelection(addonId) {
                    this.addonSelections[addonId] = this.addonSelectionQty(addonId) + 1;
                },

                decrementAddonSelection(addonId) {
                    const next = this.addonSelectionQty(addonId) - 1;
                    if (next <= 0) {
                        delete this.addonSelections[addonId];
                    } else {
                        this.addonSelections[addonId] = next;
                    }
                },

                addonModalExtrasTotal() {
                    if (!this.addonModalItem) return 0;
                    let total = 0;
                    for (const addon of (this.addonModalItem.addons || [])) {
                        const qty = this.addonSelectionQty(addon.id);
                        if (qty > 0) total += parseFloat(addon.price) * qty;
                    }
                    return total;
                },

                buildAddonsFromSelections() {
                    if (!this.addonModalItem) return [];
                    const rows = [];
                    for (const addon of (this.addonModalItem.addons || [])) {
                        const qty = this.addonSelectionQty(addon.id);
                        if (qty > 0) {
                            rows.push({
                                id: addon.id,
                                name: addon.name,
                                price: parseFloat(addon.price),
                                code: addon.code || null,
                                quantity: qty,
                            });
                        }
                    }
                    return this.normalizeAddons(rows);
                },

                confirmAddonModal() {
                    const addons = this.buildAddonsFromSelections();
                    const item = this.addonModalItem;
                    const basePrice = this.addonModalBasePrice;
                    const variantInfo = this.addonModalVariantInfo;
                    const cartIndex = this.addonModalCartIndex;

                    if (cartIndex !== null) {
                        const line = this.cart[cartIndex];
                        line.addons = addons;
                        line.base_unit_price = basePrice;
                        line.unit_price = this.computeLineUnitPrice(basePrice, addons);
                        this.closeAddonModal();
                        if (this.paymentMethod === 'card' || this.paymentMethod === 'digital_wallet') {
                            this.paidAmount = this.totalAmount;
                        }
                        return;
                    }

                    this.pushCartLine(item, basePrice, variantInfo, addons);
                    this.closeAddonModal();
                    this.showToast(item.name + ' added to cart', 'success', 1500);
                },
                
                removeFromCart(index) {
                    this.cart.splice(index, 1);
                    if (this.itemNoteEditorIndex === index) {
                        this.itemNoteEditorIndex = null;
                    } else if (this.itemNoteEditorIndex !== null && this.itemNoteEditorIndex > index) {
                        this.itemNoteEditorIndex--;
                    }
                    // Auto-update paid amount for card/digital wallet
                    if (this.paymentMethod === 'card' || this.paymentMethod === 'digital_wallet') {
                        this.paidAmount = this.totalAmount;
                    }
                },

                hasItemNote(item) {
                    return !!(item && item.special_instructions && String(item.special_instructions).trim());
                },

                toggleItemNote(index) {
                    if (this.itemNoteEditorIndex === index) {
                        this.itemNoteEditorIndex = null;
                        return;
                    }
                    this.itemNoteEditorIndex = index;
                    this.$nextTick(() => {
                        const el = document.getElementById('item-note-input-' + index);
                        if (el) {
                            el.focus();
                        }
                    });
                },

                closeItemNote() {
                    this.itemNoteEditorIndex = null;
                },

                clearItemNote(index) {
                    if (this.cart[index]) {
                        this.cart[index].special_instructions = '';
                    }
                    this.itemNoteEditorIndex = null;
                },
                
                updateQuantity(index, newQuantity) {
                    if (newQuantity > 0) {
                        this.cart[index].quantity = newQuantity;
                    } else {
                        this.removeFromCart(index);
                    }
                },
                
                updateCartItem(index) {
                    if (this.cart[index].quantity <= 0) {
                        this.removeFromCart(index);
                    } else {
                        // Auto-update paid amount for card/digital wallet
                        if (this.paymentMethod === 'card' || this.paymentMethod === 'digital_wallet') {
                            this.paidAmount = this.totalAmount;
                        }
                    }
                },
                
                clearCart() {
                    this.cart = [];
                    this.itemNoteEditorIndex = null;
                    this.paidAmount = 0;
                    this.showToast('Cart cleared', 'success');
                },
                
                showToast(message, type = 'info', duration = 3000) {
                    const toastId = Date.now() + Math.random();
                    const toast = {
                        id: toastId,
                        message: message,
                        type: type,
                        duration: duration,
                        visible: true
                    };
                    
                    this.toasts.push(toast);
                    
                    // Auto-remove toast after duration
                    setTimeout(() => {
                        this.removeToast(toastId);
                    }, duration);
                },
                
                removeToast(toastId) {
                    const index = this.toasts.findIndex(t => t.id === toastId);
                    if (index !== -1) {
                        this.toasts[index].visible = false;
                        setTimeout(() => {
                            this.toasts.splice(index, 1);
                        }, 300);
                    }
                },
                
                applyOrderCustomerFields(order) {
                    if (!order) {
                        return;
                    }
                    this.customerName = order.customer_name || '';
                    this.customerPhone = order.customer_phone || '';
                    this.customerEmail = order.customer_email || '';
                    this.customerAddress = order.customer_address || '';
                    this.selectedCustomerId = order.customer_id ? String(order.customer_id) : '';
                },
                hasCustomerAssigned() {
                    return this.hasSelectedCustomer()
                        || (this.customerName || '').trim() !== ''
                        || (this.customerPhone || '').trim() !== '';
                },
                customerSummaryLine() {
                    const parts = [];
                    if ((this.customerName || '').trim()) {
                        parts.push(this.customerName.trim());
                    } else if (this.hasSelectedCustomer()) {
                        parts.push('Customer #' + this.selectedCustomerId);
                    }
                    if ((this.customerPhone || '').trim()) {
                        parts.push(this.customerPhone.trim());
                    }
                    return parts.join(' · ') || 'Customer';
                },
                customerAddressSummary() {
                    return (this.customerAddress || '').trim();
                },
                openCustomerPickerModal(options = {}) {
                    this.customerPickerRequired = !!options.required;
                    if (options.required) {
                        this.customerPickerReason = 'credit';
                    }
                    this.customerSearchQuery = '';
                    this.customerSearchResults = [];
                    this.customerSearchDone = false;
                    this.showCustomerSelectModal = true;
                    this.fetchCustomersForPicker();
                    this.$nextTick(() => {
                        const input = document.getElementById('customer-picker-search');
                        if (input) {
                            input.focus();
                        }
                    });
                },
                customerPickDefaultAddress(customer) {
                    const addrs = customer?.addresses || [];
                    if (addrs.length === 0) {
                        return null;
                    }
                    return addrs.find((a) => a.is_default) || addrs[0];
                },
                customerPrimaryAddressLabel(customer) {
                    const addr = this.customerPickDefaultAddress(customer);
                    if (!addr) {
                        return '—';
                    }
                    return addr.full_address || addr.address_line_1 || '—';
                },
                scheduleCustomerPickerSearch() {
                    if (this.customerPickerDebounceTimer) {
                        clearTimeout(this.customerPickerDebounceTimer);
                    }
                    this.customerPickerDebounceTimer = setTimeout(() => {
                        this.fetchCustomersForPicker();
                    }, 250);
                },
                setWalkInCustomer() {
                    this.clearCustomer();
                },
                openNewCustomerModal(prefill = {}) {
                    this.newCustomerFromDeliveryWizard = !!prefill.fromDeliveryWizard;
                    this.newCustomerName = prefill.name || '';
                    this.newCustomerPhone = prefill.phone || '';
                    this.newCustomerEmail = prefill.email || '';
                    this.newCustomerAddress = prefill.address || '';
                    this.showNewCustomerModal = true;
                    this.$nextTick(() => {
                        const nameInput = document.getElementById('new-customer-name');
                        if (nameInput) {
                            nameInput.focus();
                        }
                    });
                },
                async createNewCustomer() {
                    if (!this.newCustomerName || !this.newCustomerName.trim()) {
                        this.showToast('Name is required.', 'error');
                        return;
                    }
                    if (!this.newCustomerPhone || !this.newCustomerPhone.trim()) {
                        this.showToast('Mobile is required.', 'error');
                        return;
                    }
                    const addressRequired = this.orderType === 'delivery' || this.newCustomerFromDeliveryWizard;
                    if (addressRequired && (!this.newCustomerAddress || !this.newCustomerAddress.trim())) {
                        this.showToast('Address is required for delivery.', 'error');
                        return;
                    }
                    this.newCustomerSaving = true;
                    try {
                        const payload = {
                            name: this.newCustomerName.trim(),
                            phone: this.newCustomerPhone.trim(),
                        };
                        const addressLine = (this.newCustomerAddress || '').trim();
                        if (addressLine) {
                            payload.address = addressLine;
                        }
                        const em = (this.newCustomerEmail || '').trim();
                        if (em) {
                            payload.email = em;
                        }
                        const res = await fetch('{{ route("customers.quick-store") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: JSON.stringify(payload)
                        });
                        const data = await res.json();
                        if (!res.ok) {
                            const err = data.errors ? Object.values(data.errors).flat().join(' ') : (data.message || 'Failed to create customer');
                            this.showToast(err, 'error');
                            return;
                        }
                        if (this.newCustomerFromDeliveryWizard) {
                            this.newCustomerFromDeliveryWizard = false;
                            this.showNewCustomerModal = false;
                            this.customers.push({
                                id: data.id,
                                name: data.name,
                                phone: data.phone || '',
                                email: data.email || em || '',
                                address: data.address || this.newCustomerAddress.trim(),
                            });
                            this.finishDeliverySessionWithDetails(
                                data.id,
                                data.name,
                                data.phone || '',
                                data.address || this.newCustomerAddress.trim(),
                                data.email || em || ''
                            );
                            this.newCustomerName = '';
                            this.newCustomerPhone = '';
                            this.newCustomerEmail = '';
                            this.newCustomerAddress = '';
                            this.showToast('Customer created.', 'success');
                            this.newCustomerSaving = false;
                            return;
                        }
                        this.customers.push({ id: data.id, name: data.name, phone: data.phone || '', email: data.email || '', address: data.address || '' });
                        this.selectedCustomerId = String(data.id);
                        this.customerName = data.name;
                        this.customerPhone = data.phone || '';
                        this.customerEmail = data.email || '';
                        this.customerAddress = data.address || '';
                        this.customerPickerRequired = false;
                        this.customerPickerReason = '';
                        this.showNewCustomerModal = false;
                        this.newCustomerName = '';
                        this.newCustomerPhone = '';
                        this.newCustomerEmail = '';
                        this.newCustomerAddress = '';
                        this.showToast('Customer created.', 'success');
                    } catch (e) {
                        this.showToast('Could not create customer.', 'error');
                    }
                    this.newCustomerSaving = false;
                },
                closeNewCustomerModal() {
                    this.showNewCustomerModal = false;
                    this.newCustomerFromDeliveryWizard = false;
                    this.selectedCustomerId = '';
                    this.newCustomerName = '';
                    this.newCustomerPhone = '';
                    this.newCustomerEmail = '';
                    this.newCustomerAddress = '';
                },
                async fetchCustomersForPicker() {
                    const q = (this.customerSearchQuery || '').trim();
                    this.customerSearchLoading = true;
                    try {
                        const url = '{{ route("customers.search") }}?q=' + encodeURIComponent(q) + '&limit=100';
                        const res = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                        const data = await res.json();
                        this.customerSearchResults = Array.isArray(data) ? data : [];
                        this.customerSearchDone = true;
                    } catch (e) {
                        this.customerSearchResults = [];
                        this.customerSearchDone = true;
                    }
                    this.customerSearchLoading = false;
                },
                selectSearchCustomerWithAddress(customer, address) {
                    this.selectedCustomerId = String(customer.id);
                    this.selectedCustomerBalance = parseFloat(customer.balance) || 0;
                    this.customerName = customer.name || '';
                    this.customerPhone = customer.phone || '';
                    this.customerEmail = customer.email || '';
                    this.customerAddress = address ? (address.full_address || address.address_line_1 || '') : '';
                    this.customerPickerRequired = false;
                    this.customerPickerReason = '';
                    this.closeCustomerSelectModal();
                    this.showToast('Customer selected.', 'success', 2000);
                },
                closeCustomerSelectModal() {
                    if (this.customerPickerRequired && !this.hasSelectedCustomer()) {
                        if (this.isCreditPaymentSelected()) {
                            this.selectDefaultPaymentSource();
                        } else if (this.isCheckoutCreditPaymentSelected()) {
                            this.checkoutPaymentSelection = '';
                            this.checkoutPaymentMethod = 'cash';
                        }
                    }
                    if (this.customerPickerDebounceTimer) {
                        clearTimeout(this.customerPickerDebounceTimer);
                        this.customerPickerDebounceTimer = null;
                    }
                    this.customerPickerRequired = false;
                    this.customerPickerReason = '';
                    this.showCustomerSelectModal = false;
                    this.customerSearchResults = [];
                    this.customerSearchDone = false;
                    this.customerSearchQuery = '';
                },
                clearCustomer() {
                    this.selectedCustomerId = '';
                    this.selectedCustomerBalance = 0;
                    this.customerName = '';
                    this.customerPhone = '';
                    this.customerEmail = '';
                    this.customerAddress = '';
                    this.customerSearchQuery = '';
                    this.customerSearchResults = [];
                    this.customerSearchDone = false;
                    this.showCustomerSelectModal = false;
                },
                openCreateCustomerFromPicker() {
                    const q = (this.customerSearchQuery || '').trim();
                    const prefill = {};
                    if (q.includes('@')) {
                        prefill.email = q;
                    } else if (/^[\d\s\-+()]+$/.test(q)) {
                        prefill.phone = q;
                    } else if (q) {
                        prefill.name = q;
                    }
                    this.showCustomerSelectModal = false;
                    this.openNewCustomerModal(prefill);
                },
                
                loadBranchData() {
                    // Reload page with selected branch to get branch-specific data (tables, money sources, etc.)
                    if (this.selectedBranchId) {
                        window.location.href = '{{ route("pos.index") }}?branch_id=' + this.selectedBranchId;
                    }
                },
                
                calculateTotal() {
                    // Total is calculated automatically via getters
                    // Auto-update paid amount for card/digital wallet
                    if (this.paymentMethod === 'card' || this.paymentMethod === 'digital_wallet') {
                        this.paidAmount = this.totalAmount;
                    }
                },
                
                calculateChange() {
                    // Change is calculated automatically via getter
                },
                
                updateDeliveryFee() {
                    this.updateDiscount();
                },
                
                handleMoneySourceChange() {
                    // Find selected money source (handle both string and number ID comparison)
                    const selectedSource = this.moneySources.find(s => {
                        return s.id == this.moneySourceId || parseInt(s.id) === parseInt(this.moneySourceId);
                    });
                    if (selectedSource) {
                        // Derive payment method from money source type
                        if (selectedSource.type === 'CASH') {
                            this.paymentMethod = 'cash';
                            this.paidAmount = 0; // Reset for cash (user will enter manually)
                        } else if (selectedSource.type === 'BANK') {
                            this.paymentMethod = 'card';
                            this.paidAmount = this.totalAmount; // Auto-fill for card
                        } else if (selectedSource.type === 'APP') {
                            this.paymentMethod = 'digital_wallet';
                            this.paidAmount = this.totalAmount; // Auto-fill for digital wallet
                        }
                    }
                },
                
                handlePaymentMethodChange() {
                    // This method is kept for backward compatibility but may not be used
                    if (this.paymentMethod === 'card' || this.paymentMethod === 'digital_wallet') {
                        // Auto-fill payment amount for card/digital wallet
                        this.paidAmount = this.totalAmount;
                    } else if (this.paymentMethod === 'cash') {
                        // Reset paid amount for cash (user will enter manually)
                        this.paidAmount = 0;
                    }
                },

                hasSelectedCustomer() {
                    const id = parseInt(this.selectedCustomerId, 10);
                    return !isNaN(id) && id > 0 && this.selectedCustomerId !== '__new__';
                },

                customerDisplayName() {
                    if ((this.customerName || '').trim()) {
                        return this.customerName.trim();
                    }
                    return this.hasSelectedCustomer() ? ('Customer #' + this.selectedCustomerId) : '';
                },

                creditDueAmount(paid) {
                    return Math.max(0, this.totalAmount - (parseFloat(paid) || 0));
                },

                customerCreditAvailable() {
                    return Math.max(0, -(parseFloat(this.selectedCustomerBalance) || 0));
                },

                canCoverWithCustomerCredit(paid) {
                    if (!this.hasSelectedCustomer()) {
                        return false;
                    }
                    const shortfall = this.creditDueAmount(paid);
                    if (shortfall <= 0) {
                        return true;
                    }

                    return shortfall <= this.customerCreditAvailable() + 0.001;
                },

                canSubmitPaymentFields() {
                    if (this.isSplitPaymentSelected()) {
                        return false;
                    }
                    if (this.isFocPaymentSelected()) {
                        return this.canPosFoc;
                    }
                    if (this.isCreditPaymentSelected()) {
                        return this.allowPosCreditSales && this.hasSelectedCustomer();
                    }
                    const paid = parseFloat(this.paidAmount) || 0;
                    if (!this.moneySourceId) {
                        return false;
                    }
                    if (this.paymentMethod === 'cash' && (!paid || paid < this.totalAmount)) {
                        return this.canCoverWithCustomerCredit(paid);
                    }

                    if (paid < this.totalAmount) {
                        return this.canCoverWithCustomerCredit(paid);
                    }

                    return true;
                },

                canSubmitBottomBarCheckout() {
                    if (!this.activeOrderId || this.processing || this.checkoutSubmitting) {
                        return false;
                    }
                    if (!this.orderStaffValid()) {
                        return false;
                    }

                    return this.canSubmitPaymentFields();
                },

                canSubmitFulfillmentAction(mode) {
                    if (this.processing || this.checkoutSubmitting) {
                        return false;
                    }
                    if (mode === 'save' || mode === 'kot' || mode === 'kot_bill') {
                        return this.cart.length > 0 && this.orderStaffValid();
                    }
                    if (mode === 'kot_bill_pay') {
                        if (this.cart.length === 0 && !this.activeOrderId) {
                            return false;
                        }
                        if (this.cart.length > 0 && !this.orderStaffValid()) {
                            return false;
                        }
                        if (this.isSplitPaymentSelected()) {
                            return (this.cart.length > 0 || !!this.activeOrderId);
                        }

                        return this.canSubmitPaymentFields();
                    }
                    if (mode === 'print') {
                        return this.cart.length > 0 || !!this.activeOrderId;
                    }
                    if (mode === 'checkout') {
                        if (!this.activeOrderId && this.cart.length === 0) {
                            return false;
                        }
                        if (this.cart.length > 0 && !this.orderStaffValid()) {
                            return false;
                        }
                        if (this.isSplitPaymentSelected()) {
                            return (this.cart.length > 0 || !!this.activeOrderId);
                        }

                        return this.canSubmitPaymentFields();
                    }

                    return false;
                },

                fulfillmentButtonClass(mode, enabledClass) {
                    return this.canSubmitFulfillmentAction(mode)
                        ? enabledClass
                        : 'bg-gray-200 text-gray-400 cursor-not-allowed border border-transparent';
                },

                syncCheckoutFieldsFromBottomBar() {
                    if (this.isFocPaymentSelected()) {
                        this.checkoutPaymentSelection = 'foc';
                        this.checkoutPaymentMethod = 'foc';
                        this.checkoutMoneySourceId = null;
                        this.checkoutPaidAmount = 0;

                        return;
                    }
                    if (this.isCreditPaymentSelected()) {
                        this.checkoutPaymentSelection = 'credit';
                        this.checkoutPaymentMethod = 'credit';
                        this.checkoutMoneySourceId = null;
                        this.checkoutPaidAmount = parseFloat(this.paidAmount) || 0;

                        return;
                    }

                    this.checkoutPaymentSelection = this.paymentSelection || '';
                    this.checkoutMoneySourceId = this.moneySourceId;
                    this.checkoutPaymentMethod = this.paymentMethod;
                    this.checkoutPaidAmount = parseFloat(this.paidAmount) || this.totalAmount;
                },

                validatePaymentForCheckout() {
                    if (this.isSplitPaymentSelected()) {
                        this.openSplitPaymentModal();
                        return false;
                    }
                    if (this.isFocPaymentSelected()) {
                        if (!this.canPosFoc) {
                            this.showToast('You do not have permission to use FOC.', 'error');
                            return false;
                        }
                        return true;
                    }
                    if (this.isCreditPaymentSelected()) {
                        if (!this.allowPosCreditSales) {
                            this.showToast('Credit sales are not enabled.', 'error');
                            return false;
                        }
                        if (!this.hasSelectedCustomer()) {
                            this.customerPickerReason = 'credit';
                            this.openCustomerPickerModal({ required: true });
                            return false;
                        }
                        const creditDue = this.creditDueAmount(parseFloat(this.paidAmount) || 0);
                        if (creditDue <= 0) {
                            this.showToast('Amount received covers the total. Use a cash or bank payment instead.', 'error');
                            return false;
                        }

                        return true;
                    }

                    if (!this.moneySourceId) {
                        this.showToast('Please select a payment source.', 'error');
                        return false;
                    }

                    const paid = parseFloat(this.paidAmount) || 0;
                    if (this.paymentMethod === 'cash') {
                        if (!paid || paid <= 0) {
                            this.showToast('Please enter the amount paid.', 'error');
                            return false;
                        }
                        if (paid < this.totalAmount && !this.canCoverWithCustomerCredit(paid)) {
                            this.showToast('Amount paid must cover the total, select Credit, or use customer advance.', 'error');
                            return false;
                        }
                    }

                    return true;
                },

                async checkoutFromBottomBar() {
                    await this.runPosFulfillment('checkout');
                },

                validateOrderForKitchenAction() {
                    if (this.cart.length === 0) {
                        this.showToast('Add items first.', 'error');
                        return false;
                    }
                    if (this.orderType === 'delivery' && !this.customerAddress) {
                        this.showToast('Select a customer with a delivery address.', 'error');
                        this.openCustomerPickerModal();
                        return false;
                    }
                    const branchId = this.selectedBranchId ? parseInt(this.selectedBranchId, 10) : null;
                    if (!branchId) {
                        this.showToast('Please select a branch.', 'error');
                        return false;
                    }
                    if (this.orderType === 'dine_in' && !this.selectedTableId) {
                        this.showToast('Table is required for dine in.', 'error');
                        return false;
                    }
                    if (!this.validateOrderStaff()) {
                        return false;
                    }

                    return true;
                },

                async ensureOrderSynced() {
                    if (this.orderType === 'delivery' && !this.customerAddress) {
                        this.showToast('Select a customer with a delivery address.', 'error');
                        this.openCustomerPickerModal();
                        return false;
                    }
                    if (this.cart.length === 0 && this.orderType === 'dine_in' && this.selectedTableId) {
                        await this.resumeOpenOrderIfAny();
                    }
                    if (this.cart.length > 0) {
                        if (this.orderType === 'dine_in' && !this.selectedTableId) {
                            this.showToast('Table is required for dine in.', 'error');
                            return false;
                        }
                        if (!this.validateOrderStaff()) {
                            return false;
                        }
                        const saved = await this.saveTab();
                        if (!saved) {
                            return false;
                        }
                    }
                    if (!this.activeOrderId) {
                        this.showToast('Save the order first.', 'error');
                        return false;
                    }

                    return true;
                },

                kitchenSendPayload() {
                    return {
                        type: this.orderType,
                        table_id: this.selectedTableId ? parseInt(this.selectedTableId, 10) : null,
                        ...this.orderStaffPayload(),
                        customer_name: this.customerName,
                        customer_phone: this.customerPhone,
                        customer_email: (this.customerEmail || '').trim() || null,
                        customer_address: this.customerAddress,
                        items: this.posCartItemsPayload(),
                        subtotal: this.subtotal,
                        tax_amount: this.taxAmount,
                        ...this.discountPayload(),
                        service_charge: 0,
                        delivery_fee: this.deliveryFee || 0,
                        total_amount: this.totalAmount,
                        notes: this.notes,
                    };
                },

                async ensurePrintReady(needs) {
                    const branchId = this.selectedBranchId ? parseInt(this.selectedBranchId, 10) : null;
                    if (!branchId) {
                        this.showToast('Select a branch first.', 'error');
                        return false;
                    }
                    try {
                        const params = new URLSearchParams({
                            branch_id: String(branchId),
                            needs: needs.join(','),
                        });
                        const res = await fetch("{{ route('pos.print-readiness') }}?" + params.toString(), {
                            headers: {
                                Accept: 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        });
                        const data = await res.json();
                        if (!res.ok || data.ok === false) {
                            const msg = data.errors?.[0] || data.message || 'Printing is not ready.';
                            this.showToast(msg, 'error');
                            return false;
                        }
                        return true;
                    } catch (e) {
                        this.showToast('Could not check printer setup.', 'error');
                        return false;
                    }
                },

                async printCustomerBill(orderId = null) {
                    const id = orderId ?? this.activeOrderId;
                    if (!id) {
                        return false;
                    }
                    try {
                        const url = '{{ url('/pos/orders') }}/' + id + '/print-receipt';
                        const res = await fetch(url, {
                            method: 'POST',
                            headers: {
                                Accept: 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        });
                        const data = await res.json();
                        if (!res.ok) {
                            this.showToast(data.message || data.errors?.[0] || 'Could not print receipt.', 'error');
                            return false;
                        }
                        if ((data.desktop_jobs || 0) > 0) {
                            this.showToast(data.message || 'Receipt sent for direct print.', 'success');
                        }
                        if (data.browser_print) {
                            this.printOrderReceipt(id);
                        }
                        return true;
                    } catch (e) {
                        this.showToast('Could not print receipt.', 'error');
                        return false;
                    }
                },

                showKitchenSuccessToast(result) {
                    const kots = result.kots || [];
                    if (result.data?.order?.kitchen_kots) {
                        this.applyOrderKitchenKots(result.data.order);
                    } else if (kots.length > 0) {
                        this.mergeKitchenKots(kots);
                    }
                    if (kots.length === 0 && this.orderKitchenKots.length === 0) {
                        return;
                    }
                    this.kitchenStatusPulse = true;
                    let summary = kots.map(k => 'KOT #' + k.kot_number + ' Token #' + k.token_number).join(', ');
                    if (!summary && this.orderKitchenKots.length) {
                        summary = this.kitchenSlipSummary();
                    }
                    if (result.data?.desktop_jobs > 0) {
                        summary += ' · ' + result.data.desktop_jobs + ' sent for direct print';
                    }
                    this.showToast('Kitchen: ' + summary, 'success');
                },

                async executeKitchenSend() {
                    if (!this.validateOrderForKitchenAction()) {
                        return { ok: false };
                    }

                    this.processing = true;
                    try {
                        if (!this.activeOrderId) {
                            const saved = await this.saveTab();
                            if (!saved || !this.activeOrderId) {
                                return { ok: false };
                            }
                            this.processing = true;
                        }

                        const url = '{{ url('/pos/orders') }}/' + this.activeOrderId + '/send-to-kitchen';
                        const res = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                Accept: 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: JSON.stringify(this.kitchenSendPayload()),
                        });
                        const data = await res.json();
                        if (!res.ok) {
                            const msg = data.message || (data.errors ? Object.values(data.errors).flat().join(' ') : 'Kitchen send failed');
                            this.showToast(msg, 'error');
                            return { ok: false };
                        }
                        if (data.order) {
                            this.activeOrderNumber = data.order.order_number || this.activeOrderNumber;
                            this.applyOrderTrackingFields(data.order);
                        }
                        const kots = Array.isArray(data.kots) ? data.kots : [];
                        const kitchenOutOfSync = data.order?.kitchen_sync
                            && !data.order.kitchen_sync.in_sync
                            && (data.order.kitchen_sync.sent_items?.length || 0) > 0;
                        if (kots.length === 0 && kitchenOutOfSync) {
                            this.showToast('Kitchen is out of sync with the bill. Save the order, then send KOT again.', 'error', 6000);
                            return { ok: false };
                        }
                        const browserKotIds = Array.isArray(data.browser_kot_ids) ? data.browser_kot_ids : [];
                        if (browserKotIds.length > 0) {
                            for (let i = 0; i < browserKotIds.length; i++) {
                                this.directPrintKitchenKot(browserKotIds[i]);
                                if (i < browserKotIds.length - 1) {
                                    await new Promise(resolve => setTimeout(resolve, 700));
                                }
                            }
                        } else if ((data.desktop_jobs || 0) > 0 && kots.length > 0) {
                            // Direct print only — no browser dialog
                        }

                        return {
                            ok: true,
                            noChanges: kots.length === 0,
                            kots,
                            data,
                            message: data.message || 'No kitchen changes to print.',
                        };
                    } catch (e) {
                        this.showToast('Kitchen send failed.', 'error');
                        return { ok: false };
                    } finally {
                        this.processing = false;
                    }
                },

                async runPosFulfillment(mode) {
                    if (mode === 'save') {
                        await this.saveTab();
                        return;
                    }

                    if (mode === 'print') {
                        if (!await this.ensureOrderSynced()) {
                            return;
                        }
                        if (!await this.ensurePrintReady(['receipt'])) {
                            return;
                        }
                        await this.printCustomerBill();
                        return;
                    }

                    if (mode === 'checkout') {
                        if (!await this.ensureOrderSynced()) {
                            return;
                        }
                        if (this.shouldAutoBillOnCheckout() && !await this.ensurePrintReady(['receipt', 'kitchen'])) {
                            return;
                        }
                        if (this.isSplitPaymentSelected()) {
                            this.openSplitPaymentModal();
                            return;
                        }
                        if (!this.validatePaymentForCheckout()) {
                            return;
                        }
                        this.syncCheckoutFieldsFromBottomBar();
                        await this.submitCheckout();
                        return;
                    }

                    if (!this.validateOrderForKitchenAction()) {
                        return;
                    }

                    const kitchenNeeds = ['kitchen'];
                    const receiptNeeds = ['receipt'];
                    if (mode === 'kot_bill' || mode === 'kot_bill_pay') {
                        if (!await this.ensurePrintReady([...kitchenNeeds, ...receiptNeeds])) {
                            return;
                        }
                    } else if (!await this.ensurePrintReady(kitchenNeeds)) {
                        return;
                    }

                    const kitchen = await this.executeKitchenSend();
                    if (!kitchen.ok) {
                        return;
                    }

                    if (mode === 'kot') {
                        if (kitchen.noChanges) {
                            this.showToast(kitchen.message, 'info');
                        } else {
                            this.showKitchenSuccessToast(kitchen);
                        }
                        return;
                    }

                    if (mode === 'kot_bill') {
                        if (!kitchen.noChanges) {
                            this.showKitchenSuccessToast(kitchen);
                        }
                        await this.printCustomerBill();
                        return;
                    }

                    if (mode === 'kot_bill_pay') {
                        if (!kitchen.noChanges) {
                            this.showKitchenSuccessToast(kitchen);
                        }
                        if (this.isSplitPaymentSelected()) {
                            this.openSplitPaymentModal();
                            return;
                        }
                        if (!this.validatePaymentForCheckout()) {
                            return;
                        }
                        this.syncCheckoutFieldsFromBottomBar();
                        await this.submitCheckout();
                    }
                },

                posCartItemsPayload() {
                    return this.cart.map(item => ({
                        deal_id: item.deal_id ?? null,
                        menu_item_id: item.menu_item_id ?? null,
                        item_name: (item.item_name || item.name || 'Item').trim(),
                        name: (item.name || item.item_name || 'Item').trim(),
                        quantity: item.quantity,
                        unit_price: item.unit_price,
                        variants: item.variants ?? null,
                        addons: item.addons && item.addons.length ? item.addons : null,
                        special_instructions: item.special_instructions || '',
                    }));
                },

                async pickServiceType(t) {
                    this.orderType = t;
                    if (t === 'delivery') {
                        this.sessionStep = 3;
                        this.activeOrderId = null;
                        this.activeOrderNumber = '';
                        this.cart = [];
                        this.deliveryWizardView = 'search';
                        this.deliverySearchQuery = '';
                        this.deliverySearchResults = [];
                        this.deliverySearchDone = false;
                        this.deliveryPendingCustomer = null;
                        this.deliveryManualAddress = '';
                        this.fetchDeliveryCustomers();
                        return;
                    }
                    if (t !== 'dine_in') {
                        this.selectedTableId = null;
                        this.sessionStep = 1;
                        this.activeOrderId = null;
                        this.activeOrderNumber = '';
                        this.cart = [];
                        this.posSessionReady = true;
                        this.activePosNav = 'pos';
                    } else {
                        this.sessionStep = 2;
                        this.selectedTableId = null;
                        await this.refreshSessionFloors();
                        this.selectFirstFloorForSession();
                    }
                },
                floorSessionId(floor) {
                    if (!floor) {
                        return '';
                    }
                    return floor.id === null || floor.id === undefined ? 'none' : floor.id;
                },
                selectFirstFloorForSession() {
                    if (this.floors && this.floors.length > 0) {
                        this.selectedFloorForSessionId = this.floorSessionId(this.floors[0]);
                    } else {
                        this.selectedFloorForSessionId = '';
                    }
                },
                selectFloorForTableId(tableId) {
                    if (!tableId || !this.floors || !this.floors.length) {
                        this.selectedFloorForSessionId = '';

                        return;
                    }
                    const tid = Number(tableId);
                    for (let i = 0; i < this.floors.length; i++) {
                        const f = this.floors[i];
                        if (f.tables && f.tables.some(t => Number(t.id) === tid)) {
                            this.selectedFloorForSessionId = this.floorSessionId(f);

                            return;
                        }
                    }
                    this.selectedFloorForSessionId = '';
                },
                async openOrderQueueModal(channel) {
                    const branchId = this.selectedBranchId ? parseInt(this.selectedBranchId, 10) : null;
                    if (!branchId) {
                        this.showToast('Select a branch.', 'error');
                        return;
                    }
                    this.activePosNav = channel;
                    this.orderQueueChannel = channel;
                    this.showOrderQueueModal = true;
                    this.showKitchenQueueModal = false;
                    this.showTableViewModal = false;
                    this.orderQueueList = [];
                    if (channel === 'today') {
                        this.orderQueueHistoryTypeFilter = 'all';
                        this.orderQueueHistorySearch = '';
                    }
                    await this.loadOrderQueueData();
                },
                async openKitchenQueueModal() {
                    if (!this.addonKitchenTracking) {
                        return;
                    }
                    const branchId = this.selectedBranchId ? parseInt(this.selectedBranchId, 10) : null;
                    if (!branchId) {
                        this.showToast('Select a branch.', 'error');
                        return;
                    }
                    this.activePosNav = 'kitchen';
                    this.showKitchenQueueModal = true;
                    this.showOrderQueueModal = false;
                    this.showTableViewModal = false;
                    this.kitchenQueueList = [];
                    await this.loadKitchenQueueData();
                },
                async loadKitchenQueueData() {
                    const branchId = this.selectedBranchId ? parseInt(this.selectedBranchId, 10) : null;
                    if (!branchId || !this.addonKitchenTracking) {
                        return;
                    }
                    this.kitchenQueueBusy = true;
                    try {
                        const url = "{{ route('pos.kitchen-queue') }}?branch_id=" + branchId;
                        const res = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                        const data = await res.json();
                        if (data.success && Array.isArray(data.kots)) {
                            this.kitchenQueueList = data.kots;
                        } else {
                            this.showToast(data.message || 'Could not load kitchen queue.', 'error');
                        }
                    } catch (e) {
                        this.showToast('Could not load kitchen queue.', 'error');
                    }
                    this.kitchenQueueBusy = false;
                },
                kitchenQueueTypeBadgeClass(kot) {
                    if (kot?.type === 'void') {
                        return 'bg-red-100 text-red-800';
                    }
                    if (kot?.type === 'add') {
                        return 'bg-sky-100 text-sky-800';
                    }
                    return 'bg-emerald-100 text-emerald-800';
                },
                async loadOrderQueueData() {
                    const branchId = this.selectedBranchId ? parseInt(this.selectedBranchId, 10) : null;
                    const channel = this.orderQueueChannel;
                    if (!branchId || !channel) {
                        return;
                    }
                    this.orderQueueBusy = true;
                    try {
                        const url = channel === 'today'
                            ? "{{ route('pos.today-orders') }}?branch_id=" + branchId + '&date=' + encodeURIComponent(this.orderQueueHistoryDate || '')
                            : "{{ route('pos.channel-orders') }}?branch_id=" + branchId + '&channel=' + encodeURIComponent(channel);
                        const res = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                        const data = await res.json();
                        if (data.success && Array.isArray(data.orders)) {
                            this.orderQueueList = data.orders;
                            if (data.timezone) {
                                this.orderQueueHistoryTimezone = data.timezone;
                            }
                        } else {
                            this.showToast(data.message || 'Could not load orders.', 'error');
                        }
                    } catch (e) {
                        this.showToast('Could not load orders.', 'error');
                    }
                    this.orderQueueBusy = false;
                },
                orderQueueTitle() {
                    if (this.orderQueueChannel === 'dine_in') {
                        return 'Saved / Dine-in';
                    }
                    if (this.orderQueueChannel === 'takeaway') {
                        return 'Takeaway';
                    }
                    if (this.orderQueueChannel === 'delivery') {
                        return 'Delivery';
                    }
                    if (this.orderQueueChannel === 'today') {
                        return this.isOrderQueueHistoryToday() ? 'Today (history)' : 'Order history';
                    }
                    return 'Orders';
                },
                orderQueueHistoryMaxDate() {
                    return this.posTodayDateString();
                },
                posTimezone() {
                    if (this.orderQueueHistoryTimezone) {
                        return this.orderQueueHistoryTimezone;
                    }
                    const branchId = this.selectedBranchId ? parseInt(this.selectedBranchId, 10) : null;
                    const key = branchId !== null ? String(branchId) : null;
                    if (key && this.branchTimezones && this.branchTimezones[key]) {
                        return this.branchTimezones[key];
                    }
                    if (key && this.branchTimezones && this.branchTimezones[branchId]) {
                        return this.branchTimezones[branchId];
                    }
                    return this.companyTimezone || 'UTC';
                },
                posTodayDateString() {
                    return new Intl.DateTimeFormat('en-CA', { timeZone: this.posTimezone() }).format(new Date());
                },
                formatInPosTimezone(iso, options = {}) {
                    if (!iso) {
                        return '';
                    }
                    const d = new Date(iso);
                    if (isNaN(d.getTime())) {
                        return '';
                    }
                    return new Intl.DateTimeFormat(undefined, {
                        timeZone: this.posTimezone(),
                        ...options,
                    }).format(d);
                },
                isOrderQueueHistoryToday() {
                    return this.orderQueueHistoryDate === this.posTodayDateString();
                },
                formatOrderQueueHistoryDateLabel() {
                    if (!this.orderQueueHistoryDate) {
                        return 'today';
                    }
                    if (this.isOrderQueueHistoryToday()) {
                        return 'today';
                    }
                    const d = new Date(this.orderQueueHistoryDate + 'T12:00:00');
                    if (isNaN(d.getTime())) {
                        return this.orderQueueHistoryDate;
                    }
                    return new Intl.DateTimeFormat(undefined, {
                        timeZone: this.posTimezone(),
                        weekday: 'short',
                        month: 'short',
                        day: 'numeric',
                        year: 'numeric',
                    }).format(d);
                },
                orderQueueSubtitle() {
                    if (this.orderQueueChannel === 'dine_in') {
                        return 'Open unpaid dine-in tabs — tap to load in POS';
                    }
                    if (this.orderQueueChannel === 'takeaway') {
                        return 'Open unpaid takeaway orders';
                    }
                    if (this.orderQueueChannel === 'delivery') {
                        return 'Pending and in-progress delivery orders';
                    }
                    if (this.orderQueueChannel === 'today') {
                        return 'Orders for ' + this.formatOrderQueueHistoryDateLabel();
                    }
                    return '';
                },
                orderQueueEmptyMessage() {
                    if (this.orderQueueChannel === 'dine_in') {
                        return 'No saved dine-in tabs for this branch.';
                    }
                    if (this.orderQueueChannel === 'takeaway') {
                        return 'No open takeaway orders for this branch.';
                    }
                    if (this.orderQueueChannel === 'delivery') {
                        return 'No active delivery orders for this branch.';
                    }
                    if (this.orderQueueChannel === 'today') {
                        return 'No orders for ' + this.formatOrderQueueHistoryDateLabel() + ' for this branch.';
                    }
                    return 'No orders found.';
                },
                canOpenOrderQueueRow(row) {
                    if (!row || row.payment_status !== 'unpaid') {
                        return false;
                    }
                    const activeStatuses = this.addonKitchenTracking
                        ? ['open', 'placed', 'preparing', 'ready', 'served', 'out_for_delivery', 'delivered']
                        : ['open', 'placed'];

                    return activeStatuses.includes(row.status);
                },
                orderQueueFilteredList() {
                    let list = this.orderQueueList || [];
                    if (this.orderQueueChannel !== 'today') {
                        return list;
                    }
                    if (this.orderQueueHistoryTypeFilter && this.orderQueueHistoryTypeFilter !== 'all') {
                        list = list.filter(row => row.type === this.orderQueueHistoryTypeFilter);
                    }
                    const query = (this.orderQueueHistorySearch || '').trim().toLowerCase();
                    if (query) {
                        list = list.filter(row => String(row.order_number || '').toLowerCase().includes(query));
                    }
                    return list;
                },
                orderQueueHistoryBreakdown() {
                    const types = {
                        dine_in: { count: 0, amount: 0 },
                        takeaway: { count: 0, amount: 0 },
                        delivery: { count: 0, amount: 0 },
                    };
                    const total = { count: 0, amount: 0 };
                    (this.orderQueueList || []).forEach(row => {
                        const bucket = types[row.type];
                        if (!bucket) {
                            return;
                        }
                        const amount = parseFloat(row.total_amount) || 0;
                        bucket.count++;
                        bucket.amount += amount;
                        total.count++;
                        total.amount += amount;
                    });
                    return { types, total };
                },
                orderQueueHistoryTypeRows() {
                    return ['dine_in', 'takeaway', 'delivery']
                        .map(key => ({
                            key,
                            ...this.orderQueueHistoryBreakdown().types[key],
                        }))
                        .filter(row => row.count > 0);
                },
                orderQueueHistoryHasActiveFilters() {
                    return this.orderQueueChannel === 'today'
                        && (this.orderQueueHistoryTypeFilter !== 'all' || (this.orderQueueHistorySearch || '').trim() !== '');
                },
                orderQueueHistoryFilteredEmptyMessage() {
                    if (this.orderQueueChannel !== 'today') {
                        return 'No orders to show.';
                    }
                    if (this.orderQueueList.length > 0 && this.orderQueueHistoryHasActiveFilters()) {
                        return 'No orders match your filters.';
                    }
                    return this.orderQueueEmptyMessage();
                },
                orderQueueSummary() {
                    const list = this.orderQueueList || [];
                    let serving = 0;
                    list.forEach(row => {
                        if (this.canOpenOrderQueueRow(row)) {
                            serving++;
                        }
                    });
                    return {
                        total: list.length,
                        serving,
                        done: list.length - serving,
                    };
                },
                orderQueueCardClass(row) {
                    if (this.orderQueueChannel === 'today') {
                        return 'border-emerald-200 bg-emerald-50/50';
                    }
                    if (this.canOpenOrderQueueRow(row)) {
                        return 'border-amber-400 bg-amber-50/80';
                    }
                    return 'border-gray-200 bg-gray-50/80 opacity-90';
                },
                orderQueueCardHeading(row) {
                    if (this.orderQueueChannel === 'dine_in') {
                        if (row.table?.name) {
                            return 'Table ' + row.table.name;
                        }
                        if (row.customer_name) {
                            return row.customer_name;
                        }
                        return 'Walk-in';
                    }
                    if (row.customer_name) {
                        return row.customer_name;
                    }
                    return row.order_number || 'Order';
                },
                orderQueueCardMeta(row) {
                    if (this.orderQueueChannel === 'dine_in') {
                        return row.table?.name ? 'Dine-in tab' : 'No table assigned';
                    }
                    if (this.orderQueueChannel === 'takeaway') {
                        return 'Takeaway order';
                    }
                    if (this.orderQueueChannel === 'delivery') {
                        return 'Delivery order';
                    }
                    return this.resumeOrderTypeLabel(row.type);
                },
                orderQueueCardIcon(row) {
                    if (this.orderQueueChannel === 'dine_in') {
                        return row.table?.name ? 'fa-chair' : 'fa-utensils';
                    }
                    if (this.orderQueueChannel === 'takeaway') {
                        return 'fa-shopping-bag';
                    }
                    if (this.orderQueueChannel === 'delivery') {
                        return 'fa-motorcycle';
                    }
                    if (row.type === 'dine_in') {
                        return 'fa-utensils';
                    }
                    if (row.type === 'takeaway') {
                        return 'fa-shopping-bag';
                    }
                    if (row.type === 'delivery') {
                        return 'fa-motorcycle';
                    }
                    return 'fa-receipt';
                },
                orderQueueOrderAge(row) {
                    if (this.orderQueueChannel === 'today') {
                        if (row.completed_at_display) {
                            return 'Paid ' + row.completed_at_display;
                        }
                        if (row.created_at_display) {
                            return row.created_at_display;
                        }
                    }
                    const stamp = this.orderQueueChannel === 'today'
                        ? (row.completed_at || row.updated_at || row.created_at)
                        : (row.updated_at || row.created_at);
                    if (!stamp) {
                        return '';
                    }
                    if (this.orderQueueChannel === 'today') {
                        return this.formatInPosTimezone(stamp, {
                            month: 'short',
                            day: 'numeric',
                            hour: 'numeric',
                            minute: '2-digit',
                        });
                    }
                    const d = new Date(stamp);
                    if (isNaN(d.getTime())) {
                        return '';
                    }
                    const mins = Math.max(0, Math.floor((Date.now() - d.getTime()) / 60000));
                    if (mins < 1) {
                        return 'Just now';
                    }
                    if (mins < 60) {
                        return mins + ' min ago';
                    }
                    const hrs = Math.floor(mins / 60);
                    return hrs + 'h ' + (mins % 60) + 'm ago';
                },
                async viewOrderQueueBill(row) {
                    if (!row?.id) {
                        return;
                    }
                    if (!await this.ensurePrintReady(['receipt'])) {
                        return;
                    }
                    await this.printCustomerBill(row.id);
                },
                viewOrderQueueDetails(row) {
                    if (!row?.id) {
                        return;
                    }
                    this.loadOrderDetails(row.id);
                },
                async loadOrderDetails(orderId) {
                    if (!orderId) {
                        return;
                    }
                    this.showOrderDetailsModal = true;
                    this.loadingOrderDetails = true;
                    this.orderDetailsData = null;
                    try {
                        const url = '{{ url('/pos/orders') }}/' + orderId + '/details';
                        const res = await fetch(url, {
                            headers: {
                                Accept: 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        });
                        const data = await res.json();
                        if (!res.ok || !data.success || !data.order) {
                            this.showToast(data.message || 'Could not load order details.', 'error');
                            this.showOrderDetailsModal = false;
                            return;
                        }
                        this.orderDetailsData = data.order;
                    } catch (e) {
                        this.showToast('Could not load order details.', 'error');
                        this.showOrderDetailsModal = false;
                    } finally {
                        this.loadingOrderDetails = false;
                    }
                },
                closeOrderDetailsModal() {
                    this.showOrderDetailsModal = false;
                    this.orderDetailsData = null;
                    this.loadingOrderDetails = false;
                },
                orderDetailsTypeLabel(order) {
                    if (!order?.type) {
                        return '—';
                    }
                    return order.type.replace(/_/g, ' ').replace(/\b\w/g, (l) => l.toUpperCase());
                },
                orderDetailsStatusLabel(order) {
                    if (!order) {
                        return '—';
                    }
                    const label = this.orderStatusLabel(order.status, order.type);
                    const pay = order.payment_status ? String(order.payment_status).charAt(0).toUpperCase() + String(order.payment_status).slice(1) : '';
                    return label + (pay ? ' · ' + pay : '');
                },
                orderDetailsPaymentLabel(order) {
                    if (!order) {
                        return '—';
                    }
                    if (order.payment_method === 'split') {
                        return 'Split payment';
                    }
                    if (!order.payment_method) {
                        return order.payment_status === 'unpaid' ? 'Pending' : '—';
                    }
                    return String(order.payment_method).replace(/_/g, ' ').replace(/\b\w/g, (l) => l.toUpperCase());
                },
                orderDetailsCustomerLabel(order) {
                    if (!order) {
                        return '—';
                    }
                    const name = (order.customer_name || '').trim();
                    if (name) {
                        return name;
                    }
                    return 'Walk-in customer';
                },
                orderDetailsItemCount(order) {
                    if (!order?.items?.length) {
                        return 0;
                    }
                    return order.items.reduce((sum, item) => sum + (parseFloat(item.quantity) || 0), 0);
                },
                orderDetailsKotLines(kot) {
                    if (!kot?.lines?.length) {
                        return '—';
                    }
                    return kot.lines
                        .map((line) => `${line.quantity || 0}×${line.item_name || 'Item'}`)
                        .join(', ');
                },
                orderDetailsKitchenSentLabel(data) {
                    const items = data?.kitchen_sync?.sent_items || [];
                    if (!items.length) {
                        return 'Nothing sent yet';
                    }
                    return items
                        .map((item) => `${item.quantity}×${item.item_name}`)
                        .join(', ');
                },
                orderDetailsDateTime(value) {
                    if (!value) {
                        return '—';
                    }
                    return new Date(value).toLocaleString('en-GB', {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit',
                    });
                },
                canReprintOrderQueueKot(row) {
                    if (!row?.id) {
                        return false;
                    }
                    // History tab only: no reprint (order already paid / closed)
                    if (this.showOrderQueueModal && this.orderQueueChannel === 'today') {
                        return false;
                    }
                    // Show on all active queues (Dine-in, Takeaway, Delivery) and Tables
                    // for any unpaid / partial order — even before first KOT (click shows a clear toast).
                    return row.payment_status !== 'paid' && row.payment_status !== 'refunded';
                },
                canCancelOrderQueueRow(row) {
                    if (!row?.id) {
                        return false;
                    }
                    if (row.payment_status === 'refunded') {
                        return false;
                    }
                    // Today history: completed orders can be cancelled (reversed/deleted)
                    if (this.showOrderQueueModal && this.orderQueueChannel === 'today') {
                        return true;
                    }
                    return row.payment_status !== 'paid';
                },
                async cancelOrderQueueRow(row) {
                    if (!row?.id || this.orderQueueCancellingId) {
                        return;
                    }
                    const label = row.order_number || ('#' + row.id);
                    const isHistory = this.showOrderQueueModal && this.orderQueueChannel === 'today';
                    const confirmMsg = isHistory
                        ? ('Cancel order ' + label + '? Stock and payments will be reversed. This cannot be undone.')
                        : ('Cancel order ' + label + '? This cannot be undone.');
                    if (!confirm(confirmMsg)) {
                        return;
                    }
                    this.orderQueueCancellingId = row.id;
                    try {
                        const url = '{{ url('/pos/orders') }}/' + row.id + '/cancel';
                        const res = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                Accept: 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        });
                        const data = await res.json().catch(() => ({}));
                        if (!res.ok) {
                            this.showToast(data.message || 'Could not cancel order.', 'error');
                            return;
                        }
                        this.showToast(data.message || 'Order cancelled.', 'success');
                        const browserKotIds = Array.isArray(data.browser_kot_ids) ? data.browser_kot_ids : [];
                        for (let i = 0; i < browserKotIds.length; i++) {
                            this.directPrintKitchenKot(browserKotIds[i], false, true);
                            if (i < browserKotIds.length - 1) {
                                await new Promise(resolve => setTimeout(resolve, 700));
                            }
                        }
                        if (this.activeOrderId === row.id) {
                            this.activeOrderId = null;
                            this.activeOrderNumber = '';
                            this.cart = [];
                            this.orderKitchenKots = [];
                            this.orderWorkflowStatus = 'open';
                            this.orderAllowedNextStatuses = [];
                            this.paidAmount = 0;
                        }
                        this.orderQueueList = this.orderQueueList.filter((r) => r.id !== row.id);
                        if (this.showOrderQueueModal) {
                            await this.loadOrderQueueData();
                        }
                        if (this.showTableViewModal) {
                            await this.loadTableView();
                        }
                        if (this.showKitchenQueueModal) {
                            await this.loadKitchenQueueData();
                        }
                    } catch (e) {
                        this.showToast('Could not cancel order.', 'error');
                    } finally {
                        this.orderQueueCancellingId = null;
                    }
                },
                async reprintOrderQueueKot(row) {
                    if (!row?.id || this.orderQueueKotReprintingId) {
                        return;
                    }
                    this.orderQueueKotReprintingId = row.id;
                    try {
                        const url = '{{ url('/pos/orders') }}/' + row.id + '/reprint-kot';
                        const res = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                Accept: 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        });
                        const data = await res.json();
                        if (!res.ok) {
                            this.showToast(data.message || 'KOT reprint failed.', 'error');
                            return;
                        }
                        this.showToast(data.message || 'KOT reprint sent.', 'success');
                        const browserKotIds = Array.isArray(data.browser_kot_ids) ? data.browser_kot_ids : [];
                        for (let i = 0; i < browserKotIds.length; i++) {
                            this.directPrintKitchenKot(browserKotIds[i], true);
                            if (i < browserKotIds.length - 1) {
                                await new Promise(resolve => setTimeout(resolve, 700));
                            }
                        }
                        if ((data.desktop_jobs || 0) > 0 && browserKotIds.length === 0) {
                            // Direct print only
                        }
                    } catch (e) {
                        this.showToast('KOT reprint failed.', 'error');
                    } finally {
                        this.orderQueueKotReprintingId = null;
                    }
                },
                orderQueueRowBadgeLabel(row) {
                    if (this.orderQueueChannel === 'today') {
                        return 'Done';
                    }
                    if (row.status === 'open' && row.payment_status === 'unpaid') {
                        return 'Serving';
                    }
                    if (row.status === 'placed' && row.payment_status === 'unpaid') {
                        return 'Kitchen';
                    }
                    if (row.type === 'delivery' && row.status === 'out_for_delivery') {
                        return 'On the way';
                    }
                    if (row.type === 'delivery' && row.status === 'delivered') {
                        return 'Delivered';
                    }
                    return this.orderStatusLabel(row.status, row.type);
                },
                orderQueueRowBadgeClass(row) {
                    if (this.orderQueueChannel === 'today') {
                        return 'bg-emerald-100 text-emerald-800';
                    }
                    if (row.status === 'open' && row.payment_status === 'unpaid') {
                        return 'bg-amber-100 text-amber-800';
                    }
                    if (row.status === 'placed' && row.payment_status === 'unpaid') {
                        return 'bg-orange-100 text-orange-900';
                    }
                    return 'bg-sky-100 text-sky-800';
                },
                orderStatusLabel(status, orderType) {
                    const type = orderType || this.orderType;
                    const shared = {
                        open: 'Serving',
                        placed: 'Sent to kitchen',
                        pending: 'Pending',
                        confirmed: 'Confirmed',
                        preparing: 'Preparing',
                        ready: 'Ready',
                        completed: 'Done',
                        cancelled: 'Cancelled',
                    };

                    if (type === 'delivery') {
                        const delivery = {
                            ...shared,
                            out_for_delivery: 'On the way',
                            delivered: 'Delivered',
                            served: 'Delivered',
                        };

                        return delivery[status] || status || '';
                    }

                    const dineInTakeaway = {
                        ...shared,
                        served: type === 'takeaway' ? 'Picked up' : 'Served',
                        out_for_delivery: 'On the way',
                        delivered: 'Delivered',
                    };

                    return dineInTakeaway[status] || status || '';
                },
                applyOrderKitchenKots(order) {
                    if (!order || !Array.isArray(order.kitchen_kots)) {
                        this.orderKitchenKots = [];
                        return;
                    }
                    this.orderKitchenKots = order.kitchen_kots
                        .slice()
                        .sort((a, b) => (Number(a.kot_number) || 0) - (Number(b.kot_number) || 0));
                },
                mergeKitchenKots(newKots) {
                    const byId = new Map(this.orderKitchenKots.map((kot) => [kot.id, kot]));
                    for (const kot of newKots) {
                        const existing = byId.get(kot.id) || {};
                        byId.set(kot.id, { ...existing, ...kot });
                    }
                    this.orderKitchenKots = [...byId.values()]
                        .sort((a, b) => (Number(a.kot_number) || 0) - (Number(b.kot_number) || 0));
                },
                kitchenSlipSummary() {
                    const kots = this.orderKitchenKots;
                    if (!kots.length) {
                        return '';
                    }
                    if (kots.length === 1) {
                        return 'KOT #' + kots[0].kot_number + ' · Token #' + kots[0].token_number;
                    }
                    const latest = kots[kots.length - 1];
                    return kots.length + ' tickets · latest KOT #' + latest.kot_number;
                },
                applyOrderTrackingFields(order) {
                    if (!this.addonKitchenTracking || !order) {
                        this.orderWorkflowStatus = 'open';
                        this.orderAllowedNextStatuses = [];
                        this.orderExpectedReadyAt = null;
                        this.orderStatusLogs = [];
                        this.orderKitchenKots = [];
                        return;
                    }
                    this.orderWorkflowStatus = order.status || 'open';
                    const tracking = order.tracking || {};
                    this.orderAllowedNextStatuses = Array.isArray(tracking.allowed_next_statuses)
                        ? tracking.allowed_next_statuses
                        : [];
                    this.orderExpectedReadyAt = tracking.expected_ready_at || order.expected_ready_at || null;
                    this.orderStatusLogs = Array.isArray(tracking.status_logs) ? tracking.status_logs : [];
                    this.applyOrderKitchenKots(order);
                },
                formatExpectedReady(iso) {
                    if (!iso) {
                        return '—';
                    }
                    const d = new Date(iso);
                    if (isNaN(d.getTime())) {
                        return '—';
                    }
                    return d.toLocaleString(undefined, {
                        month: 'short',
                        day: 'numeric',
                        hour: 'numeric',
                        minute: '2-digit',
                    });
                },
                openKitchenStatusModal() {
                    this.kitchenStatusPulse = false;
                    this.showKitchenStatusModal = true;
                },
                closeKitchenStatusModal() {
                    this.showKitchenStatusModal = false;
                },
                kitchenStatusSequence() {
                    const type = this.orderType;
                    if (type === 'delivery') {
                        return [
                            { key: 'placed', label: 'Sent to kitchen' },
                            { key: 'preparing', label: 'Preparing' },
                            { key: 'ready', label: 'Ready' },
                            { key: 'out_for_delivery', label: 'On the way' },
                            { key: 'delivered', label: 'Delivered' },
                        ];
                    }
                    return [
                        { key: 'placed', label: 'Sent to kitchen' },
                        { key: 'preparing', label: 'Preparing' },
                        { key: 'ready', label: 'Ready' },
                        { key: 'served', label: type === 'takeaway' ? 'Picked up' : 'Served' },
                    ];
                },
                isKitchenStepActive(key) {
                    return this.orderWorkflowStatus === key;
                },
                isKitchenStepComplete(key) {
                    const seq = this.kitchenStatusSequence().map((step) => step.key);
                    const current = seq.indexOf(this.orderWorkflowStatus);
                    const step = seq.indexOf(key);
                    if (step === -1 || this.orderWorkflowStatus === 'open') {
                        return false;
                    }
                    if (current === -1) {
                        return ['served', 'delivered', 'completed'].includes(this.orderWorkflowStatus);
                    }
                    return current > step;
                },
                orderStatusBadgeClass(status) {
                    const map = {
                        open: 'bg-gray-100 text-gray-800',
                        placed: 'bg-orange-100 text-orange-900',
                        preparing: 'bg-amber-100 text-amber-900',
                        ready: 'bg-emerald-100 text-emerald-900',
                        served: 'bg-indigo-100 text-indigo-800',
                        out_for_delivery: 'bg-violet-100 text-violet-900',
                        delivered: 'bg-emerald-100 text-emerald-800',
                        completed: 'bg-gray-100 text-gray-700',
                        cancelled: 'bg-red-100 text-red-800',
                    };
                    return map[status] || 'bg-gray-100 text-gray-800';
                },
                kitchenActionButtonClass(nextStatus) {
                    const map = {
                        placed: 'border-orange-300 bg-orange-50 text-orange-900 hover:bg-orange-100',
                        preparing: 'border-amber-300 bg-amber-50 text-amber-900 hover:bg-amber-100',
                        ready: 'border-emerald-300 bg-emerald-50 text-emerald-900 hover:bg-emerald-100',
                        served: 'border-indigo-300 bg-indigo-50 text-indigo-900 hover:bg-indigo-100',
                        out_for_delivery: 'border-violet-300 bg-violet-50 text-violet-900 hover:bg-violet-100',
                        delivered: 'border-emerald-300 bg-emerald-50 text-emerald-900 hover:bg-emerald-100',
                    };
                    return map[nextStatus] || 'border-sky-300 bg-white text-sky-900 hover:bg-sky-50';
                },
                formatStatusLogTime(iso) {
                    if (!iso) {
                        return '';
                    }
                    const d = new Date(iso);
                    if (isNaN(d.getTime())) {
                        return '';
                    }
                    return d.toLocaleString(undefined, {
                        month: 'short',
                        day: 'numeric',
                        hour: 'numeric',
                        minute: '2-digit',
                    });
                },
                async advanceOrderStatus(nextStatus) {
                    if (!this.activeOrderId || !nextStatus) {
                        return;
                    }
                    this.orderStatusUpdating = true;
                    try {
                        const url = '{{ url('/pos/orders') }}/' + this.activeOrderId + '/status';
                        const res = await fetch(url, {
                            method: 'PATCH',
                            headers: {
                                'Content-Type': 'application/json',
                                Accept: 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: JSON.stringify({ status: nextStatus }),
                        });
                        const data = await res.json();
                        if (!res.ok) {
                            this.showToast(data.message || 'Could not update status.', 'error');
                            return;
                        }
                        if (data.order) {
                            this.applyOrderTrackingFields(data.order);
                        }
                        this.showToast('Status updated to ' + this.orderStatusLabel(nextStatus, this.orderType) + '.', 'success');
                    } catch (e) {
                        this.showToast('Could not update status.', 'error');
                    } finally {
                        this.orderStatusUpdating = false;
                    }
                },
                async pickOrderQueueRow(row) {
                    if (!this.canOpenOrderQueueRow(row)) {
                        return;
                    }
                    this.orderQueueBusy = true;
                    const ok = await this.loadOrderIntoPos(row.id);
                    if (ok) {
                        this.showOrderQueueModal = false;
                    }
                    this.orderQueueBusy = false;
                },
                resumeOrderTypeLabel(t) {
                    if (t === 'dine_in') {
                        return 'Dine in';
                    }
                    if (t === 'takeaway') {
                        return 'Take away';
                    }
                    if (t === 'delivery') {
                        return 'Delivery';
                    }
                    return t || '';
                },
                resumeOrderSummaryLine(row) {
                    const parts = [];
                    if (row.type === 'dine_in' && row.table) {
                        parts.push('Table ' + row.table.name);
                    }
                    if (row.waiter && row.waiter.name) {
                        parts.push('Waiter: ' + row.waiter.name);
                    }
                    if (row.delivery_rider && row.delivery_rider.name) {
                        parts.push('Rider: ' + row.delivery_rider.name);
                    }
                    if (row.customer_name) {
                        parts.push(row.customer_name);
                    }
                    if (row.customer_phone) {
                        parts.push(row.customer_phone);
                    }
                    if (row.type === 'delivery' && row.customer_address) {
                        parts.push(row.customer_address);
                    }
                    if (row.cashier && row.cashier.name) {
                        parts.push('Cashier: ' + row.cashier.name);
                    }
                    return parts.length ? parts.join(' · ') : 'Guest';
                },
                changeOrderType(t) {
                    if (!this.canChangeOrderType()) {
                        this.showToast('Remove items or start a new order to change type.', 'info');
                        return;
                    }
                    this.orderType = t;
                    if (t !== 'dine_in') {
                        this.selectedTableId = null;
                        this.waiterId = null;
                    }
                    if (t !== 'delivery') {
                        this.deliveryRiderId = null;
                    }
                    if (t === 'delivery' && !this.customerAddress) {
                        this.$nextTick(() => this.openCustomerPickerModal());
                    }
                },
                canChangeOrderType() {
                    return !this.activeOrderId && this.cart.length === 0;
                },
                orderTypeLabel() {
                    if (this.orderType === 'dine_in') {
                        return 'Dine in';
                    }
                    if (this.orderType === 'takeaway') {
                        return 'Takeaway';
                    }
                    if (this.orderType === 'delivery') {
                        return 'Delivery';
                    }
                    return this.orderType || '';
                },
                handleOrderTypeChange() {
                    this.changeOrderType(this.orderType);
                },
                async startNewOrder() {
                    if ((this.cart.length > 0 || this.activeOrderId) && !confirm('Clear the current order and start a new one?')) {
                        return;
                    }
                    this.posSessionReady = false;
                    this.sessionStep = 1;
                    this.orderType = 'dine_in';
                    this.selectedFloorForSessionId = '';
                    this.selectedTableId = null;
                    this.waiterId = null;
                    this.deliveryRiderId = null;
                    this.activeOrderId = null;
                    this.activeOrderNumber = '';
                    this.orderKitchenKots = [];
                    this.showKitchenStatusModal = false;
                    this.kitchenStatusPulse = false;
                    this.cart = [];
                    this.notes = '';
                    this.orderNotesOpen = false;
                    this.deliveryFee = 0;
                    this.clearDiscount();
                    this.clearCustomer();
                    this.paymentSelection = '';
                    this.paymentMethod = 'cash';
                    this.moneySourceId = null;
                    this.paidAmount = 0;
                    this.checkoutPaymentSelection = '';
                    this.checkoutMoneySourceId = null;
                    this.checkoutPaidAmount = 0;
                    this.showSplitPaymentModal = false;
                    this.clearSplitPaymentLines();
                    this.splitActiveSourceId = null;
                    this.orderWorkflowStatus = 'open';
                    this.orderAllowedNextStatuses = [];
                    this.orderExpectedReadyAt = null;
                    this.orderStatusLogs = [];
                    this.deliveryWizardView = 'search';
                    this.deliverySearchQuery = '';
                    this.deliverySearchResults = [];
                    this.deliverySearchDone = false;
                    this.deliveryPendingCustomer = null;
                    this.deliveryManualAddress = '';
                    this.newCustomerFromDeliveryWizard = false;
                    // Keep dine-in table statuses current for the next order.
                    await this.refreshSessionFloors();
                },
                openPos() {
                    this.activePosNav = 'pos';
                    this.posSessionReady = true;
                    this.showTableViewModal = false;
                    this.showOrderQueueModal = false;
                    this.showKitchenQueueModal = false;
                    if (!this.orderType) {
                        this.orderType = 'takeaway';
                    }
                    if (!this.isLargeScreen) {
                        this.posTab = 'products';
                    }
                },
                openTableViewModal() {
                    this.activePosNav = 'table';
                    this.showTableViewModal = true;
                    this.showOrderQueueModal = false;
                    this.showKitchenQueueModal = false;
                    this.loadTableView();
                },
                handlePosPanelOutsideClick(event, panel) {
                    if (this.showOrderDetailsModal) {
                        return;
                    }
                    if (event.target.closest('#pos-top-nav')) {
                        return;
                    }
                    if (panel === 'queue') {
                        this.showOrderQueueModal = false;
                    }
                    if (panel === 'kitchen') {
                        this.showKitchenQueueModal = false;
                    }
                    if (panel === 'table') {
                        this.showTableViewModal = false;
                    }
                    if (this.posSessionReady) {
                        this.activePosNav = 'pos';
                    }
                },
                applyFloorsFromTableViewPayload(floorsPayload) {
                    const floors = Array.isArray(floorsPayload) ? floorsPayload : [];
                    this.floors = floors.map(floor => ({
                        id: floor.id,
                        name: floor.name,
                        tables: Array.isArray(floor.tables) ? floor.tables.map(tbl => ({
                            id: tbl.id,
                            name: tbl.name,
                            capacity: tbl.capacity,
                            status: tbl.status,
                            section: tbl.section || null,
                            open_order: tbl.open_order || null,
                        })) : [],
                    }));
                    this.tables = this.floors.flatMap(floor => (floor.tables || []).map(tbl => ({
                        id: tbl.id,
                        name: tbl.name,
                        capacity: tbl.capacity,
                        status: tbl.status,
                        floor_id: floor.id === null || floor.id === undefined ? null : floor.id,
                        open_order: tbl.open_order || null,
                    })));
                },
                async refreshSessionFloors({ silent = true } = {}) {
                    const branchId = this.selectedBranchId ? parseInt(this.selectedBranchId, 10) : null;
                    if (!branchId) {
                        return false;
                    }
                    try {
                        const url = "{{ route('pos.table-view') }}?branch_id=" + branchId;
                        const res = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                        const data = await res.json();
                        if (data.success) {
                            this.applyFloorsFromTableViewPayload(data.floors);
                            return true;
                        }
                        if (!silent) {
                            this.showToast(data.message || 'Could not refresh tables.', 'error');
                        }
                    } catch (e) {
                        if (!silent) {
                            this.showToast('Could not refresh tables.', 'error');
                        }
                    }
                    return false;
                },
                markSessionTableOccupied(tableId, openOrder = null) {
                    const tid = Number(tableId);
                    if (!tid) {
                        return;
                    }
                    (this.floors || []).forEach(floor => {
                        (floor.tables || []).forEach(tbl => {
                            if (Number(tbl.id) === tid) {
                                tbl.status = 'occupied';
                                if (openOrder) {
                                    tbl.open_order = openOrder;
                                } else if (!tbl.open_order) {
                                    tbl.open_order = { id: this.activeOrderId || null };
                                }
                            }
                        });
                    });
                    (this.tables || []).forEach(tbl => {
                        if (Number(tbl.id) === tid) {
                            tbl.status = 'occupied';
                            if (openOrder) {
                                tbl.open_order = openOrder;
                            } else if (!tbl.open_order) {
                                tbl.open_order = { id: this.activeOrderId || null };
                            }
                        }
                    });
                },
                async loadTableView() {
                    const branchId = this.selectedBranchId ? parseInt(this.selectedBranchId, 10) : null;
                    if (!branchId) {
                        this.showToast('Select a branch.', 'error');
                        return;
                    }
                    this.tableViewLoading = true;
                    try {
                        const url = "{{ route('pos.table-view') }}?branch_id=" + branchId;
                        const res = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                        const data = await res.json();
                        if (data.success) {
                            this.tableViewFloors = Array.isArray(data.floors) ? data.floors : [];
                            this.tableViewSummary = data.summary || null;
                            // Keep the session table picker in sync with live statuses.
                            this.applyFloorsFromTableViewPayload(this.tableViewFloors);
                            if (this.tableViewFloors.length > 0) {
                                const onFloor = this.tableViewFloors.find(f => String(this.tableViewFloorKey(f)) === String(this.tableViewFloorId));
                                if (!onFloor) {
                                    this.tableViewFloorId = this.tableViewFloorKey(this.tableViewFloors[0]);
                                }
                            }
                        } else {
                            this.showToast(data.message || 'Could not load tables.', 'error');
                        }
                    } catch (e) {
                        this.showToast('Could not load tables.', 'error');
                    }
                    this.tableViewLoading = false;
                },
                tableViewFloorKey(floor) {
                    if (!floor) {
                        return '';
                    }
                    return floor.id === null || floor.id === undefined ? 'none' : floor.id;
                },
                tablesForTableViewFloor() {
                    if (!this.tableViewFloors || !this.tableViewFloors.length) {
                        return [];
                    }
                    const sid = String(this.tableViewFloorId);
                    const floor = this.tableViewFloors.find(f => String(this.tableViewFloorKey(f)) === sid);
                    return floor && floor.tables ? floor.tables : [];
                },
                tableViewStatusLabel(tbl) {
                    if (tbl.open_order) {
                        return 'Serving';
                    }
                    const labels = {
                        available: 'Free',
                        occupied: 'Busy',
                        reserved: 'Reserved',
                        dirty: 'Needs clean',
                        out_of_service: 'Closed',
                    };
                    return labels[tbl.status] || tbl.status;
                },
                tableViewStatusBadgeClass(tbl) {
                    if (tbl.open_order) {
                        return 'bg-amber-200 text-amber-900';
                    }
                    const map = {
                        available: 'bg-emerald-200 text-emerald-900',
                        occupied: 'bg-amber-200 text-amber-900',
                        reserved: 'bg-blue-200 text-blue-900',
                        dirty: 'bg-orange-200 text-orange-900',
                        out_of_service: 'bg-gray-200 text-gray-600',
                    };
                    return map[tbl.status] || 'bg-gray-200 text-gray-700';
                },
                tableViewCardClass(tbl) {
                    if (tbl.open_order) {
                        return 'border-amber-400 bg-amber-50/80';
                    }
                    if (tbl.status === 'available') {
                        return 'border-emerald-300 bg-emerald-50/60';
                    }
                    if (tbl.status === 'reserved') {
                        return 'border-blue-300 bg-blue-50/50';
                    }
                    if (tbl.status === 'dirty') {
                        return 'border-orange-300 bg-orange-50/40 opacity-90';
                    }
                    return 'border-gray-200 bg-gray-50 opacity-75';
                },
                tableViewCanSeat(tbl) {
                    return !tbl.open_order && (tbl.status === 'available' || tbl.status === 'reserved');
                },
                tableViewOrderAge(openOrder) {
                    if (!openOrder || !openOrder.updated_at) {
                        return '';
                    }
                    const d = new Date(openOrder.updated_at);
                    const mins = Math.max(0, Math.floor((Date.now() - d.getTime()) / 60000));
                    if (mins < 1) {
                        return 'Just now';
                    }
                    if (mins < 60) {
                        return mins + ' min ago';
                    }
                    const hrs = Math.floor(mins / 60);
                    return hrs + 'h ' + (mins % 60) + 'm ago';
                },
                async viewTableBill(tbl) {
                    if (!tbl.open_order?.id) {
                        return;
                    }
                    if (!await this.ensurePrintReady(['receipt'])) {
                        return;
                    }
                    await this.printCustomerBill(tbl.open_order.id);
                },
                async loadOrderIntoPos(orderId) {
                    const branchId = this.selectedBranchId ? parseInt(this.selectedBranchId, 10) : null;
                    if (!branchId || !orderId) {
                        this.showToast('Could not load order.', 'error');
                        return false;
                    }
                    try {
                        const params = new URLSearchParams({ branch_id: String(branchId), order_id: String(orderId) });
                        const url = "{{ route('pos.open-order') }}?" + params.toString();
                        const res = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                        const data = await res.json();
                        if (!data.success || !data.order) {
                            this.showToast(data.message || 'Could not load that order.', 'error');
                            return false;
                        }
                        this.applyHydratedOpenOrder(data.order);
                        this.openPos();
                        this.showToast('Opened order ' + (data.order.order_number || ''), 'success');
                        return true;
                    } catch (e) {
                        this.showToast('Could not load order.', 'error');
                        return false;
                    }
                },
                async openTableOrderInPos(tbl) {
                    if (!tbl.open_order?.id) {
                        return;
                    }
                    const ok = await this.loadOrderIntoPos(tbl.open_order.id);
                    if (ok) {
                        this.showTableViewModal = false;
                    }
                },
                seatAtTable(tbl) {
                    if (tbl.open_order) {
                        this.openTableOrderInPos(tbl);
                        return;
                    }
                    if (!this.tableViewCanSeat(tbl)) {
                        this.showToast('This table is not available.', 'error');
                        return;
                    }
                    this.orderType = 'dine_in';
                    this.selectedTableId = tbl.id;
                    this.selectFloorForTableId(tbl.id);
                    this.activeOrderId = null;
                    this.activeOrderNumber = '';
                    this.openPos();
                    this.showTableViewModal = false;
                    this.showToast('Table ' + tbl.name + ' — add items in POS', 'success');
                    if (!this.isLargeScreen) {
                        this.posTab = 'order';
                    }
                },
                orderStaffPayload() {
                    return {
                        waiter_id: this.orderType === 'dine_in' && this.hasWaiterSelected() ? parseInt(this.waiterId, 10) : null,
                        delivery_rider_id: this.orderType === 'delivery' && this.hasRiderSelected() ? parseInt(this.deliveryRiderId, 10) : null,
                    };
                },
                waiterStaffOptions() {
                    return (this.branchStaff || []).filter((s) => s.type === 'waiter' || s.type === 'waiter_rider');
                },
                riderStaffOptions() {
                    return (this.branchStaff || []).filter((s) => s.type === 'rider' || s.type === 'waiter_rider');
                },
                hasWaiterSelected() {
                    const id = parseInt(this.waiterId, 10);
                    return !isNaN(id) && id > 0;
                },
                hasRiderSelected() {
                    const id = parseInt(this.deliveryRiderId, 10);
                    return !isNaN(id) && id > 0;
                },
                orderStaffValid() {
                    if (this.orderType === 'dine_in') {
                        return this.hasWaiterSelected();
                    }
                    if (this.orderType === 'delivery') {
                        return this.hasRiderSelected();
                    }
                    return true;
                },
                validateOrderStaff() {
                    if (this.orderType === 'dine_in' && !this.hasWaiterSelected()) {
                        this.showToast('Select a waiter for dine-in orders.', 'error');
                        return false;
                    }
                    if (this.orderType === 'delivery' && !this.hasRiderSelected()) {
                        this.showToast('Select a delivery rider.', 'error');
                        return false;
                    }
                    return true;
                },
                applyHydratedOpenOrder(order) {
                    this.posSessionReady = true;
                    this.sessionStep = 1;
                    this.orderType = order.type || 'dine_in';
                    this.selectedTableId = order.table_id ? Number(order.table_id) : null;
                    this.waiterId = order.waiter_id ? Number(order.waiter_id) : null;
                    this.deliveryRiderId = order.delivery_rider_id ? Number(order.delivery_rider_id) : null;
                    this.selectFloorForTableId(this.selectedTableId);
                    this.activeOrderId = order.id;
                    this.activeOrderNumber = order.order_number || '';
                    this.hydrateCartFromOrder(order);
                    this.notes = order.notes || '';
                    this.syncOrderNotesOpen();
                    this.applyOrderCustomerFields(order);
                    this.deliveryFee = parseFloat(order.delivery_fee) || 0;
                    this.applyDiscountFromOrder(order);
                    this.applyOrderPaymentFields(order);
                    this.applyOrderTrackingFields(order);
                },
                backFromDeliveryWizard() {
                    this.sessionStep = 1;
                    this.deliveryWizardView = 'search';
                    this.deliverySearchQuery = '';
                    this.deliverySearchResults = [];
                    this.deliverySearchDone = false;
                    this.deliveryPendingCustomer = null;
                    this.deliveryManualAddress = '';
                },
                scheduleDeliveryCustomerSearch() {
                    if (this.deliverySearchDebounceTimer) {
                        clearTimeout(this.deliverySearchDebounceTimer);
                    }
                    this.deliverySearchDebounceTimer = setTimeout(() => {
                        this.fetchDeliveryCustomers();
                    }, 250);
                },
                async fetchDeliveryCustomers() {
                    const q = (this.deliverySearchQuery || '').trim();
                    this.deliverySearchLoading = true;
                    try {
                        const url = "{{ route('customers.search') }}?q=" + encodeURIComponent(q) + '&limit=100';
                        const res = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                        const data = await res.json();
                        this.deliverySearchResults = Array.isArray(data) ? data : [];
                        this.deliverySearchDone = true;
                    } catch (e) {
                        this.deliverySearchResults = [];
                        this.deliverySearchDone = true;
                    }
                    this.deliverySearchLoading = false;
                },
                selectDeliveryCustomer(c) {
                    this.deliveryPendingCustomer = c;
                    if (c.addresses && c.addresses.length > 0) {
                        this.deliveryWizardView = 'addresses';
                    } else {
                        this.deliveryWizardView = 'no_addresses';
                        this.deliveryManualAddress = '';
                    }
                },
                confirmDeliveryAddress(addr) {
                    if (!this.deliveryPendingCustomer) {
                        return;
                    }
                    const line = addr.full_address || addr.address_line_1 || '';
                    this.finishDeliverySessionWithDetails(
                        this.deliveryPendingCustomer.id,
                        this.deliveryPendingCustomer.name,
                        this.deliveryPendingCustomer.phone || '',
                        line,
                        this.deliveryPendingCustomer.email || ''
                    );
                },
                confirmDeliveryManualAddress() {
                    if (!this.deliveryPendingCustomer) {
                        return;
                    }
                    const line = (this.deliveryManualAddress || '').trim();
                    if (!line) {
                        this.showToast('Enter a delivery address.', 'error');
                        return;
                    }
                    this.finishDeliverySessionWithDetails(
                        this.deliveryPendingCustomer.id,
                        this.deliveryPendingCustomer.name,
                        this.deliveryPendingCustomer.phone || '',
                        line,
                        this.deliveryPendingCustomer.email || ''
                    );
                },
                finishDeliverySessionWithDetails(customerId, name, phone, addressLine, email = '') {
                    this.selectedCustomerId = String(customerId);
                    this.customerName = name || '';
                    this.customerPhone = phone || '';
                    this.customerEmail = (email || '').trim();
                    this.customerAddress = (addressLine || '').trim();
                    this.deliveryWizardView = 'search';
                    this.deliverySearchQuery = '';
                    this.deliverySearchResults = [];
                    this.deliverySearchDone = false;
                    this.deliveryPendingCustomer = null;
                    this.deliveryManualAddress = '';
                    this.activeOrderId = null;
                    this.activeOrderNumber = '';
                    this.cart = [];
                    this.sessionStep = 1;
                    this.posSessionReady = true;
                },
                openNewCustomerForDelivery() {
                    this.openNewCustomerModal({
                        fromDeliveryWizard: true,
                        phone: (this.deliverySearchQuery || '').trim(),
                    });
                },
                tablesForSelectedSessionFloor() {
                    const sid = this.selectedFloorForSessionId;
                    if (sid === null || sid === undefined || sid === '') {
                        return [];
                    }
                    const floor = this.floors.find(f => {
                        const fid = f.id === null || f.id === undefined ? 'none' : String(f.id);
                        return fid === String(sid);
                    });
                    return floor && floor.tables ? floor.tables : [];
                },
                sessionTableStatusLabel(tbl) {
                    if (tbl.status === 'occupied' || tbl.open_order) {
                        return 'Occupied';
                    }
                    const labels = {
                        available: 'Available',
                        reserved: 'Reserved',
                        dirty: 'Dirty',
                        out_of_service: 'Closed',
                    };
                    return labels[tbl.status] || (tbl.status || 'Available');
                },
                sessionTableStatusBadgeClass(tbl) {
                    if (tbl.status === 'occupied' || tbl.open_order) {
                        return 'bg-amber-200 text-amber-900';
                    }
                    const map = {
                        available: 'bg-emerald-200 text-emerald-900',
                        reserved: 'bg-blue-200 text-blue-900',
                        dirty: 'bg-orange-200 text-orange-900',
                        out_of_service: 'bg-gray-200 text-gray-600',
                    };
                    return map[tbl.status] || 'bg-emerald-200 text-emerald-900';
                },
                sessionTableCardClass(tbl) {
                    if (tbl.status === 'occupied' || tbl.open_order) {
                        return 'border-amber-400 bg-amber-50 hover:border-amber-500';
                    }
                    if (tbl.status === 'available') {
                        return 'border-emerald-300 bg-emerald-50 hover:border-emerald-400';
                    }
                    if (tbl.status === 'reserved') {
                        return 'border-blue-300 bg-blue-50 hover:border-blue-400';
                    }
                    if (tbl.status === 'dirty') {
                        return 'border-orange-300 bg-orange-50 hover:border-orange-400';
                    }
                    if (tbl.status === 'out_of_service') {
                        return 'border-gray-300 bg-gray-100 opacity-80 hover:border-gray-400';
                    }
                    return 'border-emerald-300 bg-emerald-50 hover:border-emerald-400';
                },
                confirmDineInTable(tableId) {
                    this.selectedTableId = tableId;
                    this.posSessionReady = true;
                    this.$nextTick(() => this.resumeOpenOrderIfAny());
                },
                selectedTableLabel() {
                    const id = this.selectedTableId;
                    if (!id) {
                        return '';
                    }
                    for (let i = 0; i < this.floors.length; i++) {
                        const f = this.floors[i];
                        if (!f.tables) {
                            continue;
                        }
                        const t = f.tables.find(row => Number(row.id) === Number(id));
                        if (t) {
                            return t.name;
                        }
                    }
                    const flat = this.tables.find(row => Number(row.id) === Number(id));
                    return flat ? flat.name : '';
                },
                async resumeOpenOrderIfAny() {
                    if (!this.selectedBranchId || !this.posSessionReady) {
                        return;
                    }
                    if (this.orderType !== 'dine_in' || !this.selectedTableId) {
                        return;
                    }
                    const params = new URLSearchParams();
                    params.set('branch_id', String(this.selectedBranchId));
                    params.set('table_id', String(this.selectedTableId));
                    try {
                        const url = "{{ route('pos.open-order') }}?" + params.toString();
                        const res = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                        const data = await res.json();
                        if (data.success && data.order) {
                            this.activeOrderId = data.order.id;
                            this.activeOrderNumber = data.order.order_number || '';
                            this.hydrateCartFromOrder(data.order);
                            this.notes = data.order.notes || '';
                            this.syncOrderNotesOpen();
                            this.waiterId = data.order.waiter_id ? Number(data.order.waiter_id) : null;
                            this.deliveryRiderId = data.order.delivery_rider_id ? Number(data.order.delivery_rider_id) : null;
                            this.applyOrderCustomerFields(data.order);
                            this.deliveryFee = parseFloat(data.order.delivery_fee) || 0;
                            this.applyDiscountFromOrder(data.order);
                            this.applyOrderPaymentFields(data.order);
                            this.applyOrderTrackingFields(data.order);
                            this.showToast('Resumed open order ' + this.activeOrderNumber, 'info', 2500);
                        } else {
                            this.activeOrderId = null;
                            this.activeOrderNumber = '';
                        }
                    } catch (e) {
                        console.warn(e);
                    }
                },
                hydrateCartFromOrder(order) {
                    if (!order.items || !order.items.length) {
                        this.cart = [];
                        return;
                    }
                    this.cart = order.items.map(oi => ({
                        deal_id: oi.deal_id || null,
                        menu_item_id: oi.menu_item_id || null,
                        name: oi.item_name || 'Item',
                        quantity: parseFloat(oi.quantity),
                        base_unit_price: parseFloat(oi.unit_price) - this.addonsTotal(oi.addons || []),
                        unit_price: parseFloat(oi.unit_price),
                        variants: oi.variants || null,
                        addons: oi.addons || [],
                        special_instructions: oi.special_instructions || '',
                    }));
                },
                resetPosSession() {
                    this.startNewOrder();
                },
                async saveTab() {
                    if (this.cart.length === 0) {
                        this.showToast('Add items first.', 'error');
                        return false;
                    }
                    if (this.orderType === 'delivery' && !this.customerAddress) {
                        this.showToast('Select a customer with a delivery address.', 'error');
                        this.openCustomerPickerModal();
                        return false;
                    }
                    const branchId = this.selectedBranchId ? parseInt(this.selectedBranchId, 10) : null;
                    if (!branchId) {
                        this.showToast('Please select a branch.', 'error');
                        return false;
                    }
                    if (this.orderType === 'dine_in' && !this.selectedTableId) {
                        this.showToast('Table is required for dine in.', 'error');
                        return false;
                    }
                    if (!this.validateOrderStaff()) {
                        return false;
                    }
                    if (this.isCreditPaymentSelected()) {
                        if (!this.allowPosCreditSales) {
                            this.showToast('Credit sales are not enabled.', 'error');
                            return false;
                        }
                        if (!this.hasSelectedCustomer()) {
                            this.customerPickerReason = 'credit';
                            this.openCustomerPickerModal({ required: true });
                            return false;
                        }
                    }
                    this.processing = true;
                    try {
                        let res;
                        if (this.activeOrderId) {
                            const patchBody = {
                                type: this.orderType,
                                table_id: this.selectedTableId ? parseInt(this.selectedTableId, 10) : null,
                                ...this.orderStaffPayload(),
                                customer_name: this.customerName,
                                customer_phone: this.customerPhone,
                                customer_email: (this.customerEmail || '').trim() || null,
                                customer_address: this.customerAddress,
                                items: this.posCartItemsPayload(),
                                subtotal: this.subtotal,
                                tax_amount: this.taxAmount,
                                ...this.discountPayload(),
                                service_charge: 0,
                                delivery_fee: this.deliveryFee || 0,
                                total_amount: this.totalAmount,
                                notes: this.notes,
                                ...this.paymentIntentPayload(),
                            };
                            const patchUrl = '{{ url('/pos/orders') }}/' + this.activeOrderId;
                            res = await fetch(patchUrl, {
                                method: 'PATCH',
                                headers: {
                                    'Content-Type': 'application/json',
                                    Accept: 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                                body: JSON.stringify(patchBody),
                            });
                        } else {
                            const postBody = {
                                mode: 'tab',
                                type: this.orderType,
                                branch_id: branchId,
                                table_id: this.selectedTableId ? parseInt(this.selectedTableId, 10) : null,
                                ...this.orderStaffPayload(),
                                customer_name: this.customerName,
                                customer_phone: this.customerPhone,
                                customer_email: (this.customerEmail || '').trim() || null,
                                customer_address: this.customerAddress,
                                items: this.posCartItemsPayload(),
                                subtotal: this.subtotal,
                                tax_amount: this.taxAmount,
                                ...this.discountPayload(),
                                service_charge: 0,
                                delivery_fee: this.deliveryFee || 0,
                                total_amount: this.totalAmount,
                                notes: this.notes,
                                ...this.paymentIntentPayload(),
                            };
                            res = await fetch(@json(route('pos.store')), {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    Accept: 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                                body: JSON.stringify(postBody),
                            });
                        }
                        const data = await res.json();
                        if (!res.ok) {
                            const msg = data.message || (data.errors ? Object.values(data.errors).flat().join(' ') : 'Save failed');
                            this.showToast(msg, 'error');
                            return false;
                        }
                        if (data.order) {
                            this.activeOrderId = data.order.id;
                            this.activeOrderNumber = data.order.order_number || '';
                            this.applyOrderTrackingFields(data.order);
                            if (this.orderType === 'dine_in' && this.selectedTableId) {
                                this.markSessionTableOccupied(this.selectedTableId, {
                                    id: data.order.id,
                                    order_number: data.order.order_number || '',
                                    total_amount: Number(data.order.total_amount || this.totalAmount || 0),
                                    items_count: Array.isArray(data.order.items) ? data.order.items.length : this.cart.length,
                                    payment_status: data.order.payment_status || 'unpaid',
                                    customer_name: data.order.customer_name || this.customerName || null,
                                });
                            }
                            const kitchenJobs = Number(data.kitchen_desktop_jobs || 0);
                            const browserKotIds = Array.isArray(data.browser_kot_ids) ? data.browser_kot_ids : [];
                            for (let i = 0; i < browserKotIds.length; i++) {
                                this.directPrintKitchenKot(browserKotIds[i]);
                                if (i < browserKotIds.length - 1) {
                                    await new Promise(resolve => setTimeout(resolve, 700));
                                }
                            }
                            if (kitchenJobs > 0 || browserKotIds.length > 0) {
                                this.showToast(data.message || 'Order saved. KOT sent to kitchen.', 'success');
                            } else {
                                const kitchenOutOfSync = data.order.kitchen_sync
                                    && !data.order.kitchen_sync.in_sync
                                    && (data.order.kitchen_sync.sent_items?.length || 0) > 0;
                                if (kitchenOutOfSync) {
                                    this.showToast('Order saved — tap KOT again so the kitchen gets the updated items.', 'info', 6000);
                                } else {
                                    this.showToast(data.message || 'Order saved.', 'success');
                                }
                            }
                        } else {
                            this.showToast(data.message || 'Order saved.', 'success');
                        }
                        return true;
                    } catch (e) {
                        this.showToast('Save failed.', 'error');
                        return false;
                    } finally {
                        this.processing = false;
                    }
                },
                directPrintKitchenKot(kotId, asReprint = false, asOrderCancel = false) {
                    if (!kotId) {
                        return;
                    }
                    let url = `{{ route('pos.kitchen-kot', ':id') }}`.replace(':id', kotId)
                        + '?print=1&_=' + Date.now();
                    if (asReprint) {
                        url += '&reprint=1';
                    }
                    if (asOrderCancel) {
                        url += '&cancel=1';
                    }
                    let frame = document.getElementById('pos-kitchen-print-frame');
                    if (!frame) {
                        frame = document.createElement('iframe');
                        frame.id = 'pos-kitchen-print-frame';
                        frame.title = 'Kitchen print';
                        frame.setAttribute('aria-hidden', 'true');
                        frame.style.cssText = 'position:fixed;width:0;height:0;border:0;opacity:0;pointer-events:none;left:0;bottom:0;';
                        document.body.appendChild(frame);
                    }
                    frame.src = url;
                },
                invoicePaymentStatusDisplay() {
                    const o = this.invoiceData;
                    if (!o || !o.payment_status) {
                        return 'N/A';
                    }
                    if (o.payment_status === 'unpaid') {
                        return 'Pending';
                    }
                    return o.payment_status.charAt(0).toUpperCase() + o.payment_status.slice(1);
                },
                invoicePaymentMethodDisplay() {
                    const o = this.invoiceData;
                    if (!o) {
                        return 'N/A';
                    }
                    if (!o.payment_method) {
                        if (o.payment_status === 'unpaid') {
                            return 'Pending (pay at checkout)';
                        }
                        return '—';
                    }
                    if (o.payment_method === 'split') {
                        return 'Split payment';
                    }
                    return String(o.payment_method)
                        .replace(/_/g, ' ')
                        .replace(/\b\w/g, (l) => l.toUpperCase());
                },
                handleCheckoutMoneySourceChange() {
                    const s = this.moneySources.find(x => Number(x.id) === Number(this.checkoutMoneySourceId));
                    if (s && s.type === 'CASH') {
                        this.checkoutPaymentMethod = 'cash';
                        this.checkoutPaidAmount = this.totalAmount;
                    } else if (s && s.type === 'BANK') {
                        this.checkoutPaymentMethod = 'card';
                        this.checkoutPaidAmount = this.totalAmount;
                    } else if (s && s.type === 'APP') {
                        this.checkoutPaymentMethod = 'digital_wallet';
                        this.checkoutPaidAmount = this.totalAmount;
                    } else if (!this.isCheckoutCreditPaymentSelected() && !this.isCheckoutFocPaymentSelected()) {
                        this.checkoutPaymentMethod = 'cash';
                    }
                },
                async submitCheckout() {
                    if (!this.activeOrderId) {
                        this.showToast('No order to checkout.', 'error');
                        return;
                    }
                    const name = (this.customerName || '').trim();
                    const phone = (this.customerPhone || '').trim();
                    const email = (this.customerEmail || '').trim();
                    if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                        this.showToast('Enter a valid email or leave it blank.', 'error');
                        return;
                    }
                    this.customerName = name;
                    this.customerPhone = phone;
                    this.customerEmail = email;

                    const paidNum = parseFloat(this.checkoutPaidAmount) || 0;

                    if (this.isCheckoutFocPaymentSelected()) {
                        if (!this.canPosFoc) {
                            this.showToast('You do not have permission to use FOC.', 'error');
                            return;
                        }
                        await this.executeCheckout();
                        return;
                    }

                    if (this.isCheckoutCreditPaymentSelected()) {
                        if (!this.allowPosCreditSales) {
                            this.showToast('Credit sales are not enabled.', 'error');
                            return;
                        }
                        if (!this.hasSelectedCustomer()) {
                            this.customerPickerReason = 'credit';
                            this.openCustomerPickerModal({ required: true });
                            return;
                        }
                        const creditDue = this.creditDueAmount(paidNum);
                        if (creditDue <= 0) {
                            this.showToast('Amount received covers the total. Use a cash or bank payment instead.', 'error');
                            return;
                        }
                        await this.executeCheckout();
                        return;
                    }

                    if (!this.checkoutMoneySourceId) {
                        this.showToast('Select payment.', 'error');
                        return;
                    }

                    if (this.checkoutPaymentMethod === 'cash' && paidNum < this.totalAmount && !this.canCoverWithCustomerCredit(paidNum)) {
                        this.showToast('Amount received must cover the total, select Credit, or use customer advance.', 'error');
                        return;
                    }

                    await this.executeCheckout();
                },

                async executeCheckout() {
                    if (!this.activeOrderId) {
                        return;
                    }
                    const paidNum = this.isCheckoutFocPaymentSelected()
                        ? 0
                        : (parseFloat(this.checkoutPaidAmount) || 0);
                    const paymentStatus = this.isCheckoutCreditPaymentSelected()
                        ? (paidNum >= this.totalAmount ? 'paid' : 'partial')
                        : 'paid';
                    const customerId = this.hasSelectedCustomer() ? parseInt(this.selectedCustomerId, 10) : null;
                    let paymentMethod = null;
                    if (this.isCheckoutFocPaymentSelected()) {
                        paymentMethod = 'foc';
                    } else if (this.isCheckoutCreditPaymentSelected()) {
                        paymentMethod = 'credit';
                    }

                    this.checkoutSubmitting = true;
                    try {
                        const url = '{{ url('/pos/orders') }}/' + this.activeOrderId + '/checkout';
                        const res = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                Accept: 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: JSON.stringify({
                                money_source_id: this.isCheckoutFocPaymentSelected()
                                    ? null
                                    : (this.checkoutMoneySourceId ? parseInt(this.checkoutMoneySourceId, 10) : null),
                                payment_method: paymentMethod,
                                paid_amount: paidNum,
                                customer_id: customerId,
                                customer_name: (this.customerName || '').trim() || null,
                                customer_phone: (this.customerPhone || '').trim() || null,
                                customer_email: (this.customerEmail || '').trim() || null,
                                customer_address: this.customerAddress,
                                payment_status: paymentStatus,
                                auto_bill: this.shouldAutoBillOnCheckout(),
                            }),
                        });
                        const data = await res.json();
                        if (!res.ok) {
                            let msg = data.message || 'Checkout failed';
                            if (data.errors) {
                                msg = Object.values(data.errors).flat().join(' ');
                            }
                            this.showToast(msg, 'error');
                            this.checkoutSubmitting = false;
                            return;
                        }
                        this.showCheckoutModal = false;
                        this.showToast('Payment completed.', 'success');
                        const orderId = data.order?.id;
                        if (data.browser_print === false && data.desktop_jobs > 0) {
                            this.showToast('Receipt sent for direct print.', 'success');
                        }
                        if ((data.kitchen_desktop_jobs || 0) > 0) {
                            this.showToast('KOT sent for direct print.', 'success');
                        }
                        this.resetPosAfterCompletedOrder(orderId, data.browser_print);
                    } catch (e) {
                        this.showToast('Checkout failed.', 'error');
                    }
                    this.checkoutSubmitting = false;
                },
                
                formatCurrency(amount) {
                    const currency = '{{ get_company_config()["currency"] ?? "USD" }}';
                    const position = '{{ get_company_config()["currency_position"] ?? "left" }}';
                    const decimals = {{ get_company_config()["decimal_points"] ?? 2 }};
                    
                    const formatted = new Intl.NumberFormat('en-US', {
                        minimumFractionDigits: decimals,
                        maximumFractionDigits: decimals
                    }).format(amount);
                    
                    const symbols = {
                        'USD': '$', 'EUR': '€', 'GBP': '£', 'JPY': '¥',
                        'AUD': 'A$', 'CAD': 'C$', 'INR': '₹', 'PKR': '₨'
                    };
                    
                    const symbol = symbols[currency] || currency;
                    
                    return position === 'left' 
                        ? symbol + formatted 
                        : formatted + ' ' + symbol;
                },

                formatQuantity(quantity) {
                    const n = parseFloat(quantity);
                    if (Number.isNaN(n)) {
                        return '0';
                    }
                    if (Math.abs(n - Math.round(n)) < 0.00001) {
                        return String(Math.round(n));
                    }
                    return String(n);
                },

                receiptPreviewRootStyle() {
                    const r = this.receiptPrint;
                    return {
                        fontFamily: 'Arial, Helvetica, sans-serif',
                        fontSize: r.font_size_px + 'px',
                        fontWeight: 700,
                        lineHeight: 1.35,
                        width: r.paper_width_mm + 'mm',
                        maxWidth: '100%',
                        margin: '0 auto',
                        padding: `1.5mm ${r.pad_right_mm}mm 1.5mm ${r.pad_left_mm}mm`,
                        color: '#000',
                        boxSizing: 'border-box',
                    };
                },

                receiptPriceCellStyle() {
                    const pad = Math.max(2, parseInt(this.receiptPrint.amount_pad_right_mm || 2, 10) || 2);
                    return {
                        textAlign: 'right',
                        padding: '3px 0',
                        paddingRight: pad + 'mm',
                        borderBottom: '1px dotted #ccc',
                        whiteSpace: 'nowrap',
                        overflow: 'visible',
                        fontVariantNumeric: 'tabular-nums',
                        width: this.receiptPrint.col_price_pct + '%',
                    };
                },

                receiptSection(key) {
                    const sections = this.receiptPrint?.sections || {};
                    return sections[key] !== false;
                },

                receiptShowSubtotal() {
                    if (!this.receiptSection('subtotal') || !this.invoiceData) {
                        return false;
                    }
                    const d = this.invoiceData;
                    const subtotal = parseFloat(d.subtotal) || 0;
                    const total = parseFloat(d.total_amount) || 0;
                    const discount = parseFloat(d.discount_amount) || 0;
                    const service = parseFloat(d.service_charge) || 0;
                    const delivery = parseFloat(d.delivery_fee) || 0;
                    const tax = parseFloat(d.tax_amount) || 0;
                    if (Math.abs(subtotal - total) < 0.01 && discount <= 0.01 && service <= 0.01 && delivery <= 0.01 && tax <= 0.01) {
                        return false;
                    }
                    return true;
                },

                receiptShowOrderDetails() {
                    if (!this.invoiceData) {
                        return false;
                    }
                    const showType = this.receiptSection('order_type') && this.invoiceData.type;
                    const showTable = this.receiptSection('table') && this.invoiceData.table;
                    return showType || showTable;
                },

                receiptAmountCellStyle() {
                    const pad = Math.max(2, parseInt(this.receiptPrint.amount_pad_right_mm || 2, 10) || 2);
                    return {
                        flexShrink: 0,
                        whiteSpace: 'nowrap',
                        textAlign: 'right',
                        fontVariantNumeric: 'tabular-nums',
                        paddingRight: pad + 'mm',
                        overflow: 'visible',
                    };
                },
                
                showOrderInvoice(orderId) {
                    if (!orderId) {
                        return;
                    }
                    this.loadInvoice(orderId);
                },

                printOrderReceipt(orderId) {
                    if (!orderId) {
                        this.showToast('No order to print', 'error');
                        return;
                    }
                    const url = `{{ route('pos.invoice', ':id') }}`.replace(':id', orderId)
                        + '?_=' + Date.now();
                    let frame = document.getElementById('pos-invoice-print-frame');
                    if (!frame) {
                        frame = document.createElement('iframe');
                        frame.id = 'pos-invoice-print-frame';
                        frame.title = 'Receipt print';
                        frame.setAttribute('aria-hidden', 'true');
                        frame.style.cssText = 'position:fixed;width:0;height:0;border:0;clip:rect(0,0,0,0);overflow:hidden;pointer-events:none;left:-9999px;top:0;';
                        document.body.appendChild(frame);
                    }
                    frame.onload = () => {
                        try {
                            const win = frame.contentWindow;
                            if (win) {
                                win.focus();
                                win.print();
                            }
                        } catch (e) {
                            console.error('Print failed:', e);
                            this.showToast('Print failed.', 'error');
                        }
                        frame.onload = null;
                    };
                    frame.src = url;
                },

                async loadInvoice(orderId) {
                    if (!orderId) {
                        console.error('Order ID is missing');
                        return;
                    }
                    
                    this.loadingInvoice = true;
                    this.showInvoiceModal = true; // Show modal immediately with loading state
                    
                    try {
                        const invoiceUrl = `{{ route('pos.invoice', ':id') }}`.replace(':id', orderId);
                        const response = await fetch(invoiceUrl, {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        });
                        
                        if (response.ok) {
                            const data = await response.json();
                            if (data.success && data.order) {
                                this.invoiceData = data.order;
                            } else {
                                this.showToast('Failed to load invoice data', 'error');
                                this.showInvoiceModal = false;
                            }
                        } else {
                            this.showToast('Failed to load invoice', 'error');
                            this.showInvoiceModal = false;
                        }
                    } catch (error) {
                        console.error('Error loading invoice:', error);
                        this.showToast('Failed to load invoice: ' + error.message, 'error');
                        this.showInvoiceModal = false;
                    } finally {
                        this.loadingInvoice = false;
                    }
                },
                
                invoiceContactAddress() {
                    const company = this.invoiceData?.company;
                    const branch = this.invoiceData?.branch;
                    return (company?.address || branch?.address || '').trim() || null;
                },

                invoiceContactPhone() {
                    const company = this.invoiceData?.company;
                    const branch = this.invoiceData?.branch;
                    return (company?.phone || branch?.phone || '').trim() || null;
                },

                invoiceBranchName() {
                    const company = this.invoiceData?.company;
                    const branch = this.invoiceData?.branch;
                    if (!branch?.name) {
                        return null;
                    }
                    if (company?.name && branch.name.toLowerCase() === company.name.toLowerCase()) {
                        return null;
                    }
                    return branch.name;
                },

                printInvoice() {
                    const orderId = this.invoiceData?.id;
                    if (!orderId) {
                        this.showToast('No order to print', 'error');
                        return;
                    }
                    this.printOrderReceipt(orderId);
                },
                
                closeInvoiceModal() {
                    console.log('Closing invoice modal');
                    this.showInvoiceModal = false;
                    this.invoiceData = null;
                    this.loadingInvoice = false;
                }
            }
        }
    </script>
</body>
</html>

