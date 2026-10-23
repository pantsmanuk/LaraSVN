@extends('layout')
@section('title', ($path === '/' ? $repository : basename($path)).' · Browse · LaraSVN')
@section('content')
@include('partials.context')
<div class="page-heading"><div><p class="eyebrow">Directory</p><h1>{{ $path === '/' ? $repository : basename($path) }}</h1></div><span class="revision-badge">r{{ $revision }}</span></div>
@include('partials.navigation')
<div class="section-toolbar">
    @if($path !== '/')<a href="{{ route('listing', array_replace($params, ['path' => dirname(rtrim($path, '/'))])) }}">Parent directory</a>@else<span class="muted">Repository root</span>@endif
    <a href="{{ route('log', $params) }}">View directory history</a>
</div>
@include('partials.entries', ['tableLabel' => 'Directory contents', 'emptyMessage' => 'This directory is empty.'])
@include('partials.properties')
@endsection
