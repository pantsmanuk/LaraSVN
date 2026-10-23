@extends('layout')
@section('title', 'Compare · '.$repository.' · LaraSVN')
@section('content')
<nav class="breadcrumb" aria-label="Repository path"><a href="{{ route('listing', ['repname' => $repository, 'path' => '/']) }}">{{ $repository }}</a><span aria-hidden="true">/</span><strong aria-current="page">Compare</strong></nav>
<div class="page-heading"><h1>Compare snapshots</h1></div>
<form method="get" action="{{ route('compare') }}" novalidate class="filter-form">
    <input type="hidden" name="repname" value="{{ $repository }}">
    <input type="hidden" name="manualorder" value="1">
    <div class="comparison-inputs">
        @foreach(['From', 'To'] as $index => $label)
            <fieldset><legend>{{ $label }}</legend>
                <label for="compare-path-{{ $index }}">Repository path</label>
                <input id="compare-path-{{ $index }}" name="compare[{{ $index }}]" value="{{ $sides[$index]['path'] ?? '/' }}" aria-describedby="compare-help">
                <label for="compare-revision-{{ $index }}">Revision</label>
                <input id="compare-revision-{{ $index }}" name="compare_rev[{{ $index }}]" inputmode="numeric" value="{{ $sides[$index]['revision'] ?? '0' }}" aria-describedby="compare-help">
            </fieldset>
        @endforeach
    </div>
    <p id="compare-help" class="muted">Use 0 for latest. A path ending in @revision overrides its revision field. Paths may name files or directories.</p>
    <div class="search-controls"><label><input type="checkbox" name="ignorews" value="1" @checked($ignoreWhitespace)> Ignore whitespace</label><button type="submit">Compare</button>
    @if(count($sides) === 2)<a href="{{ route('compare', ['repname' => $repository, 'compare' => [$sides[1]['path'], $sides[0]['path']], 'compare_rev' => [$sides[1]['revision'], $sides[0]['revision']], 'manualorder' => 1, 'ignorews' => $ignoreWhitespace ? 1 : 0]) }}">Reverse comparison</a>@endif</div>
</form>
@if($patch === null)
    <p class="notice">Choose two snapshots to compare text, directory contents, and properties.</p>
@elseif($patch === '')
    <p class="notice" role="status">No differences with these comparison settings.</p>
@else
    <div class="section-toolbar"><span>{{ $sides[0]['path'] }} at r{{ $sides[0]['revision'] }} → {{ $sides[1]['path'] }} at r{{ $sides[1]['revision'] }}</span></div>
    <div class="code-scroll prism-surface" tabindex="0" role="region" aria-label="Unified comparison patch"><pre class="patch"><code data-patch>{{ $patch }}</code></pre></div>
    <p class="enhancement-status" role="status" id="highlight-status"></p>
@endif
@endsection
