@extends('layouts.app')

@section('title', 'Edit Transaction')

@section('content')
    @include('transactions._form', ['transaction' => $transaction])
@endsection

