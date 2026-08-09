@extends('layouts.app')

@section('title', 'Edit Menu Item')

@section('content')
    @include('menu-items._form', ['menuItem' => $menuItem])
@endsection

