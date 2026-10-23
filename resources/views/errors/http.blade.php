@extends('layout')
@section('title', $status.' · LaraSVN')
@section('content')
<div class="error-state"><p class="eyebrow">Request could not be completed</p><h1>{{ $status }}</h1><p>{{ $message }}</p><a href="{{ url('/') }}">Return to repositories</a></div>
@endsection
