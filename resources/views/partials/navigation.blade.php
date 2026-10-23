<nav class="view-tabs" aria-label="{{ ($isDirectory ?? false) ? 'Directory view' : 'File view' }}">
    @php($views = ($isDirectory ?? false) ? ['listing' => 'Browse', 'log' => 'History', 'search' => 'Find files'] : ['source' => 'Source', 'diff' => 'Changes', 'blame' => 'Blame', 'log' => 'History'])
    @foreach($views as $view => $label)
        <a href="{{ route($view, $params) }}" @if($view === $mode) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
    <a href="{{ route('revision', $params) }}" @if($mode === 'revision') aria-current="page" @endif>Revision</a>
</nav>

<div class="section-toolbar" aria-label="Snapshot tools">
    <div class="toolbar-actions">
        <a href="{{ route('compare', ['repname' => $repository, 'compare' => [$navigationPath ?? $path, $navigationPath ?? $path], 'compare_rev' => [$revision, $revision]]) }}">Compare snapshots</a>
        @if(app(\App\Svn\SvnClient::class)->repositories()[$repository]['rss'] ?? true)<a href="{{ route('rss', $params) }}">RSS feed</a>@endif
        @if(!($isDirectory ?? false))<a href="{{ route('source', $params + ['usemime' => 1]) }}">Raw file</a>@endif
    </div>
    @if(app(\App\Svn\SvnClient::class)->repositories()[$repository]['downloads'] ?? false)
        <div class="toolbar-actions">
            @if(!($isDirectory ?? false))<a href="{{ route('download', $params + ['dlmode' => 'plain']) }}">Download file</a>@endif
            <a href="{{ route('download', $params + ['dlmode' => 'zip']) }}">ZIP</a>
            <a href="{{ route('download', $params + ['dlmode' => 'gzip']) }}">tar.gz</a>
        </div>
    @endif
</div>
