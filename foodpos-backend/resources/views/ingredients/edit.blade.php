@extends('layouts.app')

@section('title', 'Edit Ingredient')

@section('content')
    @include('ingredients._form', ['ingredient' => $ingredient])
@endsection

