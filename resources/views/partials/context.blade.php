<div class="context-bar">
    <nav class="breadcrumb" aria-label="Repository path">
        <a href="{{ route('listing', ['repname' => $repository, 'path' => '/', 'rev' => $revision, 'peg' => $revision]) }}">{{ $repository }}</a>
        @php($segments = array_values(array_filter(explode('/', trim($navigationPath ?? $path, '/')), fn ($segment) => $segment !== '')))
        @foreach($segments as $segment)
            <span aria-hidden="true">/</span>
            @if($loop->last)
                <strong aria-current="page">{{ $segment }}</strong>
            @else
                <a href="{{ route('listing', ['repname' => $repository, 'path' => '/'.implode('/', array_slice($segments, 0, $loop->iteration)), 'rev' => $revision, 'peg' => $revision]) }}">{{ $segment }}</a>
            @endif
        @endforeach
    </nav>
    <form method="get" action="{{ route($mode) }}" novalidate class="revision-form">
        @foreach(($query ?? $params) as $key => $value)
            @if(!in_array($key, ['rev', 'page', 'sr', 'er']))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
        @endforeach
        <label for="revision">Revision</label><input id="revision" name="rev" inputmode="numeric" value="{{ $revision }}" size="5"><button type="submit">Go</button>
    </form>
</div>
