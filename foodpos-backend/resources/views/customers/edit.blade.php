@extends('layouts.app')

@section('title', 'Edit Customer')

@section('content')
@include('customers._form', ['customer' => $customer])
@endsection
