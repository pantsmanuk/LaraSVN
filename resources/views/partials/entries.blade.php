<div class="data-scroll" tabindex="0" role="region" aria-label="{{ $tableLabel }}">
    <table class="data-table">
        <thead><tr><th scope="col">Name</th><th scope="col">Revision</th><th scope="col">Author</th><th scope="col">Changed (UTC)</th><th scope="col">Size</th></tr></thead>
        <tbody>
        @forelse($entries as $entry)
            <tr>
                <td class="path-cell"><span class="entry-kind">{{ $entry['kind'] === 'dir' ? 'Directory' : 'File' }}</span><a href="{{ route($entry['kind'] === 'dir' ? 'listing' : 'source', ['repname' => $repository, 'path' => $entry['path'], 'rev' => $revision, 'peg' => $revision]) }}">{{ $entry['name'] }}{{ $entry['kind'] === 'dir' ? '/' : '' }}</a></td>
                <td><a href="{{ route('revision', ['repname' => $repository, 'path' => '/', 'rev' => $entry['revision']]) }}">r{{ $entry['revision'] }}</a></td>
                <td>{{ $entry['author'] ?: 'Unknown' }}</td>
                <td><time datetime="{{ $entry['date'] }}">{{ $entry['date'] ? gmdate('Y-m-d H:i', strtotime($entry['date'])) : 'Unknown' }}</time></td>
                <td>{{ $entry['kind'] === 'dir' ? '—' : number_format($entry['size']).' B' }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="empty-state">{{ $emptyMessage }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@include('partials.pagination')
