@auth
<div class="relative z-10 flex-shrink-0 flex h-16 bg-white shadow-sm border-b border-gray-200">
    <div class="flex-1 px-4 flex justify-between">
        <div class="flex-1 flex items-center">
            <!-- Mobile menu button -->
            <button @click="sidebarOpen = !sidebarOpen" class="lg:hidden px-4 border-r border-gray-200 text-gray-500 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-indigo-500">
                <i class="fas fa-bars text-xl"></i>
            </button>

            <!-- Search bar (optional) -->
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
            <!-- Shift Button -->
            @php
                $user = auth()->user();
                $branchId = $user ? \App\Support\BranchContext::currentBranchId($user) : null;
                $activeShift = ($user && $branchId)
                    ? \App\Models\Shift::getActiveShiftForUser($branchId, (int) $user->id)
                    : null;
            @endphp
            @php
                $shiftData = $activeShift ? [
                    'id' => $activeShift->id,
                    'opened_at' => $activeShift->opened_at->format('H:i'),
                ] : null;
            @endphp
            <div class="relative" x-data="shiftButton({{ json_encode($shiftData) }})">
                @if($activeShift)
                    <!-- End Shift Button -->
                    <a href="{{ route('shifts.edit', $activeShift->id) }}" 
                       class="flex items-center px-3 md:px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500">
                        <i class="fas fa-stop-circle mr-1 md:mr-2"></i>
                        <span class="hidden md:inline">End Shift</span>
                        <span class="md:ml-2 text-xs opacity-90" x-show="shiftData" x-text="'Started: ' + shiftStartTime"></span>
                    </a>
                @else
                    <!-- Start Shift Button -->
                    <a href="{{ route('shifts.create') }}" 
                       class="flex items-center px-3 md:px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500">
                        <i class="fas fa-play-circle mr-1 md:mr-2"></i>
                        <span class="hidden md:inline">Start Shift</span>
                    </a>
                @endif
            </div>

            <!-- Branch Switcher (SaaS multi-branch only) -->
            @if(! config('offline.enabled') && auth()->user()->canAccessMultipleBranches() && auth()->user()->company)
                <div class="relative" x-data="{ branchOpen: false }">
                    <button @click="branchOpen = !branchOpen" 
                            class="flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <i class="fas fa-code-branch mr-2 text-gray-400"></i>
                        <span class="hidden md:block">
                            @if(auth()->user()->branch)
                                {{ auth()->user()->branch->name }}
                            @else
                                Select Branch
                            @endif
                        </span>
                        <i class="fas fa-chevron-down ml-2 text-xs"></i>
                    </button>

                    <div x-show="branchOpen" 
                         x-cloak
                         @click.away="branchOpen = false"
                         x-transition:enter="transition ease-out duration-100"
                         x-transition:enter-start="transform opacity-0 scale-95"
                         x-transition:enter-end="transform opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-75"
                         x-transition:leave-start="transform opacity-100 scale-100"
                         x-transition:leave-end="transform opacity-0 scale-95"
                         class="absolute right-0 mt-2 w-64 rounded-lg shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50">
                        <div class="py-1">
                            <div class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase border-b border-gray-200">
                                Switch Branch
                            </div>
                            @php
                                $branches = auth()->user()->company->branches;
                            @endphp
                            @foreach($branches as $branch)
                                <form method="POST" action="{{ route('branch.switch') }}">
                                    @csrf
                                    <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                                    <button type="submit" 
                                            class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 flex items-center {{ auth()->user()->branch_id == $branch->id ? 'bg-indigo-50 text-indigo-700' : '' }}">
                                        <i class="fas fa-code-branch mr-2 text-gray-400"></i>
                                        <div class="flex-1">
                                            <div class="font-medium">{{ $branch->name }}</div>
                                            <div class="text-xs text-gray-500">{{ $branch->code }}</div>
                                        </div>
                                        @if(auth()->user()->branch_id == $branch->id)
                                            <i class="fas fa-check text-indigo-600 ml-2"></i>
                                        @endif
                                    </button>
                                </form>
                            @endforeach
                        </div>
                    </div>
                </div>
            @elseif(! config('offline.enabled') && auth()->user()->branch)
                <!-- Show current branch for branch-restricted users -->
                <div class="flex items-center px-3 py-2 text-sm text-gray-700 bg-gray-50 border border-gray-200 rounded-lg">
                    <i class="fas fa-code-branch mr-2 text-gray-400"></i>
                    <span class="hidden md:block">{{ auth()->user()->branch->name }}</span>
                </div>
            @endif

            <!-- User Dropdown -->
            <div class="relative" x-data="{ userOpen: false }">
                <button @click="userOpen = !userOpen" 
                        class="flex items-center max-w-xs bg-white rounded-full focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <div class="h-10 w-10 rounded-full bg-gradient-to-br from-indigo-400 to-purple-500 flex items-center justify-center text-white font-semibold">
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
                        <!-- User Info -->
                        <div class="px-4 py-3 border-b border-gray-200">
                            <p class="text-sm font-medium text-gray-900">{{ auth()->user()->name }}</p>
                            <p class="text-xs text-gray-500 truncate">{{ auth()->user()->email }}</p>
                            <p class="text-xs text-gray-400 mt-1">
                                <span class="px-2 py-1 bg-indigo-100 text-indigo-700 rounded">{{ ucfirst(str_replace('_', ' ', auth()->user()->type)) }}</span>
                            </p>
                        </div>

                        <!-- Menu Items -->
                        <a href="{{ route('dashboard') }}" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fas fa-home mr-3 text-gray-400"></i>
                            Dashboard
                        </a>
                        <a href="#" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fas fa-user mr-3 text-gray-400"></i>
                            Profile
                        </a>
                        <a href="#" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fas fa-cog mr-3 text-gray-400"></i>
                            Settings
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

