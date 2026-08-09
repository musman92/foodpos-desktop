@extends('layouts.app')

@section('title', 'Platform Actions')

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Platform Actions</h1>
        <p class="mt-1 text-sm text-gray-500">Run whitelisted maintenance commands from the browser. Only super administrators can access this page.</p>
    </div>

    @if(session('action_result'))
        @php($result = session('action_result'))
        <div class="rounded-lg border {{ $result['success'] ? 'border-green-200 bg-green-50' : 'border-red-200 bg-red-50' }} p-4">
            <div class="flex items-start gap-3">
                <i class="fas {{ $result['success'] ? 'fa-check-circle text-green-600' : 'fa-exclamation-circle text-red-600' }} mt-0.5"></i>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold {{ $result['success'] ? 'text-green-900' : 'text-red-900' }}">
                        {{ $result['title'] }} — {{ $result['success'] ? 'Completed' : 'Failed' }}
                    </p>
                    <p class="mt-1 text-xs font-mono text-gray-600 break-all">{{ $result['command'] ?? '' }}</p>
                    @if(! empty($result['output']))
                        <pre class="mt-3 max-h-64 overflow-auto rounded-md bg-white/80 p-3 text-xs text-gray-800 whitespace-pre-wrap border border-gray-200">{{ $result['output'] }}</pre>
                    @endif
                </div>
            </div>
        </div>
    @endif

    @foreach($groups as $groupKey => $group)
        <div class="bg-white shadow rounded-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                <h2 class="text-lg font-semibold text-gray-900">{{ $group['label'] }}</h2>
            </div>
            <div class="divide-y divide-gray-200">
                @foreach($group['actions'] as $action)
                    <div class="px-6 py-5">
                        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                            <div class="flex gap-4 min-w-0">
                                <div class="flex-shrink-0 h-10 w-10 rounded-lg bg-slate-100 flex items-center justify-center">
                                    <i class="fas {{ $action['icon'] ?? 'fa-terminal' }} text-slate-600"></i>
                                </div>
                                <div class="min-w-0">
                                    <h3 class="text-base font-semibold text-gray-900">{{ $action['title'] }}</h3>
                                    <p class="mt-1 text-sm text-gray-600">{{ $action['description'] }}</p>
                                    <p class="mt-2 text-xs font-mono text-gray-400">php artisan {{ $action['command'] }}</p>
                                </div>
                            </div>

                            <form action="{{ route('platform-actions.run', $action['key']) }}"
                                  method="POST"
                                  class="flex-shrink-0 w-full lg:w-auto lg:min-w-[18rem]"
                                  onsubmit="return confirm(@json($action['confirm'] ?? 'Run this command?'));">
                                @csrf

                                @if(! empty($action['inputs']))
                                    <div class="mb-3 space-y-2">
                                        @foreach($action['inputs'] as $input)
                                            @if(($input['type'] ?? '') === 'checkbox')
                                                <label class="flex items-center text-sm text-gray-700">
                                                    <input type="checkbox"
                                                           name="{{ $input['name'] }}"
                                                           value="1"
                                                           class="rounded border-gray-300 text-indigo-600 mr-2">
                                                    {{ $input['label'] }}
                                                </label>
                                            @elseif(($input['type'] ?? '') === 'text')
                                                <div>
                                                    <label for="{{ $action['key'] }}_{{ $input['name'] }}" class="block text-xs font-medium text-gray-600 mb-1">
                                                        {{ $input['label'] }}
                                                    </label>
                                                    <input type="text"
                                                           name="{{ $input['name'] }}"
                                                           id="{{ $action['key'] }}_{{ $input['name'] }}"
                                                           class="block w-full rounded-lg text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                @endif

                                <button type="submit"
                                        class="inline-flex w-full items-center justify-center px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors {{ ! empty($action['danger']) ? 'bg-red-600 text-white hover:bg-red-700' : 'bg-indigo-600 text-white hover:bg-indigo-700' }}">
                                    <i class="fas fa-play mr-2"></i>
                                    Run
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
@endsection
