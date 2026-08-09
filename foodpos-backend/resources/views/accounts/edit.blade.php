@extends('layouts.app')

@section('title', 'Edit Account')

@section('content')
    @include('accounts._form', ['account' => $account])
@endsection

