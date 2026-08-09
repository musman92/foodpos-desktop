@extends('layouts.app')

@section('title', 'Printer Settings')

@section('content')
@php $offline = offline_edition(); @endphp
<div class="max-w-7xl mx-auto" x-data="printerSettings()">
    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">Printer Settings</h1>
                    <p class="mt-1 text-sm text-gray-500">
                        @if($offline)
                            Configure kitchen and receipt printers for this POS.
                        @else
                            Configure printers per branch. Use direct print with theFoodPOS Print APP for silent printing.
                        @endif
                    </p>
                </div>
                @if(show_branch_ui())
                    <form method="GET" action="{{ route('printer-settings.index') }}" class="flex items-center gap-3">
                        <label for="branch_id" class="text-sm font-medium text-gray-700 whitespace-nowrap">Branch</label>
                        <select name="branch_id"
                                id="branch_id"
                                onchange="this.form.submit()"
                                class="block h-12 min-w-[12rem] px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            @foreach($branches as $b)
                                <option value="{{ $b->id }}" @selected($branch->id === $b->id)>{{ $b->name }}</option>
                            @endforeach
                        </select>
                    </form>
                @endif
            </div>
        </div>
        @if(!$offline && $newPlainKey)
            <div class="mx-6 mt-4 rounded-lg bg-amber-50 border border-amber-200 px-4 py-4">
                <p class="text-sm font-semibold text-amber-900">Branch key — copy and paste into the desktop app</p>
                <code class="mt-3 block break-all rounded-lg bg-white px-4 py-3 text-sm text-gray-900 border border-amber-200 font-mono">{{ $newPlainKey }}</code>
                <p class="mt-2 text-xs text-amber-800">Open theFoodPOS Print APP → enter this key under Settings. It is shown only once.</p>
            </div>
        @endif

        <div class="p-6 {{ $offline ? '' : 'grid grid-cols-1 xl:grid-cols-2 gap-8' }}">
            <section>
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Printers</h2>
                        @unless($offline)
                            <p class="text-sm text-gray-500 mt-0.5">{{ $branch->name }}</p>
                        @endunless
                    </div>
                    <button type="button"
                            @click="openAddPrinter()"
                            class="btn-form-primary inline-flex items-center text-sm">
                        <i class="fas fa-plus mr-2"></i>Add printer
                    </button>
                </div>

                @if($printers->isEmpty())
                    <div class="rounded-lg border border-dashed border-gray-300 p-8 text-center">
                        <i class="fas fa-print text-3xl text-gray-300 mb-3"></i>
                        <p class="text-sm text-gray-500">No printers configured. POS will use browser popup print until you add printers.</p>
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach($printers as $printer)
                            <div class="border border-gray-200 rounded-lg p-4 hover:border-gray-300 transition-colors"
                                 x-data="{ verify: null, verifying: false }">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="font-semibold text-gray-900">{{ $printer->title }}</h3>
                                            @if($printer->is_default)
                                                <span class="text-xs px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700 font-medium">Default</span>
                                            @endif
                                            @if(!$printer->is_active)
                                                <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 font-medium">Inactive</span>
                                            @endif
                                        </div>
                                        <p class="mt-1.5 text-sm text-gray-600">
                                            {{ ucfirst($printer->role) }} · {{ $printer->printingModeLabel() }}
                                        </p>
                                        @if($printer->device_name)
                                            <p class="mt-1 text-xs text-gray-500 font-mono">{{ $printer->device_name }}</p>
                                        @endif

                                        @if(!$offline && $printer->printing_mode === 'desktop')
                                            <div class="mt-2" x-show="verify" x-cloak>
                                                <div class="rounded-lg border px-3 py-2 text-xs"
                                                     :class="verify?.ok ? 'border-green-200 bg-green-50 text-green-800' : 'border-red-200 bg-red-50 text-red-800'">
                                                    <p class="font-medium" x-text="verify?.ok ? 'Verified' : 'Not verified'"></p>
                                                    <p class="mt-0.5" x-text="verify?.message"></p>
                                                    <template x-if="verify?.suggested_name">
                                                        <button type="button"
                                                                class="mt-2 text-xs font-semibold underline"
                                                                @click="navigator.clipboard.writeText(verify.suggested_name)">
                                                            Copy correct name: <span x-text="verify.suggested_name"></span>
                                                        </button>
                                                    </template>
                                                    <template x-if="verify?.available_printers?.length">
                                                        <div class="mt-2 pt-2 border-t border-red-200">
                                                            <p class="font-medium mb-1">Available on desktop:</p>
                                                            <ul class="space-y-0.5 font-mono">
                                                                <template x-for="item in verify.available_printers" :key="item.name + item.source">
                                                                    <li x-text="item.name + ' (' + item.source + ')'"></li>
                                                                </template>
                                                            </ul>
                                                        </div>
                                                    </template>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-3 shrink-0">
                                        @if(!$offline && $printer->printing_mode === 'desktop')
                                            <button type="button"
                                                    class="text-sm font-medium text-indigo-700 hover:text-indigo-900 disabled:opacity-50"
                                                    :disabled="verifying"
                                                    @click="verifyPrinter({{ $printer->id }}, (result) => { verify = result; verifying = false; }, () => { verifying = true; })"
                                                    title="Check OS printer name against desktop app list">
                                                <span x-show="!verifying">Verify</span>
                                                <span x-show="verifying" x-cloak>…</span>
                                            </button>
                                        @endif
                                        <form method="POST" action="{{ route('printer-settings.printers.test', $printer) }}">
                                            @csrf
                                            <button type="submit"
                                                    class="text-sm font-medium text-emerald-700 hover:text-emerald-900"
                                                    title="{{ $printer->printing_mode === 'desktop' ? 'Send test print to desktop app' : 'Open browser test print' }}">
                                                Test
                                            </button>
                                        </form>
                                        <button type="button" @click="editPrinter(@js($printer))" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">Edit</button>
                                        <form method="POST" action="{{ route('printer-settings.printers.destroy', $printer) }}" onsubmit="return confirm('Remove this printer?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-sm font-medium text-red-600 hover:text-red-800">Remove</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            @unless($offline)
            <section>
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Desktop app connection</h2>
                        <p class="text-sm text-gray-500 mt-0.5">Generate a branch key for each PC running the desktop app</p>
                    </div>
                    <button type="button"
                            @click="showAddKey = true"
                            class="inline-flex items-center h-12 px-4 text-sm font-medium text-white bg-gray-800 rounded-lg hover:bg-gray-900 transition-colors">
                        <i class="fas fa-key mr-2"></i>Generate key
                    </button>
                </div>

                <div class="rounded-lg bg-gray-50 border border-gray-200 px-4 py-3 text-sm text-gray-600 mb-4">
                    <ol class="list-decimal list-inside space-y-1">
                        <li>Install theFoodPOS Print APP on the branch PC</li>
                        <li>Generate a key here and copy it</li>
                        <li>Paste the key in the desktop app settings</li>
                    </ol>
                </div>

                @if($desktopKeys->isEmpty())
                    <div class="rounded-lg border border-dashed border-gray-300 p-8 text-center">
                        <i class="fas fa-key text-3xl text-gray-300 mb-3"></i>
                        <p class="text-sm text-gray-500">No keys yet. Generate one when setting up a new desktop app install.</p>
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach($desktopKeys as $key)
                            <div class="border border-gray-200 rounded-lg p-4"
                                 x-data="desktopKeyRow({{ $key->id }}, @js(route('printer-settings.desktop-keys.status', $key)), {{ request('fetch_key') == $key->id ? 'true' : 'false' }}, @js($key->system_printers ?? []))"
                                 x-init="init()">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="font-medium text-gray-900">{{ $key->name }}</p>
                                            @if($key->is_active && $key->isOnline())
                                                <span class="inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-800 font-medium">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>
                                                    Connected
                                                </span>
                                            @elseif($key->is_active)
                                                <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 font-medium">Offline</span>
                                            @endif
                                        </div>
                                        <p class="text-sm text-gray-500 font-mono mt-0.5">{{ $key->key_prefix }}…</p>

                                        @if($key->is_active && $key->isOnline() && $key->connection_code)
                                            <div class="mt-3 inline-flex items-center gap-2 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2">
                                                <span class="text-xs font-medium text-indigo-700 uppercase tracking-wide">Pair code</span>
                                                <span class="font-mono text-lg font-bold text-indigo-900 tracking-[0.2em]" x-text="connectionCode || '{{ $key->connection_code }}'">{{ $key->connection_code }}</span>
                                            </div>
                                            <p class="text-xs text-gray-500 mt-1">Same code is shown on the desktop app — use it to match this PC to this key.</p>
                                        @elseif($key->is_active)
                                            <p class="text-xs text-gray-400 mt-2">Open the desktop app with this key to show a pair code.</p>
                                        @endif

                                        <p class="text-xs text-gray-400 mt-2">
                                            @if($key->is_active)
                                                <span class="text-green-700 font-medium">Active</span>
                                            @else
                                                <span class="text-gray-500">Revoked</span>
                                            @endif
                                            @if($key->last_heartbeat_at)
                                                · last seen {{ $key->last_heartbeat_at->diffForHumans() }}
                                            @elseif($key->last_used_at)
                                                · last used {{ $key->last_used_at->diffForHumans() }}
                                            @endif
                                        </p>

                                        <div class="mt-3" x-show="systemPrinters.length > 0">
                                            <p class="text-xs font-medium text-gray-700 mb-1.5">System printers on this PC</p>
                                            <div class="rounded-lg border border-gray-200 divide-y divide-gray-100 bg-gray-50 text-sm">
                                                <template x-for="printer in systemPrinters" :key="printer.name">
                                                    <div class="flex items-center justify-between gap-2 px-3 py-2">
                                                        <code class="text-xs text-gray-800 break-all" x-text="printer.name"></code>
                                                        <button type="button"
                                                                @click="navigator.clipboard.writeText(printer.name)"
                                                                class="shrink-0 text-xs font-medium text-indigo-600 hover:text-indigo-800">Copy</button>
                                                    </div>
                                                </template>
                                            </div>
                                            <p class="text-xs text-gray-400 mt-1" x-show="systemPrintersAt" x-text="systemPrintersAt ? 'Updated ' + systemPrintersAt : ''"></p>
                                        </div>
                                        <p class="text-xs text-amber-700 mt-2" x-show="polling">Waiting for printer list from desktop…</p>
                                    </div>

                                    @if($key->is_active)
                                        <div class="flex flex-col items-end gap-2 shrink-0">
                                            <form method="POST" action="{{ route('printer-settings.desktop-keys.ping', $key) }}">
                                                @csrf
                                                <button type="submit"
                                                        class="text-sm font-medium text-emerald-700 hover:text-emerald-900"
                                                        title="Send a connection test to the desktop app">
                                                    Test
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('printer-settings.desktop-keys.fetch-printers', $key) }}">
                                                @csrf
                                                <button type="submit"
                                                        class="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                                                        title="Ask desktop app to send OS printer names">
                                                    Fetch printers
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('printer-settings.desktop-keys.revoke', $key) }}" onsubmit="return confirm('Revoke this key? The desktop app will stop connecting.')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-sm font-medium text-red-600 hover:text-red-800">Revoke</button>
                                            </form>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
            @endunless
        </div>

        <div class="px-6 pb-6 border-t border-gray-200 pt-6">
            <div class="mb-4">
                <h2 class="text-lg font-semibold text-gray-900">Recent print jobs</h2>
                <p class="text-sm text-gray-500 mt-0.5">
                    @if($offline)
                        Last 5 print jobs (including test prints)
                    @else
                        Last 5 print jobs for {{ $branch->name }} (including test prints)
                    @endif
                </p>
            </div>

            @if($recentPrintJobs->isEmpty())
                <div class="rounded-lg border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500">
                    No print jobs yet. Use <strong>Test</strong> on a printer or send a receipt/KOT from POS.
                </div>
            @else
                <div class="overflow-x-auto border border-gray-200 rounded-lg">
                    <table class="listing-table min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Time</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Printer</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Details</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @foreach($recentPrintJobs as $job)
                                <tr>
                                    <td class="px-4 py-3 whitespace-nowrap text-gray-700">
                                        {{ format_datetime($job->created_at) }}
                                    </td>
                                    <td class="px-4 py-3 text-gray-900">
                                        {{ $job->printer?->title ?? '—' }}
                                        @if($job->device_name)
                                            <div class="text-xs text-gray-500 font-mono mt-0.5">{{ $job->device_name }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">
                                        @if($job->document_type === 'kitchen_kot')
                                            Kitchen KOT
                                        @elseif($job->document_type === 'receipt')
                                            Receipt
                                        @elseif($job->document_type === 'test')
                                            Test
                                        @else
                                            {{ $job->document_type }}
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        @php
                                            $statusClasses = match($job->status) {
                                                'printed' => 'bg-green-100 text-green-800',
                                                'failed' => 'bg-red-100 text-red-800',
                                                'printing' => 'bg-blue-100 text-blue-800',
                                                default => 'bg-amber-100 text-amber-800',
                                            };
                                        @endphp
                                        <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $statusClasses }}">
                                            {{ ucfirst($job->status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600">
                                        @if($job->error_message)
                                            <span class="text-red-700">{{ $job->error_message }}</span>
                                        @elseif($job->printed_at)
                                            Printed {{ $job->printed_at->diffForHumans() }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <!-- Add printer modal -->
    <div x-show="showAddPrinter" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40" @keydown.escape.window="showAddPrinter = false">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto" @click.outside="showAddPrinter = false">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Add printer</h3>
            </div>
            <form method="POST" action="{{ route('printer-settings.printers.store') }}" class="p-6 space-y-6">
                @csrf
                <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                @include('printer-settings._printer-fields')
                <div class="flex justify-end gap-3 pt-2 border-t border-gray-100">
                    <button type="button" @click="showAddPrinter = false" class="btn-form-secondary">Cancel</button>
                    <button type="submit" class="btn-form-primary">Save printer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit printer modal -->
    <div x-show="showEditPrinter" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40" @keydown.escape.window="showEditPrinter = false">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto" @click.outside="showEditPrinter = false">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Edit printer</h3>
            </div>
            <form :action="editFormAction" method="POST" class="p-6 space-y-6">
                @csrf
                @method('PUT')
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Printer name <span class="text-red-500">*</span></label>
                    <input type="text" name="title" x-model="editPrinterData.title" required class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Role</label>
                        <select name="role" x-model="editPrinterData.role" class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="kitchen">Kitchen</option>
                            <option value="receipt">Receipt</option>
                        </select>
                    </div>
                    <div>
                        <span class="block text-sm font-medium text-gray-700 mb-2">Print option</span>
                        <div class="space-y-2">
                            <label class="flex items-start gap-3 rounded-lg border px-4 py-3 cursor-pointer"
                                   :class="editPrinterData.printing_mode === 'browser' ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:bg-gray-50'">
                                <input type="radio" name="printing_mode" value="browser" x-model="editPrinterData.printing_mode" class="mt-1 h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                <span>
                                    <span class="block text-sm font-medium text-gray-900">Browser popup print</span>
                                    <span class="block text-xs text-gray-500 mt-0.5">Opens browser print dialog.</span>
                                </span>
                            </label>
                            @unless($offline)
                            <label class="flex items-start gap-3 rounded-lg border px-4 py-3 cursor-pointer"
                                   :class="editPrinterData.printing_mode === 'desktop' ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:bg-gray-50'">
                                <input type="radio" name="printing_mode" value="desktop" x-model="editPrinterData.printing_mode" class="mt-1 h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                <span>
                                    <span class="block text-sm font-medium text-gray-900">Direct print</span>
                                    <span class="block text-xs text-gray-500 mt-0.5">Silent via desktop app.</span>
                                </span>
                            </label>
                            @endunless
                        </div>
                    </div>
                </div>
                @unless($offline)
                <div x-show="editPrinterData.printing_mode === 'desktop'" x-cloak>
                    <div class="flex items-end gap-3">
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700 mb-2">OS printer name <span class="text-red-500">*</span></label>
                            <input type="text" name="device_name" x-model="editPrinterData.device_name" x-bind:required="editPrinterData.printing_mode === 'desktop'" class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Exact name from OS printer list">
                        </div>
                        <button type="button"
                                class="h-12 px-4 text-sm font-medium text-indigo-700 border border-indigo-200 rounded-lg hover:bg-indigo-50 disabled:opacity-50"
                                :disabled="editVerifyLoading || !editPrinterData.device_name"
                                @click="verifyEditPrinter()">
                            <span x-show="!editVerifyLoading">Verify</span>
                            <span x-show="editVerifyLoading" x-cloak>…</span>
                        </button>
                    </div>
                    <div class="mt-2 rounded-lg border px-3 py-2 text-xs"
                         x-show="editVerifyResult"
                         x-cloak
                         :class="editVerifyResult?.ok ? 'border-green-200 bg-green-50 text-green-800' : 'border-red-200 bg-red-50 text-red-800'">
                        <p x-text="editVerifyResult?.message"></p>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">Use Fetch printers on the desktop key first, then verify the name matches exactly.</p>
                </div>
                @endunless
                <div class="flex flex-wrap items-center gap-6">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                        <input type="hidden" name="is_default" value="0">
                        <input type="checkbox" name="is_default" value="1" x-model="editPrinterData.is_default" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        Default for this role
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" x-model="editPrinterData.is_active" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        Active
                    </label>
                </div>
                <div class="flex justify-end gap-3 pt-2 border-t border-gray-100">
                    <button type="button" @click="showEditPrinter = false" class="btn-form-secondary">Cancel</button>
                    <button type="submit" class="btn-form-primary">Update printer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Generate key modal -->
    @unless($offline)
    <div x-show="showAddKey" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40" @keydown.escape.window="showAddKey = false">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md" @click.outside="showAddKey = false">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Generate branch key</h3>
                <p class="text-sm text-gray-500 mt-1">For a desktop app install on this branch</p>
            </div>
            <form method="POST" action="{{ route('printer-settings.desktop-keys.generate') }}" class="p-6 space-y-6">
                @csrf
                <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                <div>
                    <label for="key_name" class="block text-sm font-medium text-gray-700 mb-2">Label <span class="text-red-500">*</span></label>
                    <input type="text"
                           name="name"
                           id="key_name"
                           required
                           class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                           placeholder="e.g. Counter PC 1">
                    <p class="mt-1 text-xs text-gray-500">Helps you identify which PC uses this key.</p>
                </div>
                <div class="flex justify-end gap-3 pt-2 border-t border-gray-100">
                    <button type="button" @click="showAddKey = false" class="btn-form-secondary">Cancel</button>
                    <button type="submit" class="btn-form-primary">Generate key</button>
                </div>
            </form>
        </div>
    </div>
    @endunless
</div>

<script>
function printerSettings() {
    return {
        showAddPrinter: false,
        showEditPrinter: false,
        showAddKey: false,
        printingMode: 'browser',
        printerRole: 'kitchen',
        editPrinterData: {},
        editFormAction: '',
        editVerifyResult: null,
        editVerifyLoading: false,
        csrfToken: @js(csrf_token()),
        verifyUrlBase: @js(url('/printer-settings/printers')),
        openAddPrinter() {
            this.printingMode = 'browser';
            this.printerRole = 'kitchen';
            this.showAddPrinter = true;
        },
        editPrinter(printer) {
            this.editPrinterData = {
                id: printer.id,
                title: printer.title,
                role: printer.role,
                printing_mode: printer.printing_mode,
                device_name: printer.device_name || '',
                is_default: !!printer.is_default,
                is_active: !!printer.is_active,
            };
            this.editFormAction = '{{ url('/printer-settings/printers') }}/' + printer.id;
            this.editVerifyResult = null;
            this.showEditPrinter = true;
        },
        async verifyEditPrinter() {
            if (!this.editPrinterData.id) {
                return;
            }
            this.editVerifyLoading = true;
            this.editVerifyResult = await this.fetchPrinterVerification(
                this.editPrinterData.id,
                this.editPrinterData.device_name
            );
            this.editVerifyLoading = false;
        },
        async fetchPrinterVerification(printerId, deviceName = null) {
            try {
                const res = await fetch(`${this.verifyUrlBase}/${printerId}/verify`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify(deviceName ? { device_name: deviceName } : {}),
                });
                if (!res.ok) {
                    return { ok: false, message: 'Could not verify printer. Please try again.' };
                }
                return await res.json();
            } catch (_) {
                return { ok: false, message: 'Could not verify printer. Check your connection.' };
            }
        },
    };
}

async function verifyPrinter(printerId, onDone, onStart) {
    if (typeof onStart === 'function') {
        onStart();
    }
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    try {
        const res = await fetch(`/printer-settings/printers/${printerId}/verify`, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        const data = res.ok ? await res.json() : { ok: false, message: 'Could not verify printer.' };
        onDone(data);
    } catch (_) {
        onDone({ ok: false, message: 'Could not verify printer. Check your connection.' });
    }
}

function desktopKeyRow(keyId, statusUrl, autoPoll, initialPrinters) {
    return {
        connectionCode: '',
        systemPrinters: Array.isArray(initialPrinters) ? initialPrinters : [],
        systemPrintersAt: '',
        polling: false,
        pollTimer: null,
        init() {
            if (autoPoll) {
                this.startPolling();
            }
        },
        startPolling() {
            this.polling = true;
            let attempts = 0;
            this.pollTimer = setInterval(async () => {
                attempts += 1;
                try {
                    const res = await fetch(statusUrl, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (!res.ok) return;
                    const data = await res.json();
                    if (data.connection_code) {
                        this.connectionCode = data.connection_code;
                    }
                    if (Array.isArray(data.system_printers) && data.system_printers.length > 0) {
                        this.systemPrinters = data.system_printers;
                        this.systemPrintersAt = data.system_printers_at
                            ? new Date(data.system_printers_at).toLocaleString()
                            : '';
                        this.polling = false;
                        clearInterval(this.pollTimer);
                    }
                } catch (_) {
                    // ignore
                }
                if (attempts >= 15) {
                    this.polling = false;
                    clearInterval(this.pollTimer);
                }
            }, 2000);
        },
    };
}
</script>
@endsection
