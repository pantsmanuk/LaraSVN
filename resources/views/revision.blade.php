@extends('layout')
@section('title', 'Revision '.$revision.' · '.$repository.' · LaraSVN')
@section('content')
@include('partials.context')
<div class="page-heading"><h1>Revision {{ $revision }}</h1></div>
@include('partials.navigation')
<nav class="section-toolbar" aria-label="Repository revisions">
    @if($revision > 1)<a rel="prev" href="{{ route('revision', array_replace($params, ['rev' => $revision - 1, 'peg' => $revision - 1])) }}">Previous · r{{ $revision - 1 }}</a>@endif
    <a href="{{ route('listing', $params) }}">Browse this revision</a>
    @if($revision < $head)<a rel="next" href="{{ route('revision', array_replace($params, ['rev' => $revision + 1, 'peg' => $revision + 1])) }}">Next · r{{ $revision + 1 }}</a>@endif
</nav>
<dl class="commit-details"><div><dt>Author</dt><dd>{{ $entry['author'] ?: 'Unknown' }}</dd></div><div><dt>Date (UTC)</dt><dd><time datetime="{{ $entry['date'] }}">{{ $entry['date'] ? gmdate('Y-m-d H:i:s', strtotime($entry['date'])) : 'Unknown' }}</time></dd></div></dl>
<p class="commit-body revision-message">{{ $entry['message'] ?: 'No commit message.' }}</p>
<section class="properties"><h2>Changed paths</h2>@include('partials.changes')</section>
@endsection
