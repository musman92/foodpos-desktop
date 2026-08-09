@extends('layouts.app')

@section('title', 'Edit Tax')

@section('content')
    @include('taxes._form', ['tax' => $tax])
@endsection

