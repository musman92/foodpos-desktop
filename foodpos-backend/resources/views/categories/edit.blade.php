@extends('layouts.app')

@section('title', 'Edit Category')

@section('content')
    @include('categories._form', ['category' => $category])
@endsection

