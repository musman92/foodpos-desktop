@extends('layouts.app')

@section('title', 'Secret Login - ' . $company->name)

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="bg-white shadow rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <h1 class="text-xl font-semibold text-gray-900">Secret Login Link</h1>
            <p class="mt-1 text-sm text-gray-500">One-time link to log in as <strong>{{ $adminUser->name }}</strong> ({{ $company->name }})</p>
        </div>

        <div class="p-6 space-y-4">
            <div class="rounded-lg bg-amber-50 border border-amber-200 p-4">
                <p class="text-sm text-amber-800">
                    <i class="fas fa-clock mr-1"></i>
                    This link expires at <strong>{{ $expiresAt->format('M j, Y g:i A') }}</strong> and can only be used once.
                </p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Secret URL</label>
                <div class="flex gap-2">
                    <input type="text"
                           id="secret-url"
                           readonly
                           value="{{ $url }}"
                           class="flex-1 h-12 px-4 rounded-lg border border-gray-300 bg-gray-50 text-sm font-mono">
                    <button type="button"
                            id="copy-btn"
                            onclick="copyUrl()"
                            class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
                        <i class="fas fa-copy mr-2"></i>
                        Copy
                    </button>
                </div>
            </div>

            <p class="text-sm text-gray-500">
                Open this URL in a browser (or incognito window) to log in as the company admin. Keep it private — anyone with the link can sign in until it is used or expires.
            </p>

            <div class="flex items-center justify-between pt-4 border-t border-gray-200">
                <a href="{{ route('companies.index') }}"
                   class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Back to Companies
                </a>
                <a href="{{ $url }}"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="inline-flex items-center px-4 py-2 bg-amber-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 transition">
                    <i class="fas fa-external-link-alt mr-2"></i>
                    Open in new tab
                </a>
            </div>
        </div>
    </div>
</div>

<script>
function copyUrl() {
    const input = document.getElementById('secret-url');
    input.select();
    input.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(input.value).then(function() {
        const btn = document.getElementById('copy-btn');
        if (!btn) return;
        const origHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check mr-2"></i> Copied!';
        btn.classList.add('bg-green-600', 'hover:bg-green-700');
        btn.classList.remove('bg-indigo-600', 'hover:bg-indigo-700');
        setTimeout(function() {
            btn.innerHTML = origHtml;
            btn.classList.remove('bg-green-600', 'hover:bg-green-700');
            btn.classList.add('bg-indigo-600', 'hover:bg-indigo-700');
        }, 2000);
    });
}
</script>
@endsection
