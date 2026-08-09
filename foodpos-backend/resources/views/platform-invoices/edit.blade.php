@extends('layouts.app')

@section('title', 'Edit Invoice '.$invoice->invoice_number)

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Edit {{ $invoice->invoice_number }}</h1>
        <p class="mt-1 text-sm text-gray-500">{{ $invoice->company->name }}</p>
    </div>

    <div class="bg-white shadow rounded-lg p-6">
        @include('platform-invoices._form')
    </div>
</div>
@endsection
