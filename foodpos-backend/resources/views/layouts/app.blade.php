<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Food POS System')</title>
    @if(get_favicon())
        <link rel="icon" type="image/x-icon" href="{{ get_favicon() }}">
    @endif
    <script src="https://cdn.tailwindcss.com"></script>
    @include('partials.searchable-select-alpine')
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        [x-cloak] { display: none !important; }

        /* Visible borders for form fields (Tailwind CDN: border-gray-* without `border` has no width) */
        main input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]):not([type="range"]):not([type="button"]):not([type="submit"]):not([type="reset"]):not([type="image"]),
        main select,
        main textarea {
            border-style: solid;
            border-width: 1px;
            border-color: rgb(209 213 219); /* gray-300 */
        }

        .filter-control {
            height: 2.75rem;
            padding-left: 0.75rem;
            padding-right: 0.75rem;
            border-radius: 0.5rem;
            border: 1px solid rgb(209 213 219);
            background-color: #fff;
            font-size: 0.875rem;
            line-height: 1.25rem;
            box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
        }

        .filter-control:focus {
            outline: none;
            border-color: rgb(99 102 241);
            box-shadow: 0 0 0 1px rgb(99 102 241);
        }

        /* Striped rows for admin listing tables */
        .listing-table > tbody > tr:nth-child(even) {
            background-color: rgb(249 250 251);
        }

        .listing-table > tbody > tr:hover {
            background-color: rgb(243 244 246) !important;
        }

        /* Standard form fields (match users/customers create forms) */
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
    </style>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen overflow-hidden" x-data="{ sidebarOpen: false }">
        <!-- Sidebar: super user (no tenant) gets different nav -->
        @if(auth()->check() && auth()->user()->isSuperUser() && ! config('offline.enabled'))
            @include('layouts.sidebar-super')
        @else
            @include('layouts.sidebar')
        @endif

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Top Navbar -->
            @if(auth()->check() && auth()->user()->isSuperUser() && ! config('offline.enabled'))
                @include('layouts.navbar-super')
            @else
                @include('layouts.navbar')
            @endif

            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto bg-gray-50 p-6">
                @if(session('success'))
                    <div class="mb-4 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg flex items-center">
                        <i class="fas fa-check-circle mr-2"></i>
                        {{ session('success') }}
                    </div>
                @endif

                @if(session('error'))
                    <div class="mb-4 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg flex items-center">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        {{ session('error') }}
                    </div>
                @endif

                @if(session('warning'))
                    <div class="mb-4 bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 rounded-lg flex items-center">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        {{ session('warning') }}
                    </div>
                @endif

                @if(session('info'))
                    <div class="mb-4 bg-blue-50 border border-blue-200 text-blue-900 px-4 py-3 rounded-lg flex items-center">
                        <i class="fas fa-info-circle mr-2"></i>
                        {{ session('info') }}
                    </div>
                @endif

                @if($errors->any())
                    <div class="mb-4 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">
                        <ul class="list-disc list-inside">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    @stack('modals')

    <!-- Mobile Sidebar Overlay -->
    <div x-show="sidebarOpen" 
         x-cloak
         @click="sidebarOpen = false"
         class="fixed inset-0 bg-gray-900 bg-opacity-50 z-20 lg:hidden"
         x-transition:enter="transition-opacity ease-linear duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-linear duration-300"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
    </div>

    <script>
        function shiftButton(shiftData) {
            return {
                shiftData: shiftData,
                get shiftStartTime() {
                    if (!this.shiftData || !this.shiftData.opened_at) {
                        return '';
                    }
                    return this.shiftData.opened_at;
                }
            }
        }

        // Ensure active sidebar item is visible on desktop
        document.addEventListener('DOMContentLoaded', function () {
            // Tailwind lg breakpoint starts at 1024px
            if (window.innerWidth < 1024) return;

            var sidebar = document.querySelector('.hidden.lg\\:flex .overflow-y-auto');
            if (!sidebar) return;

            var activeLink = sidebar.querySelector('a.bg-indigo-50.text-indigo-700');
            if (!activeLink) return;

            var targetScroll = activeLink.offsetTop - (sidebar.clientHeight / 2) + (activeLink.offsetHeight / 2);
            if (targetScroll < 0) targetScroll = 0;

            sidebar.scrollTop = targetScroll;
        });
    </script>
    @stack('scripts')
</body>
</html>
