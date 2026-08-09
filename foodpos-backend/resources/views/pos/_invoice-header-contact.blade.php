@php
    $company = $order->company;
    $branch = $order->branch;
    $address = $company?->address ?: $branch?->address;
    $phone = $company?->phone ?: $branch?->phone;
    $showBranchName = $branch?->name
        && $company?->name
        && strcasecmp($branch->name, $company->name) !== 0;
@endphp
@if($showBranchName)
    <div class="company-info">{{ $branch->name }}</div>
@endif
@if($address)
    <div class="company-info">{{ $address }}</div>
@endif
@if($phone)
    <div class="company-info">Tel: {{ $phone }}</div>
@endif
