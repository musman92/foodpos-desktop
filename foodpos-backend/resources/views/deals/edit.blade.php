@extends('layouts.app')

@section('title', 'Edit Deal')

@section('content')
    @include('deals._form', ['deal' => $deal])
@endsection
