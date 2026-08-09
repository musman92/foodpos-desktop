@extends('layouts.app')

@section('title', 'Edit Variant')

@section('content')
    @include('variants._form', ['variant' => $variant])
@endsection
