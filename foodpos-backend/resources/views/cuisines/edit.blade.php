@extends('layouts.app')

@section('title', 'Edit Cuisine')

@section('content')
    @include('cuisines._form', ['cuisine' => $cuisine])
@endsection

