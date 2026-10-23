@extends('layout')
@section('title', 'Find files · '.$repository.' · LaraSVN')
@section('content')
@include('partials.context')
<div class="page-heading"><h1>Find files</h1><span class="revision-badge">r{{ $revision }}</span></div>
@include('partials.navigation')
<form method="get" action="{{ route('search') }}" novalidate class="filter-form">
    @foreach($params as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
    <label for="filename-search">Filename pattern</label>
    <div class="search-controls"><input type="search" id="filename-search" name="search" value="{{ $search }}" aria-describedby="search-help"><button type="submit">Search</button>@if($search !== '')<button type="button" data-clear-search="filename-search" hidden aria-label="Clear filename search">Clear</button><a href="{{ route('search', $params) }}">Reset search</a>@endif</div>
    <p id="search-help" class="muted">Search filenames below {{ $path }} at r{{ $revision }}. Use * and ? for wildcards. File contents are not searched.</p>
</form>
@if($search === '')
    <p class="notice">Enter a filename or pattern to search this directory and its descendants.</p>
@else
    @include('partials.entries', ['tableLabel' => 'Filename search results', 'emptyMessage' => 'No filenames match this pattern.'])
@endif
@endsection
