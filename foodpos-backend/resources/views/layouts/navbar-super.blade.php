@auth
<div class="relative z-10 flex-shrink-0 flex h-16 bg-white shadow-sm border-b border-gray-200">
    <div class="flex-1 px-4 flex justify-between">
        <div class="flex-1 flex items-center">
            <button @click="sidebarOpen = !sidebarOpen" class="lg:hidden px-4 border-r border-gray-200 text-gray-500 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-indigo-500">
                <i class="fas fa-bars text-xl"></i>
            </button>
            <div class="w-full max-w-lg lg:max-w-xs ml-4">
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-search text-gray-400"></i>
                    </div>
                    <input type="text"
                           class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                           placeholder="Search...">
                </div>
            </div>
        </div>

        <div class="ml-4 flex items-center md:ml-6 space-x-4">
            <span class="px-3 py-1.5 text-xs font-medium bg-slate-100 text-slate-700 rounded-full">Super Admin</span>

            <!-- User Dropdown -->
            <div class="relative" x-data="{ userOpen: false }">
                <button @click="userOpen = !userOpen"
                        class="flex items-center max-w-xs bg-white rounded-full focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <div class="h-10 w-10 rounded-full bg-gradient-to-br from-slate-600 to-slate-800 flex items-center justify-center text-white font-semibold">
                        {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                    </div>
                    <span class="hidden md:block ml-3 text-sm font-medium text-gray-700">
                        {{ auth()->user()->name }}
                    </span>
                    <i class="fas fa-chevron-down ml-2 text-xs text-gray-400"></i>
                </button>

                <div x-show="userOpen"
                     x-cloak
                     @click.away="userOpen = false"
                     x-transition:enter="transition ease-out duration-100"
                     x-transition:enter-start="transform opacity-0 scale-95"
                     x-transition:enter-end="transform opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-75"
                     x-transition:leave-start="transform opacity-100 scale-100"
                     x-transition:leave-end="transform opacity-0 scale-95"
                     class="absolute right-0 mt-2 w-64 rounded-lg shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50">
                    <div class="py-1">
                        <div class="px-4 py-3 border-b border-gray-200">
                            <p class="text-sm font-medium text-gray-900">{{ auth()->user()->name }}</p>
                            <p class="text-xs text-gray-500 truncate">{{ auth()->user()->email }}</p>
                            <p class="text-xs text-gray-400 mt-1">
                                <span class="px-2 py-1 bg-slate-100 text-slate-700 rounded">Super Admin</span>
                            </p>
                        </div>
                        <a href="{{ route('dashboard') }}" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fas fa-home mr-3 text-gray-400"></i>
                            Dashboard
                        </a>
                        <a href="{{ route('platform-media.index') }}" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fas fa-images mr-3 text-gray-400"></i>
                            Media Library
                        </a>
                        <a href="{{ route('platform-invoices.index') }}" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fas fa-file-invoice-dollar mr-3 text-gray-400"></i>
                            Invoices
                        </a>
                        <a href="{{ route('platform-actions.index') }}" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fas fa-terminal mr-3 text-gray-400"></i>
                            Platform Actions
                        </a>
                        <a href="{{ route('database-backups.index') }}" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fas fa-database mr-3 text-gray-400"></i>
                            Database Backups
                        </a>
                        <div class="border-t border-gray-200"></div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="w-full text-left flex items-center px-4 py-2 text-sm text-red-700 hover:bg-red-50">
                                <i class="fas fa-sign-out-alt mr-3"></i>
                                Logout
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endauth
