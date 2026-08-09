@extends('layouts.app')

@section('title', 'Edit Supplier')

@section('content')
    @include('suppliers._form', ['supplier' => $supplier])
@endsection

