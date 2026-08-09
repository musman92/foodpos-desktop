@extends('layouts.app')

@section('title', 'Supplier Prepayments')

@section('content')
@include('reports._outstanding-report', ['reportType' => 'supplier-prepayment'])
@endsection
