@extends('layouts.app')

@section('title', 'Accounts Receivable')

@section('content')
@include('reports._outstanding-report', ['reportType' => 'receivable'])
@endsection
