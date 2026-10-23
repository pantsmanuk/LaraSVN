<ul class="changed-paths">
    @foreach($entry['changes'] as $change)
        @php
            $before = $change['action'] === 'D';
            $changeRevision = $before ? $entry['revision'] - 1 : $entry['revision'];
            $kind = $change['kind'] === 'dir' ? 'listing' : 'source';
            $action = ['A' => 'Added', 'D' => 'Deleted', 'M' => 'Modified', 'R' => 'Replaced'][$change['action']] ?? $change['action'];
        @endphp
        <li><span class="change-action">{{ $action }}</span><a href="{{ route($kind, ['repname' => $repository, 'path' => $change['path'], 'rev' => $changeRevision, 'peg' => $changeRevision]) }}">{{ $change['path'] }}</a>@if($before)<span class="muted"> at r{{ $changeRevision }} before deletion</span>@endif
            @if($change['propertiesModified'])<span class="local-badge">Properties changed</span>@endif
            @if($change['copyFrom'] !== '')
                <span class="copy-origin">Copied from <a href="{{ route($kind, ['repname' => $repository, 'path' => $change['copyFrom'], 'rev' => $change['copyRevision'], 'peg' => $change['copyRevision']]) }}">{{ $change['copyFrom'].'@'.$change['copyRevision'] }}</a></span>
            @endif
        </li>
    @endforeach
</ul>
