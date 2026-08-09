@extends('layouts.app')

@section('title', 'Customer Credits')

@section('content')
@include('reports._outstanding-report', ['reportType' => 'customer-credit'])
@endsection
