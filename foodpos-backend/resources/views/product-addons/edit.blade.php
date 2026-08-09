@extends('layouts.app')

@section('title', 'Edit Product Addon')

@section('content')
    @include('product-addons._form', ['productAddon' => $productAddon])
@endsection
