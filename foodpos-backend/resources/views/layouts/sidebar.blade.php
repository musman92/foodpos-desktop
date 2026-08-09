@auth
<div class="hidden lg:flex lg:flex-shrink-0">
    <div class="flex flex-col w-64">
        <div class="flex flex-col flex-grow bg-white border-r border-gray-200 pt-5 pb-4 overflow-y-auto">
            <!-- Logo -->
            <div class="flex items-center flex-shrink-0 px-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        @if(get_logo())
                            <img src="{{ get_logo() }}" 
                                 alt="Company Logo" 
                                 class="h-10 w-10 rounded-lg object-contain">
                        @else
                            <div class="flex items-center justify-center">
                                <img src="{{ asset('images/thefoodpos-favicon.png') }}" alt="TheFoodPOS" class="h-10 w-10 object-contain">
                            </div>
                        @endif
                    </div>
                    <div class="ml-3">
                        <h1 class="text-lg font-bold text-gray-900">{{ get_company_name() }}</h1>
                        <p class="text-xs text-gray-500">Restaurant System</p>
                    </div>
                </div>
            </div>

            <!-- Navigation -->
            <div class="mt-8 flex-grow flex flex-col">
                <nav class="flex-1 px-2 space-y-0.5">
                    <!-- Dashboard (no collapse) -->
                    @if(auth()->user()->hasAppPermission('dashboard.index'))
                    <a href="{{ route('dashboard') }}" class="group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg {{ request()->routeIs('dashboard') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                        <i class="fas fa-home mr-3 text-lg {{ request()->routeIs('dashboard') ? 'text-indigo-500' : 'text-gray-400 group-hover:text-gray-500' }}"></i>
                        Dashboard
                    </a>
                    @endif

                    @if(auth()->user()->branch_id || auth()->user()->isSuperAdmin() || auth()->user()->isCompanyAdmin())
                    <!-- Shifts (no dropdown) -->
                    <a href="{{ route('shifts.index') }}" class="group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg {{ request()->routeIs('shifts.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                        <i class="fas fa-clock mr-3 text-lg {{ request()->routeIs('shifts.*') ? 'text-indigo-500' : 'text-gray-400 group-hover:text-gray-500' }}"></i>
                        Shifts
                    </a>

                    <!-- POS (no dropdown) -->
                    <a href="{{ route('pos.index') }}" class="group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg {{ request()->routeIs('pos.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                        <i class="fas fa-cash-register mr-3 text-lg {{ request()->routeIs('pos.*') ? 'text-indigo-500' : 'text-gray-400 group-hover:text-gray-500' }}"></i>
                        POS
                    </a>

                        <!-- Order Management (collapsible) -->
                        @php
                            $openOrd = request()->routeIs('order-management.*');
                            $ordOrdersNav = request()->routeIs('order-management.index', 'order-management.show', 'order-management.append-note');
                            $ordRefundsNav = request()->routeIs('order-management.refunds.index', 'order-management.refunds.process');
                            $u = auth()->user();
                            $canOrderMgmtNav = $u->hasAnyAppPermission(['order-management.index', 'order-management.show', 'order-management.append-note', 'order-management.refund']);
                            $canOrdersListNav = $u->hasAppPermission('order-management.index');
                            $canRefundMenu = $u->hasAppPermission('order-management.refund');
                        @endphp
                        @if($canOrderMgmtNav)
                        <div class="pt-2" x-data="{ open: {{ $openOrd ? 'true' : 'false' }} }">
                            <button type="button" @click="open = !open" class="w-full group flex items-center justify-between px-3 py-2.5 text-sm font-medium rounded-lg {{ $openOrd ? 'text-gray-900 bg-gray-50' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }} transition-colors">
                                <span class="flex items-center">
                                    <i class="fas fa-receipt mr-3 text-lg text-gray-400 group-hover:text-gray-500"></i>
                                    <span class="text-xs uppercase tracking-wider {{ $openOrd ? 'font-bold text-gray-700' : 'font-semibold text-gray-500' }}">Order Management</span>
                                </span>
                                <i class="fas fa-chevron-down text-gray-400 text-xs transition-transform duration-200" :class="open ? 'rotate-180' : ''"></i>
                            </button>
                            <div x-show="open"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="mt-0.5 space-y-0.5 pl-1">
                                @if($canOrdersListNav)
                                <a href="{{ route('order-management.index') }}" class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ $ordOrdersNav ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                    <i class="fas fa-list mr-3 text-lg {{ $ordOrdersNav ? 'text-indigo-500' : 'text-gray-400 group-hover:text-gray-500' }}"></i>
                                    Orders
                                </a>
                                @endif
                                @if($canRefundMenu)
                                <a href="{{ route('order-management.refunds.index') }}" class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ $ordRefundsNav ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                    <i class="fas fa-undo-alt mr-3 text-lg {{ $ordRefundsNav ? 'text-indigo-500' : 'text-gray-400 group-hover:text-gray-500' }}"></i>
                                    Refunds
                                </a>
                                @endif
                            </div>
                        </div>
                        @endif

                        <!-- Menu Management (collapsible) -->
                        @php $openMenu = request()->routeIs('categories.*', 'menu-items.*', 'deals.*', 'cuisines.*', 'product-addons.*', 'variants.*', 'recipes.*'); @endphp
                        <div class="pt-2" x-data="{ open: {{ $openMenu ? 'true' : 'false' }} }">
                            <button type="button" @click="open = !open" class="w-full group flex items-center justify-between px-3 py-2.5 text-sm font-medium rounded-lg {{ $openMenu ? 'text-gray-900 bg-gray-50' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }} transition-colors">
                                <span class="flex items-center">
                                    <i class="fas fa-utensils mr-3 text-lg text-gray-400 group-hover:text-gray-500"></i>
                                    <span class="text-xs uppercase tracking-wider {{ $openMenu ? 'font-bold text-gray-700' : 'font-semibold text-gray-500' }}">Menu Management</span>
                                </span>
                                <i class="fas fa-chevron-down text-gray-400 text-xs transition-transform duration-200" :class="open ? 'rotate-180' : ''"></i>
                            </button>
                            <div x-show="open"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="mt-0.5 space-y-0.5 pl-1">
                                <a href="{{ route('categories.index') }}" class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('categories.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                    <i class="fas fa-folder mr-3 text-lg {{ request()->routeIs('categories.*') ? 'text-indigo-500' : 'text-gray-400 group-hover:text-gray-500' }}"></i>
                                    Categories
                                </a>
                                <a href="{{ route('menu-items.index') }}" class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('menu-items.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                    <i class="fas fa-utensils mr-3 text-lg {{ request()->routeIs('menu-items.*') ? 'text-indigo-500' : 'text-gray-400 group-hover:text-gray-500' }}"></i>
                                    Menu Items
                                </a>
                                <a href="{{ route('deals.index') }}" class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('deals.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                    <i class="fas fa-tags mr-3 text-lg {{ request()->routeIs('deals.*') ? 'text-indigo-500' : 'text-gray-400 group-hover:text-gray-500' }}"></i>
                                    Deals
                                </a>
                                <a href="{{ route('cuisines.index') }}" class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('cuisines.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                    <i class="fas fa-globe mr-3 text-lg {{ request()->routeIs('cuisines.*') ? 'text-indigo-500' : 'text-gray-400 group-hover:text-gray-500' }}"></i>
                                    Cuisines
                                </a>
                                <a href="{{ route('product-addons.index') }}" class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('product-addons.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                    <i class="fas fa-plus-circle mr-3 text-lg {{ request()->routeIs('product-addons.*') ? 'text-indigo-500' : 'text-gray-400 group-hover:text-gray-500' }}"></i>
                                    Product Addons
                                </a>
                                <a href="{{ route('variants.index') }}" class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('variants.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                    <i class="fas fa-tags mr-3 text-lg {{ request()->routeIs('variants.*') ? 'text-indigo-500' : 'text-gray-400 group-hover:text-gray-500' }}"></i>
                                    Variants
                                </a>
                                <a href="{{ route('recipes.index') }}" class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('recipes.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                    <i class="fas fa-book-open mr-3 text-lg {{ request()->routeIs('recipes.*') ? 'text-indigo-500' : 'text-gray-400 group-hover:text-gray-500' }}"></i>
                                    Recipes
                                </a>
                            </div>
                        </div>

                        <!-- Inventory Management (collapsible) -->
                        @php $openInv = request()->routeIs('ingredient-categories.*', 'ingredient-units.*', 'ingredients.*', 'purchases.*', 'purchase-returns.*', 'inventory.*'); @endphp
                        <div class="pt-2" x-data="{ open: {{ $openInv ? 'true' : 'false' }} }">
                            <button type="button" @click="open = !open" class="w-full group flex items-center justify-between px-3 py-2.5 text-sm font-medium rounded-lg {{ $openInv ? 'text-gray-900 bg-gray-50' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }} transition-colors">
                                <span class="flex items-center">
                                    <i class="fas fa-boxes mr-3 text-lg text-gray-400 group-hover:text-gray-500"></i>
                                    <span class="text-xs uppercase tracking-wider {{ $openInv ? 'font-bold text-gray-700' : 'font-semibold text-gray-500' }}">Inventory</span>
                                </span>
                                <i class="fas fa-chevron-down text-gray-400 text-xs transition-transform duration-200" :class="open ? 'rotate-180' : ''"></i>
                            </button>
                            <div x-show="open"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="mt-0.5 space-y-0.5 pl-1">
                                <a href="{{ route('ingredient-categories.index') }}" class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('ingredient-categories.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                    <i class="fas fa-tags mr-3 text-lg {{ request()->routeIs('ingredient-categories.*') ? 'text-indigo-500' : 'text-gray-400 group-hover:text-gray-500' }}"></i>
                                    Ingredient Categories
                                </a>
                                <a href="{{ route('ingredient-units.index') }}" class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('ingredient-units.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                    <i class="fas fa-ruler-combined mr-3 text-lg {{ request()->routeIs('ingredient-units.*') ? 'text-indigo-500' : 'text-gray-400 group-hover:text-gray-500' }}"></i>
                                    Ingredient Units
                                </a>
                                <a href="{{ route('ingredients.index') }}" class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('ingredients.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                    <i class="fas fa-leaf mr-3 text-lg {{ request()->routeIs('ingredients.*') ? 'text-indigo-500' : 'text-gray-400 group-hover:text-gray-500' }}"></i>
                                    Ingredients
                                </a>
                                <a href="{{ route('purchases.index') }}" class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('purchases.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                    <i class="fas fa-shopping-cart mr-3 text-lg {{ request()->routeIs('purchases.*') ? 'text-indigo-500' : 'text-gray-400 group-hover:text-gray-500' }}"></i>
                                    Purchases
                                </a>
                                <a href="{{ route('purchase-returns.index') }}" class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('purchase-returns.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                    <i class="fas fa-undo mr-3 text-lg {{ request()->routeIs('purchase-returns.*') ? 'text-indigo-500' : 'text-gray-400 group-hover:text-gray-500' }}"></i>
                                    Purchase Returns
                                </a>
                                <a href="{{ route('inventory.index') }}" class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('inventory.index') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                    <i class="fas fa-warehouse mr-3 text-lg {{ request()->routeIs('inventory.index') ? 'text-indigo-500' : 'text-gray-400 group-hover:text-gray-500' }}"></i>
                                    Stock
                                </a>
                                <a href="{{ route('inventory.adjustment.index') }}" class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('inventory.adjustment.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                    <i class="fas fa-sliders-h mr-3 text-lg {{ request()->routeIs('inventory.adjustment.*') ? 'text-indigo-500' : 'text-gray-400 group-hover:text-gray-500' }}"></i>
                                    Inventory adjustment
                                </a>
                            </div>
                        </div>
                    @endif

                    @if(auth()->user()->branch_id || auth()->user()->isSuperAdmin() || auth()->user()->isCompanyAdmin())
                        @php $reportsNavActive = request()->routeIs('reports.*') || request()->routeIs('shifts.z-report*') || request()->routeIs('account-statements.*'); @endphp
                        <a href="{{ route('reports.index') }}" class="group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg {{ $reportsNavActive ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                            <i class="fas fa-chart-pie mr-3 text-lg {{ $reportsNavActive ? 'text-indigo-500' : 'text-gray-400 group-hover:text-gray-500' }}"></i>
                            Reports
                        </a>
                    @endif

                    @php
                        $canHrNav = auth()->user()->hasAnyAppPermission([
                            'employees.index', 'attendance.index', 'leaves.index', 'payroll.index'
                        ]);
                        $openHr = request()->routeIs('hr.*');
                    @endphp
                    @if($canHrNav)
                        <div class="pt-2" x-data="{ open: {{ $openHr ? 'true' : 'false' }} }">
                            <button type="button" @click="open = !open" class="w-full group flex items-center justify-between px-3 py-2.5 text-sm font-medium rounded-lg {{ $openHr ? 'text-gray-900 bg-gray-50' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                <span class="flex items-center"><i class="fas fa-id-badge mr-3 text-lg text-gray-400"></i><span class="text-xs uppercase tracking-wider {{ $openHr ? 'font-bold text-gray-700' : 'font-semibold text-gray-500' }}">HR · Human Resources</span></span>
                                <i class="fas fa-chevron-down text-gray-400 text-xs transition-transform duration-200" :class="open ? 'rotate-180' : ''"></i>
                            </button>
                            <div x-show="open" class="mt-0.5 space-y-0.5 pl-1">
                                @if(auth()->user()->hasAppPermission('employees.index'))
                                    <a href="{{ route('hr.employees.index') }}" class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('hr.employees.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}"><i class="fas fa-users mr-3 text-lg"></i>Employees</a>
                                @endif
                                @if(auth()->user()->hasAppPermission('attendance.index'))
                                    <a href="{{ route('hr.attendance.index') }}" class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('hr.attendance.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}"><i class="fas fa-user-clock mr-3 text-lg"></i>Attendance</a>
                                @endif
                                @if(auth()->user()->hasAppPermission('leaves.index'))
                                    <a href="{{ route('hr.leaves.index') }}" class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('hr.leaves.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}"><i class="fas fa-calendar-minus mr-3 text-lg"></i>Leave</a>
                                @endif
                                @if(auth()->user()->hasAppPermission('payroll.index'))
                                    <a href="{{ route('hr.payroll.index') }}" class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('hr.payroll.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}"><i class="fas fa-file-invoice-dollar mr-3 text-lg"></i>Payroll</a>
                                    <a href="{{ route('hr.adjustments.index') }}" class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('hr.adjustments.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}"><i class="fas fa-balance-scale mr-3 text-lg"></i>Bonuses & Deductions</a>
                                @endif
                            </div>
                        </div>
                    @endif

                    @if(auth()->user()->isSuperAdmin() || auth()->user()->isCompanyAdmin())
                        <!-- People & Contacts (collapsible) -->
                        @php $openPeople = request()->routeIs('customers.*', 'suppliers.*', 'users.*'); @endphp
                        <div class="pt-2" x-data="{ open: {{ $openPeople ? 'true' : 'false' }} }">
                            <button type="button" @click="open = !open" class="w-full group flex items-center justify-between px-3 py-2.5 text-sm font-medium rounded-lg {{ $openPeople ? 'text-gray-900 bg-gray-50' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }} transition-colors">
                                <span class="flex items-center">
                                    <i class="fas fa-user-friends mr-3 text-lg text-gray-400 group-hover:text-gray-500"></i>
                                    <span class="text-xs uppercase tracking-wider {{ $openPeople ? 'font-bold text-gray-700' : 'font-semibold text-gray-500' }}">People & Contacts</span>
                                </span>
                                <i class="fas fa-chevron-down text-gray-400 text-xs transition-transform duration-200" :class="open ? 'rotate-180' : ''"></i>
                            </button>
                            <div x-show="open"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="mt-0.5 space-y-0.5 pl-1">
                                <a href="{{ route('customers.index') }}" class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('customers.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                    <i class="fas fa-user-friends mr-3 text-lg {{ request()->routeIs('customers.*') ? 'text-indigo-500' : 'text-gray-400 group-hover:text-gray-500' }}"></i>
                                    Customers
                                </a>
                                <a href="{{ route('suppliers.index') }}" class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('suppliers.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                    <i class="fas fa-truck mr-3 text-lg {{ request()->routeIs('suppliers.*') ? 'text-indigo-500' : 'text-gray-400 group-hover:text-gray-500' }}"></i>
                                    Suppliers
                                </a>
                                <a href="{{ route('users.index') }}" class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('users.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                    <i class="fas fa-users mr-3 text-lg {{ request()->routeIs('users.*') ? 'text-indigo-500' : 'text-gray-400 group-hover:text-gray-500' }}"></i>
                                    Users
                                </a>
                            </div>
                        </div>

                        <!-- Financial (collapsible) -->
                        @php $openFinancial = request()->routeIs('accounts.*', 'money-sources.*', 'transactions.*', 'supplier-payments.*', 'customer-payments.*', 'employee-payments.*', 'taxes.*'); @endphp
                        <div class="pt-2" x-data="{ open: {{ $openFinancial ? 'true' : 'false' }} }">
                            <button type="button" @click="open = !open" class="w-full group flex items-center justify-between px-3 py-2.5 text-sm font-medium rounded-lg {{ $openFinancial ? 'text-gray-900 bg-gray-50' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }} transition-colors">
                                <span class="flex items-center">
                                    <i class="fas fa-wallet mr-3 text-lg text-gray-400 group-hover:text-gray-500"></i>
                                    <span class="text-xs uppercase tracking-wider {{ $openFinancial ? 'font-bold text-gray-700' : 'font-semibold text-gray-500' }}">Financial</span>
                                </span>
                                <i class="fas fa-chevron-down text-gray-400 text-xs transition-transform duration-200" :class="open ? 'rotate-180' : ''"></i>
                            </button>
                            <div x-show="open"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="mt-0.5 space-y-0.5 pl-1">
                                <a href="{{ route('accounts.index') }}" class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('accounts.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                    <i class="fas fa-wallet mr-3 text-lg {{ request()->routeIs('accounts.*') ? 'text-indigo-500' : 'text-gray-400 group-hover:text-gray-500' }}"></i>
                                    Accounts
                                </a>
                                <a href="{{ route('money-sources.index') }}" class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('money-sources.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                    <i class="fas fa-money-bill-wave mr-3 text-lg {{ request()->routeIs('money-sources.*') ? 'text-indigo-500' : 'text-gray-400 group-hover:text-gray-500' }}"></i>
                                    Money Sources
                                </a>
                                <a href="{{ route('transactions.index') }}" class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('transactions.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                    <i class="fas fa-exchange-alt mr-3 text-lg {{ request()->routeIs('transactions.*') ? 'text-indigo-500' : 'text-gray-400 group-hover:text-gray-500' }}"></i>
                                    Transactions
                                </a>
                                <a href="{{ route('supplier-payments.index') }}" class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('supplier-payments.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                    <i class="fas fa-money-bill-wave mr-3 text-lg {{ request()->routeIs('supplier-payments.*') ? 'text-indigo-500' : 'text-gray-400 group-hover:text-gray-500' }}"></i>
                                    Supplier Payments
                                </a>
                                <a href="{{ route('customer-payments.index') }}" class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('customer-payments.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                    <i class="fas fa-hand-holding-usd mr-3 text-lg {{ request()->routeIs('customer-payments.*') ? 'text-indigo-500' : 'text-gray-400 group-hover:text-gray-500' }}"></i>
                                    Customer Payments
                                </a>
                                @if(auth()->user()->hasAppPermission('employee-payments.index'))
                                <a href="{{ route('employee-payments.index') }}" class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('employee-payments.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                    <i class="fas fa-user-tie mr-3 text-lg {{ request()->routeIs('employee-payments.*') ? 'text-indigo-500' : 'text-gray-400' }}"></i>
                                    Employee Payments
                                </a>
                                @endif
                                <a href="{{ route('taxes.index') }}" class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('taxes.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                    <i class="fas fa-percent mr-3 text-lg {{ request()->routeIs('taxes.*') ? 'text-indigo-500' : 'text-gray-400 group-hover:text-gray-500' }}"></i>
                                    Taxes
                                </a>
                            </div>
                        </div>

                        <!-- Administration (collapsible) -->
                        @php $openAdmin = request()->routeIs('company-settings.*', 'printer-settings.*', 'branches.*', 'roles.*', 'floors.*', 'tables.*', 'activity-logs.*'); @endphp
                        <div class="pt-2" x-data="{ open: {{ $openAdmin ? 'true' : 'false' }} }">
                            <button type="button" @click="open = !open" class="w-full group flex items-center justify-between px-3 py-2.5 text-sm font-medium rounded-lg {{ $openAdmin ? 'text-gray-900 bg-gray-50' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }} transition-colors">
                                <span class="flex items-center">
                                    <i class="fas fa-cog mr-3 text-lg text-gray-400 group-hover:text-gray-500"></i>
                                    <span class="text-xs uppercase tracking-wider {{ $openAdmin ? 'font-bold text-gray-700' : 'font-semibold text-gray-500' }}">Administration</span>
                                </span>
                                <i class="fas fa-chevron-down text-gray-400 text-xs transition-transform duration-200" :class="open ? 'rotate-180' : ''"></i>
                            </button>
                            <div x-show="open"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="mt-0.5 space-y-0.5 pl-1">
                                <a href="{{ route('company-settings.index') }}" class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('company-settings.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                    <i class="fas fa-cog mr-3 text-lg {{ request()->routeIs('company-settings.*') ? 'text-indigo-500' : 'text-gray-400 group-hover:text-gray-500' }}"></i>
                                    Company Settings
                                </a>
                                @if(auth()->user()->isSuperAdmin() || auth()->user()->isCompanyAdmin() || auth()->user()->hasAppPermission('activity-logs.index'))
                                <a href="{{ route('activity-logs.index') }}" class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('activity-logs.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                    <i class="fas fa-clipboard-list mr-3 text-lg {{ request()->routeIs('activity-logs.*') ? 'text-indigo-500' : 'text-gray-400 group-hover:text-gray-500' }}"></i>
                                    Activity Logs
                                </a>
                                @endif
                                <a href="{{ route('printer-settings.index') }}" class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('printer-settings.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                    <i class="fas fa-print mr-3 text-lg {{ request()->routeIs('printer-settings.*') ? 'text-indigo-500' : 'text-gray-400 group-hover:text-gray-500' }}"></i>
                                    Printer Settings
                                </a>
                                @if(! config('offline.enabled'))
                                <a href="{{ route('branches.index') }}" class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('branches.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                    <i class="fas fa-code-branch mr-3 text-lg {{ request()->routeIs('branches.*') ? 'text-indigo-500' : 'text-gray-400 group-hover:text-gray-500' }}"></i>
                                    Branches
                                </a>
                                @endif
                                <a href="{{ route('floors.index') }}" class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('floors.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                    <i class="fas fa-layer-group mr-3 text-lg {{ request()->routeIs('floors.*') ? 'text-indigo-500' : 'text-gray-400 group-hover:text-gray-500' }}"></i>
                                    Floors
                                </a>
                                <a href="{{ route('tables.index') }}" class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('tables.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                    <i class="fas fa-chair mr-3 text-lg {{ request()->routeIs('tables.*') ? 'text-indigo-500' : 'text-gray-400 group-hover:text-gray-500' }}"></i>
                                    Tables
                                </a>
                                <a href="{{ route('roles.index') }}" class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('roles.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                    <i class="fas fa-user-shield mr-3 text-lg {{ request()->routeIs('roles.*') ? 'text-indigo-500' : 'text-gray-400 group-hover:text-gray-500' }}"></i>
                                    Roles & Permissions
                                </a>
                            </div>
                        </div>
                    @endif

                    @if(! config('offline.enabled') && auth()->user()->isSuperAdmin())
                        <!-- System (collapsible) -->
                        @php $openSystem = request()->routeIs('companies.*'); @endphp
                        <div class="pt-2" x-data="{ open: {{ $openSystem ? 'true' : 'false' }} }">
                            <button type="button" @click="open = !open" class="w-full group flex items-center justify-between px-3 py-2.5 text-sm font-medium rounded-lg {{ $openSystem ? 'text-gray-900 bg-gray-50' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }} transition-colors">
                                <span class="flex items-center">
                                    <i class="fas fa-building mr-3 text-lg text-gray-400 group-hover:text-gray-500"></i>
                                    <span class="text-xs uppercase tracking-wider {{ $openSystem ? 'font-bold text-gray-700' : 'font-semibold text-gray-500' }}">System</span>
                                </span>
                                <i class="fas fa-chevron-down text-gray-400 text-xs transition-transform duration-200" :class="open ? 'rotate-180' : ''"></i>
                            </button>
                            <div x-show="open"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="mt-0.5 space-y-0.5 pl-1">
                                <a href="{{ route('companies.index') }}" class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('companies.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                    <i class="fas fa-building mr-3 text-lg {{ request()->routeIs('companies.*') ? 'text-indigo-500' : 'text-gray-400 group-hover:text-gray-500' }}"></i>
                                    Companies
                                </a>
                                <a href="#" class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg text-gray-700 hover:bg-gray-50 hover:text-gray-900">
                                    <i class="fas fa-cog mr-3 text-lg text-gray-400 group-hover:text-gray-500"></i>
                                    Settings
                                </a>
                            </div>
                        </div>
                    @endif
                </nav>
            </div>
        </div>
    </div>
</div>

<!-- Mobile Sidebar -->
<div x-show="sidebarOpen" 
     x-cloak
     class="fixed inset-y-0 left-0 z-30 w-64 bg-white border-r border-gray-200 lg:hidden"
     x-transition:enter="transition ease-out duration-300 transform"
     x-transition:enter-start="-translate-x-full"
     x-transition:enter-end="translate-x-0"
     x-transition:leave="transition ease-in duration-300 transform"
     x-transition:leave-start="translate-x-0"
     x-transition:leave-end="-translate-x-full">
    <div class="flex flex-col h-full pt-5 pb-4 overflow-y-auto">
        <!-- Mobile Logo -->
        <div class="flex items-center flex-shrink-0 px-4">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    @if(get_logo())
                        <img src="{{ get_logo() }}" 
                             alt="Company Logo" 
                             class="h-10 w-10 rounded-lg object-contain">
                    @else
                        <div class="h-10 w-10 rounded-lg bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center">
                            <i class="fas fa-utensils text-white text-xl"></i>
                        </div>
                    @endif
                </div>
                <div class="ml-3">
                    <h1 class="text-lg font-bold text-gray-900">{{ get_company_name() }}</h1>
                </div>
            </div>
        </div>

        <!-- Mobile Navigation (collapsible same as desktop) -->
        <div class="mt-8 flex-grow flex flex-col">
            <nav class="flex-1 px-2 space-y-0.5">
                @if(auth()->user()->hasAppPermission('dashboard.index'))
                <a href="{{ route('dashboard') }}" class="group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg {{ request()->routeIs('dashboard') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}">
                    <i class="fas fa-home mr-3 text-lg"></i>
                    Dashboard
                </a>
                @endif

                @if(auth()->user()->branch_id || auth()->user()->isSuperAdmin() || auth()->user()->isCompanyAdmin())
                <a href="{{ route('shifts.index') }}" class="group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg {{ request()->routeIs('shifts.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}">
                    <i class="fas fa-clock mr-3 text-lg"></i>
                    Shifts
                </a>

                <a href="{{ route('pos.index') }}" class="group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg {{ request()->routeIs('pos.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}">
                    <i class="fas fa-cash-register mr-3 text-lg"></i>
                    POS
                </a>

                    @php
                        $openOrd = request()->routeIs('order-management.*');
                        $ordOrdersNav = request()->routeIs('order-management.index', 'order-management.show', 'order-management.append-note');
                        $ordRefundsNav = request()->routeIs('order-management.refunds.index', 'order-management.refunds.process');
                        $uMob = auth()->user();
                        $canOrderMgmtNavMob = $uMob->hasAnyAppPermission(['order-management.index', 'order-management.show', 'order-management.append-note', 'order-management.refund']);
                        $canOrdersListNavMob = $uMob->hasAppPermission('order-management.index');
                        $canRefundMenuMob = $uMob->hasAppPermission('order-management.refund');
                    @endphp
                    @if($canOrderMgmtNavMob)
                    <div class="pt-2" x-data="{ open: {{ $openOrd ? 'true' : 'false' }} }">
                        <button type="button" @click="open = !open" class="w-full group flex items-center justify-between px-3 py-2.5 text-sm font-medium rounded-lg {{ $openOrd ? 'text-gray-900 bg-gray-50' : 'text-gray-700 hover:bg-gray-50' }}">
                            <span class="flex items-center"><i class="fas fa-receipt mr-3 text-lg"></i><span class="text-xs uppercase {{ $openOrd ? 'font-bold text-gray-700' : 'font-semibold text-gray-500' }}">Order Management</span></span>
                            <i class="fas fa-chevron-down text-gray-400 text-xs transition-transform duration-200" :class="open ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="mt-0.5 space-y-0.5 pl-1">
                            @if($canOrdersListNavMob)
                            <a href="{{ route('order-management.index') }}" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ $ordOrdersNav ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}"><i class="fas fa-list mr-3 text-lg"></i>Orders</a>
                            @endif
                            @if($canRefundMenuMob)
                            <a href="{{ route('order-management.refunds.index') }}" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ $ordRefundsNav ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}"><i class="fas fa-undo-alt mr-3 text-lg"></i>Refunds</a>
                            @endif
                        </div>
                    </div>
                    @endif

                    @php $openMenu = request()->routeIs('categories.*', 'menu-items.*', 'deals.*', 'cuisines.*', 'product-addons.*', 'variants.*', 'recipes.*'); @endphp
                    <div class="pt-2" x-data="{ open: {{ $openMenu ? 'true' : 'false' }} }">
                        <button type="button" @click="open = !open" class="w-full group flex items-center justify-between px-3 py-2.5 text-sm font-medium rounded-lg {{ $openMenu ? 'text-gray-900 bg-gray-50' : 'text-gray-700 hover:bg-gray-50' }}">
                            <span class="flex items-center"><i class="fas fa-utensils mr-3 text-lg"></i><span class="text-xs uppercase {{ $openMenu ? 'font-bold text-gray-700' : 'font-semibold text-gray-500' }}">Menu Management</span></span>
                            <i class="fas fa-chevron-down text-gray-400 text-xs transition-transform duration-200" :class="open ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="mt-0.5 space-y-0.5 pl-1">
                            <a href="{{ route('categories.index') }}" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('categories.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}"><i class="fas fa-folder mr-3 text-lg"></i>Categories</a>
                            <a href="{{ route('menu-items.index') }}" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('menu-items.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}"><i class="fas fa-utensils mr-3 text-lg"></i>Menu Items</a>
                            <a href="{{ route('deals.index') }}" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('deals.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}"><i class="fas fa-tags mr-3 text-lg"></i>Deals</a>
                            <a href="{{ route('cuisines.index') }}" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('cuisines.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}"><i class="fas fa-globe mr-3 text-lg"></i>Cuisines</a>
                            <a href="{{ route('product-addons.index') }}" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('product-addons.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}"><i class="fas fa-plus-circle mr-3 text-lg"></i>Product Addons</a>
                            <a href="{{ route('variants.index') }}" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('variants.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}"><i class="fas fa-tags mr-3 text-lg"></i>Variants</a>
                            <a href="{{ route('recipes.index') }}" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('recipes.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}"><i class="fas fa-book-open mr-3 text-lg"></i>Recipes</a>
                        </div>
                    </div>

                    @php $openInv = request()->routeIs('ingredient-categories.*', 'ingredient-units.*', 'ingredients.*', 'purchases.*', 'purchase-returns.*', 'inventory.*'); @endphp
                    <div class="pt-2" x-data="{ open: {{ $openInv ? 'true' : 'false' }} }">
                        <button type="button" @click="open = !open" class="w-full group flex items-center justify-between px-3 py-2.5 text-sm font-medium rounded-lg {{ $openInv ? 'text-gray-900 bg-gray-50' : 'text-gray-700 hover:bg-gray-50' }}">
                            <span class="flex items-center"><i class="fas fa-boxes mr-3 text-lg"></i><span class="text-xs uppercase {{ $openInv ? 'font-bold text-gray-700' : 'font-semibold text-gray-500' }}">Inventory</span></span>
                            <i class="fas fa-chevron-down text-gray-400 text-xs transition-transform duration-200" :class="open ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="mt-0.5 space-y-0.5 pl-1">
                            <a href="{{ route('ingredient-categories.index') }}" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('ingredient-categories.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}"><i class="fas fa-tags mr-3 text-lg"></i>Ingredient Categories</a>
                            <a href="{{ route('ingredient-units.index') }}" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('ingredient-units.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}"><i class="fas fa-ruler-combined mr-3 text-lg"></i>Ingredient Units</a>
                            <a href="{{ route('ingredients.index') }}" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('ingredients.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}"><i class="fas fa-leaf mr-3 text-lg"></i>Ingredients</a>
                            <a href="{{ route('purchases.index') }}" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('purchases.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}"><i class="fas fa-shopping-cart mr-3 text-lg"></i>Purchases</a>
                            <a href="{{ route('purchase-returns.index') }}" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('purchase-returns.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}"><i class="fas fa-undo mr-3 text-lg"></i>Purchase Returns</a>
                            <a href="{{ route('inventory.index') }}" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('inventory.index') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}"><i class="fas fa-warehouse mr-3 text-lg"></i>Stock</a>
                            <a href="{{ route('inventory.adjustment.index') }}" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('inventory.adjustment.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}"><i class="fas fa-sliders-h mr-3 text-lg"></i>Inventory adjustment</a>
                        </div>
                    </div>
                @endif

                @if(auth()->user()->branch_id || auth()->user()->isSuperAdmin() || auth()->user()->isCompanyAdmin())
                    @php $reportsNavActiveMob = request()->routeIs('reports.*') || request()->routeIs('shifts.z-report*') || request()->routeIs('account-statements.*'); @endphp
                    <a href="{{ route('reports.index') }}" class="group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg {{ $reportsNavActiveMob ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}">
                        <i class="fas fa-chart-pie mr-3 text-lg"></i>
                        Reports
                    </a>
                @endif

                @php
                    $canHrNavMob = auth()->user()->hasAnyAppPermission([
                        'employees.index', 'attendance.index', 'leaves.index', 'payroll.index'
                    ]);
                    $openHrMob = request()->routeIs('hr.*');
                @endphp
                @if($canHrNavMob)
                    <div class="pt-2" x-data="{ open: {{ $openHrMob ? 'true' : 'false' }} }">
                        <button type="button" @click="open = !open" class="w-full group flex items-center justify-between px-3 py-2.5 text-sm font-medium rounded-lg {{ $openHrMob ? 'text-gray-900 bg-gray-50' : 'text-gray-700 hover:bg-gray-50' }}">
                            <span class="flex items-center"><i class="fas fa-id-badge mr-3 text-lg"></i><span class="text-xs uppercase {{ $openHrMob ? 'font-bold text-gray-700' : 'font-semibold text-gray-500' }}">HR · Human Resources</span></span>
                            <i class="fas fa-chevron-down text-gray-400 text-xs transition-transform duration-200" :class="open ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open" class="mt-0.5 space-y-0.5 pl-1">
                            @if(auth()->user()->hasAppPermission('employees.index'))<a href="{{ route('hr.employees.index') }}" class="flex items-center px-3 py-2 text-sm rounded-lg {{ request()->routeIs('hr.employees.*') ? 'bg-indigo-50 text-indigo-700' : '' }}"><i class="fas fa-users mr-3"></i>Employees</a>@endif
                            @if(auth()->user()->hasAppPermission('attendance.index'))<a href="{{ route('hr.attendance.index') }}" class="flex items-center px-3 py-2 text-sm rounded-lg {{ request()->routeIs('hr.attendance.*') ? 'bg-indigo-50 text-indigo-700' : '' }}"><i class="fas fa-user-clock mr-3"></i>Attendance</a>@endif
                            @if(auth()->user()->hasAppPermission('leaves.index'))<a href="{{ route('hr.leaves.index') }}" class="flex items-center px-3 py-2 text-sm rounded-lg {{ request()->routeIs('hr.leaves.*') ? 'bg-indigo-50 text-indigo-700' : '' }}"><i class="fas fa-calendar-minus mr-3"></i>Leave</a>@endif
                            @if(auth()->user()->hasAppPermission('payroll.index'))<a href="{{ route('hr.payroll.index') }}" class="flex items-center px-3 py-2 text-sm rounded-lg {{ request()->routeIs('hr.payroll.*') ? 'bg-indigo-50 text-indigo-700' : '' }}"><i class="fas fa-file-invoice-dollar mr-3"></i>Payroll</a><a href="{{ route('hr.adjustments.index') }}" class="flex items-center px-3 py-2 text-sm rounded-lg {{ request()->routeIs('hr.adjustments.*') ? 'bg-indigo-50 text-indigo-700' : '' }}"><i class="fas fa-balance-scale mr-3"></i>Bonuses & Deductions</a>@endif
                        </div>
                    </div>
                @endif

                @if(auth()->user()->isSuperAdmin() || auth()->user()->isCompanyAdmin())
                    @php $openPeople = request()->routeIs('customers.*', 'suppliers.*', 'users.*'); @endphp
                    <div class="pt-2" x-data="{ open: {{ $openPeople ? 'true' : 'false' }} }">
                        <button type="button" @click="open = !open" class="w-full group flex items-center justify-between px-3 py-2.5 text-sm font-medium rounded-lg {{ $openPeople ? 'text-gray-900 bg-gray-50' : 'text-gray-700 hover:bg-gray-50' }}">
                            <span class="flex items-center"><i class="fas fa-user-friends mr-3 text-lg"></i><span class="text-xs uppercase {{ $openPeople ? 'font-bold text-gray-700' : 'font-semibold text-gray-500' }}">People & Contacts</span></span>
                            <i class="fas fa-chevron-down text-gray-400 text-xs transition-transform duration-200" :class="open ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="mt-0.5 space-y-0.5 pl-1">
                            <a href="{{ route('customers.index') }}" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('customers.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}"><i class="fas fa-user-friends mr-3 text-lg"></i>Customers</a>
                            <a href="{{ route('suppliers.index') }}" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('suppliers.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}"><i class="fas fa-truck mr-3 text-lg"></i>Suppliers</a>
                            <a href="{{ route('users.index') }}" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('users.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}"><i class="fas fa-users mr-3 text-lg"></i>Users</a>
                        </div>
                    </div>

                    @php $openFinancial = request()->routeIs('accounts.*', 'money-sources.*', 'transactions.*', 'supplier-payments.*', 'customer-payments.*', 'employee-payments.*', 'taxes.*'); @endphp
                    <div class="pt-2" x-data="{ open: {{ $openFinancial ? 'true' : 'false' }} }">
                        <button type="button" @click="open = !open" class="w-full group flex items-center justify-between px-3 py-2.5 text-sm font-medium rounded-lg {{ $openFinancial ? 'text-gray-900 bg-gray-50' : 'text-gray-700 hover:bg-gray-50' }}">
                            <span class="flex items-center"><i class="fas fa-wallet mr-3 text-lg"></i><span class="text-xs uppercase {{ $openFinancial ? 'font-bold text-gray-700' : 'font-semibold text-gray-500' }}">Financial</span></span>
                            <i class="fas fa-chevron-down text-gray-400 text-xs transition-transform duration-200" :class="open ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="mt-0.5 space-y-0.5 pl-1">
                            <a href="{{ route('accounts.index') }}" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('accounts.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}"><i class="fas fa-wallet mr-3 text-lg"></i>Accounts</a>
                            <a href="{{ route('money-sources.index') }}" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('money-sources.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}"><i class="fas fa-money-bill-wave mr-3 text-lg"></i>Money Sources</a>
                            <a href="{{ route('transactions.index') }}" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('transactions.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}"><i class="fas fa-exchange-alt mr-3 text-lg"></i>Transactions</a>
                            <a href="{{ route('supplier-payments.index') }}" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('supplier-payments.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}"><i class="fas fa-money-bill-wave mr-3 text-lg"></i>Supplier Payments</a>
                            <a href="{{ route('customer-payments.index') }}" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('customer-payments.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}"><i class="fas fa-hand-holding-usd mr-3 text-lg"></i>Customer Payments</a>
                            @if(auth()->user()->hasAppPermission('employee-payments.index'))
                            <a href="{{ route('employee-payments.index') }}" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('employee-payments.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}"><i class="fas fa-user-tie mr-3 text-lg"></i>Employee Payments</a>
                            @endif
                            <a href="{{ route('taxes.index') }}" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('taxes.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}"><i class="fas fa-percent mr-3 text-lg"></i>Taxes</a>
                        </div>
                    </div>

                    @php $openAdmin = request()->routeIs('company-settings.*', 'printer-settings.*', 'branches.*', 'roles.*', 'floors.*', 'tables.*', 'activity-logs.*'); @endphp
                    <div class="pt-2" x-data="{ open: {{ $openAdmin ? 'true' : 'false' }} }">
                        <button type="button" @click="open = !open" class="w-full group flex items-center justify-between px-3 py-2.5 text-sm font-medium rounded-lg {{ $openAdmin ? 'text-gray-900 bg-gray-50' : 'text-gray-700 hover:bg-gray-50' }}">
                            <span class="flex items-center"><i class="fas fa-cog mr-3 text-lg"></i><span class="text-xs uppercase {{ $openAdmin ? 'font-bold text-gray-700' : 'font-semibold text-gray-500' }}">Administration</span></span>
                            <i class="fas fa-chevron-down text-gray-400 text-xs transition-transform duration-200" :class="open ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="mt-0.5 space-y-0.5 pl-1">
                            <a href="{{ route('company-settings.index') }}" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('company-settings.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}"><i class="fas fa-cog mr-3 text-lg"></i>Company Settings</a>
                            @if(auth()->user()->isSuperAdmin() || auth()->user()->isCompanyAdmin() || auth()->user()->hasAppPermission('activity-logs.index'))
                            <a href="{{ route('activity-logs.index') }}" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('activity-logs.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}"><i class="fas fa-clipboard-list mr-3 text-lg"></i>Activity Logs</a>
                            @endif
                            <a href="{{ route('printer-settings.index') }}" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('printer-settings.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}"><i class="fas fa-print mr-3 text-lg"></i>Printer Settings</a>
                            @if(! config('offline.enabled'))
                            <a href="{{ route('branches.index') }}" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('branches.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}"><i class="fas fa-code-branch mr-3 text-lg"></i>Branches</a>
                            @endif
                            <a href="{{ route('floors.index') }}" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('floors.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}"><i class="fas fa-layer-group mr-3 text-lg"></i>Floors</a>
                            <a href="{{ route('tables.index') }}" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('tables.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}"><i class="fas fa-chair mr-3 text-lg"></i>Tables</a>
                            <a href="{{ route('roles.index') }}" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('roles.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}"><i class="fas fa-user-shield mr-3 text-lg"></i>Roles & Permissions</a>
                        </div>
                    </div>
                @endif

                @if(! config('offline.enabled') && auth()->user()->isSuperAdmin())
                    @php $openSystem = request()->routeIs('companies.*'); @endphp
                    <div class="pt-2" x-data="{ open: {{ $openSystem ? 'true' : 'false' }} }">
                        <button type="button" @click="open = !open" class="w-full group flex items-center justify-between px-3 py-2.5 text-sm font-medium rounded-lg {{ $openSystem ? 'text-gray-900 bg-gray-50' : 'text-gray-700 hover:bg-gray-50' }}">
                            <span class="flex items-center"><i class="fas fa-building mr-3 text-lg"></i><span class="text-xs uppercase {{ $openSystem ? 'font-bold text-gray-700' : 'font-semibold text-gray-500' }}">System</span></span>
                            <i class="fas fa-chevron-down text-gray-400 text-xs transition-transform duration-200" :class="open ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="mt-0.5 space-y-0.5 pl-1">
                            <a href="{{ route('companies.index') }}" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('companies.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}"><i class="fas fa-building mr-3 text-lg"></i>Companies</a>
                            <a href="#" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg text-gray-700 hover:bg-gray-50"><i class="fas fa-cog mr-3 text-lg text-gray-400"></i>Settings</a>
                        </div>
                    </div>
                @endif
            </nav>
        </div>
    </div>
</div>
@endauth
