@extends('layouts.app')

@section('title', 'Accounts Payable')

@section('content')
@include('reports._outstanding-report', ['reportType' => 'payable'])
@endsection
