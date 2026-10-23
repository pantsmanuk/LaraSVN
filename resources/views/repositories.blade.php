@extends('layout')
@section('title', 'Repositories · LaraSVN')
@section('content')
<div class="page-heading"><h1>Repositories</h1></div>
@forelse(collect($repositories)->groupBy('group') as $group => $members)
    <section class="repository-group"><h2>{{ $group ?: 'Repositories' }}</h2>
        <ul class="repository-list">
            @foreach($members as $entry)
                <li><a href="{{ route('listing', ['repname' => $entry['name'], 'path' => '/']) }}">{{ $entry['name'] }}</a>
                    @if($entry['info'])<span class="muted">r{{ $entry['info']['revision'] }} · {{ $entry['info']['author'] }}</span>@else<span class="muted">{{ $entry['error'] }}</span>@endif
                </li>
            @endforeach
        </ul>
    </section>
@empty
    <p class="notice">No repositories are configured.</p>
@endforelse
@endsection
