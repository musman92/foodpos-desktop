@auth
<div class="hidden lg:flex lg:flex-shrink-0">
    <div class="flex flex-col w-64">
        <div class="flex flex-col flex-grow bg-white border-r border-gray-200 pt-5 pb-4 overflow-y-auto">
            <!-- Logo - Super Admin -->
            <div class="flex items-center flex-shrink-0 px-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="h-10 w-10 rounded-lg bg-gradient-to-br from-slate-700 to-slate-900 flex items-center justify-center">
                            <i class="fas fa-shield-alt text-white text-xl"></i>
                        </div>
                    </div>
                    <div class="ml-3">
                        <h1 class="text-lg font-bold text-gray-900">Super Admin</h1>
                        <p class="text-xs text-gray-500">System Control</p>
                    </div>
                </div>
            </div>

            <!-- Navigation - Super User only -->
            <div class="mt-8 flex-grow flex flex-col">
                <nav class="flex-1 px-2 space-y-1">
                    <a href="{{ route('dashboard') }}" class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('dashboard') ? 'bg-slate-100 text-slate-800' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                        <i class="fas fa-home mr-3 text-lg {{ request()->routeIs('dashboard') ? 'text-slate-600' : 'text-gray-400 group-hover:text-gray-500' }}"></i>
                        Dashboard
                    </a>

                    <div class="pt-4">
                        <p class="px-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Billing</p>
                        <div class="mt-2 space-y-1">
                            <a href="{{ route('platform-invoices.index') }}" class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('platform-invoices.*') ? 'bg-slate-100 text-slate-800' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                <i class="fas fa-file-invoice-dollar mr-3 text-lg {{ request()->routeIs('platform-invoices.*') ? 'text-slate-600' : 'text-gray-400 group-hover:text-gray-500' }}"></i>
                                Invoices
                            </a>
                            <a href="{{ route('platform-billing.report') }}" class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('platform-billing.*') ? 'bg-slate-100 text-slate-800' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                <i class="fas fa-chart-bar mr-3 text-lg {{ request()->routeIs('platform-billing.*') ? 'text-slate-600' : 'text-gray-400 group-hover:text-gray-500' }}"></i>
                                Billing Report
                            </a>
                        </div>
                    </div>

                    <div class="pt-4">
                        <p class="px-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">System</p>
                        <div class="mt-2 space-y-1">
                            <a href="{{ route('companies.index') }}" class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('companies.*') ? 'bg-slate-100 text-slate-800' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                <i class="fas fa-building mr-3 text-lg {{ request()->routeIs('companies.*') ? 'text-slate-600' : 'text-gray-400 group-hover:text-gray-500' }}"></i>
                                Companies
                            </a>
                            <a href="{{ route('platform-media.index') }}" class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('platform-media.*') ? 'bg-slate-100 text-slate-800' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                <i class="fas fa-images mr-3 text-lg {{ request()->routeIs('platform-media.*') ? 'text-slate-600' : 'text-gray-400 group-hover:text-gray-500' }}"></i>
                                Media Library
                            </a>
                            <a href="{{ route('platform-actions.index') }}" class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('platform-actions.*') ? 'bg-slate-100 text-slate-800' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                <i class="fas fa-terminal mr-3 text-lg {{ request()->routeIs('platform-actions.*') ? 'text-slate-600' : 'text-gray-400 group-hover:text-gray-500' }}"></i>
                                Platform Actions
                            </a>
                            <a href="{{ route('database-backups.index') }}" class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('database-backups.*') ? 'bg-slate-100 text-slate-800' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                <i class="fas fa-database mr-3 text-lg {{ request()->routeIs('database-backups.*') ? 'text-slate-600' : 'text-gray-400 group-hover:text-gray-500' }}"></i>
                                Database Backups
                            </a>
                        </div>
                    </div>
                </nav>
            </div>
        </div>
    </div>
</div>

<!-- Mobile Sidebar - Super -->
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
        <div class="flex items-center flex-shrink-0 px-4">
            <div class="h-10 w-10 rounded-lg bg-gradient-to-br from-slate-700 to-slate-900 flex items-center justify-center">
                <i class="fas fa-shield-alt text-white text-xl"></i>
            </div>
            <div class="ml-3">
                <h1 class="text-lg font-bold text-gray-900">Super Admin</h1>
            </div>
        </div>
        <div class="mt-8 flex-grow flex flex-col">
            <nav class="flex-1 px-2 space-y-1">
                <a href="{{ route('dashboard') }}" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('dashboard') ? 'bg-slate-100 text-slate-800' : 'text-gray-700 hover:bg-gray-50' }}">
                    <i class="fas fa-home mr-3 text-lg"></i>
                    Dashboard
                </a>
                <div class="pt-4">
                    <p class="px-3 text-xs font-semibold text-gray-500 uppercase">Billing</p>
                    <div class="mt-2 space-y-1">
                        <a href="{{ route('platform-invoices.index') }}" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('platform-invoices.*') ? 'bg-slate-100 text-slate-800' : 'text-gray-700 hover:bg-gray-50' }}">
                            <i class="fas fa-file-invoice-dollar mr-3 text-lg"></i>
                            Invoices
                        </a>
                        <a href="{{ route('platform-billing.report') }}" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('platform-billing.*') ? 'bg-slate-100 text-slate-800' : 'text-gray-700 hover:bg-gray-50' }}">
                            <i class="fas fa-chart-bar mr-3 text-lg"></i>
                            Billing Report
                        </a>
                    </div>
                </div>
                <div class="pt-4">
                    <p class="px-3 text-xs font-semibold text-gray-500 uppercase">System</p>
                    <div class="mt-2 space-y-1">
                        <a href="{{ route('companies.index') }}" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('companies.*') ? 'bg-slate-100 text-slate-800' : 'text-gray-700 hover:bg-gray-50' }}">
                            <i class="fas fa-building mr-3 text-lg"></i>
                            Companies
                        </a>
                        <a href="{{ route('platform-media.index') }}" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('platform-media.*') ? 'bg-slate-100 text-slate-800' : 'text-gray-700 hover:bg-gray-50' }}">
                            <i class="fas fa-images mr-3 text-lg"></i>
                            Media Library
                        </a>
                        <a href="{{ route('platform-actions.index') }}" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('platform-actions.*') ? 'bg-slate-100 text-slate-800' : 'text-gray-700 hover:bg-gray-50' }}">
                            <i class="fas fa-terminal mr-3 text-lg"></i>
                            Platform Actions
                        </a>
                        <a href="{{ route('database-backups.index') }}" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('database-backups.*') ? 'bg-slate-100 text-slate-800' : 'text-gray-700 hover:bg-gray-50' }}">
                            <i class="fas fa-database mr-3 text-lg"></i>
                            Database Backups
                        </a>
                    </div>
                </div>
            </nav>
        </div>
    </div>
</div>
@endauth
