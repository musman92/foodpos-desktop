@extends('layouts.app')

@section('title', 'Edit Ingredient Category')

@section('content')
    @include('ingredient-categories._form', ['ingredientCategory' => $ingredientCategory])
@endsection

