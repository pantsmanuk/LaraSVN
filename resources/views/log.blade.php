@extends('layout')
@section('title', 'History · '.$path.' · LaraSVN')
@section('content')
@include('partials.context')
<div class="page-heading"><div><p class="eyebrow">Revision history</p><h1>{{ $path === '/' ? $repository : basename($path) }}</h1></div><span class="revision-badge">r{{ $revision }}</span></div>
@include('partials.navigation')
<form method="get" action="{{ route('log') }}" novalidate class="filter-form">
    @foreach($params as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
    @foreach(array_intersect_key($query, array_flip(['fr', 'all', 'showchanges', 'max'])) as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
    <label for="history-search">Search messages and authors</label>
    <div class="search-controls"><input id="history-search" name="search" type="search" value="{{ $search }}"><button type="submit">Search</button>@if($search !== '')<button type="button" data-clear-search="history-search" hidden aria-label="Clear history search">Clear</button><a href="{{ route('log', $params) }}">Reset filters</a>@endif</div>
    <div class="range-controls"><label>Start revision <input name="sr" value="{{ $query['sr'] ?? '' }}" placeholder="{{ $revision }}" size="6"></label><label>End revision <input name="er" value="{{ $query['er'] ?? '' }}" placeholder="1" size="6"></label><button type="submit">Apply range</button></div>
</form>
<div class="section-toolbar"><a href="{{ route('log', array_replace($query, ['showchanges' => $showChanges ? '0' : '1'])) }}">{{ $showChanges ? 'Hide changed paths' : 'Show changed paths' }}</a><a href="{{ route('log', array_replace($query, ['all' => $all ? '0' : '1'])) }}">{{ $all ? 'Paginated history' : 'Show all selected changes' }}</a></div>
@if($limited)<p class="notice">Showing the first {{ $max }} changes in this range. Narrow the range or increase the max parameter for older history.</p>@endif
<ol class="history-list">
    @forelse($entries as $entry)
        <li class="history-entry">
            <div class="history-heading"><a class="commit-revision" href="{{ route('revision', ['repname' => $repository, 'path' => '/', 'rev' => $entry['revision']]) }}">r{{ $entry['revision'] }}</a><span>{{ $entry['author'] ?: 'Unknown author' }}</span><time datetime="{{ $entry['date'] }}">{{ $entry['date'] ? gmdate('Y-m-d H:i', strtotime($entry['date'])).' UTC' : 'Unknown date' }}</time></div>
            <p class="commit-body">{{ $entry['message'] ?: 'No commit message.' }}</p>
            @if($showChanges)@include('partials.changes')@endif
        </li>
    @empty
        <li class="notice">No changes match this search and revision range.</li>
    @endforelse
</ol>
@include('partials.pagination', ['itemsLabel' => 'changes'])
@endsection
